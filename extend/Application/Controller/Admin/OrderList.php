<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidSolutionCatalysts\Stripe\extend\Application\Controller\Admin;

use OxidEsales\Eshop\Application\Model\Order;
use OxidSolutionCatalysts\Stripe\Application\Helper\Payment as PaymentHelper;
use OxidSolutionCatalysts\Stripe\Core\RefundMailService;

/**
 * OrderList class
 *
 * @mixin \OxidEsales\Eshop\Application\Controller\Admin\OrderList
 */
class OrderList extends OrderList_parent
{
    /**
     * Sends the cancellation confirmation mail after an order was cancelled in
     * the backend. A cancellation moves no money in this module - a payment that
     * has not been captured yet is merely cancelled at Stripe - so the mail
     * confirms the cancellation only; a refund is triggered separately in the
     * refund screen and confirmed separately.
     *
     * This override is deliberately the only place the cancellation mail is sent
     * from: the model's cancelOrder() is also reached by customer aborts in the
     * checkout and by Stripe's transaction handler, which must not mail anybody.
     *
     * Orders of other payment methods and orders that cannot be loaded are passed
     * straight through to the parent implementation.
     *
     * @return void
     */
    public function cancelOrder()
    {
        $sOxId = $this->getEditObjectId();
        if (empty($sOxId)) {
            parent::cancelOrder();

            return;
        }

        $oOrder = oxNew(Order::class);
        if (!$oOrder->load($sOxId) || !$this->stripeIsStripeOrder($oOrder)) {
            parent::cancelOrder();

            return;
        }

        parent::cancelOrder();

        $oMailService = oxNew(RefundMailService::class);
        $oMailService->sendCancelMail($oOrder, null, $this->stripeOrderFieldAsString($oOrder, 'oxcurrency'));
    }

    /**
     * Whether the order was paid with a stripe payment method, the same check the
     * module's order model uses - without relying on the model extension, so the
     * payment id is enough.
     *
     * @param Order $oOrder
     * @return bool
     */
    protected function stripeIsStripeOrder(Order $oOrder)
    {
        return PaymentHelper::getInstance()->isStripePaymentMethod(
            $this->stripeOrderFieldAsString($oOrder, 'oxpaymenttype')
        );
    }

    /**
     * getFieldData() is untyped, so anything that is not a plain value yields an
     * empty string instead of being cast.
     *
     * @param Order $oOrder
     * @param string $sField
     * @return string
     */
    protected function stripeOrderFieldAsString(Order $oOrder, $sField)
    {
        $mValue = $oOrder->getFieldData($sField);

        return is_scalar($mValue) ? (string)$mValue : '';
    }
}
