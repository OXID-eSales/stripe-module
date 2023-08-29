<?php
/**
 * Copyright © FATCHIP GmbH. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\stripe\Application\Controller\Admin;

use FC\stripe\Application\Helper\Payment as PaymentHelper;
use FC\stripe\Application\Model\RequestLog;
use OxidEsales\Eshop\Application\Controller\Admin\AdminDetailsController;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\Field;
use Stripe\Charge;
use Stripe\PaymentIntent;

class OrderRefund extends AdminDetailsController
{
    /**
     * Template to be used
     *
     * @var string
     */
    protected $_sTemplate = "stripe_order_refund.tpl";

    /**
     * Order object
     *
     * @var Order|null
     */
    protected $_oOrder = null;

    /**
     * Error message property
     *
     * @var string|bool
     */
    protected $_sErrorMessage = false;

    /**
     * Stripe ApiOrder
     *
     * @var PaymentIntent
     */
    protected $_oStripeApiOrder = null;

    /**
     * Stripe ApiCharge
     *
     * @var Charge
     */
    protected $_oStripeApiCharge = null;

    /**
     * Flag if a successful refund was executed
     *
     * @var bool|null
     */
    protected $_blSuccessfulRefund = null;

    /**
     * Array of refund items
     *
     * @var array|null
     */
    protected $_aRefundItems = null;

    /**
     * Main render method
     *
     * @return string
     */
    public function render()
    {
        parent::render();

        $oOrder = $this->getOrder();
        if ($oOrder) {
            $this->_aViewData["edit"] = $oOrder;
        }

        return $this->_sTemplate;
    }

    /**
     * Execute full refund action
     *
     * @return void
     */
    public function fullRefund()
    {
        $oRequestLog = oxNew(RequestLog::class);
        try {
            $oStripeApiCharge = $this->getStripeApiOrderLastCharge(true);
            if ($oStripeApiCharge instanceof Charge) {
                $aParams = $this->getRefundParameters();
                $aParams['charge'] = $oStripeApiCharge->id;
                $oResponse = $this->getStripeApiRequestModel()->refunds->create($aParams);

                $oRequestLog->logRequest($aParams, $oResponse, $this->getOrder()->getId(), $this->getConfig()->getShopId());
                $this->markOrderAsFullyRefunded();
                $this->_blSuccessfulRefund = true;
            } else {
                $this->setErrorMessage(Registry::getLang()->translateString('STRIPE_REFUND_FAILED'));
                $this->_blSuccessfulRefund = false;
            }
        } catch (\Exception $oEx) {
            $this->setErrorMessage($oEx->getMessage());
            $oRequestLog->logExceptionResponse($aParams, $oEx->getCode(), $oEx->getMessage(), 'refund', $this->getOrder()->getId());
            $this->_blSuccessfulRefund = false;
        }
    }

    /**
     * Returns remaining refundable amount from Stripe Api
     *
     * @return double
     */
    public function getRemainingRefundableAmount()
    {
        $oApiCharge = $this->getStripeApiOrderLastCharge(true);

        $dPrice = 0;
        if ($oApiCharge && !empty($oApiCharge->amount_refunded)) {
            $dPrice = ($oApiCharge->amount_captured - $oApiCharge->amount_refunded) / 100;
        }
        return $this->getFormatedPrice($dPrice);
    }

    /**
     * Get refunded amount formatted
     *
     * @return string
     */
    public function getFormatedPrice($dPrice)
    {
        $oCurrency = $this->getConfig()->getCurrencyObject($this->getOrder()->oxorder__oxcurrency->value);

        return Registry::getLang()->formatCurrency($dPrice, $oCurrency);
    }

    /**
     * Loads current order
     *
     * @return null|object|Order
     */
    public function getOrder()
    {
        if ($this->_oOrder === null) {
            $oOrder = oxNew(Order::class);

            $soxId = $this->getEditObjectId();
            if (isset($soxId) && $soxId != "-1") {
                $oOrder->load($soxId);

                $this->_oOrder = $oOrder;
            }
        }
        return $this->_oOrder;
    }

    /**
     * Checks if there were previous partial refunds and therefore full refund is not available anymore
     *
     * @return bool
     */
    public function isFullRefundAvailable()
    {
        $oOrder = $this->getOrder();
        foreach ($oOrder->getOrderArticles() as $orderArticle) {
            if ((double)$orderArticle->oxorderarticles__stripeamountrefunded->value > 0 || $orderArticle->oxorderarticles__stripequantityrefunded->value > 0) {
                return false;
            }
        }

        if ($oOrder->oxorder__stripedelcostrefunded->value > 0
            || $oOrder->oxorder__stripepaycostrefunded->value > 0
            || $oOrder->oxorder__stripewrapcostrefunded->value > 0
            || $oOrder->oxorder__stripegiftcardrefunded->value > 0
            || $oOrder->oxorder__stripevoucherdiscountrefunded->value > 0
            || $oOrder->oxorder__stripediscountrefunded->value > 0) {
            return false;
        }
        return true;
    }

    /**
     * Check Stripe API if order is refundable
     *
     * @return bool
     */
    public function isOrderRefundable()
    {
        if ($this->wasRefundSuccessful() === true && Registry::getRequest()->getRequestEscapedParameter('fnc') == 'fullRefund') {
            // In case the stripe order is not updated instantly, this is used to show that the order was fully refunded already
            return false;
        }

        $oApiOrderCharge = $this->getStripeApiOrderLastCharge(true);

        if (empty($oApiOrderCharge->amount_refunded) || $oApiOrderCharge->amount_refunded != $oApiOrderCharge->amount) {
            return true;
        }
        return false;
    }

    /**
     * Checks if order was payed with Stripe
     *
     * @return bool
     */
    public function isStripeOrder()
    {
        return PaymentHelper::getInstance()->isStripePaymentMethod($this->getOrder()->oxorder__oxpaymenttype->value);
    }

    /**
     * Triggers sending Stripe second chance email
     *
     * @return void
     */
    public function sendSecondChanceEmail()
    {
        $oOrder = $this->getOrder();
        if ($oOrder && $oOrder->stripeIsStripePaymentUsed()) {
            $oOrder->stripeSendSecondChanceEmail();
        }
    }

    /**
     * Returns errormessage
     *
     * @return bool|string
     */
    public function getErrorMessage()
    {
        return $this->_sErrorMessage;
    }

    /**
     * Sets error message
     *
     * @param string $sError
     */
    public function setErrorMessage($sError)
    {
        $this->_sErrorMessage = $sError;
    }

    /**
     * Returns if refund was successful
     *
     * @return bool
     */
    public function wasRefundSuccessful()
    {
        return $this->_blSuccessfulRefund;
    }

    /**
     * Format prices to always have 2 decimal places
     *
     * @param double $dPrice
     * @return string
     */
    protected function formatPrice($dPrice)
    {
        return number_format($dPrice, 2, '.', '');
    }

    /**
     * Generate request parameter array
     *
     * @return array
     */
    protected function getRefundParameters()
    {
        $dAmount = $this->getOrder()->oxorder__oxtotalordersum->value;
        if (!empty(Registry::getRequest()->getRequestEscapedParameter('refundRemaining'))) {
            $dAmount = $this->getRemainingRefundableAmount();
        }
        $aParams = ["amount" => PaymentHelper::getInstance()->priceInCent($dAmount)];

        $sReason = Registry::getRequest()->getRequestEscapedParameter('refund_reason');
        if (!empty($sReason)) {
            $aParams['reason'] = $sReason;
        }

        $sDescription = Registry::getRequest()->getRequestEscapedParameter('refund_description');
        if (!empty($sDescription)) {
            $aParams['metadata'] = ['description' => $sDescription];
        }
        return $aParams;
    }

    /**
     * Return Stripe api order
     *
     * @param bool $blRefresh
     * @return PaymentIntent
     */
    protected function getStripeApiOrder($blRefresh = false)
    {
        try{
            if ($this->_oStripeApiOrder === null || $blRefresh === true) {
                $this->_oStripeApiOrder = $this->getStripeApiRequestModel()->paymentIntents->retrieve($this->getOrder()->oxorder__oxtransid->value);
            }
            return $this->_oStripeApiOrder;
        } catch (\Exception $oEx) {
            return null;
        }
    }

    /**
     * @param boolean $blRefresh
     * @return \Stripe\Charge|null
     */
    protected function getStripeApiOrderLastCharge($blRefresh = false)
    {
        try{
            if ($this->_oStripeApiCharge === null || $blRefresh === true) {

                $oApiOrder = $this->getStripeApiOrder($blRefresh);
                $sLastChargeId = $oApiOrder->latest_charge;

                if (! $sLastChargeId) {
                    return null;
                }

                $this->_oStripeApiCharge = $this->getStripeApiRequestModel()->charges->retrieve($sLastChargeId);
            }
            return $this->_oStripeApiCharge;
        } catch (\Exception $oEx) {
            return null;
        }
    }

    /**
     * Returns Stripe payment or order Api
     *
     * @return \Stripe\StripeClient
     */
    protected function getStripeApiRequestModel()
    {
        $oOrder = $this->getOrder();
        return PaymentHelper::getInstance()->getApiClientByOrder($oOrder);
    }

    /**
     * Fills refunded db-fields with full costs
     *
     * @return void
     */
    protected function markOrderAsFullyRefunded()
    {
        $oOrder = $this->getOrder();
        $oOrder->oxorder__stripedelcostrefunded = new Field($oOrder->oxorder__oxdelcost->value);
        $oOrder->oxorder__stripepaycostrefunded = new Field($oOrder->oxorder__oxpaycost->value);
        $oOrder->oxorder__stripewrapcostrefunded = new Field($oOrder->oxorder__oxwrapcost->value);
        $oOrder->oxorder__stripegiftcardrefunded = new Field($oOrder->oxorder__oxgiftcardcost->value);
        $oOrder->oxorder__stripevoucherdiscountrefunded = new Field($oOrder->oxorder__oxvoucherdiscount->value);
        $oOrder->oxorder__stripediscountrefunded = new Field($oOrder->oxorder__oxdiscount->value);
        $oOrder->save();

        foreach ($this->getOrder()->getOrderArticles() as $oOrderArticle) {
            $oOrderArticle->oxorderarticles__stripeamountrefunded = new Field($oOrderArticle->oxorderarticles__oxbrutprice->value);
            $oOrderArticle->save();
        }

        $this->_oOrder = $oOrder; // update order for renderering the page
        $this->_aRefundItems = null;
    }
}
