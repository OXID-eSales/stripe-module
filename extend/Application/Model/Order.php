<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidSolutionCatalysts\Stripe\extend\Application\Model;

use OxidSolutionCatalysts\Stripe\Application\Helper\Order as OrderHelper;
use OxidSolutionCatalysts\Stripe\Application\Helper\Payment as PaymentHelper;
use OxidSolutionCatalysts\Stripe\Application\Model\Payment\Base;
use OxidSolutionCatalysts\Stripe\Application\Model\RequestLog;
use OxidEsales\Eshop\Application\Model\Basket;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Core\Registry;
use Stripe\PaymentIntent;

class Order extends Order_parent
{
    /**
     * Toggles certain behaviours in finalizeOrder for when the customer returns after the payment
     *
     * @var bool
     */
    protected $blStripeFinalizeReturnMode = false;

    /**
     * Toggles certain behaviours in finalizeOrder for when order is being finished automatically
     * because customer did not come back to shop
     *
     * @var bool
     */
    protected $blStripeFinishOrderReturnMode = false;

    /**
     * Toggles certain behaviours in finalizeOrder for when the the payment is being reinitialized at a later point in time
     *
     * @var bool
     */
    protected $blStripeReinitializePaymentMode = false;

    /**
     * Temporary field for saving the order nr
     *
     * @var int|null
     */
    protected $stripeTmpOrderNr = null;

    /**
     * State is saved to prevent order being set to transstatus OK during recalculation
     *
     * @var bool|null
     */
    protected $stripeRecalculateOrder = null;

    /**
     * Used to trigger the _setNumber() method before the payment-process during finalizeOrder to have the order-number there already
     *
     * @return void
     */
    public function stripeSetOrderNumber()
    {
        if (!$this->oxorder__oxordernr->value) {
            $this->_setNumber();
        }
    }

    /**
     * Generate Stripe payment model from paymentId
     *
     * @return Base
     */
    public function stripeGetPaymentModel()
    {
        return PaymentHelper::getInstance()->getStripePaymentModel($this->oxorder__oxpaymenttype->value);
    }

    /**
     * Returns if order was payed with a Stripe payment type
     *
     * @return bool
     */
    public function stripeIsStripePaymentUsed()
    {
        if(PaymentHelper::getInstance()->isStripePaymentMethod($this->oxorder__oxpaymenttype->value)) {
            return true;
        }
        return false;
    }

    /**
     * Marks order as shipped in Stripe API
     *
     * @return void
     */
    public function stripeMarkOrderAsShipped()
    {
        if ($this->stripeIsStripePaymentUsed() === false) {
            return;
        }

        $oRequestLog = oxNew(RequestLog::class);

        try {
            $oApiEndpoint = PaymentHelper::getInstance()->getApiClientByOrder($this);
            $oStripeApiOrder = $oApiEndpoint->paymentIntents->retrieve($this->oxorder__oxtransid->value);
            if ($oStripeApiOrder instanceof PaymentIntent) {
                if ($this->oxorder__oxtrackcode->value != '') {
                    $aParams = [];


                    if (empty($oStripeApiOrder->shipping)) {
                        $aShipping = OrderHelper::getInstance()->getShippingAddressParameters($this);
                        if (empty($aAddress['city'])) {
                            $aShipping = OrderHelper::getInstance()->getBillingAddressParametersFromOrder($this);
                            unset($aShipping['email']);
                        }
                    } else {
                        $aShipping = $oStripeApiOrder->shipping->toArray();
                    }
                    $aShipping['carrier'] = 'N/A';
                    $aShipping['tracking_number'] = $this->oxorder__oxtrackcode->value;

                    $aParams['shipping'] = $aShipping;

                    $oResponse = $oApiEndpoint->paymentIntents->update($oStripeApiOrder->id, $aParams);
                    $oRequestLog->logRequest($aParams, $oResponse, $this->getId(), $this->getConfig()->getShopId());
                    DatabaseProvider::getDb()->Execute("UPDATE oxorder SET stripeshipmenthasbeenmarked = 1 WHERE oxid = ?", array($this->getId()));
                }
            }
        } catch (\Exception $oEx) {
            $oRequestLog->logExceptionResponse([], $oEx->getCode(), $oEx->getMessage(), $this->getId(), $this->getConfig()->getShopId());
        }
    }

