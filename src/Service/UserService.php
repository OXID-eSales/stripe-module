<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidSolutionCatalysts\Stripe\Service;

use OxidEsales\Eshop\Application\Model\User as CoreUser;
use OxidEsales\Eshop\Core\Field;

/**
 * Service for managing Stripe user/customer operations
 */
class UserService
{
    /**
     * @var StripeApiService
     */
    private StripeApiService $apiService;

    /**
     * Constructor
     *
     * @param StripeApiService $apiService
     */
    public function __construct(StripeApiService $apiService)
    {
        $this->apiService = $apiService;
    }

    /**
     * Creates Stripe API user and adds customerId to user model
     * Returns customerId for direct usage
     *
     * @param  CoreUser $oUser
     * @return string
     * @throws \Exception
     */
    public function createStripeUser(CoreUser &$oUser): string
    {
        $client = $this->apiService->getClient();

        $oResponse = $client->customers->create([
            'name' => $oUser->oxuser__oxfname->value . ' ' . $oUser->oxuser__oxlname->value,
            'email' => $oUser->oxuser__oxusername->value,
        ]);

        if ($oResponse && !empty($oResponse->id)) {
            $oUser->oxuser__stripecustomerid = new Field($oResponse->id);
            $oUser->save();
        }

        return $oUser->oxuser__stripecustomerid->value;
    }

    /**
     * Checks if given CustomerId is still valid on Stripe account side
     *
     * @param string $sStripeCustomerId
     * @return bool
     */
    public function isValidCustomerId(string $sStripeCustomerId): bool
    {
        if (empty($sStripeCustomerId)) {
            return false;
        }

        try {
            $client = $this->apiService->getClient();
            $oResponse = $client->customers->retrieve($sStripeCustomerId);

            if (isset($oResponse->deleted) && $oResponse->deleted) {
                return false;
            }

            return !empty($oResponse->email);
        } catch (\Exception $e) {
            return false;
        }
    }
}
