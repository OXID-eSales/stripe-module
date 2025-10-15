<?php

declare(strict_types=1);

namespace OxidSolutionCatalysts\Stripe\Service;

use OxidEsales\Eshop\Application\Model\Basket;
use OxidEsales\Eshop\Application\Model\Country;
use OxidEsales\Eshop\Core\Registry;
use OxidSolutionCatalysts\Stripe\Service\PaymentService as PaymentHelper;
use OxidSolutionCatalysts\Stripe\Contract\PaymentMethodFilterInterface;
use OxidSolutionCatalysts\Stripe\Contract\PaymentMethodRegistryInterface;
use OxidSolutionCatalysts\Stripe\PaymentComponent\Service\PaymentMethodFilter;
use OxidSolutionCatalysts\Stripe\PaymentComponent\Service\PaymentMethodRegistry;

/**
 * Stripe payment method service (OXID-specific)
 *
 * This service integrates the generic PaymentComponent abstractions
 * with the existing Stripe module infrastructure.
 *
 * It provides:
 * - Payment method registration from existing PaymentHelper
 * - Payment method filtering based on basket/user context
 * - Integration with OXID payment list
 */
class StripePaymentMethodService
{
    /**
     * Payment method registry
     */
    private PaymentMethodRegistryInterface $registry;

    /**
     * Payment method filter
     */
    private PaymentMethodFilterInterface $filter;

    /**
     * Payment helper (legacy)
     */
    private PaymentHelper $paymentHelper;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->paymentHelper = PaymentHelper::getInstance();
        $this->registry = new PaymentMethodRegistry();
        $this->filter = new PaymentMethodFilter($this->registry);

        // Register Stripe payment methods from existing helper
        $this->registerStripePaymentMethods();
    }

    /**
     * Register Stripe payment methods from existing helper
     */
    private function registerStripePaymentMethods(): void
    {
        $stripeMethods = $this->paymentHelper->getStripePaymentMethods();

        foreach ($stripeMethods as $methodId => $title) {
            try {
                $model = $this->paymentHelper->getStripePaymentModel($methodId);
                $this->registry->registerPaymentMethod(
                    $methodId,
                    $title,
                    get_class($model)
                );
            } catch (\Exception $e) {
                Registry::getLogger()->error("Failed to register payment method: {$methodId}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Get payment method registry
     *
     * @return PaymentMethodRegistryInterface
     */
    public function getRegistry(): PaymentMethodRegistryInterface
    {
        return $this->registry;
    }

    /**
     * Get payment method filter
     *
     * @return PaymentMethodFilterInterface
     */
    public function getFilter(): PaymentMethodFilterInterface
    {
        return $this->filter;
    }

    /**
     * Get all registered Stripe payment method IDs
     *
     * @return array<string>
     */
    public function getAllPaymentMethodIds(): array
    {
        return $this->registry->getPaymentMethodIds();
    }

    /**
     * Get all registered Stripe payment methods as array (id => title)
     *
     * @return array<string, string>
     */
    public function getAllPaymentMethods(): array
    {
        return $this->registry->getPaymentMethodsAsSimpleArray();
    }

    /**
     * Filter payment methods based on basket context
     *
     * @param Basket $basket OXID basket
     * @param bool $removeByCountry Whether to filter by country
     * @param bool $removeByCurrency Whether to filter by currency
     * @return array<string> Filtered payment method IDs
     */
    public function getAvailablePaymentMethods(
        Basket $basket,
        bool $removeByCountry = false,
        bool $removeByCurrency = false
    ): array {
        $allMethodIds = $this->getAllPaymentMethodIds();

        // Build filter criteria
        $criteria = [
            'amount' => $basket->getPrice()->getBruttoPrice(),
            'isB2B' => $this->isB2BOrder($basket),
        ];

        // Add country filter if enabled
        if ($removeByCountry) {
            $criteria['countryCode'] = $this->getBillingCountryCode($basket);
        }

        // Add currency filter if enabled
        if ($removeByCurrency) {
            $criteria['currencyCode'] = $basket->getBasketCurrency()->name;
        }

        // Filter methods
        return $this->filter->filterPaymentMethods($allMethodIds, $criteria);
    }

    /**
     * Check if payment method is Stripe method
     *
     * @param string $methodId Payment method ID
     * @return bool
     */
    public function isStripePaymentMethod(string $methodId): bool
    {
        return $this->registry->hasPaymentMethod($methodId);
    }

    /**
     * Get payment method model
     *
     * @param string $methodId Payment method ID
     * @return object Payment method model
     * @throws \Exception If method not found
     */
    public function getPaymentMethodModel(string $methodId): object
    {
        return $this->registry->getPaymentMethodModel($methodId);
    }

    /**
     * Check if Stripe token is configured
     *
     * @return bool
     */
    public function isStripeConfigured(): bool
    {
        return $this->paymentHelper->stripeIsTokenConfigured();
    }

    /**
     * Get billing country code from basket
     *
     * @param Basket $basket OXID basket
     * @return string ISO alpha-2 country code
     */
    private function getBillingCountryCode(Basket $basket): string
    {
        $user = $basket->getBasketUser();
        if (!$user) {
            return '';
        }

        $country = oxNew(Country::class);
        $country->load($user->oxuser__oxcountryid->value);

        if (!$country->oxcountry__oxisoalpha2) {
            return '';
        }

        return $country->oxcountry__oxisoalpha2->value;
    }

    /**
     * Check if order is B2B (company field filled)
     *
     * @param Basket $basket OXID basket
     * @return bool
     */
    private function isB2BOrder(Basket $basket): bool
    {
        $user = $basket->getBasketUser();
        if (!$user) {
            return false;
        }

        return !empty($user->oxuser__oxcompany->value);
    }

    /**
     * Get Stripe mode (test/live)
     *
     * @return string
     */
    public function getStripeMode(): string
    {
        return $this->paymentHelper->getStripeMode();
    }

    /**
     * Get Stripe publishable key
     *
     * @param string|null $mode Mode (test/live), null for current mode
     * @return string
     */
    public function getPublishableKey(?string $mode = null): string
    {
        if ($mode === null) {
            $mode = $this->getStripeMode();
        }
        return $this->paymentHelper->getPublishableKey($mode);
    }

    /**
     * Get Stripe token
     *
     * @param string|null $mode Mode (test/live), null for current mode
     * @return string
     */
    public function getStripeToken(?string $mode = null): string
    {
        if ($mode === null) {
            $mode = $this->getStripeMode();
        }
        return $this->paymentHelper->getStripeToken($mode);
    }
}
