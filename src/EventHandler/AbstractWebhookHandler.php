<?php

declare(strict_types=1);

namespace OxidSolutionCatalysts\Stripe\EventHandler;

use OxidSolutionCatalysts\Stripe\Model\PaymentTransaction;
use OxidSolutionCatalysts\Stripe\PaymentComponent\Contract\OrderManagerInterface;
use OxidSolutionCatalysts\Stripe\PaymentComponent\Contract\OrderRepositoryInterface;
use OxidSolutionCatalysts\Stripe\Trait\LoggableTrait;
use Psr\Log\LoggerInterface;

/**
 * Abstract base class for webhook event handlers
 *
 * Reusability: 100% - Template method pattern fully reusable
 *
 * This class implements the Template Method pattern for webhook processing:
 * 1. Extract provider order ID from payload
 * 2. Extract transaction ID from payload
 * 3. Extract status from payload
 * 4. Find order by provider order ID
 * 5. Find or create transaction
 * 6. Process webhook (optional hook)
 * 7. Update transaction
 * 8. Mark order if paid
 * 9. Cleanup abandoned orders
 *
 * Provider-specific implementations only need to implement:
 * - getProviderOrderIdFromPayload()
 * - getTransactionIdFromPayload()
 * - getStatusFromPayload()
 * - optionally override processWebhook()
 */
abstract class AbstractWebhookHandler
{
    use LoggableTrait;

    /**
     * Constructor
     *
     * @param OrderRepositoryInterface $orderRepository Order repository
     * @param OrderManagerInterface $orderManager Order manager
     * @param LoggerInterface $logger Logger
     */
    public function __construct(
        protected readonly OrderRepositoryInterface $orderRepository,
        protected readonly OrderManagerInterface $orderManager,
        LoggerInterface $logger
    ) {
        $this->setLogger($logger);
    }

    /**
     * Handle webhook event (Template Method)
     *
     * This method orchestrates the webhook processing workflow.
     * DO NOT override this method in subclasses.
     *
     * @param object $event Webhook event object
     */
    final public function handle(object $event): void
    {
        try {
            // Extract payload
            $payload = $this->getEventPayload($event);

            $this->logDebug('Processing webhook', [
                'eventType' => $this->getEventType($event),
            ]);

            // Extract provider-specific fields
            $providerOrderId = $this->getProviderOrderIdFromPayload($payload);
            $transactionId = $this->getTransactionIdFromPayload($payload);
            $status = $this->getStatusFromPayload($payload);

            if (empty($providerOrderId)) {
                $this->logWarning('Webhook missing provider order ID', ['payload' => $payload]);
                return;
            }

            // Find order and transaction
            $order = $this->orderRepository->getOrderByProviderOrderId($providerOrderId);
            $transaction = $this->orderRepository->getTransactionByOrderAndProvider(
                method_exists($order, 'getId') ? $order->getId() : '',
                $providerOrderId,
                $transactionId
            );

            // Process webhook (optional hook for provider-specific logic)
            $this->processWebhook($order, $transaction, $payload);

            // Update transaction
            $this->updateTransaction($transaction, $status, $transactionId);

            // Mark order as paid if completed
            $this->markOrderIfPaid($order, $transaction);

            // Cleanup old orders
            $this->cleanupOrders();

            $this->logInfo('Webhook processed successfully', [
                'providerOrderId' => $providerOrderId,
                'transactionId' => $transactionId,
                'status' => $status,
            ]);
        } catch (\Throwable $e) {
            $this->logException($e, 'Failed to process webhook');
            throw $e;
        }
    }

    // ==================== Abstract Methods (Provider-Specific) ====================

    /**
     * Extract provider order ID from webhook payload
     *
     * @param array $payload Webhook payload
     * @return string Provider order ID
     */
    abstract protected function getProviderOrderIdFromPayload(array $payload): string;

    /**
     * Extract transaction ID from webhook payload
     *
     * @param array $payload Webhook payload
     * @return string Transaction ID
     */
    abstract protected function getTransactionIdFromPayload(array $payload): string;

    /**
     * Extract status from webhook payload
     *
     * @param array $payload Webhook payload
     * @return string Transaction status
     */
    abstract protected function getStatusFromPayload(array $payload): string;

    // ==================== Optional Hooks ====================

    /**
     * Process webhook (optional hook for provider-specific logic)
     *
     * Override this method in subclasses to add custom processing logic.
     *
     * @param object $order Shop order
     * @param PaymentTransaction $transaction Payment transaction
     * @param array $payload Webhook payload
     */
    protected function processWebhook(object $order, PaymentTransaction $transaction, array $payload): void
    {
        // Default implementation does nothing
        // Override in subclass if needed
    }

    // ==================== Helper Methods ====================

    /**
     * Get event payload
     *
     * @param object $event Event object
     * @return array Payload array
     */
    protected function getEventPayload(object $event): array
    {
        if (method_exists($event, 'getPayload')) {
            return $event->getPayload();
        }

        if (method_exists($event, 'getData')) {
            return $event->getData();
        }

        throw new \RuntimeException('Event object has no getPayload() or getData() method');
    }

    /**
     * Get event type
     *
     * @param object $event Event object
     * @return string Event type
     */
    protected function getEventType(object $event): string
    {
        if (method_exists($event, 'getType')) {
            return $event->getType();
        }

        if (method_exists($event, 'getEventType')) {
            return $event->getEventType();
        }

        return 'unknown';
    }

    /**
     * Update transaction with new data
     *
     * @param PaymentTransaction $transaction Transaction to update
     * @param string $status New status
     * @param string $transactionId New transaction ID
     */
    protected function updateTransaction(PaymentTransaction $transaction, string $status, string $transactionId): void
    {
        $transaction->setStatus($status);

        if (!empty($transactionId)) {
            $transaction->setTransactionId($transactionId);
        }

        $transaction->save();
    }

    /**
     * Mark order as paid if transaction completed
     *
     * @param object $order Shop order
     * @param PaymentTransaction $transaction Payment transaction
     */
    protected function markOrderIfPaid(object $order, PaymentTransaction $transaction): void
    {
        if ($transaction->isCompleted()) {
            $this->orderManager->markOrderAsPaid($order, $transaction->getTransactionId() ?? '');

            $this->logInfo('Order marked as paid', [
                'orderId' => method_exists($order, 'getId') ? $order->getId() : 'unknown',
            ]);
        }
    }

    /**
     * Cleanup abandoned orders
     */
    protected function cleanupOrders(): void
    {
        try {
            $count = $this->orderRepository->cleanUpAbandonedOrders();

            if ($count > 0) {
                $this->logInfo("Cleaned up {$count} abandoned orders");
            }
        } catch (\Throwable $e) {
            $this->logException($e, 'Failed to cleanup abandoned orders');
        }
    }

    /**
     * Process payment capture webhook
     *
     * Helper method for common capture webhook processing.
     *
     * @param string $providerOrderId Provider order ID
     * @param string $transactionId Transaction ID
     * @param string $status Transaction status
     */
    protected function processPaymentCapture(string $providerOrderId, string $transactionId, string $status): void
    {
        $order = $this->orderRepository->getOrderByProviderOrderId($providerOrderId);
        $transaction = $this->orderRepository->getTransactionByOrderAndProvider(
            method_exists($order, 'getId') ? $order->getId() : '',
            $providerOrderId,
            $transactionId
        );

        $this->updateTransaction($transaction, $status, $transactionId);
        $this->markOrderIfPaid($order, $transaction);
    }
}
