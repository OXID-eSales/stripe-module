<?php
/**
 * Copyright © FATCHIP GmbH. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\stripe\extend\Application\Controller\Admin;

use FC\stripe\Application\Helper\Payment;
use OxidEsales\Eshop\Core\Registry;
use Stripe\WebhookEndpoint;

class ModuleConfiguration extends ModuleConfiguration_parent
{
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
     * Returns array with options for iStripeCronSecondChanceTimeDiff config option
     *
     * @return array
     */
    public function stripeSecondChanceDayDiffs()
    {
        $aReturn = [];
        for ($i = 1; $i <= 14; $i++) {
            $aReturn[] = $i;
        }
        return $aReturn;
    }

    /**
     * @return bool
     */
    public function stripeIsTestMode()
    {
        return Registry::getConfig()->getShopConfVar('sStripeMode') == 'test';
    }

    /**
     * Check if test- or api-key is configured
     *
     * @return bool
     */
    public function stripeHasApiKeys()
    {
        if (!empty(Registry::getConfig()->getShopConfVar('sStripeLiveToken'))) {
            return true;
        }
        if (!empty(Registry::getConfig()->getShopConfVar('sStripeTestToken'))) {
            return true;
        }
        return false;
    }

    /**
     * Check if connection can be established for the api key
     *
     * @param  string $sConfVar
     * @return bool
     */
    public function stripeIsApiKeyUsable($sConfVar)
    {
        return Payment::getInstance()->isConnectionWithTokenSuccessful($sConfVar);
    }

    /**
     * Check if webhooks are configured in this shop
     *
     * @return bool
     */
    public function stripeGetWebhookCreateUrl()
    {
        return Registry::getConfig()->getShopUrl().'?cl=stripeWebhook&fnc=createWebhookEndpoint';
    }

    /**
     * Check if webhooks are configured in this shop
     *
     * @return bool
     */
    public function stripeWebhookCanCreate()
    {
        return Payment::getInstance()->stripeIsKeyConfigured();
    }

    /**
     * Returns array of all Stripe payment methods
     *
     * @return array
     */
    public function stripePaymentMethods()
    {
        return Payment::getInstance()->getStripePaymentMethods();
    }

    /**
     * Clean file name so that processFile routine doesnt throw an exception
     *
     * @param  string $sConfVar
     * @return void
     */
    protected function stripeCleanUploadFileName($sConfVar)
    {
        $_FILES[$sConfVar]['name'] = preg_replace('/[^\-_a-z0-9\.]/i', '', $_FILES[$sConfVar]['name']);
    }

    /**
     * @return bool
     */
    public function stripeIsStripe()
    {
        return $this->getEditObjectId() == 'stripe';
    }

    /**
     * @param string $sVarName
     * @return string
     */
    public function stripeGetConnectUrl($sVarName)
    {
        $sMode = $sVarName == 'sStripeTestToken' ? 'test' : 'live';
        $redirectUrl = Registry::getConfig()->getSslShopUrl().'admin/index.php?cl=stripeConnect&fnc=stripeFinishOnBoarding';
        $redirectUrl.= '&stoken=' . $this->getSession()->getSessionChallengeToken();
        $redirectUrl.= '&shop_param=' . $sMode;

        if ($sMode == 'test') {
            return 'https://dev-osm.oxid-esales.com/stripe-connect?shop_redirect_url=' . rawurlencode($redirectUrl);
        }
        return 'https://osm.oxid-esales.com/stripe-connect?shop_redirect_url=' . rawurlencode($redirectUrl);
    }

    /**
     * @return bool
     */
    public function stripeIsWebhookReady()
    {
        $oPaymentHelper = Payment::getInstance();
        if (!$oPaymentHelper->stripeIsWebhookConfigured()) {
            return false;
        }

        $oStripeWebhookEndpoint = $oPaymentHelper->stripeRetrieveWebhookEndpoint($oPaymentHelper->getWebhookEndpointId());
        if (!$oStripeWebhookEndpoint instanceof WebhookEndpoint) {
            return false;
        }

        return $oPaymentHelper->stripeIsWebhookValid($oStripeWebhookEndpoint);
    }
}
