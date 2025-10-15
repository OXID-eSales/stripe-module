<?php

declare(strict_types=1);

namespace OxidSolutionCatalysts\Stripe\PaymentComponent\Service;

use OxidSolutionCatalysts\Stripe\PaymentComponent\Contract\CachableApiInterface;
use OxidSolutionCatalysts\Stripe\PaymentComponent\Contract\ModuleSettingsInterface;
use OxidSolutionCatalysts\Stripe\PaymentComponent\Contract\OrderRepositoryInterface;
use OxidSolutionCatalysts\Stripe\PaymentComponent\Contract\PaymentServiceInterface;
use OxidSolutionCatalysts\Stripe\PaymentComponent\Model\PaymentTransaction;
use OxidSolutionCatalysts\Stripe\PaymentComponent\Trait\CachableApiTrait;
use OxidSolutionCatalysts\Stripe\PaymentComponent\Trait\ConfigurableTrait;
use OxidSolutionCatalysts\Stripe\PaymentComponent\Trait\LoggableTrait;
use OxidSolutionCatalysts\Stripe\PaymentComponent\ValueObject\ProviderOrder;
use Psr\Log\LoggerInterface;

/**
 * Abstract base class for payment service implementations
 *
 * Reusability: 90% - Core workflow generic, provider calls adaptable
 *
 * This class provides:
 * - Request-scoped API caching
 * - PSR-3 logging
 * - Configuration access
 * - Generic transaction tracking
 * - Session management
 *
 * Provider-specific implementations should extend this class and implement:
 * - createProviderOrder()
 * - updateProviderOrder()
 * - captureProviderPayment()
 * - authorizeProviderPayment()
 * - refundProviderPayment()
 * - fetchProviderOrderById()
 */
abstract class AbstractPaymentService implements PaymentServiceInterface, CachableApiInterface
{
    use CachableApiTrait;
    use LoggableTrait;
    use ConfigurableTrait;

    /**
     * Constructor
     *
     * @param OrderRepositoryInterface $orderRepository Order repository
     * @param ModuleSettingsInterface $moduleSettings Module settings
     * @param LoggerInterface $logger PSR-3 logger
     */
    public function __construct(
        protected readonly OrderRepositoryInterface $orderRepository,
        ModuleSettingsInterface $moduleSettings,
        LoggerInterface $logger
    ) {
        $this->setModuleSettings($moduleSettings);
        $this->setLogger($logger);
    }

    // ==================== Public API (Implemented) ====================

    /**
     * @inheritDoc
     */
    final public function createPaymentOrder(object $basket, string $intent, array $options = []): ProviderOrder
    {
        $this->logDebug('Creating payment order', [
            'intent' => $intent,
            'basketTotal' => method_exists($basket, 'getPrice') ? $basket->getPrice()->getBruttoPrice() : 'unknown',
        ]);

        try {
            $providerOrder = $this->createProviderOrder($basket, $intent, $options);

            // Cache the order for subsequent calls
            $cacheKey = "order:{$providerOrder->getId()}";
            $this->cacheApiResponse($cacheKey, $providerOrder);

            $this->logInfo('Payment order created', [
                'providerOrderId' => $providerOrder->getId(),
                'status' => $providerOrder->getStatus(),
            ]);

            return $providerOrder;
        } catch (\Throwable $e) {
            $this->logException($e, 'Failed to create payment order');
            throw $e;
        }
    }

    /**
     * @inheritDoc
     */
    final public function updatePaymentOrder(object $basket, string $providerOrderId): void
    {
        $this->logDebug('Updating payment order', ['providerOrderId' => $providerOrderId]);

        try {
            $this->updateProviderOrder($basket, $providerOrderId);

            // Invalidate cache
            $this->invalidateCache("order:{$providerOrderId}");

            $this->logInfo('Payment order updated', ['providerOrderId' => $providerOrderId]);
        } catch (\Throwable $e) {
            $this->logException($e, 'Failed to update payment order');
            throw $e;
        }
    }

    /**
     * @inheritDoc
     */
    final public function capturePayment(object $order, string $providerOrderId, string $paymentMethodId): ProviderOrder
    {
        $this->logDebug('Capturing payment', [
            'providerOrderId' => $providerOrderId,
            'paymentMethodId' => $paymentMethodId,
        ]);

        try {
            $providerOrder = $this->captureProviderPayment($order, $providerOrderId, $paymentMethodId);

            // Invalidate cache
            $this->invalidateCache("order:{$providerOrderId}");

            $this->logInfo('Payment captured', [
                'providerOrderId' => $providerOrder->getId(),
                'transactionId' => $providerOrder->getTransactionId(),
            ]);

            return $providerOrder;
        } catch (\Throwable $e) {
            $this->logException($e, 'Failed to capture payment');
            throw $e;
        }
    }

