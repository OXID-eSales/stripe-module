<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidSolutionCatalysts\Stripe\extend\Application\Controller\Admin;

use OxidSolutionCatalysts\Stripe\Application\Helper\Order;
use OxidSolutionCatalysts\Stripe\Application\Helper\Payment;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\EshopCommunity\Internal\Container\ContainerFactory;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\Bridge\ModuleSettingBridgeInterface;
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
        return Order::getInstance()->stripeGetOrderFolders();
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
        return Payment::getInstance()->getShopConfVar('sStripeMode') == 'test';
    }

    /**
     * Check if test- or api-key is configured
     *
     * @return bool
     */
    public function stripeHasApiKeys()
    {
        if (!empty(Payment::getInstance()->getShopConfVar('sStripeLiveToken'))) {
            return true;
        }
        if (!empty(Payment::getInstance()->getShopConfVar('sStripeTestToken'))) {
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
     * Returns URL for webhook creation AJAX call (admin controller)
     *
     * @return string
     */
    public function stripeGetWebhookCreateUrl()
    {
        return Registry::getConfig()->getShopUrl(0, true) . 'admin/index.php?cl=module_config&fnc=stripeCreateWebhookEndpoint';
    }

    /**
     * AJAX endpoint for creating a webhook endpoint on Stripe connected account.
     * Tries to delete the currently configured one first if any.
     */
    public function stripeCreateWebhookEndpoint()
    {
        if (!Registry::getSession()->checkSessionChallenge()) {
            http_response_code(403);
            echo json_encode(['code' => 403, 'status' => 'ERROR', 'body' => ['message' => 'Access denied']]);
            exit();
        }

        $oPaymentHelper = Payment::getInstance();

        // Delete existing webhook endpoint if configured
        $sConfiguredEndpoint = $oPaymentHelper->getWebhookEndpointId();
        if (!empty($sConfiguredEndpoint)) {
            $oExisting = $oPaymentHelper->stripeRetrieveWebhookEndpoint($sConfiguredEndpoint);
            if ($oExisting && !$oExisting->isDeleted()) {
                try {
                    $oPaymentHelper->loadStripeApiWithToken(
                        $oPaymentHelper->getStripeKey($oPaymentHelper->getStripeMode())
                    )->webhookEndpoints->delete($sConfiguredEndpoint);
                } catch (\Exception $oEx) {
                    Registry::getLogger()->error($oEx);
                    echo json_encode([
                        'code' => 400,
                        'status' => 'ERROR',
                        'body' => ['message' => Registry::getLang()->translateString('STRIPE_WEBHOOK_CREATE_ERROR_DELETE_FAILED')],
                    ]);
                    exit();
                }
            }
            $oPaymentHelper->stripeDeleteWebhookParameter();
        }

        try {
            $sMode = Registry::getRequest()->getRequestEscapedParameter('mode') ?? '';
            $sPrivateKey = $oPaymentHelper->getStripeKey($sMode);
            $oApi = $oPaymentHelper->loadStripeApiWithToken($sPrivateKey);
            $sUrl = $oPaymentHelper->getWebhookUrl();
            $oWebhookEndpoint = $oApi->webhookEndpoints->create([
                'url' => $sUrl,
                'enabled_events' => [
                    'payment_intent.payment_failed',
                    'payment_intent.succeeded',
                    'charge.refunded',
                ],
                'connect' => true,
            ]);

            if ($oWebhookEndpoint) {
                $moduleSettingService = ContainerFactory::getInstance()->getContainer()->get(ModuleSettingBridgeInterface::class);
                $moduleSettingService->save('sStripeWebhookEndpoint', $oWebhookEndpoint->id, 'stripe');
                $moduleSettingService->save('sStripeWebhookEndpointSecret', $oWebhookEndpoint->secret, 'stripe');

                echo json_encode([
                    'code' => 200,
                    'status' => 'SUCCESS',
                    'body' => ['endpointId' => $oWebhookEndpoint->id],
                ]);
            } else {
                echo json_encode([
                    'code' => 400,
                    'status' => 'ERROR',
                    'body' => ['message' => Registry::getLang()->translateString('STRIPE_WEBHOOK_CREATE_ERROR')],
                ]);
            }
        } catch (\Exception $oEx) {
            echo json_encode([
                'code' => 400,
                'status' => 'ERROR',
                'body' => ['message' => Registry::getLang()->translateString('STRIPE_WEBHOOK_CREATE_ERROR') . ':' . $oEx->getMessage()],
            ]);
        }

        exit();
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
        $redirectUrl = Registry::getConfig()->getShopUrl(0,true).'admin/index.php?cl=stripeConnect&fnc=stripeFinishOnBoarding';
        $redirectUrl.= '&stoken=' . Registry::getSession()->getSessionChallengeToken();
        $redirectUrl.= '&shop_param=' . $sMode;
        $redirectUrl.= '&shp=' . Registry::getConfig()->getShopId();

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
