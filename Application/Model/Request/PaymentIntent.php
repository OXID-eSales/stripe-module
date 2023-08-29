<?php
/**
 * Copyright © FATCHIP GmbH. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\stripe\Application\Model\Request;

use FC\stripe\Application\Helper\Order as OrderHelper;
use FC\stripe\Application\Helper\Payment as PaymentHelper;
use FC\stripe\Application\Model\RequestLog;
use OxidEsales\Eshop\Application\Model\Order as CoreOrder;

class PaymentIntent extends Base
{
    /**
     * Add needed parameters to the API request
     *
     * @param CoreOrder $oOrder
     * @param double $dAmount
     * @param string $sReturnUrl
     * @param string $sPaymentMethodId
     * @return void
     */
    public function addRequestParameters(CoreOrder $oOrder, $dAmount, $sReturnUrl, $sPaymentMethodId)
    {
        $oPaymentModel = $oOrder->stripeGetPaymentModel();

        $aAmount = $this->getAmountParameters($oOrder, $dAmount);
        $this->addParameter('payment_method', $sPaymentMethodId);
        $this->addParameter('payment_method_types', [$oPaymentModel->getStripePaymentCode()]);
        $this->addParameter('amount', PaymentHelper::getInstance()->priceInCent($aAmount['value']));
        $this->addParameter('currency', $aAmount['currency']);
        $this->addParameter('description', OrderHelper::getInstance()->getFilledDescriptionText($oOrder));

        $this->addParameter('capture_method', 'automatic'); // or 'manual'
        $this->addParameter('confirmation_method', 'automatic'); // or 'manual'
        $this->addParameter('confirm', true); // or false

        $oCoreUser = $oOrder->getUser();
        $sStripeCustomerId = $this->getCustomerId($oCoreUser);
        if (!empty($sStripeCustomerId)) {
            $this->addParameter('customer', $sStripeCustomerId);
        }
        $this->addParameter('receipt_email', $this->getCustomerEmail($oCoreUser));

        if ($oPaymentModel->isRedirectUrlNeeded($oOrder) === true) {
            $this->addParameter('return_url', $sReturnUrl);
        }
        $this->addParameter('metadata', $this->getMetadataParameters($oOrder));

        if ($oOrder->oxorder__oxdellname->value != '') {
            $this->addParameter('shipping', $this->getShippingAddressParameters($oOrder));
        } else {
            $aParams = $this->getBillingAddressParametersFromOrder($oOrder);
            unset($aParams['email']);
            $this->addParameter('shipping', $aParams);
        }

        $aPaymentSpecificParameters = $oPaymentModel->getPaymentIntentSpecificParameters($oOrder);
        if (!empty($aPaymentSpecificParameters)) {
            $aPaymentSpecificParameters = ['payment_method_data' => $aPaymentSpecificParameters];
        }
        $aPaymentSpecificOptions = $oPaymentModel->getPaymentIntentSpecificOptions($oOrder);
        if (!empty($aPaymentSpecificOptions)) {
            $aPaymentSpecificOptions = ['payment_method_options' => $aPaymentSpecificOptions];
        }

        $this->aParameters = array_merge($this->aParameters, $aPaymentSpecificParameters, $aPaymentSpecificOptions);
    }

    /**
     * Execute Request to Stripe API and return Response
     *
     * @return \Stripe\PaymentIntent
     * @throws \Exception
     */
    public function execute()
    {
        $oRequestLog = oxNew(RequestLog::class);
        try {
            $oResponse = PaymentHelper::getInstance()->loadStripeApi()->paymentIntents->create($this->getParameters());

            $oRequestLog->logRequest($this->getParameters(), $oResponse);
        } catch (\Exception $oEx) {
            $oRequestLog->logExceptionResponse($this->getParameters(), $oEx->getCode(), $oEx->getMessage());
            throw $oEx;
        }

        if (isset($oResponse->details->failureMessage)) {
            throw new \Exception($oResponse->details->failureMessage);
        } elseif (isset($oResponse->extra->failureMessage)) {
            throw new \Exception($oResponse->extra->failureMessage);
        }

        return $oResponse;
    }
}