    /**
     * Update tracking code of shipping entity
     *
     * @param  string $sTrackingCode
     * @return void
     */
    public function stripeUpdateShippingTrackingCode($sTrackingCode)
    {
        try {
            $oApiEndpoint = PaymentHelper::getInstance()->getApiClientByOrder($this);
            $oStripeApiOrder = $oApiEndpoint->paymentIntents->retrieve($this->oxorder__oxtransid->value);
            if ($oStripeApiOrder instanceof PaymentIntent) {
                if ($oStripeApiOrder->shipping) {
                    $oApiEndpoint->paymentIntents->update($oStripeApiOrder->id, [
                        'shipping' => [
                            'tracking_number' => $sTrackingCode
                        ]
                    ]);
                }
            }
        } catch (\Exception $exc) {
            $oRequestLog = oxNew(RequestLog::class);
            $oRequestLog->logExceptionResponse([], $exc->getCode(), $exc->getMessage(), 'updateTracking', $this->getId(), $this->getConfig()->getShopId());
        }
    }

    /**
     * Remove cancellation of the order
     *
     * @return void
     */
    public function stripeUncancelOrder()
    {
        if ($this->oxorder__oxstorno->value == 1) {
            $this->oxorder__oxstorno = new \OxidEsales\Eshop\Core\Field(0);
            if ($this->save()) {
                // canceling ordered products
                foreach ($this->getOrderArticles() as $oOrderArticle) {
                    $oOrderArticle->stripeUncancelOrderArticle();
                }
            }
        }
    }

    /**
     * Returns if the order is marked as paid, since OXID doesnt have a proper flag
     *
     * @return bool
     */
    public function stripeIsPaid()
    {
        if (!empty($this->oxorder__oxpaid->value) && $this->oxorder__oxpaid->value != "0000-00-00 00:00:00") {
            return true;
        }
        return false;
    }

    /**
     * Mark order as paid
     *
     * @return void
     */
    public function stripeMarkAsPaid()
    {
        $sDate = date('Y-m-d H:i:s');

        $sQuery = "UPDATE oxorder SET oxpaid = ? WHERE oxid = ?";
        DatabaseProvider::getDb()->Execute($sQuery, array($sDate, $this->getId()));

        $this->oxorder__oxpaid = new Field($sDate);
    }

    /**
     * Mark order as paid
     *
     * @return void
     */
    public function stripeMarkAsSecondChanceMailSent()
    {
        $sDate = date('Y-m-d H:i:s');

        $sQuery = "UPDATE oxorder SET stripesecondchancemailsent = ? WHERE oxid = ?";
        DatabaseProvider::getDb()->Execute($sQuery, array($sDate, $this->getId()));

        $this->oxorder__stripesecondchancemailsent = new Field($sDate);
    }

    /**
     * Set order folder
     *
     * @param string $sFolder
     * @return void
     */
    public function stripeSetFolder($sFolder)
    {
        $sQuery = "UPDATE oxorder SET oxfolder = ? WHERE oxid = ?";
        DatabaseProvider::getDb()->Execute($sQuery, array($sFolder, $this->getId()));

        $this->oxorder__oxfolder = new Field($sFolder);
    }

    /**
     * Save transaction id in order object
     *
     * @param  string $sTransactionId
     * @return void
     */
    public function stripeSetTransactionId($sTransactionId)
    {
        DatabaseProvider::getDb()->execute('UPDATE oxorder SET oxtransid = ? WHERE oxid = ?', array($sTransactionId, $this->getId()));

        $this->oxorder__oxtransid = new Field($sTransactionId);
    }

