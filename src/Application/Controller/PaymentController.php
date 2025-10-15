<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 *
 * Enhanced Payment Controller using generic PaymentComponent services
 *
 * Key improvements:
 * - Uses PaymentMethodRegistry for method management
 * - Uses PaymentMethodFilter for filtering logic
 * - Cleaner, more testable code
 * - Separation of concerns
 */

namespace OxidSolutionCatalysts\Stripe\Application\Controller;

use OxidSolutionCatalysts\Stripe\Service\OrderService;
use OxidSolutionCatalysts\Stripe\Service\PaymentService;
use OxidEsales\Eshop\Application\Model\Basket;
use OxidEsales\Eshop\Application\Model\Country;
use OxidEsales\Eshop\Core\Registry;
use OxidSolutionCatalysts\Stripe\Service\StripePaymentMethodService;

class PaymentController extends PaymentController_parent
{
    /**
     * Stripe payment method service
     *
     * @var StripePaymentMethodService|null
     */
    private ?StripePaymentMethodService $stripePaymentService = null;

    /**
     * Delete sess_challenge from session to trigger the creation of a new order when needed
     */
    public function init()
    {
        $sSessChallenge = Registry::getSession()->getVariable('sess_challenge');
        $blStripeIsRedirected = Registry::getSession()->getVariable('stripeIsRedirected');
        if (!empty($sSessChallenge) && $blStripeIsRedirected === true) {
            OrderService::getInstance()->cancelCurrentOrder();
        }
        Registry::getSession()->deleteVariable('stripeIsRedirected');
        parent::init();
    }

