<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidSolutionCatalysts\Stripe\Application\Helper;

class Database
{
    /**
     * @var Database
     */
    protected static $oInstance = null;

    /**
     * Create singleton instance of database helper
     *
     * @return Database
     */
    public static function getInstance()
    {
        if (self::$oInstance === null) {
            self::$oInstance = oxNew(self::class);
        }
        return self::$oInstance;
    }

    /**
     * Returns parameter-string for prepared mysql statement
     *
     * @param array $aValues
     * @return string
     */
    public function getPreparedInStatement($aValues)
    {
        $sReturn = '';
        foreach ($aValues as $sValue) {
            $sReturn .= '?,';
        }
        return '('.rtrim($sReturn, ',').')';
    }
}