    /**
     * Save external transaction id in order object
     *
     * @param  string $sTransactionId
     * @return void
     */
    public function stripeSetExternalTransactionId($sTransactionId)
    {
        DatabaseProvider::getDb()->execute('UPDATE oxorder SET stripeexternaltransid = ? WHERE oxid = ?', array($sTransactionId, $this->getId()));

        $this->oxorder__stripeexternaltransid = new Field($sTransactionId);
    }

    /**
     * Determines if the current call is a return from a redirect payment
     *
     * @return bool
     */
    protected function stripeIsReturnAfterPayment()
    {
        if (Registry::getRequest()->getRequestEscapedParameter('fnc') == 'handleStripeReturn') {
            return true;
        }
        return false;
    }

    /**
     * Extension: Return false in return mode
     *
     * @param string $sOxId order ID
     * @return bool
     */
    protected function _checkOrderExist($sOxId = null)
    {
        if ($this->blStripeFinalizeReturnMode === false && $this->blStripeReinitializePaymentMode === false) {
            return parent::_checkOrderExist($sOxId);
        }
        return false; // In finalize return situation the order will already exist, but thats ok
    }

    /**
     * Extension: In return mode load order from DB instead of generation from basket because it already exists
     *
     * @param \OxidEsales\EshopCommunity\Application\Model\Basket $oBasket Shopping basket object
     */
    protected function _loadFromBasket(\OxidEsales\Eshop\Application\Model\Basket $oBasket)
    {
        if ($this->blStripeFinalizeReturnMode === false) {
            return parent::_loadFromBasket($oBasket);
        }
        $this->load(Registry::getSession()->getVariable('sess_challenge'));
    }

    /**
     * Extension: In return mode load existing userpayment instead of creating a new one
     *
     * @param string $sPaymentid used payment id
     * @return \OxidEsales\Eshop\Application\Model\UserPayment
     */
    protected function _setPayment($sPaymentid)
    {
        if ($this->blStripeFinalizeReturnMode === false) {
            $mParentReturn = parent::_setPayment($sPaymentid);

            if ($this->stripeIsStripePaymentUsed()) {
                $this->oxorder__stripemode = new Field(PaymentHelper::getInstance()->getStripeMode());
            }
            return $mParentReturn;
        }
        $oUserpayment = oxNew(\OxidEsales\Eshop\Application\Model\UserPayment::class);
        $oUserpayment->load($this->oxorder__oxpaymentid->value);
        return $oUserpayment;
    }

    /**
     * Extension: Return true in return mode since this was done in the first step
     *
     * @param \OxidEsales\EshopCommunity\Application\Model\Basket $oBasket      basket object
     * @param object                                              $oUserpayment user payment object
     * @return  integer 2 or an error code
     */
    protected function _executePayment(\OxidEsales\Eshop\Application\Model\Basket $oBasket, $oUserpayment)
    {
        if ($this->blStripeFinalizeReturnMode === false) {
            return parent::_executePayment($oBasket, $oUserpayment);
        }

        if ($this->blStripeReinitializePaymentMode === true) {
            // Finalize order would set a new incremented order-nr if already filled
            // Doing this to prevent this, oxordernr will be filled again in _setNumber
            $this->stripeTmpOrderNr = $this->oxorder__oxordernr->value;
            $this->oxorder__oxordernr->value = "";
        }
        return true;
    }

    /**
     * Tries to fetch and set next record number in DB. Returns true on success
     *
     * @return bool
     */
    protected function _setNumber()
    {
        if ($this->blStripeFinalizeReturnMode === false && $this->blStripeReinitializePaymentMode === false && $this->stripeTmpOrderNr === null) {
            return parent::_setNumber();
        }

        if (!$this->oxorder__oxordernr instanceof Field) {
            $this->oxorder__oxordernr = new Field($this->stripeTmpOrderNr);
        } else {
            $this->oxorder__oxordernr->value = $this->stripeTmpOrderNr;
        }

        return true;
    }

