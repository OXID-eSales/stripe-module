<?php
/**
 * Copyright © FATCHIP GmbH. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\stripe\Application\Model\Payment;

use FC\stripe\Application\Model\PaymentConfig;
use FC\stripe\Application\Model\Request\Card;
use FC\stripe\Application\Model\Request\PaymentIntent;
use FC\stripe\Application\Model\Request\PaymentMethod;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Application\Model\Order;
use FC\stripe\Application\Helper\Payment;

abstract class Base
{
    /**
     * Payment id in the oxid shop
     *
     * @var string
     */
    protected $sOxidPaymentId = null;

    /**
     * Method code used for API request
     *
     * @var string
     */
    protected $sStripePaymentCode = null;

    /**
     * Loaded payment config
     *
     * @var array
     */
    protected $aPaymentConfig = null;

    /**
     * Determines if the payment methods has to add a redirect url to the request
     *
     * @var bool
     */
    protected $blIsRedirectUrlNeeded = true;

    /**
     * Determines custom config template if existing, otherwise false
     *
     * @var string|bool
     */
    protected $sCustomConfigTemplate = false;

    /**
     * Determines custom frontend template if existing, otherwise false
     *
     * @var string|bool
     */
    protected $sCustomFrontendTemplate = false;

    /**
     * Determines if the payment method is hidden at first when payment list is displayed
     *
     * @var bool
     */
    protected $blIsMethodHiddenInitially = false;

    /**
     * Array with country-codes the payment method is restricted to
     * If property is set to false it is available to all countries
     *
     * @var array|false
     */
    protected $aBillingCountryRestrictedTo = false;

    /**
     * Array with currency-codes the payment method is restricted to
     * If property is set to false it is available to all currencies
     *
     * @var array|false
     */
    protected $aCurrencyRestrictedTo = false;

    /**
     * Determines if the payment method is only available for B2B orders
     * B2B mode is assumed when the company field in the billing address is filled
     *
     * @var bool
     */
    protected $blIsOnlyB2BSupported = false;

    /**
     * Return Oxid payment id
     *
     * @return string
     */
    public function getOxidPaymentId()
    {
        return $this->sOxidPaymentId;
    }

    /**
     * Return Stripe payment code
     *
     * @return string
     */
    public function getStripePaymentCode()
    {
        return $this->sStripePaymentCode;
    }

    /**
     * Returns array of billing country restrictions
     *
     * @return bool
     */
    public function getBillingCountryRestrictedCountries()
    {
        return $this->aBillingCountryRestrictedTo;
    }

    /**
     * Returns array of currency restrictions
     *
     * @return bool
     */
    public function getCurrencyRestrictedCurrencies()
    {
        return $this->aCurrencyRestrictedTo;
    }

    /**
     * Returns if the payment method only supports B2B orders
     *
     * @return bool
     */
    public function isOnlyB2BSupported()
    {
        return $this->blIsOnlyB2BSupported;
    }

    /**
     * Returns if the payment methods needs to add the redirect url
     *
     * @param  Order $oOrder
     * @return bool
     */
    public function isRedirectUrlNeeded(Order $oOrder)
    {
        return $this->blIsRedirectUrlNeeded;
    }

    /**
     * Returns custom config template or false if not existing
     *
     * @return bool|string
     */
    public function getCustomConfigTemplate()
    {
        return $this->sCustomConfigTemplate;
    }

    /**
     * Returns custom frontend template or false if not existing
     *
     * @return bool|string
     */
    public function getCustomFrontendTemplate()
    {
        return $this->sCustomFrontendTemplate;
    }

    /**
     * Returns if the payment methods has to be hidden initially
     *
     * @return bool
     */
    public function isStripeMethodHiddenInitially()
    {
        return $this->blIsMethodHiddenInitially;
    }

    /**
     * Loads payment config if not loaded, otherwise returns preloaded config
     *
     * @return array
     */
    public function getPaymentConfig()
    {
        if ($this->aPaymentConfig === null) {
            $oPaymentConfig = oxNew(PaymentConfig::class);
            $this->aPaymentConfig = $oPaymentConfig->getPaymentConfig($this->getOxidPaymentId());
        }
        return $this->aPaymentConfig;
    }

    /**
     * Get dynvalue parameters from session or request
     *
     * @return mixed|null
     */
    protected function getDynValueParameters()
    {
        $aDynvalue = Registry::getSession()->getVariable('dynvalue');
        if (empty($aDynvalue)) {
            $aDynvalue = Registry::getRequest()->getRequestParameter('dynvalue');
        }
        return $aDynvalue;
    }

    /**
     * Return dynvalue parameter
     *
     * @param string $sParam
     * @return string|false
     */
    protected function getDynValueParameter($sParam)
    {
        $aDynValue = $this->getDynValueParameters();
        if (isset($aDynValue[$sParam])) {
            return $aDynValue[$sParam];
        }
        return false;
    }

    /**
     * Returnes minimum order sum for Stripe payment type to be usable
     *
     * @return object|false
     */
    public function getStripeFromAmount()
    {
        $aInfo = Payment::getInstance()->getStripePaymentInfo();
        if (isset($aInfo[$this->sStripePaymentCode]['minAmount'])) {
            return $aInfo[$this->sStripePaymentCode]['minAmount'];
        }
        return false;
    }

    /**
     * Returnes maximum order sum for Stripe payment type to be usable
     *
     * @return object|false
     */
    public function getStripeToAmount()
    {
        $aInfo = Payment::getInstance()->getStripePaymentInfo();
        if (!empty(isset($aInfo[$this->sStripePaymentCode]['maxAmount']))) {
            return $aInfo[$this->sStripePaymentCode]['maxAmount'];
        }
        return false;
    }

    /**
     * Checks if given basket brutto price is withing the payment sum limitations of the current Stripe payment type
     *
     * @param double $dBasketBruttoPrice
     * @return bool
     */
    public function stripeIsBasketSumInLimits($dBasketBruttoPrice)
    {
        $oFrom = $this->getStripeFromAmount();
        if ($oFrom && $dBasketBruttoPrice < $oFrom->value) {
            return false;
        }

        $oTo = $this->getStripeToAmount();
        if ($oTo && $dBasketBruttoPrice > $oTo->value) {
            return false;
        }
        return true;
    }

    /**
     * Checks if the payment method is available for the current billing country
     *
     * @param  string $sBillingCountryCode
     * @return bool
     */
    public function stripeIsMethodAvailableForCountry($sBillingCountryCode)
    {
        $aCountryRestrictions = $this->getBillingCountryRestrictedCountries();
        return ($aCountryRestrictions === false || in_array($sBillingCountryCode, $aCountryRestrictions) === true);
    }

    /**
     * Checks if the payment method is available for the current currency
     *
     * @param  string $sCurrencyCode
     * @return bool
     */
    public function stripeIsMethodAvailableForCurrency($sCurrencyCode)
    {
        $aCurrencyRestrictions = $this->getCurrencyRestrictedCurrencies();
        return ($aCurrencyRestrictions === false || in_array($sCurrencyCode, $aCurrencyRestrictions) === true);
    }

    /**
     * Return PaymentIntent parameters specific to the given payment type, if existing
     *
     * @param Order $oOrder
     * @return array
     */
    public function getPaymentIntentSpecificParameters(Order $oOrder)
    {
        return [];
    }

    /**
     * Return PaymentIntent options specific to the given payment type, if existing
     *
     * @param Order $oOrder
     * @return array
     */
    public function getPaymentIntentSpecificOptions(Order $oOrder)
    {
        return [];
    }

    /**
     * Return PaymentMethod parameters specific to the given payment type, if existing
     *
     * @return array
     */
    public function getPaymentMethodSpecificParameters()
    {
        return [];
    }

    /**
     * Returns config value
     *
     * @param string $sParameterName
     * @return string
     */
    public function getConfigParam($sParameterName)
    {
        $aPaymentConfig = $this->getPaymentConfig();

        if (isset($aPaymentConfig[$sParameterName])) {
            return $aPaymentConfig[$sParameterName];
        }
        return false;
    }

    /**
     * Returns current Stripe profileId
     *
     * @return string
     * @throws \Exception
     */
    public function getPublishableKey()
    {
        $sMode = Payment::getInstance()->getStripeMode();
        return Payment::getInstance()->getPublishableKey($sMode);
    }

    /**
     * Return the transaction status handler
     *
     * @return \FC\stripe\Application\Model\TransactionHandler\Base
     */
    public function getTransactionHandler()
    {
        return new \FC\stripe\Application\Model\TransactionHandler\Payment();
    }

    /**
     * Return API Payment Method creation request
     *
     * @return PaymentMethod
     */
    public function getPaymentMethodRequest()
    {
        return new PaymentMethod();
    }

    /**
     * Return API PaymentIntent creation request
     *
     * @return PaymentIntent
     */
    public function getPaymentIntentRequest()
    {
        return new PaymentIntent();
    }

    /**
     * Return API Card creation request
     *
     * @return Card
     */
    public function getCardRequest()
    {
        return new Card();
    }
}
