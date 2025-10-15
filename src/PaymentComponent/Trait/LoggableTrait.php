<?php

declare(strict_types=1);

namespace OxidSolutionCatalysts\Stripe\PaymentComponent\Trait;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Trait for PSR-3 logger integration
 *
 * Reusability: 100%
 * Usage: Add to service classes to enable logging
 */
trait LoggableTrait
{
    /**
     * PSR-3 logger instance
     */
    private ?LoggerInterface $logger = null;

    /**
     * Set logger instance
     *
     * @param LoggerInterface $logger PSR-3 logger
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Get logger instance (lazy initialization with NullLogger)
     *
     * @return LoggerInterface Logger instance
     */
    protected function getLogger(): LoggerInterface
    {
        if ($this->logger === null) {
            $this->logger = new NullLogger();
        }

        return $this->logger;
    }

    /**
     * Log debug message with context
     *
     * @param string $message Log message
     * @param array $context Additional context
     */
    protected function logDebug(string $message, array $context = []): void
    {
        $this->getLogger()->debug($message, $context);
    }

    /**
     * Log info message with context
     *
     * @param string $message Log message
     * @param array $context Additional context
     */
    protected function logInfo(string $message, array $context = []): void
    {
        $this->getLogger()->info($message, $context);
    }

    /**
     * Log warning message with context
     *
     * @param string $message Log message
     * @param array $context Additional context
     */
    protected function logWarning(string $message, array $context = []): void
    {
        $this->getLogger()->warning($message, $context);
    }

    /**
     * Log error message with context
     *
     * @param string $message Log message
     * @param array $context Additional context
     */
    protected function logError(string $message, array $context = []): void
    {
        $this->getLogger()->error($message, $context);
    }

    /**
     * Log exception with context
     *
     * @param \Throwable $exception Exception to log
     * @param string $message Additional message
     * @param array $context Additional context
     */
    protected function logException(\Throwable $exception, string $message = '', array $context = []): void
    {
        $context['exception'] = $exception;
        $context['exceptionClass'] = get_class($exception);
        $context['exceptionMessage'] = $exception->getMessage();
        $context['exceptionCode'] = $exception->getCode();
        $context['exceptionFile'] = $exception->getFile();
        $context['exceptionLine'] = $exception->getLine();

        $logMessage = $message ?: 'Exception occurred: ' . $exception->getMessage();

        $this->getLogger()->error($logMessage, $context);
    }
}
