<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidSolutionCatalysts\Stripe\Service;

use OxidSolutionCatalysts\Stripe\Application\Model\Payment\Base;
use OxidEsales\Eshop\Application\Model\Order as CoreOrder;
use Stripe\StripeClient;
use Stripe\WebhookEndpoint;

/**
 * Facade service for Stripe payment operations
 *
 * This service acts as a facade that delegates to specialized services:
 * - StripeConfigService: Configuration management
 * - StripeApiService: API client management
 * - StripeWebhookService: Webhook operations
 * - StripePaymentMethodService: Payment method management
 */
class PaymentService
{
    /**
     * @var StripeConfigService
     */
    private StripeConfigService $configService;

    /**
     * @var StripeApiService
     */
    private StripeApiService $apiService;

    /**
     * @var StripeWebhookService
     */
    private StripeWebhookService $webhookService;

    /**
     * @var StripePaymentMethodService
     */
    private StripePaymentMethodService $paymentMethodService;

    /**
     * Constructor
     *
     * @param StripeConfigService $configService
     * @param StripeApiService $apiService
     * @param StripeWebhookService $webhookService
     * @param StripePaymentMethodService $paymentMethodService
     */
    public function __construct(
        StripeConfigService $configService,
        StripeApiService $apiService,
        StripeWebhookService $webhookService,
        StripePaymentMethodService $paymentMethodService
    ) {
        $this->configService = $configService;
        $this->apiService = $apiService;
        $this->webhookService = $webhookService;
        $this->paymentMethodService = $paymentMethodService;
    }

    // ========================================================================
    // Payment Method Management (delegates to StripePaymentMethodService)
    // ========================================================================

    /**
     * Return all available Stripe payment methods
     *
     * @return array
     */
    public function getStripePaymentMethods(): array
    {
        return $this->paymentMethodService->getAllPaymentMethods();
    }

    /**
     * Determine if given paymentId is a Stripe payment method
     *
     * @param string $sPaymentId
     * @return bool
     */
    public function isStripePaymentMethod(string $sPaymentId): bool
    {
        return $this->paymentMethodService->isStripePaymentMethod($sPaymentId);
    }

    /**
     * Returns payment model for given paymentId
     *
     * @param string $sPaymentId
     * @return Base
     * @throws \Exception
     */
    public function getStripePaymentModel(string $sPaymentId): Base
    {
        return $this->paymentMethodService->getPaymentMethodModel($sPaymentId);
    }

    /**
     * Collect information about all activated Stripe payment types
     *
     * @return array
     */
    public function getStripePaymentInfo(): array
    {
        // This can be enhanced in StripePaymentMethodService later
        $methods = $this->paymentMethodService->getAllPaymentMethods();
        $info = [];
        foreach ($methods as $id => $title) {
            $info[$id] = [
                'title' => $title,
                'minAmount' => 0,
                'maxAmount' => 999999,
            ];
        }
        return $info;
    }

    // ========================================================================
    // Configuration Management (delegates to StripeConfigService)
    // ========================================================================

    /**
     * Returns configured mode of stripe
     *
     * @return string
     */
    public function getStripeMode(): string
    {
        return $this->configService->getStripeMode();
    }

    /**
     * Return Stripe access token for parameter mode
     *
     * @param string $sMode
     * @return string
     */
    public function getStripeToken(string $sMode = ''): string
    {
        return $this->configService->getStripeToken($sMode);
    }

    /**
     * Return Stripe private key for parameter mode
     *
     * @param string $sMode
     * @return string
     */
    public function getStripeKey(string $sMode = ''): string
    {
        return $this->configService->getStripeKey($sMode);
    }

    /**
     * Returns current Stripe publishable key for parameter mode
     *
     * @param string $sMode
     * @return string
     */
    public function getPublishableKey(string $sMode = ''): string
    {
        return $this->configService->getPublishableKey($sMode);
    }

    /**
     * Check if Stripe token is configured
     *
     * @return bool
     */
    public function stripeIsTokenConfigured(): bool
    {
        return $this->configService->isTokenConfigured();
    }

    /**
     * Check if Stripe key is configured
     *
     * @return bool
     */
    public function stripeIsKeyConfigured(): bool
    {
        return $this->configService->isKeyConfigured();
    }

    /**
     * Generates locale string
     *
     * @return string
     */
    public function getLocale(): string
    {
        return $this->configService->getLocale();
    }

