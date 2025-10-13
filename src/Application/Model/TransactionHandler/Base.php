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

abstract class Base
{
    /**
     * Logfile name
     *
     * @var string
     */
    protected $sLogFileName = 'StripeTransactions.log';

    /**
     * Log transaction status to log file if enabled
     *
     * @param array $aResult
     * @return void
     */
    protected function logResult($aResult)
    {
        if ((bool)PaymentHelper::getInstance()->getShopConfVar('blStripeLogTransactionInfo') === true) {
            $sMessage = (new \DateTimeImmutable())->format('Y-m-d H:i:s')." Transaction handled: ".print_r($aResult, true)." \n";

            $sLogFilePath = getShopBasePath().'/log/'.$this->sLogFileName;
            $oLogFile = fopen($sLogFilePath, "a");
            if ($oLogFile) {
                fwrite($oLogFile, $sMessage);
                fclose($oLogFile);
            }
        }
    }

    /**
     * Check for given external trans id
     *
     * @param PaymentIntent $oTransaction
     * @param Order $oOrder
     * @return void
     */
    protected function handleExternalTransId(PaymentIntent $oTransaction, Order $oOrder)
    {
        $sExternalTransactionId = false;
        if (isset($oTransaction->payment_method_options->paypal->reference)) {
            $sExternalTransactionId = $oTransaction->payment_method_options->paypal->reference;
        }

        if ($sExternalTransactionId !== false) {
            $oOrder->stripeSetExternalTransactionId($sExternalTransactionId);
        }
    }

    /**
     * Process transaction status after payment and in the webhook
     *
     * @param Order $oOrder
     * @param string $sType
     * @return array
     */
    public function processTransaction(Order $oOrder, $sType = 'webhook')
    {
        try {
            $oTransaction = PaymentHelper::getInstance()->getApiClientByOrder($oOrder)->paymentIntents->retrieve($oOrder->oxorder__oxtransid->value);

            $aResult = $this->handleTransactionStatus($oTransaction, $oOrder, $sType);
        } catch(\Exception $exc) {
            $aResult = ['success' => false, 'status' => 'exception', 'error' => $exc->getMessage()];
        }

        $aResult['transactionId'] = $oOrder->oxorder__oxtransid->value;
        $aResult['orderId'] = $oOrder->getId();
        $aResult['type'] = $sType;

        $this->logResult($aResult);

        return $aResult;
    }
}
