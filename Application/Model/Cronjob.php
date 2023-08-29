<?php
/**
 * Copyright © FATCHIP GmbH. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\stripe\Application\Model;

use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\EshopCommunity\Core\Database\Adapter\DatabaseInterface;

class Cronjob
{
    /**
     * @var Cronjob
     */
    protected static $oInstance = null;

    /**
     * Table name
     *
     * @var string
     */
    public static $sTableName = "stripecronjob";

    /**
     * Create singleton instance of cronjob resource model
     *
     * @return Cronjob
     */
    public static function getInstance()
    {
        if (self::$oInstance === null) {
            self::$oInstance = oxNew(self::class);
        }
        return self::$oInstance;
    }

    /**
     * Return create query for module installation
     *
     * @return string
     */
    public static function getTableCreateQuery()
    {
        return "CREATE TABLE `".self::$sTableName."` (
            `OXID` CHAR(32) NOT NULL COLLATE 'latin1_general_ci',
            `MINUTE_INTERVAL` INT(11) NOT NULL,
            `LAST_RUN` DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (`OXID`) USING BTREE
        ) COLLATE='utf8_general_ci' ENGINE=InnoDB";
    }

    /**
     * Adds new cronjob to the table
     *
     * @param  string $sCronjobId
     * @param  int    $iDefaultMinuteInterval
     * @return void
     */
    public function addNewCronjob($sCronjobId, $iDefaultMinuteInterval)
    {
        $sQuery = "INSERT INTO `".self::$sTableName."` (OXID, MINUTE_INTERVAL, LAST_RUN) VALUES(:oxid, :minuteinterval, '0000-00-00 00:00:00');";

        DatabaseProvider::getDb()->Execute($sQuery, [
            ':oxid' => $sCronjobId,
            ':minuteinterval' => $iDefaultMinuteInterval,
        ]);
    }

    /**
     * Check if cronjob already exists
     *
     * @param  string $sCronjobId
     * @return bool
     */
    public function isCronjobAlreadyExisting($sCronjobId)
    {
        $sQuery = "SELECT OXID FROM `".self::$sTableName."` WHERE OXID = ?;";
        if (!DatabaseProvider::getDb()->getOne($sQuery, array($sCronjobId))) {
            return false;
        }
        return true;
    }

    /**
     * Marks given cronjob id as finished
     *
     * @param  string $sCronjobId
     * @return void
     */
    public function markCronjobAsFinished($sCronjobId)
    {
        DatabaseProvider::getDb()->execute("UPDATE `".self::$sTableName."` SET LAST_RUN = NOW() WHERE OXID = ?;", array($sCronjobId));
    }

    /**
     * Return cronjob data for given cronjobId
     *
     * @param  string $sCronjobId
     * @return array
     */
    public function getCronjobData($sCronjobId)
    {
        $oDb = DatabaseProvider::getDb(true);
        $oDb->setFetchMode(DatabaseInterface::FETCH_MODE_ASSOC);
        $sQuery = "SELECT * FROM `".self::$sTableName."` WHERE OXID = ?;";
        return $oDb->getRow($sQuery, array($sCronjobId));
    }
}