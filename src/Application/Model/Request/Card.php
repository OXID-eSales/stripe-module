<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidSolutionCatalysts\Stripe\Application\Model\Request;

use OxidSolutionCatalysts\Stripe\Application\Helper\Payment as PaymentHelper;
use OxidSolutionCatalysts\Stripe\Application\Helper\User as UserHelper;
use OxidSolutionCatalysts\Stripe\Application\Model\RequestLog;
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
        $sStripeCustomerId = $this->getCustomerId($oUser);
        if (!UserHelper::getInstance()->isValidCustomerId($sStripeCustomerId)) {
            $sStripeCustomerId = UserHelper::getInstance()->createStripeUser($oUser);
        }

        $this->sStripeCustomerId = $sStripeCustomerId;
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
