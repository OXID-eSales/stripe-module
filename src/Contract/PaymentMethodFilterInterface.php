<?php

declare(strict_types=1);

namespace OxidSolutionCatalysts\Stripe\Contract;

/**
 * Interface for payment method filtering
 *
 * Reusability: 100% - Generic filtering logic
 *
 * This interface defines methods for filtering payment methods based on:
 * - Billing country
 * - Currency
 * - Basket amount (min/max limits)
 * - B2B restriction
 * - Custom criteria
 */
interface PaymentMethodFilterInterface
{
    /**
     * Check if payment method is available for country
     *
     * @param string $methodId Payment method ID
     * @param string $countryCode ISO alpha-2 country code
     * @return bool True if available
     */
    public function isAvailableForCountry(string $methodId, string $countryCode): bool;

    /**
     * Check if payment method is available for currency
     *
     * @param string $methodId Payment method ID
     * @param string $currencyCode ISO 4217 currency code
     * @return bool True if available
     */
    public function isAvailableForCurrency(string $methodId, string $currencyCode): bool;

    /**
     * Check if basket amount is within method limits
     *
     * @param string $methodId Payment method ID
     * @param float $amount Basket amount
     * @return bool True if within limits
     */
    public function isAmountWithinLimits(string $methodId, float $amount): bool;

    /**
     * Check if payment method is available for B2B/B2C order
     *
     * @param string $methodId Payment method ID
     * @param bool $isB2B True if B2B order
     * @return bool True if available
     */
    public function isAvailableForBusinessType(string $methodId, bool $isB2B): bool;

    /**
     * Filter payment methods by all criteria
     *
     * @param array $methodIds Payment method IDs to filter
     * @param array $criteria Filter criteria
     * @return array Filtered payment method IDs
     */
    public function filterPaymentMethods(array $methodIds, array $criteria): array;
}
