<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidSolutionCatalysts\Stripe\Application\Controller;

use OxidEsales\Eshop\Application\Controller\FrontendController;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Core\Registry;

class StripeFinishPayment extends FrontendController
{
    /**
     * @var string
     */
    protected $_sThisTemplate = 'stripewebhook.tpl';

    /**
     * Returns order or false if no id given or order not eligible
     *
     * @return bool|object
     */
    protected function getOrder()
    {
        $sOrderId = Registry::getRequest()->getRequestParameter('id');
        if ($sOrderId) {
            $oOrder = oxNew(Order::class);
            $oOrder->load($sOrderId);
            if ($oOrder->getId() && $oOrder->stripeIsEligibleForPaymentFinish()) {
                // The visitor has to be signed in AND has to own the order. Being signed in is
                // not optional here: the link travels by email and carries nothing but the order
                // id, so anybody holding it would otherwise finish a stranger's payment - and,
                // before this was fixed, be signed in as that customer on the way (see the
                // security entry in the CHANGELOG).
                $oUser = Registry::getSession()->getUser();
                if (!$oUser || $oUser->getId() !== $oOrder->getFieldData('oxuserid')) {
                    return false;
                }
                return $oOrder;
            }
        }
        return false;
    }

    /**
     * The render function
     */
    public function render()
    {
        $config = Registry::getConfig();

        if (!Registry::getSession()->getUser()) {
            // Nobody is signed in: send the customer to the login page rather than to the
            // basket, so the second chance link still leads somewhere useful - they sign in
            // and open the link from the email again.
            Registry::getUtils()->redirect(
                $config->getSslShopUrl() . 'index.php?cl=account&shp=' . $config->getShopId()
            );
            return;
        }

        $sRedirectUrl = $config->getCurrentShopUrl() . 'index.php?cl=basket&shp=' . $config->getShopId();

        $oOrder = $this->getOrder();
        if ($oOrder !== false) {
            $oOrder->stripeReinitializePayment();
            $sRedirectUrl = $config->getCurrentShopUrl() . 'index.php?cl=success&shp=' . $config->getShopId();
        }

        Registry::getUtils()->redirect($sRedirectUrl);
    }
}
