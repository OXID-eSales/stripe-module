<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidSolutionCatalysts\Stripe\extend\Application\Controller;

use OxidSolutionCatalysts\Stripe\Application\Helper\Order as OrderHelper;
use OxidSolutionCatalysts\Stripe\Application\Helper\Payment as PaymentHelper;
use OxidEsales\Eshop\Application\Model\Basket;
use OxidEsales\Eshop\Application\Model\Country;
use OxidEsales\Eshop\Core\Registry;

class PaymentController extends PaymentController_parent
{
    /**
     * Delete sess_challenge from session to trigger the creation of a new order when needed
     */
    public function init()
    {
        $sSessChallenge = Registry::getSession()->getVariable('sess_challenge');
        $blStripeIsRedirected = Registry::getSession()->getVariable('stripeIsRedirected');
        if (!empty($sSessChallenge) && $blStripeIsRedirected === true) {
            OrderHelper::getInstance()->cancelCurrentOrder();
        }
        Registry::getSession()->deleteVariable('stripeIsRedirected');
        parent::init();
    }

    /**
     * Returns billing country code of current basket
     *
     * @param  Basket $oBasket
     * @return string
     */
    protected function stripeGetBillingCountry($oBasket)
    {
        $oUser = $oBasket->getBasketUser();

        $oCountry = oxNew(Country::class);
        $oCountry->load($oUser->oxuser__oxcountryid->value);

        if (!$oCountry->oxcountry__oxisoalpha2) {
            return '';
        }

        return $oCountry->oxcountry__oxisoalpha2->value;
    }

    /**
     * Returns if current order is being considered as a B2B order
     *
     * @param  Basket $oBasket
     * @return bool
     */
    protected function stripeIsB2BOrder($oBasket)
    {
        $oUser = $oBasket->getBasketUser();
        if (!empty($oUser->oxuser__oxcompany->value)) {
            return true;
        }
        return false;
    }

    /**
     * Removes Stripe payment methods which are not available for the current basket situation. The limiting factors can be:
     * 1. Config option "blStripeRemoveByBillingCountry" activated AND payment method is not available for given billing country
     * 2. Config option "blStripeRemoveByBasketCurrency" activated AND payment method is not available for given basket currency
     * 3. BasketSum is outside the min-/max-limits of the payment method
     * 4. Payment method has a B2B restriction and order does not belong to this category
     *
     * @return void
     */
    protected function stripeRemoveUnavailablePaymentMethods()
    {
        $oPaymentHelper = PaymentHelper::getInstance();
        $sToken = $oPaymentHelper->getStripeToken($oPaymentHelper->getStripeMode());
        $blRemoveByBillingCountry = (bool)PaymentHelper::getInstance()->getShopConfVar('blStripeRemoveByBillingCountry');
        $blRemoveByBasketCurrency = (bool)PaymentHelper::getInstance()->getShopConfVar('blStripeRemoveByBasketCurrency');
        $oBasket = Registry::getSession()->getBasket();
        $sBillingCountryCode = $this->stripeGetBillingCountry($oBasket);
        $sCurrency = $oBasket->getBasketCurrency()->name;

        foreach ($this->_oPaymentList as $oPayment) {
            if (method_exists($oPayment, 'isStripePaymentMethod') && $oPayment->isStripePaymentMethod() === true) {
                $oStripePayment = $oPayment->getStripePaymentModel();
                if (empty($sToken) ||
                    ($blRemoveByBillingCountry === true && $oStripePayment->stripeIsMethodAvailableForCountry($sBillingCountryCode) === false) ||
                    ($blRemoveByBasketCurrency === true && $oStripePayment->stripeIsMethodAvailableForCurrency($sCurrency) === false) ||
                    $oStripePayment->stripeIsBasketSumInLimits($oBasket->getPrice()->getBruttoPrice()) === false ||
                    ($oStripePayment->isOnlyB2BSupported() === true && $this->stripeIsB2BOrder($oBasket) === false)
                ) {
                    unset($this->_oPaymentList[$oPayment->getId()]);
                }
            }
        }
    }

    /**
     * Template variable getter. Returns paymentlist
     *
     * @return object
     */
    public function getPaymentList()
    {
        parent::getPaymentList();
        $this->stripeRemoveUnavailablePaymentMethods();
        return $this->_oPaymentList;
    }

    /**
     * @return string
     */
    public function validatepayment()
    {
        $mRet = parent::validatepayment();

        $sPaymentId = Registry::getRequest()->getRequestParameter('paymentid');
        if (!PaymentHelper::getInstance()->isStripePaymentMethod($sPaymentId)) {
            return $mRet;
        }
        try {
            $oBasket = Registry::getSession()->getBasket();
            $oStripePaymentModel = PaymentHelper::getInstance()->getStripePaymentModel($sPaymentId);

            if ($sPaymentId == 'stripecreditcard') {
                $sStripeTokenId =  $this->getDynValue()['stripe_token_id'];
                $oStripeCardRequest = $oStripePaymentModel->getCardRequest();
                $oStripeCardRequest->addRequestParameters($sStripeTokenId, $oBasket->getUser());
                $oCard = $oStripeCardRequest->execute();
                if (!empty($oCard->id)) {
                    Registry::getSession()->setVariable('stripe_current_payment_method_id', $oCard->id);
                }
            } else {
                $oStripePaymentMethodRequest = $oStripePaymentModel->getPaymentMethodRequest();
                $oStripePaymentMethodRequest->addRequestParameters($oStripePaymentModel, $oBasket->getUser());
                $oPaymentMethod = $oStripePaymentMethodRequest->execute();

                if (!empty($oPaymentMethod->id)) {
                    Registry::getSession()->setVariable('stripe_current_payment_method_id', $oPaymentMethod->id);
                }
            }
        } catch (\Exception $oEx) {
            Registry::getLogger()->error($oEx->getTraceAsString());
            $mRet = 'payment';
        }

        return $mRet;
    }

    /**
     * @return string[]
     */
    public function stripeGetSofortCountries()
    {
        return ['AT','BE','DE','ES','IT','NL'];
    }
}
