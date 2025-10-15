<?php

declare(strict_types=1);

namespace OxidSolutionCatalysts\Stripe\PaymentComponent\Service;

use OxidSolutionCatalysts\Stripe\Contract\PaymentMethodRegistryInterface;
use OxidSolutionCatalysts\Stripe\Trait\LoggableTrait;

/**
 * Payment method registry service
 *
 * Reusability: 100% - Generic registry implementation
 *
 * This service manages payment method registration and retrieval.
 * It allows dynamic addition of payment methods at runtime.
 */
class PaymentMethodRegistry implements PaymentMethodRegistryInterface
{
    use LoggableTrait;

    /**
     * Registered payment methods
     *
     * @var array<string, array{title: string, model: string, config: array}>
     */
    private array $paymentMethods = [];

    /**
     * @inheritDoc
     */
    public function registerPaymentMethod(
        string $methodId,
        string $title,
        string $modelClass,
        array $config = []
    ): void {
        $this->logDebug("Registering payment method", [
            'methodId' => $methodId,
            'title' => $title,
            'modelClass' => $modelClass,
        ]);

        $this->paymentMethods[$methodId] = [
            'title' => $title,
            'model' => $modelClass,
            'config' => $config,
        ];
    }

    /**
     * @inheritDoc
     */
    public function getAllPaymentMethods(): array
    {
        return $this->paymentMethods;
    }

    /**
     * @inheritDoc
     */
    public function hasPaymentMethod(string $methodId): bool
    {
        return isset($this->paymentMethods[$methodId]);
    }

    /**
     * @inheritDoc
     */
    public function getPaymentMethodModel(string $methodId): object
    {
        if (!$this->hasPaymentMethod($methodId)) {
            throw new \Exception("Payment method not registered: {$methodId}");
        }

        $modelClass = $this->paymentMethods[$methodId]['model'];

        if (!class_exists($modelClass)) {
            throw new \Exception("Payment method model class not found: {$modelClass}");
        }

        // Use oxNew if available (OXID), otherwise use new
        if (function_exists('oxNew')) {
            return \oxNew($modelClass);
        }

        return new $modelClass();
    }

    /**
     * @inheritDoc
     */
    public function getPaymentMethodTitle(string $methodId): string
    {
        if (!$this->hasPaymentMethod($methodId)) {
            return '';
        }

        return $this->paymentMethods[$methodId]['title'];
    }

    /**
     * @inheritDoc
     */
    public function getPaymentMethodConfig(string $methodId): array
    {
        if (!$this->hasPaymentMethod($methodId)) {
            return [];
        }

        return $this->paymentMethods[$methodId]['config'];
    }

    /**
     * @inheritDoc
     */
    public function unregisterPaymentMethod(string $methodId): void
    {
        $this->logDebug("Unregistering payment method", ['methodId' => $methodId]);

        unset($this->paymentMethods[$methodId]);
    }

    /**
     * Register multiple payment methods at once
     *
     * @param array $methods Array of methods [methodId => [title, model, config]]
     */
    public function registerMultiple(array $methods): void
    {
        foreach ($methods as $methodId => $methodData) {
            $this->registerPaymentMethod(
                $methodId,
                $methodData['title'] ?? '',
                $methodData['model'] ?? '',
                $methodData['config'] ?? []
            );
        }
    }

    /**
     * Get payment method IDs only
     *
     * @return array<string> Array of payment method IDs
     */
    public function getPaymentMethodIds(): array
    {
        return array_keys($this->paymentMethods);
    }

    /**
     * Get payment methods as simple array (id => title)
     *
     * @return array<string, string> Array of [id => title]
     */
    public function getPaymentMethodsAsSimpleArray(): array
    {
        $methods = [];
        foreach ($this->paymentMethods as $methodId => $methodData) {
            $methods[$methodId] = $methodData['title'];
        }
        return $methods;
    }
}
