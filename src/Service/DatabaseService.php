<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidSolutionCatalysts\Stripe\Service;

/**
 * Service for database utility operations
 */
class DatabaseService
{
    /**
     * Returns parameter-string for prepared mysql statement
     *
     * @param array $aValues
     * @return string
     */
    public function getPreparedInStatement(array $aValues): string
    {
        $sReturn = '';
        foreach ($aValues as $sValue) {
            $sReturn .= '?,';
        }
        return '(' . rtrim($sReturn, ',') . ')';
    }
}
