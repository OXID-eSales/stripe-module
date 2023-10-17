<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidSolutionCatalysts\Stripe\extend\Application\Controller\Admin;

use OxidSolutionCatalysts\Stripe\Application\Helper\Payment;
use OxidSolutionCatalysts\Stripe\Application\Model\PaymentConfig;
use OxidEsales\Eshop\Core\Registry;

class PaymentMain extends PaymentMain_parent
{
    /**
     * Saves payment parameters changes.
     *
     * @return void
     */
    public function save()
    {
        parent::save();

        $aStripeParams = Registry::getRequest()->getRequestParameter("stripe");

        $oPaymentConfig = oxNew(PaymentConfig::class);
        $oPaymentConfig->savePaymentConfig($this->getEditObjectId(), $aStripeParams);
    }

    /**
     * Return order status array
     *
     * @return array
     */
    public function stripeGetOrderFolders()
    {
        return Registry::getConfig()->getConfigParam('aOrderfolder');
    }

    /**
     * Check if the token was correctly configured
     *
     * @return bool
     */
    public function stripeIsTokenConfigured()
    {
        return Payment::getInstance()->stripeIsTokenConfigured();
    }

    /**
     * Check if the token was correctly configured
     *
     * @return bool
     */
    public function stripeIsKeyConfigured()
    {
        return Payment::getInstance()->stripeIsTokenConfigured();
    }
}
