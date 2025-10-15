<?php

declare(strict_types=1);

namespace OxidSolutionCatalysts\Stripe\PaymentComponent\Contract;

use OxidSolutionCatalysts\Stripe\Model\PaymentTransaction;

/**
 * Interface for order and transaction repository
 *
 * Reusability: 100% - Fully generic data access
 */
interface OrderRepositoryInterface
{
    // ==================== Find Transactions ====================

    /**
     * Get payment transaction by order and provider IDs
     *
     * @param string $shopOrderId Shop order ID
     * @param string $providerOrderId Provider order ID (optional)
     * @param string $transactionId Provider transaction ID (optional)
     * @return PaymentTransaction Payment transaction
     */
    public function getTransactionByOrderAndProvider(
        string $shopOrderId,
        string $providerOrderId = '',
        string $transactionId = ''
    ): PaymentTransaction;

    /**
     * Get all transactions for a shop order
     *
     * @param string $shopOrderId Shop order ID
     * @return PaymentTransaction[] Array of transactions
     */
    public function getTransactionsByOrderId(string $shopOrderId): array;

    // ==================== Find Orders ====================

    /**
     * Get shop order by provider order ID
     *
     * @param string $providerOrderId Provider order ID
     * @return object Shop order object
     */
    public function getOrderByProviderOrderId(string $providerOrderId): object;

    /**
     * Get shop order by transaction ID
     *
     * @param string $transactionId Provider transaction ID
     * @return object Shop order object
     */
    public function getOrderByTransactionId(string $transactionId): object;

    // ==================== Get IDs ====================

    /**
     * Get provider order ID by shop order ID
     *
     * @param string $shopOrderId Shop order ID
     * @return string Provider order ID
     */
    public function getProviderOrderIdByShopOrderId(string $shopOrderId): string;

    // ==================== Session ====================

    /**
     * Get current order ID from session
     *
     * @return string Current order ID
     */
    public function getCurrentOrderId(): string;

    /**
     * Get current order from session
     *
     * @return object Current order object
     */
    public function getCurrentOrder(): object;

    // ==================== Cleanup ====================

    /**
     * Clean up abandoned orders (NOT_FINISHED status, older than X hours)
     *
     * @param int $hoursThreshold Hours threshold for cleanup (default: 24)
     * @return int Number of orders cleaned up
     */
    public function cleanUpAbandonedOrders(int $hoursThreshold = 24): int;
}
