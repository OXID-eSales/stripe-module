<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidSolutionCatalysts\Stripe\Application\Model\Cronjob;

use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Core\DatabaseProvider;

class OrderShipment extends Base
{
    /**
     * Id of current cronjob
     *
     * @var string
     */
    protected $sCronjobId = 'stripe_order_shipment';

    /**
     * Default cronjob interval in minutes
     *
     * @var int
     */
    protected $iDefaultMinuteInterval = 10;

    /**
     * Collects all orders with a send date which was not marked yet
     *
     * @return array
     */
    protected function getUnmarkedShippedOrders()
    {
        $aOrders = [];

        $sMinSendDate = date('Y-m-d H:i:s', time() - (60 * 60 * 24)); // only look at orders in the last 24 hours

        $sQuery = " SELECT 
                        oxid 
                    FROM 
                        oxorder 
                    WHERE 
                        oxpaymenttype LIKE '%stripe%' AND
                        oxtransid LIKE '%pi_%' AND
                        oxsenddate >= ? AND
                        stripeshipmenthasbeenmarked = 0";
        $aParams = [$sMinSendDate];
        if ($this->getShopId() !== false) {
            $sQuery .= " AND oxshopid = ? ";
            $aParams[] = $this->getShopId();
        }
        $aResult = DatabaseProvider::getDb()->getAll($sQuery, $aParams);
        foreach ($aResult as $aRow) {
            $aOrders[] = $aRow[0];
        }

        return $aOrders;
    }

    /**
     * Collects unmarked order ids and marks them as shipped
     *
     * @return bool
     */
    protected function handleCronjob()
    {
        $aUnmarkedOrders = $this->getUnmarkedShippedOrders();
        foreach ($aUnmarkedOrders as $sUnmarkedOrderId) {
            $oOrder = oxNew(Order::class);
            if ($oOrder->load($sUnmarkedOrderId) && $oOrder->stripeIsStripePaymentUsed()) {
                $oOrder->stripeMarkOrderAsShipped();
                $this->outputInfo("Marked order-id ".$oOrder->getId()." as shipped.");
            }
        }
        return true;
    }
}
