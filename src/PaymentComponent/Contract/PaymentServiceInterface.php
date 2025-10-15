<?php

declare(strict_types=1);

namespace OxidSolutionCatalysts\Stripe\PaymentComponent\Contract;

use OxidSolutionCatalysts\Stripe\PaymentComponent\Model\PaymentTransaction;
use OxidSolutionCatalysts\Stripe\PaymentComponent\ValueObject\ProviderOrder;

/**
 * Interface for payment service orchestration
 *
 * Reusability: 90% - Core workflow generic, provider calls adaptable
 */
interface PaymentServiceInterface
{
    // ==================== Order Creation ====================

    /**
     * Create payment order at provider
     *
     * @param object $basket Shop basket object
     * @param string $intent 'capture' (immediate) or 'authorize' (capture later)
     * @param array $options Additional options (returnUrl, cancelUrl, paymentSource, etc.)
     * @return ProviderOrder Provider order object
     */
    public function createPaymentOrder(object $basket, string $intent, array $options = []): ProviderOrder;

    /**
     * Update existing payment order with new basket data
     *
     * @param object $basket Updated basket
     * @param string $providerOrderId Provider's order ID
     */
    public function updatePaymentOrder(object $basket, string $providerOrderId): void;

    // ==================== Payment Execution ====================

    /**
     * Capture payment (charge authorized funds)
     *
     * @param object $order Shop order object
     * @param string $providerOrderId Provider's order ID
     * @param string $paymentMethodId Payment method identifier
     * @return ProviderOrder Updated provider order
     */
    public function capturePayment(object $order, string $providerOrderId, string $paymentMethodId): ProviderOrder;

    /**
     * Authorize payment without capturing
     *
     * @param object $order Shop order object
     * @param string $providerOrderId Provider's order ID
     * @param string $paymentMethodId Payment method identifier
     * @return array Authorization result with status
     */
    public function authorizePayment(object $order, string $providerOrderId, string $paymentMethodId): array;

    /**
     * Refund captured payment
     *
     * @param string $transactionId Transaction ID to refund
     * @param float $amount Amount to refund
     * @param string $reason Refund reason
     * @return array Refund result
     */
    public function refundPayment(string $transactionId, float $amount, string $reason = ''): array;

    // ==================== Transaction Tracking ====================

    /**
     * Track payment transaction in database
     *
     * @param string $shopOrderId Shop order ID
     * @param string $providerOrderId Provider order ID
     * @param string $paymentMethodId Payment method used
     * @param string $status Transaction status
     * @param string $transactionId Provider transaction ID
     * @param string $transactionType 'capture', 'authorization', or 'refund'
     * @return PaymentTransaction Created/updated transaction
     */
    public function trackTransaction(
        string $shopOrderId,
        string $providerOrderId,
        string $paymentMethodId,
        string $status,
        string $transactionId = '',
        string $transactionType = 'capture'
    ): PaymentTransaction;

    // ==================== Utilities ====================

    /**
     * Fetch provider order details
     *
     * @param string $providerOrderId Provider order ID
     * @return ProviderOrder Provider order object
     */
    public function fetchProviderOrderDetails(string $providerOrderId): ProviderOrder;

    /**
     * Check if payment method belongs to this provider
     *
     * @param string $paymentMethodId Payment method ID
     * @return bool True if this provider handles this method
     */
    public function isPaymentMethod(string $paymentMethodId): bool;

    /**
     * Remove temporary order (cleanup on failure)
     */
    public function removeTemporaryOrder(): void;

    /**
     * Clean up session data
     */
    public function cleanupSession(): void;
}
