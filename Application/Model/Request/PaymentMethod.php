<?php
/**
 * Copyright © FATCHIP GmbH. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\stripe\Application\Model\Request;

use FC\stripe\Application\Helper\Payment as PaymentHelper;
use FC\stripe\Application\Model\Payment\Base as PaymentBase;
use FC\stripe\Application\Model\RequestLog;
use OxidEsales\Eshop\Application\Model\User as CoreUser;

class PaymentMethod extends Base
{
    /**
     * Add needed parameters to the API request
     *
     * @param PaymentBase $oPaymentModel
     * @param CoreUser $oUser
     * @return void
     */
    public function addRequestParameters(PaymentBase $oPaymentModel, CoreUser $oUser)
    {
        $this->addParameter('type', $oPaymentModel->getStripePaymentCode());
        $this->addParameter('billing_details', $this->getBillingAddressParametersFromUser($oUser));

        $aPaymentMethodSpecificParameters = $oPaymentModel->getPaymentMethodSpecificParameters();
        if (!empty($aPaymentMethodSpecificParameters)) {
            $aPaymentMethodSpecificParameters = [$oPaymentModel->getStripePaymentCode() => $aPaymentMethodSpecificParameters];
            $this->aParameters = array_merge($this->aParameters, $aPaymentMethodSpecificParameters);
        }
    }

    /**
     * Execute Request to Stripe API and return Response
     *
     * @return \Stripe\PaymentMethod
     * @throws \Exception
     */
    public function execute()
    {
        $oRequestLog = oxNew(RequestLog::class);
        try {
            $oResponse = PaymentHelper::getInstance()->loadStripeApi()->paymentMethods->create($this->getParameters());

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
