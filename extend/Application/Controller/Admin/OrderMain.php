<?php
/**
 * Copyright © FATCHIP GmbH. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\stripe\extend\Application\Controller\Admin;

class OrderMain extends OrderMain_parent
{
    /**
     * Method is used for overriding.
     */
    protected function onOrderSend()
    {
        parent::onOrderSend();

        $oOrder = oxNew(\OxidEsales\Eshop\Application\Model\Order::class);
        if ($oOrder->load($this->getEditObjectId()) && $oOrder->stripeIsStripePaymentUsed()) {
            $oOrder->stripeMarkOrderAsShipped();
        }
    }

    /**
     * Saves main orders configuration parameters.
     */
    public function save()
    {
        $aParams = \OxidEsales\Eshop\Core\Registry::getRequest()->getRequestParameter('editval');

        $blUpdateTrackingCode = false;
        $oOrder = oxNew(\OxidEsales\Eshop\Application\Model\Order::class);
        if (!empty($aParams['oxorder__oxtrackcode']) &&
            $oOrder->load($this->getEditObjectId()) &&
            $oOrder->stripeIsStripePaymentUsed() &&
            $aParams['oxorder__oxtrackcode'] != $oOrder->oxorder__oxtrackcode->value &&
            $oOrder->oxorder__oxsenddate->value != '-' &&
            $oOrder->oxorder__oxsenddate->value != '0000-00-00 00:00:00'
        ) {
            $blUpdateTrackingCode = true;
        }

        parent::save();

        if ($blUpdateTrackingCode === true) {
            $oOrder->stripeUpdateShippingTrackingCode($aParams['oxorder__oxtrackcode']);
        }
    }
}
