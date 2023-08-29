<?php
/**
 * Copyright © FATCHIP GmbH. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\stripe\Application\Model\Cronjob;

use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Registry;

class SecondChance extends Base
{
    /**
     * Id of current cronjob
     *
     * @var string
     */
    protected $sCronjobId = 'stripe_second_chance';

    /**
     * Default cronjob interval in minutes
     *
     * @var int
     */
    protected $iDefaultMinuteInterval = 10;

    /**
     * Collects all unfinished orders
     *
     * @return array
     */
    protected function getUnfinishedOrders()
    {
        $aOrders = [];

        $iLastRunTime = time() - (60 * $this->iDefaultMinuteInterval);
        if (!empty($this->getLastRunDateTime()) && $this->getLastRunDateTime() != '0000-00-00 00:00:00') {
            $iLastRunTime = strtotime($this->getLastRunDateTime());
        }

        $iDayDiff = (int)Registry::getConfig()->getShopConfVar('iStripeCronSecondChanceTimeDiff');
        $iTriggerTimeDiff = 60 * 60 * 24 * $iDayDiff;

        $sTriggerMinDate = date('Y-m-d H:i:s', $iLastRunTime - $iTriggerTimeDiff - 10);
        $sTriggerDate = date('Y-m-d H:i:s', time() - $iTriggerTimeDiff + 10);

        $sQuery = " SELECT 
                        OXID 
                    FROM 
                        oxorder 
                    WHERE 
                        oxpaymenttype LIKE '%stripe%' AND 
                        oxorderdate > ? AND 
                        oxorderdate < ? AND 
                        oxtransstatus = 'NOT_FINISHED' AND  
                        oxpaid = '0000-00-00 00:00:00' AND
                        stripesecondchancemailsent = '0000-00-00 00:00:00'";
        $aParams = [$sTriggerMinDate, $sTriggerDate];
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
     * Collects unfinished order ids and send second chance email for these orders
     *
     * @return bool
     */
    protected function handleCronjob()
    {
        $aUnfinishedOrders = $this->getUnfinishedOrders();
        foreach ($aUnfinishedOrders as $sUnfinishedOrderId) {
            $oOrder = oxNew(Order::class);
            if ($oOrder->load($sUnfinishedOrderId) && $oOrder->stripeIsEligibleForPaymentFinish(true)) {
                $oOrder->stripeSendSecondChanceEmail();
            }
        }
        return true;
    }
}
