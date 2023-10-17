<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidSolutionCatalysts\Stripe\Application\Model\Cronjob;

use OxidSolutionCatalysts\Stripe\Application\Model\Cronjob;
use OxidEsales\Eshop\Core\Registry;

class Base
{
    /**
     * Id of current cronjob
     *
     * @var string
     */
    protected $sCronjobId = null;

    /**
     * Default cronjob interval in minutes
     *
     * @var int
     */
    protected $iDefaultMinuteInterval = null;

    /**
     * Logfile name
     *
     * @var string
     */
    protected $sLogFileName = 'StripeCronjobErrors.log';

    /**
     * Data from cronjob table
     *
     * @var array
     */
    protected $aDbData = null;

    /**
     * ShopId used for cronjob, false means no shopId restriction
     *
     * @var int|false
     */
    protected $iShopId = false;

    /**
     * Base constructor.
     *
     * @param int|false $iShopId
     * @return void
     */
    public function __construct($iShopId = false)
    {
        $this->iShopId = $iShopId;

        $oCronjob = Cronjob::getInstance();
        if ($this->getCronjobId() !== null && $oCronjob->isCronjobAlreadyExisting($this->getCronjobId()) === false) {
            $oCronjob->addNewCronjob($this->getCronjobId(), $this->getDefaultMinuteInterval());
        }
        $this->loadDbData();
    }

    /**
     * Adds data of cronjob to property
     *
     * @return void
     */
    protected function loadDbData()
    {
        $this->aDbData = Cronjob::getInstance()->getCronjobData($this->getCronjobId());
    }

    /**
     * Return cronjob id
     *
     * @return string
     */
    public function getCronjobId()
    {
        return $this->sCronjobId;
    }

    /**
     * Returns shop id set by cronjob call
     *
     * @return int|false
     */
    public function getShopId()
    {
        return $this->iShopId;
    }

    /**
     * Return default interval in minutes
     *
     * @return int
     */
    public function getDefaultMinuteInterval()
    {
        return $this->iDefaultMinuteInterval;
    }

    /**
     * Returns datetime of last run of the cronjob
     *
     * @return string
     */
    public function getLastRunDateTime()
    {
        return $this->aDbData['LAST_RUN'];
    }

    /**
     * Returns configured minute interval for cronjob
     *
     * @return int
     */
    public function getMinuteInterval()
    {
        return $this->aDbData['MINUTE_INTERVAL'];
    }

    /**
     * Converts cronjob id to activity conf var name
     *
     * @return string
     */
    protected function getActivityConfVarName()
    {
        $sConfVarName = $this->getCronjobId();
        $sConfVarName = str_ireplace('stripe_', 'stripe_cron_', $sConfVarName);
        $sConfVarName = str_replace('_', ' ', $sConfVarName);
        $sConfVarName = ucwords(($sConfVarName));
        $sConfVarName = str_replace(' ', '', $sConfVarName);
        $sConfVarName = 's'.$sConfVarName.'Active';
        return $sConfVarName;
    }

    /**
     * Checks if cronjob is activated in config
     *
     * @return bool
     */
    public function isCronjobActivated()
    {
        if ((bool)Registry::getConfig()->getShopConfVar($this->getActivityConfVarName()) === true) {
            return true;
        }
        return false;
    }

    /**
     * Echoes given information
     *
     * @param  string $sMessage
     * @return void
     */
    public static function outputInfo($sMessage)
    {
        echo date('Y-m-d H:i:s - ').$sMessage."\n";
    }

    /**
     * Main method for cronjobs
     * Hook to be overloaded by child classes
     * Return true if successful
     * Return false if not successful
     *
     * @return bool
     */
    protected function handleCronjob()
    {
        return false;
    }

    /**
     * Finished cronjob
     *
     * @param bool         $blResult
     * @param string|false $sError
     * @return void
     */
    protected function finishCronjob($blResult, $sError = false)
    {
        Cronjob::getInstance()->markCronjobAsFinished($this->getCronjobId());
        if ($blResult === false) {
            error_log('Cron "'.$this->getCronjobId().'" failed'.($sError !== false ? " (Error: ".$sError.")" : ""), getShopBasePath().'/log/'.$this->sLogFileName);
        }
    }

    /**
     * Starts cronjob
     *
     * @return bool
     */
    public function startCronjob()
    {
        self::outputInfo("Start cronjob '".$this->getCronjobId()."'");

        $sError = false;
        $blResult = false;
        try {
            $blResult = $this->handleCronjob();
        } catch (\Exception $exc) {
            $sError = $exc->getMessage();
        }
        $this->finishCronjob($blResult, $sError);

        self::outputInfo("Finished cronjob '".$this->getCronjobId()."' - Status: ".($blResult === false ? 'NOT' : '')." successful");
        if ($sError !== false) {
            self::outputInfo("Error-Message: ".$sError);
        }

        return $blResult;
    }
}