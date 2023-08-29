<?php
/**
 * Copyright © FATCHIP GmbH. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\stripe\Application\Model\Request;

use FC\stripe\Application\Helper\Payment as PaymentHelper;
use FC\stripe\Application\Helper\User as UserHelper;
use FC\stripe\Application\Model\RequestLog;
use OxidEsales\Eshop\Application\Model\User as CoreUser;

class Card extends Base
{
    /** @var string */
    private $sStripeCustomerId;

    /**
     * Add needed parameters to the API request
     * @param string $sStripeCardToken
     * @param CoreUser $oUser
     */
    public function addRequestParameters($sStripeCardToken, CoreUser $oUser)
    {
        if (empty($this->getCustomerId($oUser))) {
            UserHelper::getInstance()->createStripeUser($oUser);
        }
        $this->sStripeCustomerId = $this->getCustomerId($oUser);
        $this->addParameter('source', $sStripeCardToken);
    }

    /**
     * Execute Request to Stripe API and return Response
     *
     * @return \Stripe\Card
     * @throws \Exception
     */
    public function execute()
    {
        $oRequestLog = oxNew(RequestLog::class);
        try {
            $oResponse = PaymentHelper::getInstance()->loadStripeApi()->customers->createSource(
                $this->sStripeCustomerId,
                $this->getParameters()
            );

            $oRequestLog->logRequest($this->getParameters(), $oResponse);
        } catch (\Exception $exc) {
            $oRequestLog->logExceptionResponse($this->getParameters(), $exc->getCode(), $exc->getMessage());
            throw $exc;
        }

        if (isset($oResponse->details->failureMessage)) {
            throw new \Exception($oResponse->details->failureMessage);
        } elseif (isset($oResponse->extra->failureMessage)) {
            throw new \Exception($oResponse->extra->failureMessage);
        }

        return $oResponse;
    }
}
