<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidSolutionCatalysts\Stripe\Application\Model\Request;

use OxidEsales\Eshop\Application\Model\Order as CoreOrder;
use OxidEsales\Eshop\Application\Model\User as CoreUser;
use OxidEsales\Eshop\Core\Registry;

abstract class Base
{
    /**
     * Array or request parameters
     *
     * @var array
     */
    protected $aParameters = [];

    /**
     * Add parameter to request
     *
     * @param string $sKey
     * @param string|array $mValue
     * @return void
     */
    public function addParameter($sKey, $mValue)
    {
        $this->aParameters[$sKey] = $mValue;
    }

    /**
     * Execute Request to Stripe API and return Response
     *
     * @return mixed
     * @throws \Exception
     */
    public abstract function execute();

    /**
     * Get amount array
     *
     * @param CoreOrder $oOrder
     * @param double $dAmount
     * @return array
     */
    protected function getAmountParameters(CoreOrder $oOrder, $dAmount)
    {
        return [
            'currency' => $oOrder->oxorder__oxcurrency->value,
            'value' => number_format($dAmount, 2, '.', ''),
        ];
    }

    /**
     * Loads country object and return country iso code
     *
     * @param string $sCountryId
     * @return string
     */
    protected function getCountryCode($sCountryId)
    {
        $oCountry = oxNew('oxcountry');
        $oCountry->load($sCountryId);
        return $oCountry->oxcountry__oxisoalpha2->value;
    }

    /**
     * Convert region id into region title
     *
     * @param string $sRegionId
     * @return string
     */
    protected function getRegionTitle($sRegionId)
    {
        $oState = oxNew('oxState');
        return $oState->getTitleById($sRegionId);
    }

    /**
     * Return billing address parameters from Order object
     *
     * @param CoreOrder $oOrder
     * @return array
     */
    protected function getBillingAddressParametersFromOrder(CoreOrder $oOrder)
    {
        $aReturn = [
            'address' => [
                'line1' => trim($oOrder->oxorder__oxbillstreet->value.' '.$oOrder->oxorder__oxbillstreetnr->value),
                'postal_code' => $oOrder->oxorder__oxbillzip->value,
                'city' => $oOrder->oxorder__oxbillcity->value,
                'country' => $this->getCountryCode($oOrder->oxorder__oxbillcountryid->value)
            ]
        ];
        if (!empty((string)$oOrder->oxorder__oxbillcompany->value)) {
            $aReturn['name'] = $oOrder->oxorder__oxbillcompany->value;
        }
        else {
            $sTranslatedSalutation = Registry::getLang()->translateString($oOrder->oxorder__oxbillsal->value);
            if (!empty($sTranslatedSalutation)) {
                $aReturn['name'] = $sTranslatedSalutation . ' ';
            }
            $aReturn['name'] .= $oOrder->oxorder__oxbillfname->value . ' ' . $oOrder->oxorder__oxbilllname->value;
        }

        if (!empty((string)$oOrder->oxorder__oxbillstateid->value)) {
            $aReturn['address']['state'] = $this->getRegionTitle($oOrder->oxorder__oxbillstateid->value);
        }

        if (!empty((string)$oOrder->oxorder__oxbillfon->value)) {
            $aReturn['phone'] = $oOrder->oxorder__oxbillfon->value;
        }

        if (!empty((string)$oOrder->oxorder__oxbillemail->value)) {
            $aReturn['email'] = $oOrder->oxorder__oxbillemail->value;
        }

        return $aReturn;
    }

    /**
     * Return billing address parameters from User object
     *
     * @param CoreUser $oUser
     * @return array
     */
    protected function getBillingAddressParametersFromUser(CoreUser $oUser)
    {
        $aReturn = [
            'address' => [
                'line1' => trim($oUser->oxuser__oxstreet->value.' '.$oUser->oxuser__oxstreetnr->value),
                'postal_code' => $oUser->oxuser__oxzip->value,
                'city' => $oUser->oxuser__oxcity->value,
                'country' => $this->getCountryCode($oUser->oxuser__oxcountryid->value)
            ]
        ];
        if (!empty((string)$oUser->oxuser__oxcompany->value)) {
            $aReturn['name'] = $oUser->oxuser__oxcompany->value;
        }
        else {
            $sTranslatedSalutation = Registry::getLang()->translateString($oUser->oxuser__oxsal->value);
            if (!empty($sTranslatedSalutation)) {
                $aReturn['name'] = $sTranslatedSalutation . ' ';
            }
            $aReturn['name'] .= $oUser->oxuser__oxfname->value . ' ' . $oUser->oxuser__oxlname->value;
        }

        if (!empty((string)$oUser->oxuser__oxstateid->value)) {
            $aReturn['address']['state'] = $this->getRegionTitle($oUser->oxuser__oxstateid->value);
        }

        if (!empty((string)$oUser->oxuser__oxfon->value)) {
            $aReturn['phone'] = $oUser->oxuser__oxfon->value;
        }

        if (!empty((string)$oUser->oxuser__oxusername->value)) {
            $aReturn['email'] = $oUser->oxuser__oxusername->value;
        }

        return $aReturn;
    }

    /**
     * Return shipping address parameters
     *
     * @param CoreOrder $oOrder
     * @return array
     */
    protected function getShippingAddressParameters(CoreOrder $oOrder)
    {
        $aReturn = [
            'address' => [
                'line1' => trim($oOrder->oxorder__oxdelstreet->value.' '.$oOrder->oxorder__oxdelstreetnr->value),
                'postal_code' => $oOrder->oxorder__oxdelzip->value,
                'city' => $oOrder->oxorder__oxdelcity->value,
                'country' => $this->getCountryCode($oOrder->oxorder__oxdelcountryid->value)
            ]
        ];
        if (!empty((string)$oOrder->oxorder__oxdelcompany->value)) {
            $aReturn['name'] = $oOrder->oxorder__oxdelcompany->value;
        }
        else {
            $sTranslatedSalutation = Registry::getLang()->translateString($oOrder->oxorder__oxdelsal->value);
            if (!empty($sTranslatedSalutation)) {
                $aReturn['name'] = $sTranslatedSalutation . ' ';
            }
            $aReturn['name'] .= $oOrder->oxorder__oxdelfname->value . ' ' . $oOrder->oxorder__oxdellname->value;
        }

        if (!empty((string)$oOrder->oxorder__oxdelstateid->value)) {
            $aReturn['address']['state'] = $this->getRegionTitle($oOrder->oxorder__oxdelstateid->value);
        }

        if (!empty((string)$oOrder->oxorder__oxdelfon->value)) {
            $aReturn['phone'] = $oOrder->oxorder__oxdelfon->value;
        }

        return $aReturn;
    }

    /**
     * Return metadata parameters
     *
     * @param CoreOrder $oOrder
     * @return array
     */
    protected function getMetadataParameters(CoreOrder $oOrder)
    {
        return [
            'order_id' => $oOrder->getId(),
            'store_id' => $oOrder->getShopId(),
        ];
    }

    /**
     * @param CoreUser $oUser
     * @return string
     */
    protected function getCustomerId(CoreUser $oUser)
    {
        return $oUser->oxuser__stripecustomerid->value;
    }

    /**
     * @param CoreUser $oUser
     * @return string
     */
    protected function getCustomerEmail(CoreUser $oUser)
    {
        return $oUser->oxuser__oxusername->value;
    }

    /**
     * Returns collected request parameters
     *
     * @return array
     */
    protected function getParameters()
    {
        return $this->aParameters;
    }
}
