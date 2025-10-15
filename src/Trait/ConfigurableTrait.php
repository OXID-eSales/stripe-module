<?php

declare(strict_types=1);

namespace OxidSolutionCatalysts\Stripe\Trait;

use OxidSolutionCatalysts\Stripe\PaymentComponent\Contract\ModuleSettingsInterface;

/**
 * Trait for configuration access
 *
 * Reusability: 100%
 * Usage: Add to service classes that need configuration
 */
trait ConfigurableTrait
{
    /**
     * Module settings instance
     */
    private ?ModuleSettingsInterface $moduleSettings = null;

    /**
     * Set module settings
     *
     * @param ModuleSettingsInterface $settings Module settings
     */
    public function setModuleSettings(ModuleSettingsInterface $settings): void
    {
        $this->moduleSettings = $settings;
    }

    /**
     * Get module settings
     *
     * @return ModuleSettingsInterface Module settings
     * @throws \RuntimeException If settings not set
     */
    protected function getModuleSettings(): ModuleSettingsInterface
    {
        if ($this->moduleSettings === null) {
            throw new \RuntimeException('Module settings not initialized');
        }

        return $this->moduleSettings;
    }

    /**
     * Check if in sandbox mode
     *
     * @return bool True if sandbox
     */
    protected function isSandboxMode(): bool
    {
        return $this->getModuleSettings()->isSandbox();
    }

    /**
     * Check if in production mode
     *
     * @return bool True if production
     */
    protected function isProductionMode(): bool
    {
        return $this->getModuleSettings()->isProduction();
    }

    /**
     * Get capture strategy
     *
     * @return string Capture strategy
     */
    protected function getCaptureStrategy(): string
    {
        return $this->getModuleSettings()->getCaptureStrategy();
    }

    /**
     * Check if payment method is enabled
     *
     * @param string $methodId Payment method ID
     * @return bool True if enabled
     */
    protected function isPaymentMethodEnabled(string $methodId): bool
    {
        return $this->getModuleSettings()->isPaymentMethodEnabled($methodId);
    }
}
