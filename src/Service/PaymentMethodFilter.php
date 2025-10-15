<?php

declare(strict_types=1);

namespace OxidSolutionCatalysts\Stripe\PaymentComponent\Service;

use OxidSolutionCatalysts\Stripe\Contract\PaymentMethodFilterInterface;
use OxidSolutionCatalysts\Stripe\Contract\PaymentMethodRegistryInterface;
use OxidSolutionCatalysts\Stripe\Trait\LoggableTrait;

/**
 * Payment method filter service
 *
 * Reusability: 100% - Generic filtering implementation
 *
 * This service filters payment methods based on various criteria:
 * - Country restrictions
 * - Currency restrictions
 * - Amount limits (min/max)
 * - B2B restrictions
 */
class PaymentMethodFilter implements PaymentMethodFilterInterface
{
    use LoggableTrait;

    /**
     * Constructor
     *
     * @param PaymentMethodRegistryInterface $registry Payment method registry
     */
    public function __construct(
        private readonly PaymentMethodRegistryInterface $registry
    ) {
    }

    /**
     * @inheritDoc
     */
    public function isAvailableForCountry(string $methodId, string $countryCode): bool
    {
        try {
            $model = $this->registry->getPaymentMethodModel($methodId);

            // Check if model has country restriction method
            if (method_exists($model, 'getBillingCountryRestrictedCountries')) {
                $restrictedCountries = $model->getBillingCountryRestrictedCountries();

                // false means available for all countries
                if ($restrictedCountries === false) {
                    return true;
                }

                // Check if country is in restricted list
                if (is_array($restrictedCountries)) {
                    return in_array(strtoupper($countryCode), array_map('strtoupper', $restrictedCountries));
                }
            }

            // Default: available
            return true;
        } catch (\Exception $e) {
            $this->logException($e, 'Failed to check country availability');
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function isAvailableForCurrency(string $methodId, string $currencyCode): bool
    {
        try {
            $model = $this->registry->getPaymentMethodModel($methodId);

            // Check if model has currency restriction method
            if (method_exists($model, 'getCurrencyRestrictedCurrencies')) {
                $restrictedCurrencies = $model->getCurrencyRestrictedCurrencies();

                // false means available for all currencies
                if ($restrictedCurrencies === false) {
                    return true;
                }

                // Check if currency is in restricted list
                if (is_array($restrictedCurrencies)) {
                    return in_array(strtoupper($currencyCode), array_map('strtoupper', $restrictedCurrencies));
                }
            }

            // Default: available
            return true;
        } catch (\Exception $e) {
            $this->logException($e, 'Failed to check currency availability');
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function isAmountWithinLimits(string $methodId, float $amount): bool
    {
        try {
            $model = $this->registry->getPaymentMethodModel($methodId);

            // Get min amount
            $minAmount = 0;
            if (method_exists($model, 'getStripeFromAmount')) {
                $min = $model->getStripeFromAmount();
                if ($min !== false && is_numeric($min)) {
                    $minAmount = (float) $min;
                }
            }

            // Get max amount
            $maxAmount = PHP_FLOAT_MAX;
            if (method_exists($model, 'getStripeToAmount')) {
                $max = $model->getStripeToAmount();
                if ($max !== false && is_numeric($max)) {
                    $maxAmount = (float) $max;
                }
            }

            // Check limits
            $withinLimits = $amount >= $minAmount && $amount <= $maxAmount;

            if (!$withinLimits) {
                $this->logDebug("Payment method amount out of limits", [
                    'methodId' => $methodId,
                    'amount' => $amount,
                    'minAmount' => $minAmount,
                    'maxAmount' => $maxAmount,
                ]);
            }

            return $withinLimits;
        } catch (\Exception $e) {
            $this->logException($e, 'Failed to check amount limits');
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function isAvailableForBusinessType(string $methodId, bool $isB2B): bool
    {
        try {
            $model = $this->registry->getPaymentMethodModel($methodId);

            // Check if model has B2B restriction method
            if (method_exists($model, 'isOnlyB2BSupported')) {
                $onlyB2B = $model->isOnlyB2BSupported();

                // If method only supports B2B, check if order is B2B
                if ($onlyB2B === true && $isB2B === false) {
                    $this->logDebug("Payment method requires B2B order", [
                        'methodId' => $methodId,
                        'isB2B' => $isB2B,
                    ]);
                    return false;
                }
            }

            // Default: available
            return true;
        } catch (\Exception $e) {
            $this->logException($e, 'Failed to check business type availability');
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function filterPaymentMethods(array $methodIds, array $criteria): array
    {
        $filteredMethods = [];

        foreach ($methodIds as $methodId) {
            if ($this->isMethodAvailable($methodId, $criteria)) {
                $filteredMethods[] = $methodId;
            }
        }

        $this->logInfo("Filtered payment methods", [
            'originalCount' => count($methodIds),
            'filteredCount' => count($filteredMethods),
            'criteria' => array_keys($criteria),
        ]);

        return $filteredMethods;
    }

    /**
     * Check if method is available based on all criteria
     *
     * @param string $methodId Payment method ID
     * @param array $criteria Filter criteria
     * @return bool True if available
     */
    private function isMethodAvailable(string $methodId, array $criteria): bool
    {
        // Check country
        if (isset($criteria['countryCode'])) {
            if (!$this->isAvailableForCountry($methodId, $criteria['countryCode'])) {
                return false;
            }
        }

        // Check currency
        if (isset($criteria['currencyCode'])) {
            if (!$this->isAvailableForCurrency($methodId, $criteria['currencyCode'])) {
                return false;
            }
        }

        // Check amount
        if (isset($criteria['amount'])) {
            if (!$this->isAmountWithinLimits($methodId, (float) $criteria['amount'])) {
                return false;
            }
        }

        // Check B2B
        if (isset($criteria['isB2B'])) {
            if (!$this->isAvailableForBusinessType($methodId, (bool) $criteria['isB2B'])) {
                return false;
            }
        }

        return true;
    }
}
