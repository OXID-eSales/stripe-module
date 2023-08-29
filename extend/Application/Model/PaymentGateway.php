<?php
/**
 * Copyright © FATCHIP GmbH. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\stripe\extend\Application\Model;

use FC\stripe\Application\Model\Request\PaymentIntent;
use OxidEsales\Eshop\Application\Model\Order as CoreOrder;
use OxidEsales\Eshop\Core\Registry;
use FC\stripe\Application\Helper\Payment as PaymentHelper;

class PaymentGateway extends PaymentGateway_parent
{
    /**
     * OXID URL parameters to copy from initial order execute request
     *
     * @var array
     */
    protected $aStripeUrlCopyParameters = [
        'stoken',
        'sDeliveryAddressMD5',
        'oxdownloadableproductsagreement',
        'oxserviceproductsagreement',
    ];

    /**
     * Initiate Stripe payment functionality for Stripe payment types
     *
     * Executes payment, returns true on success.
     *
     * @param double $dAmount Goods amount
     * @param object $oOrder  User ordering object
     *
     * @extend executePayment
     * @return bool
     */
    public function executePayment($dAmount, &$oOrder)
    {
        if(!PaymentHelper::getInstance()->isStripePaymentMethod($oOrder->oxorder__oxpaymenttype->value)) {
            return parent::executePayment($dAmount, $oOrder);
        }
        return $this->handleStripePayment($oOrder, $dAmount);
    }

    /**
     * Collect parameters from the current order execute call and add them to the return URL
     * Also add parameters needed for the return process
     *
     * @return string
     */
    protected function stripeGetAdditionalParameters()
    {
        $oRequest = Registry::getRequest();
        $oSession = Registry::getSession();

        $sAddParams = '';

        foreach ($this->aStripeUrlCopyParameters as $sParamName) {
            $sValue = $oRequest->getRequestEscapedParameter($sParamName);
            if (!empty($sValue)) {
                $sAddParams .= '&'.$sParamName.'='.$sValue;
            }
        }

        $sSid = $oSession->sid(true);
        if ($sSid != '') {
            $sAddParams .= '&'.$sSid;
        }

        if (!$oRequest->getRequestEscapedParameter('stoken')) {
            $sAddParams .= '&stoken='.$oSession->getSessionChallengeToken();
        }
        $sAddParams .= '&ord_agb=1';
        $sAddParams .= '&rtoken='.$oSession->getRemoteAccessToken();

        return $sAddParams;
    }

    /**
     * Generate a return url with all necessary return flags
     *
     * @return string
     */
    protected function getRedirectUrl()
    {
        $sBaseUrl = Registry::getConfig()->getCurrentShopUrl().'index.php?cl=order&fnc=handleStripeReturn';

        return $sBaseUrl.$this->stripeGetAdditionalParameters();
    }

    /**
     * Execute Stripe API request and redirect to Stripe for payment
     *
     * @param CoreOrder $oOrder
     * @param double $dAmount
     * @return bool
     */
    protected function handleStripePayment(CoreOrder &$oOrder, $dAmount)
    {
        $oOrder->stripeSetOrderNumber();

        try {
            $oStripePaymentModel = $oOrder->stripeGetPaymentModel();

            $sPaymentMethodId = Registry::getSession()->getVariable('stripe_current_payment_method_id');

            /** @var PaymentIntent $oStripePaymentIntentRequest */
            $oStripePaymentIntentRequest = $oStripePaymentModel->getPaymentIntentRequest();
            $oStripePaymentIntentRequest->addRequestParameters($oOrder, $dAmount, $this->getRedirectUrl(), $sPaymentMethodId);
            $oStripePaymentIntent = $oStripePaymentIntentRequest->execute();

            $oOrder->stripeSetTransactionId($oStripePaymentIntent->id);

            if (isset($oStripePaymentIntent->next_action) && $oStripePaymentIntent->next_action->type == 'redirect_to_url') {
                Registry::getSession()->setVariable('stripeIsRedirected', true);
                Registry::getUtils()->redirect($oStripePaymentIntent->next_action->redirect_to_url->url);
            }
        } catch(\Exception $exc) {
            $this->_iLastErrorNo = $exc->getCode();
            $this->_sLastError = $exc->getMessage();
            return false;
        }
        return true;
    }
}
