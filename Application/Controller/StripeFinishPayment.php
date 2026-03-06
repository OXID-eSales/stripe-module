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
                // Verify ownership: if a user is logged in, they must own the order
                $oUser = Registry::getSession()->getUser();
                if ($oUser && $oUser->getId() !== $oOrder->getFieldData('oxuserid')) {
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
        $sRedirectUrl = $config->getCurrentShopUrl() . 'index.php?cl=basket&shp=' . $config->getShopId();

        $oOrder = $this->getOrder();
        if ($oOrder !== false) {
            $oOrder->stripeReinitializePayment();
            $sRedirectUrl = $config->getCurrentShopUrl() . 'index.php?cl=success&shp=' . $config->getShopId();
        }

        Registry::getUtils()->redirect($sRedirectUrl);
    }
}