    /**
     * Returns a floating price as integer in cents
     *
     * @param float $fPrice
     * @return int
     */
    public function priceInCent(float $fPrice): int
    {
        return $this->configService->priceInCent($fPrice);
    }

    /**
     * Returns config value
     *
     * @param  string $sVarName
     * @return mixed
     */
    public function getShopConfVar(string $sVarName)
    {
        return $this->configService->getShopConfVar($sVarName);
    }

    // ========================================================================
    // API Client Management (delegates to StripeApiService)
    // ========================================================================

    /**
     * Returns Stripe Client
     *
     * @param string $sMode
     * @return StripeClient
     * @throws \Exception
     */
    public function loadStripeApi(string $sMode = ''): StripeClient
    {
        return $this->apiService->getClient($sMode);
    }

    /**
     * Load Stripe API with specific token
     *
     * @param string $sStripeToken
     * @return StripeClient
     * @throws \Exception
     */
    public function loadStripeApiWithToken(string $sStripeToken): StripeClient
    {
        return $this->apiService->getClientWithToken($sStripeToken);
    }

    /**
     * Returns matching api endpoint the given order was created in
     *
     * @param  CoreOrder $oOrder
     * @return StripeClient
     * @throws \Exception
     */
    public function getApiClientByOrder(CoreOrder $oOrder): StripeClient
    {
        return $this->apiService->getClientByOrder($oOrder);
    }

    /**
     * Check if connection with token can be established
     *
     * @param  string $sTokenConfVar
     * @return bool
     */
    public function isConnectionWithTokenSuccessful(string $sTokenConfVar): bool
    {
        return $this->apiService->testConnectionWithConfigVar($sTokenConfVar);
    }

    // ========================================================================
    // Webhook Management (delegates to StripeWebhookService)
    // ========================================================================

    /**
     * Return the Stripe webhook url
     *
     * @return string
     */
    public function getWebhookUrl(): string
    {
        return $this->webhookService->getWebhookUrl();
    }

    /**
     * Return the Stripe webhook endpoint ID
     *
     * @return string
     */
    public function getWebhookEndpointId(): string
    {
        return $this->webhookService->getWebhookEndpointId();
    }

    /**
     * Return the Stripe webhook secret
     *
     * @return string
     */
    public function getWebhookEndpointSecret(): string
    {
        return $this->webhookService->getWebhookEndpointSecret();
    }

    /**
     * Return the Stripe webhook object if found
     *
     * @param string $sStripeWebhookEndpointId
     * @return WebhookEndpoint|null
     */
    public function stripeRetrieveWebhookEndpoint(string $sStripeWebhookEndpointId): ?WebhookEndpoint
    {
        return $this->webhookService->retrieveWebhookEndpoint($sStripeWebhookEndpointId);
    }

    /**
     * Check if webhook is configured
     *
     * @return bool
     */
    public function stripeIsWebhookConfigured(): bool
    {
        return $this->webhookService->isWebhookConfigured();
    }

    /**
     * Check if webhook is valid
     *
     * @param WebhookEndpoint $oStripeWebhookEndpoint
     * @return bool
     */
    public function stripeIsWebhookValid(WebhookEndpoint $oStripeWebhookEndpoint): bool
    {
        return $this->webhookService->isWebhookValid($oStripeWebhookEndpoint);
    }

    /**
     * Deletes configured webhook endpoint and secret
     *
     * @return void
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function stripeDeleteWebhookParameter(): void
    {
        $this->webhookService->deleteWebhookConfiguration();
    }

    // ========================================================================
    // Service Accessors (for advanced usage)
    // ========================================================================

    /**
     * Get config service
     *
     * @return StripeConfigService
     */
    public function getConfigService(): StripeConfigService
    {
        return $this->configService;
    }

    /**
     * Get API service
     *
     * @return StripeApiService
     */
    public function getApiService(): StripeApiService
    {
        return $this->apiService;
    }

    /**
     * Get webhook service
     *
     * @return StripeWebhookService
     */
    public function getWebhookService(): StripeWebhookService
    {
        return $this->webhookService;
    }

    /**
     * Get payment method service
     *
     * @return StripePaymentMethodService
     */
    public function getPaymentMethodService(): StripePaymentMethodService
    {
        return $this->paymentMethodService;
    }
}