    /**
     * Get Stripe payment method service
     *
     * @return StripePaymentMethodService
     */
    protected function getStripePaymentService(): StripePaymentMethodService
    {
        if ($this->stripePaymentService === null) {
            $this->stripePaymentService = oxNew(StripePaymentMethodService::class);
        }
        return $this->stripePaymentService;
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
     * Removes Stripe payment methods which are not available for the current basket situation
     *
     * Uses the new generic PaymentMethodFilter service when available, with fallback to legacy filtering.
     *
     * Limiting factors:
     * 1. Config option "blStripeRemoveByBillingCountry" AND payment method not available for billing country
     * 2. Config option "blStripeRemoveByBasketCurrency" AND payment method not available for basket currency
     * 3. BasketSum outside min-/max-limits of payment method
     * 4. Payment method has B2B restriction and order not B2B
     *
     * @return void
     */
    protected function stripeRemoveUnavailablePaymentMethods()
    {
        $paymentList = parent::getPaymentList();

        // Try to use enhanced service if available
        try {
            $stripeService = $this->getStripePaymentService();

            // Check if Stripe is configured
            if (!$stripeService->isStripeConfigured()) {
                // Remove all Stripe payment methods if not configured
                foreach ($paymentList as $payment) {
                    if (method_exists($payment, 'isStripePaymentMethod') && $payment->isStripePaymentMethod() === true) {
                        unset($this->_oPaymentList[$payment->getId()]);
                    }
                }
                return;
            }

            // Get filter settings
            $removeByCountry = (bool) $this->paymentService->getShopConfVar('blStripeRemoveByBillingCountry');
            $removeByCurrency = (bool) PaymentService::getInstance()->getShopConfVar('blStripeRemoveByBasketCurrency');

            // Get basket
            $basket = Registry::getSession()->getBasket();

            // Get available payment methods using the new filter service
            $availableMethods = $stripeService->getAvailablePaymentMethods(
                $basket,
                $removeByCountry,
                $removeByCurrency
            );

            // Remove unavailable methods from payment list
            foreach ($paymentList as $payment) {
                if (method_exists($payment, 'isStripePaymentMethod') && $payment->isStripePaymentMethod() === true) {
                    $methodId = $payment->getId();

                    // If method is not in available list, remove it
                    if (!in_array($methodId, $availableMethods, true)) {
                        unset($this->_oPaymentList[$methodId]);

                        Registry::getLogger()->debug("Removed unavailable Stripe payment method", [
                            'methodId' => $methodId,
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            // Fallback to legacy filtering if enhanced service is not available
            Registry::getLogger()->debug("Using legacy payment filtering", [
                'reason' => $e->getMessage()
            ]);

            $this->stripeLegacyRemoveUnavailablePaymentMethods();
        }
    }

    /**
     * Legacy payment method filtering (fallback)
     *
     * @return void
     */
    protected function stripeLegacyRemoveUnavailablePaymentMethods()
    {
        $paymentList = parent::getPaymentList();
        $oPaymentHelper = PaymentService::getInstance();
        $sToken = $oPaymentHelper->getStripeToken($oPaymentHelper->getStripeMode());
        $blRemoveByBillingCountry = (bool)PaymentService::getInstance()->getShopConfVar('blStripeRemoveByBillingCountry');
        $blRemoveByBasketCurrency = (bool)PaymentService::getInstance()->getShopConfVar('blStripeRemoveByBasketCurrency');
        $oBasket = Registry::getSession()->getBasket();
        $sBillingCountryCode = $this->stripeGetBillingCountry($oBasket);
        $sCurrency = $oBasket->getBasketCurrency()->name;

        foreach ($paymentList as $payment) {
            if (method_exists($payment, 'isStripePaymentMethod') && $payment->isStripePaymentMethod() === true) {
                $oStripePayment = $payment->getStripePaymentModel();
                if (empty($sToken) ||
                    ($blRemoveByBillingCountry === true && $oStripePayment->stripeIsMethodAvailableForCountry($sBillingCountryCode) === false) ||
                    ($blRemoveByBasketCurrency === true && $oStripePayment->stripeIsMethodAvailableForCurrency($sCurrency) === false) ||
                    $oStripePayment->stripeIsBasketSumInLimits($oBasket->getPrice()->getBruttoPrice()) === false ||
                    ($oStripePayment->isOnlyB2BSupported() === true && $this->stripeIsB2BOrder($oBasket) === false)
                ) {
                    unset($this->_oPaymentList[$payment->getId()]);
                }
            }
        }
    }

    /**
     * Template variable getter. Returns payment list
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
     * Validate payment selection
     *
     * @return string
     */
    public function validatepayment()
    {
        $mRet = parent::validatepayment();

        $sPaymentId = Registry::getRequest()->getRequestParameter('paymentid');

        // Try to use enhanced service, fallback to legacy helper
        try {
            $stripeService = $this->getStripePaymentService();
            $isStripeMethod = $stripeService->isStripePaymentMethod($sPaymentId);
        } catch (\Exception $e) {
            $isStripeMethod = PaymentService::getInstance()->isStripePaymentMethod($sPaymentId);
        }

        if (!$isStripeMethod) {
            return $mRet;
        }

        try {
            $oBasket = Registry::getSession()->getBasket();

            // Try enhanced service first, fallback to legacy
            try {
                $stripeService = $this->getStripePaymentService();
                $oStripePaymentModel = $stripeService->getPaymentMethodModel($sPaymentId);
            } catch (\Exception $e) {
                $oStripePaymentModel = PaymentService::getInstance()->getStripePaymentModel($sPaymentId);
            }

            if ($sPaymentId == 'stripecreditcard') {
                $sStripeTokenId = $this->getDynValue()['stripe_token_id'];
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
     * Get Sofort supported countries
     *
     * @return string[]
     */
    public function stripeGetSofortCountries()
    {
        return ['AT', 'BE', 'DE', 'ES', 'IT', 'NL'];
    }

    /**
     * Get all registered Stripe payment methods for template
     *
     * @return array<string, string>
     */
    public function getStripePaymentMethods(): array
    {
        try {
            return $this->getStripePaymentService()->getAllPaymentMethods();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Stripe publishable key for frontend
     *
     * @return string
     */
    public function getStripePublishableKey(): string
    {
        try {
            return $this->getStripePaymentService()->getPublishableKey();
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Check if Stripe is in test mode
     *
     * @return bool
     */
    public function isStripeTestMode(): bool
    {
        try {
            return $this->getStripePaymentService()->getStripeMode() === 'test';
        } catch (\Exception $e) {
            return false;
        }
    }
}
