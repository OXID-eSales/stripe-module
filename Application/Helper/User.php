<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidSolutionCatalysts\Stripe\Application\Helper;

use OxidEsales\Eshop\Application\Model\User as CoreUser;
use OxidEsales\Eshop\Core\Field;

class User
{
    /**
     * @var User
     */
    protected static $oInstance = null;

    /**
     * Create singleton instance of order helper
     *
     * @return User
     */
    public static function getInstance()
    {
        if (self::$oInstance === null) {
            self::$oInstance = oxNew(self::class);
        }
        return self::$oInstance;
    }

    /**
     * Creates Stripe API user and adds customerId to user model
     *
     * @param  CoreUser $oUser
     * @return void
     */
    public function createStripeUser(CoreUser &$oUser)
    {
        $oResponse = Payment::getInstance()->loadStripeApi()->customers->create([
            'name' => $oUser->oxuser__oxfname->value.' '.$oUser->oxuser__oxlname->value,
            'email' => $oUser->oxuser__oxusername->value,
        ]);

        if ($oResponse && !empty($oResponse->id)) {
            $oUser->oxuser__stripecustomerid = new Field($oResponse->id);
            $oUser->save();
        }
    }
}
