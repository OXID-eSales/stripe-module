<?php
/**
 * Copyright © FATCHIP GmbH. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\stripe\Application\Model;

use OxidEsales\Eshop\Core\DatabaseProvider;

class RequestLog
{
    public static $sTableName = "striperequestlog";

    /**
     * Return create query for module installation
     *
     * @return string
     */
    public static function getTableCreateQuery()
    {
        return "CREATE TABLE `".self::$sTableName."` (
            `OXID` INT(32) NOT NULL AUTO_INCREMENT COLLATE 'latin1_general_ci',
            `TIMESTAMP` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `ORDERID` VARCHAR(32) NOT NULL,
            `STOREID` VARCHAR(32) NOT NULL,
            `REQUESTTYPE` VARCHAR(32) NOT NULL DEFAULT '',
            `RESPONSESTATUS` VARCHAR(32) NOT NULL DEFAULT '',
            `REQUEST` TEXT NOT NULL,
            `RESPONSE` TEXT NOT NULL,
            PRIMARY KEY (OXID)
        ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT COLLATE='utf8_general_ci';";
    }

    /**
     * Encode data object to a saveable string
     *
     * @param $oData
     * @return string
     */
    protected function encodeData($oData)
    {
        return json_encode($oData);
    }

    /**
     * Decode data array from a encoded string
     *
     * @param string $sData
     * @return array
     */
    protected function decodeData($sData)
    {
        return json_decode($sData, true);
    }

    /**
     * Logs an error response from a request, coming in form of an exception
     *
     * @param  array $aRequest
     * @param  string $sCode
     * @param  string $sMessage
     * @param  string $sOrderId
     * @param  string $sStoreId
     * @return void
     */
    public function logExceptionResponse($aRequest, $sCode, $sMessage, $sOrderId = null, $sStoreId = null)
    {
        $aResponse = [
            'status' => 'ERROR',
            'code' => $sCode,
            'customMessage' => $sMessage
        ];

        $this->logRequest($aRequest, (object)$aResponse, $sOrderId, $sStoreId);
    }

    /**
     * Remove unnecessary information from the response
     *
     * @param object $oResponse
     * @return array
     */
    protected function formatResponse($oResponse)
    {
        $aResponse = get_object_vars($oResponse);
        if (isset($aResponse['_links'])) {
            unset($aResponse['_links']);
        }
        if (method_exists($oResponse, 'getCheckoutUrl')) {
            $sCheckoutUrl = $oResponse->getCheckoutUrl();
            if (!empty($sCheckoutUrl)) {
                $aResponse['checkoutUrl'] = $sCheckoutUrl;
            }
        }
        return $aResponse;
    }

    /**
     * Returns log entry for given order id
     *
     * @param  string $sOrderId
     * @return array|bool
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
     */
    public function getLogEntryForOrder($sOrderId)
    {
        $oDb = DatabaseProvider::getDb();
        $oDb->setFetchMode(DatabaseProvider::FETCH_MODE_ASSOC);

        $sQuery = "SELECT * FROM ".self::$sTableName." WHERE orderid = ? AND ((requesttype = 'order' AND responsestatus = 'created') OR (requesttype = 'payment' AND responsestatus = 'open')) AND response LIKE '%checkoutUrl%'";
        $aRow = $oDb->getRow($sQuery, array($sOrderId));
        if ($aRow) {
            return $aRow;
        }
        return false;
    }

    /**
     * Parse data and write the request and response in one DB entry
     *
     * @param array       $aRequest
     * @param string|null $sOrderId
     * @param string|null $sStoreId
     * @param $oResponse
     */
    public function logRequest($aRequest, $oResponse, $sOrderId = null, $sStoreId = null)
    {
        $oDb = DatabaseProvider::getDb();

        if ($sOrderId === null) {
            $sOrderId = isset($aRequest['metadata']['order_id']) ? $aRequest['metadata']['order_id'] : '';
        }
        if ($sStoreId === null) {
            $sStoreId = isset($aRequest['metadata']['store_id']) ? $aRequest['metadata']['store_id'] : '';
        }
        $sRequestType = (property_exists($oResponse, "resource") && !is_null($oResponse->resource)) ? $oResponse->resource : '';
        $sResponseStatus = (property_exists($oResponse, "status") && !is_null($oResponse->status)) ? $oResponse->status : '';

        $sSavedRequest = $this->encodeData($aRequest);
        $sSavedResponse = $this->encodeData($this->formatResponse($oResponse));

        $sQuery = " INSERT INTO `".self::$sTableName."` (
                        ORDERID, STOREID, REQUESTTYPE, RESPONSESTATUS, REQUEST, RESPONSE
                    ) VALUES (
                        :orderid, :storeid, :requesttype, :responsestatus, :savedrequest, :savedresponse
                    )";
        $oDb->Execute($sQuery, [
            ':orderid' => $sOrderId,
            ':storeid' => $sStoreId,
            ':requesttype' => $sRequestType,
            ':responsestatus' => $sResponseStatus,
            ':savedrequest' => $sSavedRequest,
            ':savedresponse' => $sSavedResponse,
        ]);
    }
}
