<?php

declare(strict_types=1);

namespace OxidSolutionCatalysts\Stripe\PaymentComponent\Factory;

use OxidSolutionCatalysts\Stripe\PaymentComponent\Trait\LoggableTrait;

/**
 * Abstract base class for request factories
 *
 * Reusability: 80% - Structure reusable, formats vary by provider
 *
 * Request factories convert shop entities (basket, user, order)
 * to provider API request format.
 */
abstract class AbstractRequestFactory
{
    use LoggableTrait;

    /**
     * Build common request headers
     *
     * @param array $additionalHeaders Additional headers
     * @return array Request headers
     */
    protected function buildHeaders(array $additionalHeaders = []): array
    {
        return array_merge([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ], $additionalHeaders);
    }

    /**
     * Format amount for provider (e.g., convert to cents)
     *
     * @param float $amount Amount in shop currency
     * @param bool $toCents Whether to convert to cents (default: true)
     * @return int|float Formatted amount
     */
    protected function formatAmount(float $amount, bool $toCents = true): int|float
    {
        if ($toCents) {
            return (int) round($amount * 100);
        }

        return $amount;
    }

    /**
     * Format currency code
     *
     * @param string $currency Currency code
     * @param bool $lowercase Whether to convert to lowercase (default: true)
     * @return string Formatted currency
     */
    protected function formatCurrency(string $currency, bool $lowercase = true): string
    {
        return $lowercase ? strtolower($currency) : strtoupper($currency);
    }

    /**
     * Extract line items from basket
     *
     * @param object $basket Shop basket
     * @return array Line items
     */
    protected function extractLineItems(object $basket): array
    {
        $items = [];

        if (!method_exists($basket, 'getContents')) {
            return $items;
        }

        foreach ($basket->getContents() as $basketItem) {
            $items[] = $this->formatLineItem($basketItem);
        }

        return $items;
    }

    /**
     * Format single line item (to be implemented by provider-specific factory)
     *
     * @param object $basketItem Basket item
     * @return array Formatted line item
     */
    abstract protected function formatLineItem(object $basketItem): array;

    /**
     * Build shipping information
     *
     * @param object $user User object
     * @return array Shipping information
     */
    protected function buildShippingInfo(object $user): array
    {
        return [
            'name' => $this->getUserName($user),
            'address' => $this->getUserAddress($user),
        ];
    }

    /**
     * Get user name
     *
     * @param object $user User object
     * @return array Name array
     */
    protected function getUserName(object $user): array
    {
        return [
            'given_name' => method_exists($user, 'getFirstName') ? $user->getFirstName() : '',
            'surname' => method_exists($user, 'getLastName') ? $user->getLastName() : '',
        ];
    }

    /**
     * Get user address
     *
     * @param object $user User object
     * @return array Address array
     */
    abstract protected function getUserAddress(object $user): array;

    /**
     * Sanitize string for API
     *
     * @param string $value Value to sanitize
     * @param int $maxLength Maximum length
     * @return string Sanitized value
     */
    protected function sanitizeString(string $value, int $maxLength = 255): string
    {
        $sanitized = trim($value);
        $sanitized = preg_replace('/\s+/', ' ', $sanitized);

        if (mb_strlen($sanitized) > $maxLength) {
            $sanitized = mb_substr($sanitized, 0, $maxLength);
        }

        return $sanitized;
    }
}