    /**
     * Extension: Set pending folder for Stripe orders
     *
     * @return void
     */
    protected function _setFolder()
    {
        if (PaymentHelper::getInstance()->isStripePaymentMethod(Registry::getSession()->getBasket()->getPaymentId()) === false) {
            return parent::_setFolder();
        }

        if ($this->blStripeFinalizeReturnMode === false && $this->blStripeFinishOrderReturnMode === false) { // Stripe module has it's own folder management, so order should not be set to status NEW by oxid core
            $this->oxorder__oxfolder = new Field(Registry::getConfig()->getShopConfVar('sStripeStatusPending'), Field::T_RAW);
        }
    }

    /**
     * Extension: Changing the order in the backend results in da finalizeOrder call with recaltulateOrder = true
     * This sets oxtransstatus to OK, which should not happen for Stripe orders when they were not finished
     * This prevents this behaviour
     *
     * @param string $sStatus order transaction status
     */
    protected function _setOrderStatus($sStatus)
    {
        if ($this->stripeRecalculateOrder === true && $this->oxorder__oxtransstatus->value == "NOT_FINISHED" && $this->stripeIsStripePaymentUsed()) {
            return;
        }
        parent::_setOrderStatus($sStatus);
    }

    /**
     * Extension: Order already existing because order was created before the user was redirected to Stripe,
     * therefore no stock validation needed. Otherwise an exception would be thrown on return when last product in stock was bought
     *
     * @param object $oBasket basket object
     */
    public function validateStock($oBasket)
    {
        if ($this->blStripeFinalizeReturnMode === false) {
            return parent::validateStock($oBasket);
        }
    }

    /**
     * Validates order parameters like stock, delivery and payment
     * parameters
     *
     * @param \OxidEsales\Eshop\Application\Model\Basket $oBasket basket object
     * @param \OxidEsales\Eshop\Application\Model\User   $oUser   order user
     *
     * @return null
     */
    public function validateOrder($oBasket, $oUser)
    {
        if ($this->blStripeFinishOrderReturnMode === false) {
            return parent::validateOrder($oBasket, $oUser);
        }
    }

    /**
     * Checks if payment used for current order is available and active.
     * Throws exception if not available
     *
     * @param \OxidEsales\Eshop\Application\Model\Basket    $oBasket basket object
     * @param \OxidEsales\Eshop\Application\Model\User|null $oUser   user object
     *
     * @return null
     */
    public function validatePayment($oBasket, $oUser = null)
    {
        if ($this->blStripeReinitializePaymentMode === false) {
            $oReflection = new \ReflectionMethod(\OxidEsales\Eshop\Application\Model\Order::class, 'validatePayment');
            $aParams = $oReflection->getParameters();
            if (count($aParams) == 1) {
                return parent::validatePayment($oBasket); // Oxid 6.1 didnt have the $oUser parameter yet
            }

            return parent::validatePayment($oBasket, $oUser);
        }
    }

    /**
     * This overloaded method sets the return mode flag so that the behaviour of some methods is changed when the customer
     * returns after successful payment from Stripe
     *
     * @param \OxidEsales\Eshop\Application\Model\Basket $oBasket              Basket object
     * @param object                                     $oUser                Current User object
     * @param bool                                       $blRecalculatingOrder Order recalculation
     * @return integer
     */
    public function finalizeOrder(\OxidEsales\Eshop\Application\Model\Basket $oBasket, $oUser, $blRecalculatingOrder = false)
    {
        $this->stripeRecalculateOrder = $blRecalculatingOrder;
        if (PaymentHelper::getInstance()->isStripePaymentMethod($oBasket->getPaymentId()) === true && $this->stripeIsReturnAfterPayment() === true) {
            $this->blStripeFinalizeReturnMode = true;
        }
        if (Registry::getSession()->getVariable('stripeReinitializePaymentMode')) {
            $this->blStripeReinitializePaymentMode = true;
        }
        return parent::finalizeOrder($oBasket, $oUser, $blRecalculatingOrder);
    }

    /**
     * Assigns to new oxorder object customer delivery and shipping info
     *
     * @param object $oUser user object
     */
    protected function _setUser($oUser)
    {
        parent::_setUser($oUser);
    }

