<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidSolutionCatalysts\Stripe\Service;

use OxidEsales\Eshop\Application\Model\Order as CoreOrder;
use OxidEsales\Eshop\Core\Registry;
use Stripe\StripeClient;

/**
 * Service for managing Stripe API client connections
 */
class StripeApiService
{
    /**
     * @var StripeConfigService
     */
    private StripeConfigService $configService;

    /**
     * Constructor
     *
     * @param StripeConfigService $configService
     */
    public function __construct(StripeConfigService $configService)
    {
        $this->configService = $configService;
    }

    /**
     * Returns Stripe Client for current mode
     *
     * @param string $sMode Optional mode override (live/test)
     * @return StripeClient
     * @throws \Exception
     */
    public function getClient(string $sMode = ''): StripeClient
    {
        if (empty($sMode)) {
            $sMode = $this->configService->getStripeMode();
        }
        $sStripeToken = $this->configService->getStripeToken($sMode);
        return $this->getClientWithToken($sStripeToken);
    }

    /**
     * Load Stripe API client with specific token
     *
     * @param string $sStripeToken
     * @return StripeClient
     * @throws \Exception
     */
    public function getClientWithToken(string $sStripeToken): StripeClient
    {
        try {
            if (!$sStripeToken) {
                throw new \Exception(Registry::getLang()->translateString('STRIPE_CLIENT_MISSING_API_KEY_ERROR'));
            }

            if (class_exists('Stripe\StripeClient')) {
                return new StripeClient($sStripeToken);
            } else {
                throw new \Exception(Registry::getLang()->translateString('STRIPE_CLIENT_MISSING_API_CLASS_ERROR'));
            }
        } catch (\Exception $oEx) {
            Registry::getLogger()->error($oEx->getMessage());
            throw new \Exception(Registry::getLang()->translateString('STRIPE_CLIENT_CONNECTION_ERROR'));
        }
    }

    /**
     * Returns matching API client for the mode the given order was created in
     *
     * @param  CoreOrder $oOrder
     * @return StripeClient
     * @throws \Exception
     */
    public function getClientByOrder(CoreOrder $oOrder): StripeClient
    {
        $sMode = $oOrder->oxorder__stripemode->value;
        if (empty($sMode)) {
            $sMode = '';
        }

        return $this->getClient($sMode);
    }

    /**
     * Check if connection with token can be established
     *
     * @param  string $sToken
     * @return bool
     */
    public function testConnection(string $sToken): bool
    {
        try {
            $aStripeInfo = $this->getClientWithToken($sToken)->customers->all();
            if (empty($aStripeInfo)) {
                return false;
            }
        } catch (\Exception $oEx) {
            return false;
        }
        return true;
    }

    /**
     * Check if connection with config variable token can be established
     *
     * @param  string $sTokenConfVar
     * @return bool
     */
    public function testConnectionWithConfigVar(string $sTokenConfVar): bool
    {
        $sStripeToken = $this->configService->getShopConfVar($sTokenConfVar);
        return $this->testConnection($sStripeToken);
    }
}
