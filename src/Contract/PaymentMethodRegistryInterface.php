<?php

declare(strict_types=1);

namespace OxidSolutionCatalysts\Stripe\Contract;

/**
 * Interface for payment method registry
 *
 * Reusability: 100% - Generic payment method management
 *
 * This interface defines methods for registering and retrieving payment methods.
 * It allows dynamic addition of payment methods at runtime and provides
 * filtering capabilities based on various criteria.
 */
interface PaymentMethodRegistryInterface
{
    /**
     * Register a payment method
     *
     * @param string $methodId Payment method ID (e.g., 'stripecreditcard')
     * @param string $title Human-readable title
     * @param string $modelClass Fully qualified class name of payment model
     * @param array $config Additional configuration
     */
    public function registerPaymentMethod(
        string $methodId,
        string $title,
        string $modelClass,
        array $config = []
    ): void;

    /**
     * Get all registered payment methods
     *
     * @return array<string, array> Array of payment methods [id => [title, model, config]]
     */
    public function getAllPaymentMethods(): array;

    /**
     * Check if payment method is registered
     *
     * @param string $methodId Payment method ID
     * @return bool True if registered
     */
    public function hasPaymentMethod(string $methodId): bool;

    /**
     * Get payment method model instance
     *
     * @param string $methodId Payment method ID
     * @return object Payment method model
     * @throws \Exception If method not registered
     */
    public function getPaymentMethodModel(string $methodId): object;

    /**
     * Get payment method title
     *
     * @param string $methodId Payment method ID
     * @return string Payment method title
     */
    public function getPaymentMethodTitle(string $methodId): string;

    /**
     * Get payment method configuration
     *
     * @param string $methodId Payment method ID
     * @return array Payment method configuration
     */
    public function getPaymentMethodConfig(string $methodId): array;

    /**
     * Unregister payment method
     *
     * @param string $methodId Payment method ID
     */
    public function unregisterPaymentMethod(string $methodId): void;
}