    /**
     * @inheritDoc
     */
    final public function authorizePayment(object $order, string $providerOrderId, string $paymentMethodId): array
    {
        $this->logDebug('Authorizing payment', [
            'providerOrderId' => $providerOrderId,
            'paymentMethodId' => $paymentMethodId,
        ]);

        try {
            $result = $this->authorizeProviderPayment($order, $providerOrderId, $paymentMethodId);

            $this->logInfo('Payment authorized', [
                'providerOrderId' => $providerOrderId,
                'status' => $result['status'] ?? 'unknown',
            ]);

            return $result;
        } catch (\Throwable $e) {
            $this->logException($e, 'Failed to authorize payment');
            throw $e;
        }
    }

    /**
     * @inheritDoc
     */
    final public function refundPayment(string $transactionId, float $amount, string $reason = ''): array
    {
        $this->logDebug('Refunding payment', [
            'transactionId' => $transactionId,
            'amount' => $amount,
            'reason' => $reason,
        ]);

        try {
            $result = $this->refundProviderPayment($transactionId, $amount, $reason);

            $this->logInfo('Payment refunded', [
                'transactionId' => $transactionId,
                'refundId' => $result['refundId'] ?? 'unknown',
            ]);

            return $result;
        } catch (\Throwable $e) {
            $this->logException($e, 'Failed to refund payment');
            throw $e;
        }
    }

    /**
     * @inheritDoc
     */
    public function trackTransaction(
        string $shopOrderId,
        string $providerOrderId,
        string $paymentMethodId,
        string $status,
        string $transactionId = '',
        string $transactionType = 'capture'
    ): PaymentTransaction {
        $this->logDebug('Tracking transaction', [
            'shopOrderId' => $shopOrderId,
            'providerOrderId' => $providerOrderId,
            'transactionType' => $transactionType,
        ]);

        $transaction = new PaymentTransaction();
        $transaction->setShopOrderId($shopOrderId);
        $transaction->setProviderOrderId($providerOrderId);
        $transaction->setPaymentMethodId($paymentMethodId);
        $transaction->setStatus($status);
        $transaction->setTransactionId($transactionId);
        $transaction->setTransactionType($transactionType);

        // Save transaction (implementation depends on shop platform)
        $transaction->save();

        $this->logInfo('Transaction tracked', [
            'transactionId' => $transaction->getId(),
        ]);

        return $transaction;
    }

    /**
     * @inheritDoc
     */
    final public function fetchProviderOrderDetails(string $providerOrderId): ProviderOrder
    {
        // Try cache first
        return $this->getCachedOrFetch(
            "order:{$providerOrderId}",
            fn() => $this->fetchProviderOrderById($providerOrderId)
        );
    }

    /**
     * @inheritDoc
     */
    public function removeTemporaryOrder(): void
    {
        $this->logDebug('Removing temporary order');
        // Implementation depends on shop platform
    }

    /**
     * @inheritDoc
     */
    public function cleanupSession(): void
    {
        $this->logDebug('Cleaning up session');
        $this->clearCache();
    }

    // ==================== Abstract Methods (Provider-Specific) ====================

    /**
     * Create order at payment provider
     *
     * @param object $basket Shop basket
     * @param string $intent 'capture' or 'authorize'
     * @param array $options Additional options
     * @return ProviderOrder Provider order object
     */
    abstract protected function createProviderOrder(object $basket, string $intent, array $options): ProviderOrder;

    /**
     * Update order at payment provider
     *
     * @param object $basket Updated basket
     * @param string $providerOrderId Provider order ID
     */
    abstract protected function updateProviderOrder(object $basket, string $providerOrderId): void;

    /**
     * Capture payment at provider
     *
     * @param object $order Shop order
     * @param string $providerOrderId Provider order ID
     * @param string $paymentMethodId Payment method ID
     * @return ProviderOrder Updated provider order
     */
    abstract protected function captureProviderPayment(
        object $order,
        string $providerOrderId,
        string $paymentMethodId
    ): ProviderOrder;

    /**
     * Authorize payment at provider
     *
     * @param object $order Shop order
     * @param string $providerOrderId Provider order ID
     * @param string $paymentMethodId Payment method ID
     * @return array Authorization result
     */
    abstract protected function authorizeProviderPayment(
        object $order,
        string $providerOrderId,
        string $paymentMethodId
    ): array;

    /**
     * Refund payment at provider
     *
     * @param string $transactionId Transaction ID to refund
     * @param float $amount Amount to refund
     * @param string $reason Refund reason
     * @return array Refund result
     */
    abstract protected function refundProviderPayment(string $transactionId, float $amount, string $reason): array;

    /**
     * Fetch order details from provider
     *
     * @param string $providerOrderId Provider order ID
     * @return ProviderOrder Provider order object
     */
    abstract protected function fetchProviderOrderById(string $providerOrderId): ProviderOrder;

    // ==================== Helper Methods ====================

    /**
     * Build cache key for customer
     *
     * @param string $customerId Customer ID
     * @return string Cache key
     */
    protected function buildCustomerCacheKey(string $customerId): string
    {
        return "customer:{$customerId}";
    }

    /**
     * Build cache key for payment method
     *
     * @param string $paymentMethodId Payment method ID
     * @return string Cache key
     */
    protected function buildPaymentMethodCacheKey(string $paymentMethodId): string
    {
        return "payment_method:{$paymentMethodId}";
    }
}
