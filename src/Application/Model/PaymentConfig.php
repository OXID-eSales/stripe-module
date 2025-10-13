<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidSolutionCatalysts\Stripe\Application\Model;

use OxidEsales\Eshop\Core\DatabaseProvider;

class PaymentConfig
{
    public static $sTableName = "stripepaymentconfig";

    /**
     * Return create query for module installation
     *
     * @return string
     */
    public static function getTableCreateQuery()
    {
        return "CREATE TABLE `".self::$sTableName."` (
            `OXID` CHAR(32) NOT NULL COLLATE 'latin1_general_ci',
            `CONFIG` TEXT NOT NULL,
            PRIMARY KEY (`OXID`)
        ) COLLATE='utf8_general_ci' ENGINE=InnoDB;";
    }

    /**
     * Save Stripe payment configuration for given payment type
     *
     * @param string $sPaymentId
     * @param array $aConfig
     * @return bool
     */
    public function savePaymentConfig($sPaymentId, $aConfig)
    {
        return $this->handleData($sPaymentId, $aConfig);
    }

    /**
     * Encode custom config array to a saveable string
     *
     * @param array $aCustomConfig
     * @return string
     */
    protected function encodeCustomConfig($aCustomConfig)
    {
        return json_encode($aCustomConfig);
    }

    /**
     * Decode custom config array to a saveable string
     *
     * @param string $sCustomConfig
     * @return array
     */
    protected function decodeCustomConfig($sCustomConfig)
    {
        return json_decode($sCustomConfig, true);
    }

    /**
     * Insert new entity
     *
     * @param string $sPaymentId
     * @param array $aCustomConfig
     * @return bool
     */
    protected function handleData($sPaymentId, $aCustomConfig)
    {
        $sConfig = $this->encodeCustomConfig($aCustomConfig);

        $sQuery = "INSERT INTO ".self::$sTableName." (OXID, CONFIG) VALUES(:paymentid, :config) ON DUPLICATE KEY UPDATE CONFIG = :config";

        DatabaseProvider::getDb()->Execute($sQuery, [
            ':paymentid' => $sPaymentId,
            ':config' => $sConfig,
        ]);

        return true;
    }

    /**
     * Return config array for given payment method
     *
     * @param string $sPaymentId
     * @return array
     */
    public function getPaymentConfig($sPaymentId)
    {
        $oDb = DatabaseProvider::getDb(DatabaseProvider::FETCH_MODE_ASSOC);

        $sQuery = "SELECT * FROM ".self::$sTableName." WHERE OXID = ? LIMIT 1";
        $aResult = $oDb->getRow($sQuery, array($sPaymentId));

        $aReturn = [];
        if (!empty($aResult)) {
            $aReturn = $this->decodeCustomConfig($aResult['CONFIG']);
        }
        return $aReturn;
    }
}