    /**
     * Checks if delivery address (billing or shipping) was not changed during checkout
     * Throws exception if not available
     *
     * @param \OxidEsales\Eshop\Application\Model\User $oUser user object
     *
     * @return int
     */
    public function validateDeliveryAddress($oUser)
    {
        if ($this->blStripeReinitializePaymentMode === false) {
            return parent::validateDeliveryAddress($oUser);
        }
        return 0;
    }

    /**
     * Performs order cancel process
     */
    public function cancelOrder()
    {
        parent::cancelOrder();
        if ($this->stripeIsStripePaymentUsed() === true) {
            $sCancelledFolder = Registry::getConfig()->getShopConfVar('sStripeStatusCancelled');
            if (!empty($sCancelledFolder)) {
                $this->stripeSetFolder($sCancelledFolder);
            }

            if (!empty($this->oxorder__oxtransid->value)) {
                $oApiEndpoint = PaymentHelper::getInstance()->loadStripeApi()->paymentIntents;
                $oStripePaymentIntent = $oApiEndpoint->retrieve($this->oxorder__oxtransid->value);
                if (OrderHelper::getInstance()->stripeIsCancelablePaymentIntent($oStripePaymentIntent)) {
                    $oApiEndpoint->cancel($this->oxorder__oxtransid->value);
                }
            }
        }
    }

    /**
     * Returns finish payment url
     *
     * @return string|bool
     */
    public function stripeGetPaymentFinishUrl()
    {
        return Registry::getConfig()->getSslShopUrl()."?cl=stripeFinishPayment&id=".$this->getId();
    }

    /**
     * Checks if Stripe order was not finished correctly
     *
     * @return bool
     */
    public function stripeIsOrderInUnfinishedState()
    {
        if ($this->oxorder__oxtransstatus->value == "NOT_FINISHED" && $this->oxorder__oxfolder->value == Registry::getConfig()->getShopConfVar('sStripeStatusProcessing')) {
            return true;
        }
        return false;
    }

    /**
     * Recreates basket from order information
     *
     * @return Basket
     */
    public function stripeRecreateBasket()
    {
        $oBasket = $this->_getOrderBasket();

        // add this order articles to virtual basket and recalculates basket
        $this->_addOrderArticlesToBasket($oBasket, $this->getOrderArticles(true));

        // recalculating basket
        $oBasket->calculateBasket(true);

        Registry::getSession()->setVariable('sess_challenge', $this->getId());
        Registry::getSession()->setVariable('paymentid', $this->oxorder__oxpaymenttype->value);
        Registry::getSession()->setBasket($oBasket);

        return $oBasket;
    }

    /**
     * Checks if order is elibible for finishing the payment
     *
     * @param bool $blSecondChanceEmail
     * @return bool
     */
    public function stripeIsEligibleForPaymentFinish($blSecondChanceEmail = false)
    {
        if (!$this->stripeIsStripePaymentUsed() || $this->oxorder__oxpaid->value != '0000-00-00 00:00:00' || $this->oxorder__oxtransstatus->value != 'NOT_FINISHED') {
            return false;
        }

        $aStatus = $this->stripeGetPaymentModel()->getTransactionHandler()->processTransaction($this, 'succeeded');

        $aStatusBlacklist = ['paid'];
        if ($blSecondChanceEmail === true) {
            $aStatusBlacklist[] = 'canceled';
        }
        if (in_array($aStatus['status'], $aStatusBlacklist)) {
            return false;
        }
        return true;
    }

    /**
     * Triggers sending Stripe second chance email
     *
     * @return void
     */
    public function stripeSendSecondChanceEmail()
    {
        $oEmail = oxNew(\OxidEsales\Eshop\Core\Email::class);
        $oEmail->stripeSendSecondChanceEmail($this, $this->stripeGetPaymentFinishUrl());

        $this->stripeMarkAsSecondChanceMailSent();
    }

