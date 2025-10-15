<?php

declare(strict_types=1);

namespace OxidSolutionCatalysts\Stripe\PaymentComponent\Contract;

use OxidSolutionCatalysts\Stripe\PaymentComponent\Model\PaymentTransaction;

/**
 * Interface for order lifecycle management
 *
 * Reusability: 100% - Generic order operations
 */
interface OrderManagerInterface
{
    /**
     * Create temporary shop order (NOT_FINISHED state)
     *
     * @param object $user Shop user object
     * @param object $basket Shop basket object
     * @return object Created shop order
     */
    public function createTemporaryOrder(object $user, object $basket): object;

    /**
     * Finalize order after successful payment
     *
     * @param object $order Shop order object
     * @param PaymentTransaction $transaction Payment transaction
     */
    public function finalizeOrder(object $order, PaymentTransaction $transaction): void;

    /**
     * Mark order as paid
     *
     * @param object $order Shop order object
     * @param string $transactionId Provider transaction ID
     */
    public function markOrderAsPaid(object $order, string $transactionId): void;

    /**
     * Cancel order (payment failed)
     *
     * @param object $order Shop order object
     * @param string $reason Cancellation reason
     */
    public function cancelOrder(object $order, string $reason): void;
}
