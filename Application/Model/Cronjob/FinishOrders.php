<?php
/**
 * Copyright © FATCHIP GmbH. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\stripe\Application\Model\Cronjob;

use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Registry;

class FinishOrders extends Base
{
    /**
     * Id of current cronjob
     *
     * @var string
     */
    protected $sCronjobId = 'stripe_finish_orders';

    /**
     * Default cronjob interval in minutes
     *
     * @var int
     */
    protected $iDefaultMinuteInterval = 10;

    /**
     * Collects all expired order ids
     *
     * @return array
     */
    protected function getPaidUnfinishedOrders()
    {
        $aOrders = [];

        $sProcessingFolder = Registry::getConfig()->getShopConfVar('sStripeStatusProcessing');
        $sTriggerDate = date('Y-m-d H:i:s', time() - (60 * 60 * 24));
        $sMinPaidDate = date('Y-m-d H:i:s', time() - (60 * 2)); // This will prevent finishing legit orders before the customer does
        $sQuery = " SELECT 
                        OXID 
                    FROM 
                        oxorder 
                    WHERE 
                        oxstorno = 0 AND 
                        oxpaymenttype LIKE '%stripe%' AND 
                        oxorderdate > ? AND 
                        oxtransstatus = 'NOT_FINISHED' AND 
                        oxfolder = ? AND 
                        oxpaid != '0000-00-00 00:00:00' AND
                        oxpaid < ?";
        $aParams = [$sTriggerDate, $sProcessingFolder, $sMinPaidDate];
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
     * Collects expired order ids and finishes these orders
     *
     * @return bool
     */
    protected function handleCronjob()
    {
        $aUnfinishedOrders = $this->getPaidUnfinishedOrders();
        foreach ($aUnfinishedOrders as $sUnfinishedOrderId) {
            $oOrder = oxNew(Order::class);
            if ($oOrder->load($sUnfinishedOrderId) && $oOrder->stripeIsOrderInUnfinishedState()) {
                $oOrder->stripeFinishOrder();
                $this->outputInfo("Finished Order with ID ".$oOrder->getId());
            }
        }
        return true;
    }
}
