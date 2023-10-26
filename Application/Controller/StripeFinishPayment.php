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
    protected $_sThisTemplate = '@stripe/stripewebhook';

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
        $sRedirectUrl = Registry::getConfig()->getSslShopUrl()."?cl=basket";

        $oOrder = $this->getOrder();
        if ($oOrder !== false) {
            $oOrder->stripeReinitializePayment();
            $sRedirectUrl = Registry::getConfig()->getSslShopUrl()."?cl=success";
        }

        Registry::getUtils()->redirect($sRedirectUrl);
    }
}
