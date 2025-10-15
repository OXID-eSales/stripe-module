<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidSolutionCatalysts\Stripe\Service;

use OxidEsales\Eshop\Core\Registry;
use Stripe\WebhookEndpoint;

/**
 * Service for managing Stripe webhook operations
 */
class StripeWebhookService
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
     * Constructor
     *
     * @param StripeConfigService $configService
     * @param StripeApiService $apiService
     */
    public function __construct(StripeConfigService $configService, StripeApiService $apiService)
    {
        $this->configService = $configService;
        $this->apiService = $apiService;
    }

    /**
     * Return the Stripe webhook url
     *
     * @return string
     */
    public function getWebhookUrl(): string
    {
        return Registry::getConfig()->getCurrentShopUrl() . 'index.php?cl=stripeWebhook';
    }

    /**
     * Return the Stripe webhook endpoint ID
     *
     * @return string
     */
    public function getWebhookEndpointId(): string
    {
        return (string) $this->configService->getShopConfVar('sStripeWebhookEndpoint');
    }

    /**
     * Return the Stripe webhook secret
     *
     * @return string
     */
    public function getWebhookEndpointSecret(): string
    {
        return (string) $this->configService->getShopConfVar('sStripeWebhookEndpointSecret');
    }

    /**
     * Check if webhook is configured
     *
     * @return bool
     */
    public function isWebhookConfigured(): bool
    {
        return !empty($this->getWebhookEndpointId());
    }

    /**
     * Retrieve the Stripe webhook endpoint object if found
     *
     * @param string $sStripeWebhookEndpointId
     * @return WebhookEndpoint|null
     */
    public function retrieveWebhookEndpoint(string $sStripeWebhookEndpointId): ?WebhookEndpoint
    {
        $sPrivateKey = $this->configService->getStripeKey($this->configService->getStripeMode());
        try {
            return $this->apiService->getClientWithToken($sPrivateKey)->webhookEndpoints->retrieve($sStripeWebhookEndpointId);
        } catch (\Exception $oEx) {
            return null;
        }
    }

    /**
     * Check if webhook is valid (enabled status)
     *
     * @param WebhookEndpoint $oStripeWebhookEndpoint
     * @return bool
     */
    public function isWebhookValid(WebhookEndpoint $oStripeWebhookEndpoint): bool
    {
        return $oStripeWebhookEndpoint->status == 'enabled';
    }

    /**
     * Delete configured webhook endpoint and secret from config
     *
     * @return void
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function deleteWebhookConfiguration(): void
    {
        $this->configService->saveShopConfVar('sStripeWebhookEndpoint', '');
        $this->configService->saveShopConfVar('sStripeWebhookEndpointSecret', '');
    }

    /**
     * Create webhook endpoint on Stripe
     *
     * @param array $aEnabledEvents
     * @return WebhookEndpoint
     * @throws \Exception
     */
    public function createWebhookEndpoint(array $aEnabledEvents = []): WebhookEndpoint
    {
        if (empty($aEnabledEvents)) {
            $aEnabledEvents = ['*'];
        }

        $client = $this->apiService->getClient();
        return $client->webhookEndpoints->create([
            'url' => $this->getWebhookUrl(),
            'enabled_events' => $aEnabledEvents,
        ]);
    }

    /**
     * Update webhook endpoint on Stripe
     *
     * @param string $sWebhookEndpointId
     * @param array $aEnabledEvents
     * @return WebhookEndpoint
     * @throws \Exception
     */
    public function updateWebhookEndpoint(string $sWebhookEndpointId, array $aEnabledEvents = []): WebhookEndpoint
    {
        if (empty($aEnabledEvents)) {
            $aEnabledEvents = ['*'];
        }

        $client = $this->apiService->getClient();
        return $client->webhookEndpoints->update($sWebhookEndpointId, [
            'enabled_events' => $aEnabledEvents,
        ]);
    }

    /**
     * Delete webhook endpoint from Stripe
     *
     * @param string $sWebhookEndpointId
     * @return void
     * @throws \Exception
     */
    public function deleteWebhookEndpoint(string $sWebhookEndpointId): void
    {
        $client = $this->apiService->getClient();
        $client->webhookEndpoints->delete($sWebhookEndpointId);
    }
}
