<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidSolutionCatalysts\Stripe\Application\Model\TransactionHandler;

use OxidSolutionCatalysts\Stripe\Application\Helper\Payment as PaymentHelper;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Core\Registry;
use Stripe\PaymentIntent;

class Payment extends Base
{
    /**
     * Handle order according to the given transaction status
     *
     * @param PaymentIntent $oTransaction
     * @param Order $oOrder
     * @param string $sType
     * @return array
     */
    protected function handleTransactionStatus(PaymentIntent $oTransaction, Order $oOrder, $sType)
    {
        $blSuccess = false;
        $sStatus = $oTransaction->status;

        if ($sStatus == 'succeeded') {
            if (strtolower($oTransaction->currency) != strtolower($oOrder->oxorder__oxcurrency->value)) {
                return ['success' => false, 'status' => 'paid', 'error' => 'Currency does not match.'];
            }

            if ($oOrder->stripeIsPaid() === false && $sType == 'webhook') {
                if ($oOrder->oxorder__oxstorno->value == 1) {
                    $oOrder->stripeUncancelOrder();
                }

                if (abs($oTransaction->amount_received - PaymentHelper::getInstance()->priceInCent($oOrder->oxorder__oxtotalordersum->value)) < 0.01) {
                    $oOrder->stripeMarkAsPaid();
                    $oOrder->stripeSetFolder(Registry::getConfig()->getShopConfVar('sStripeStatusProcessing'));
                }
            }
            $blSuccess = true;
        }

        if ($sStatus == 'canceled') {
            $oOrder->cancelOrder();
        }

        if ($sStatus == 'processing') {
            $blSuccess = true;
            $sStatus = 'pending';
        }

        $this->handleExternalTransId($oTransaction, $oOrder);

        return ['success' => $blSuccess, 'status' => $sStatus];
    }
}
