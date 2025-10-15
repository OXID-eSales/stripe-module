<?php

declare(strict_types=1);

namespace OxidSolutionCatalysts\Stripe\PaymentComponent\Contract;

/**
 * Interface for module configuration management
 *
 * Reusability: 100% - Structure generic, values provider-specific
 */
interface ModuleSettingsInterface
{
    // ==================== Environment ====================

    /**
     * Check if module is in sandbox/test mode
     *
     * @return bool True if sandbox mode
     */
    public function isSandbox(): bool;

    /**
     * Check if module is in production mode
     *
     * @return bool True if production mode
     */
    public function isProduction(): bool;

    // ==================== Credentials ====================

    /**
     * Get API client ID
     *
     * @return string Client ID
     */
    public function getClientId(): string;

    /**
     * Get API client secret
     *
     * @return string Client secret
     */
    public function getClientSecret(): string;

    /**
     * Get merchant/partner ID
     *
     * @return string Merchant ID
     */
    public function getMerchantId(): string;

    /**
     * Get webhook ID/secret
     *
     * @return string Webhook secret
     */
    public function getWebhookSecret(): string;

    /**
     * Save API credentials
     *
     * @param array $credentials Credentials array
     */
    public function saveCredentials(array $credentials): void;

    // ==================== Feature Flags ====================

    /**
     * Check if payment method is enabled
     *
     * @param string $methodId Payment method ID
     * @return bool True if enabled
     */
    public function isPaymentMethodEnabled(string $methodId): bool;

    /**
     * Check if vaulting/tokenization is enabled
     *
     * @return bool True if enabled
     */
    public function isVaultingEnabled(): bool;

    /**
     * Check if 3D Secure is enabled
     *
     * @return bool True if enabled
     */
    public function is3DSecureEnabled(): bool;

    // ==================== Capture Strategy ====================

    /**
     * Get capture strategy
     *
     * @return string 'direct' (immediate), 'on_delivery' (manual on shipment), 'manual'
     */
    public function getCaptureStrategy(): string;

    // ==================== Logging ====================

    /**
     * Get log level
     *
     * @return string PSR-3 log level (debug, info, warning, error)
     */
    public function getLogLevel(): string;

    /**
     * Check if debug logging is enabled
     *
     * @return bool True if debug enabled
     */
    public function isDebugEnabled(): bool;
}
