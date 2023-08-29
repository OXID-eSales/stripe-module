<?php
/**
 * Copyright © FATCHIP GmbH. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\stripe\extend\Application\Controller;

use FC\stripe\Application\Helper\Order as OrderHelper;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Core\Registry;

class OrderController extends OrderController_parent
{
    /**
     * Delete sess_challenge from session to trigger the creation of a new order when needed
     */
    public function render()
    {
        $sSessChallenge = Registry::getSession()->getVariable('sess_challenge');
        $blStripeIsRedirected = Registry::getSession()->getVariable('stripeIsRedirected');
        if (!empty($sSessChallenge) && $blStripeIsRedirected === true) {
            OrderHelper::getInstance()->cancelCurrentOrder();
        }
        Registry::getSession()->deleteVariable('stripeIsRedirected');
        return parent::render();
    }

    /**
     * Load previously created order
     *
     * @return Order|false
     */
    protected function stripeGetOrder()
    {
        $sOrderId = Registry::getSession()->getVariable('sess_challenge');
        if (!empty($sOrderId)) {
            $oOrder = oxNew(Order::class);
            $oOrder->load($sOrderId);
            if ($oOrder->isLoaded() === true) {
                return $oOrder;
            }
        }
        return false;
    }

    /**
     * Writes error-status to session and redirects to payment page
     *
     * @param string $sErrorLangIdent
     * @return false
     */
    protected function redirectWithError($sErrorLangIdent)
    {
        Registry::getSession()->setVariable('payerror', -50);
        Registry::getSession()->setVariable('payerrortext', Registry::getLang()->translateString($sErrorLangIdent));
        Registry::getUtils()->redirect(Registry::getConfig()->getCurrentShopUrl().'index.php?cl=payment');
        return false; // execution ends with redirect - return used for unit tests
    }

    /**
     *
     * @return string
     */
    public function handleStripeReturn()
    {
        $oPayment = $this->getPayment();
        if ($oPayment && $oPayment->isStripePaymentMethod()) {
            Registry::getSession()->deleteVariable('stripeIsRedirected');

            $oOrder = $this->stripeGetOrder();
            if (!$oOrder) {
                return $this->redirectWithError('STRIPE_ERROR_ORDER_NOT_FOUND');
            }

            $sTransactionId = $oOrder->oxorder__oxtransid->value;
            if (empty($sTransactionId)) {
                return $this->redirectWithError('STRIPE_ERROR_TRANSACTIONID_NOT_FOUND');
            }

            $aResult = $oOrder->stripeGetPaymentModel()->getTransactionHandler()->processTransaction($oOrder, 'success');

            if ($aResult['success'] === false) {
                Registry::getSession()->deleteVariable('sess_challenge');
                $sErrorIdent = 'STRIPE_ERROR_SOMETHING_WENT_WRONG';
                if ($aResult['status'] == 'canceled') {
                    $sErrorIdent = 'STRIPE_ERROR_ORDER_CANCELED';
                } elseif ($aResult['status'] == 'failed') {
                    $sErrorIdent = 'STRIPE_ERROR_ORDER_FAILED';
                }
                return $this->redirectWithError($sErrorIdent);
            }

            // else - continue to parent::execute since success must be true
        }
        $sReturn = parent::execute();

        if (Registry::getSession()->getVariable('stripeReinitializePaymentMode')) {
            Registry::getSession()->deleteVariable('usr'); // logout user since the payment link should not be seen as a successful login
        }

        Registry::getSession()->deleteVariable('stripeReinitializePaymentMode');

        return $sReturn;
    }
}