    /**
     * Tries to finish an order which was paid but where the customer seemingly didn't return to the shop after payment to finish the order process
     *
     * @return integer
     */
    public function stripeFinishOrder()
    {
        $oBasket = $this->stripeRecreateBasket();

        $this->blStripeFinalizeReturnMode = true;
        $this->blStripeFinishOrderReturnMode = true;

        //finalizing order (skipping payment execution, vouchers marking and mail sending)
        return $this->finalizeOrder($oBasket, $this->getOrderUser());
    }

    /**
     * Starts a new payment with Stripe
     *
     * @return integer
     */
    public function stripeReinitializePayment()
    {
        if ($this->oxorder__oxstorno->value == 1) {
            $this->stripeUncancelOrder();
        }

        $oUser = $this->getUser();
        if (!$oUser) {
            $oUser = oxNew(\OxidEsales\Eshop\Application\Model\User::class);
            $oUser->load($this->oxorder__oxuserid->value);
            $this->setUser($oUser);
            Registry::getSession()->setVariable('usr', $this->oxorder__oxuserid->value);
        }

        $this->blStripeReinitializePaymentMode = true;

        Registry::getSession()->setVariable('stripeReinitializePaymentMode', true);

        $oBasket = Registry::getSession()->getBasket();
        $oStripePaymentModel = PaymentHelper::getInstance()->getStripePaymentModel(Registry::getSession()->getVariable('paymentid'));
        $oStripePaymentMethodRequest = $oStripePaymentModel->getPaymentMethodRequest();
        $oStripePaymentMethodRequest->addRequestParameters($oStripePaymentModel, $oBasket->getUser());
        $oPaymentMethod = $oStripePaymentMethodRequest->execute();

        if (!empty($oPaymentMethod->id)) {
            Registry::getSession()->setVariable('stripe_current_payment_method_id', $oPaymentMethod->id);
        }

        return $this->finalizeOrder($oBasket, $oUser);
    }

    /**
     * Retrieves order id connected to given transaction id and trys to load it
     * Returns if order was found and loading was a success
     *
     * @param string $sTransactionId
     * @return bool
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
     */
    public function stripeLoadOrderByTransactionId($sTransactionId)
    {
        $sQuery = "SELECT oxid FROM oxorder WHERE oxtransid = ?";

        $sOrderId = DatabaseProvider::getDb()->getOne($sQuery, array($sTransactionId));
        if (!empty($sOrderId)) {
            return $this->load($sOrderId);
        }
        return false;
    }

    public function stripeGetExtraInfo()
    {
        $sStripePaymentId = $this->stripeGetPaymentModel()->getOxidPaymentId();
        $sTransactionId = $this->oxorder__oxtransid->value;

        if (empty($sTransactionId)) {
            return '';
        }

        try {
            $oApi = PaymentHelper::getInstance()->getApiClientByOrder($this);
            $oTransaction = $oApi->paymentIntents->retrieve($sTransactionId, ['expand' => ['payment_method','latest_charge']]);

            switch ($sStripePaymentId) {
                case 'stripeideal':
                    $oInfo = $oTransaction->payment_method->ideal;
                    if (empty($oInfo->bank)) {
                        return '';
                    }
                    return 'Bank: ' . $oInfo->bank . (!empty($oInfo->bic) ? ' (BIC: ' . $oInfo->bic . ')' : '');
                case 'stripeeps':
                    $oInfo = $oTransaction->payment_method->eps;
                    if (empty($oInfo->bank)) {
                        return '';
                    }
                    return 'Bank: ' . $oInfo->bank;
                case 'stripep24':
                    $oInfo = $oTransaction->payment_method->p24;
                    if (empty($oInfo->bank)) {
                        return '';
                    }
                    return 'Bank: ' . $oInfo->bank;
                case 'stripegiropay':
                    $oInfo = $oTransaction->latest_charge->payment_method_details->giropay;
                    if (empty($oInfo->bank_name)) {
                        return '';
                    }
                    return 'Bank: ' . $oInfo->bank_name . (!empty($oInfo->bic) ? ' (BIC: ' . $oInfo->bic . ')' : '');
            }
        } catch (\Exception $oEx) {
            return '';
        }

        return '';
    }
}
