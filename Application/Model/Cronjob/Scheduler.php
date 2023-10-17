<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidSolutionCatalysts\Stripe\Application\Model\Cronjob;

use OxidEsales\Eshop\Core\Registry;

class Scheduler
{
    /**
     * List of all existing cronjobs
     *
     * @var array
     */
    protected $aCronjobs = [
        FinishOrders::class,
        SecondChance::class,
        OrderShipment::class,
    ];

    /**
     * Returns list of all cronjobs
     *
     * @return array
     */
    protected function getCronjobs()
    {
        return $this->aCronjobs;
    }

    /**
     * Check if cronjob is due again
     *
     * @param  Base $oCronjob
     * @return bool
     */
    protected function isCronjobDue(Base $oCronjob)
    {
        $iGracePeriod = 5; // Grace period timer to prevent cronjob not starting when crontab timer and minute interval are exactly the same
        if (empty($oCronjob->getLastRunDateTime()) || (strtotime($oCronjob->getLastRunDateTime()) - $iGracePeriod) <= (time() - ($oCronjob->getMinuteInterval() * 60))) {
            return true;
        }
        return false;
    }

    /**
     * Starts all cronjobs
     *
     * @param  int|false $iShopId
     * @return void
     */
    public function start($iShopId = false)
    {
        Base::outputInfo("START STRIPE CRONJOB EXECUTION");

        if ($iShopId !== false) {
            $oConfig = Registry::getConfig();
            $oConfig->setShopId($iShopId);
            Registry::set(\OxidEsales\Eshop\Core\Config::class, $oConfig);
        }

        foreach ($this->getCronjobs() as $sCronjobClass) {
            $oCronjob = oxNew($sCronjobClass, $iShopId);
            if ($oCronjob->isCronjobActivated() && $this->isCronjobDue($oCronjob)) {
                $oCronjob->startCronjob();
            }
        }

        Base::outputInfo("FINISHED STRIPE CRONJOB EXECUTION");
    }
}
