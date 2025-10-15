<?php

declare(strict_types=1);

namespace OxidSolutionCatalysts\Stripe\PaymentComponent\EventHandler;

use OxidSolutionCatalysts\Stripe\PaymentComponent\Contract\OrderManagerInterface;
use OxidSolutionCatalysts\Stripe\PaymentComponent\Contract\PaymentServiceInterface;
use OxidSolutionCatalysts\Stripe\PaymentComponent\Trait\LoggableTrait;
use Psr\Log\LoggerInterface;

/**
 * Abstract base class for payment event handlers
 *
 * Reusability: 95% - Base for payment handlers
 *
 * This class provides common functionality for payment event handlers:
 * - Access to cached request data via EventContext
 * - Payment service integration
 * - Order manager integration
 * - Logging
 *
 * Provider-specific implementations should extend this class.
 */
abstract class AbstractPaymentHandler
{
    use LoggableTrait;

    /**
     * Constructor
     *
     * @param PaymentServiceInterface $paymentService Payment service
     * @param OrderManagerInterface $orderManager Order manager
     * @param LoggerInterface $logger Logger
     */
    public function __construct(
        protected readonly PaymentServiceInterface $paymentService,
        protected readonly OrderManagerInterface $orderManager,
        LoggerInterface $logger
    ) {
        $this->setLogger($logger);
    }

    /**
     * Validate event context has required data
     *
     * @param object $context Event context
     * @throws \InvalidArgumentException If required data missing
     */
    protected function validateContext(object $context): void
    {
        if (!method_exists($context, 'getBasket') || !$context->getBasket()) {
            throw new \InvalidArgumentException('Event context missing basket');
        }

        if (!method_exists($context, 'getUser') || !$context->getUser()) {
            throw new \InvalidArgumentException('Event context missing user');
        }
    }

    /**
     * Extract basket from context
     *
     * @param object $context Event context
     * @return object Basket object
     */
    protected function getBasketFromContext(object $context): object
    {
        if (!method_exists($context, 'getBasket')) {
            throw new \RuntimeException('Context does not have getBasket() method');
        }

        return $context->getBasket();
    }

    /**
     * Extract user from context
     *
     * @param object $context Event context
     * @return object User object
     */
    protected function getUserFromContext(object $context): object
    {
        if (!method_exists($context, 'getUser')) {
            throw new \RuntimeException('Context does not have getUser() method');
        }

        return $context->getUser();
    }

    /**
     * Extract session from context
     *
     * @param object $context Event context
     * @return object|null Session object
     */
    protected function getSessionFromContext(object $context): ?object
    {
        if (!method_exists($context, 'getSession')) {
            return null;
        }

        return $context->getSession();
    }

    /**
     * Get request parameter from context
     *
     * @param object $context Event context
     * @param string $key Parameter key
     * @param mixed $default Default value
     * @return mixed Parameter value
     */
    protected function getRequestParam(object $context, string $key, mixed $default = null): mixed
    {
        if (!method_exists($context, 'getRequestParam')) {
            return $default;
        }

        return $context->getRequestParam($key, $default);
    }
}
