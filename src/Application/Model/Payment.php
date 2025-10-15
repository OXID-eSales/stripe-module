<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidSolutionCatalysts\Stripe\Application\Model;

use OxidSolutionCatalysts\Stripe\Service\PaymentService;

class Payment extends Payment_parent
{
    /**
     * Check if given payment method is a Stripe method
     *
     * @return bool
     */
    public function isStripePaymentMethod()
    {
        return PaymentService::getInstance()->isStripePaymentMethod($this->getId());
    }

    /**
     * Return Stripe payment model
     *
     * @return \OxidSolutionCatalysts\Stripe\Application\Model\Payment\Base
     */
    public function getStripePaymentModel()
    {
        if ($this->isStripePaymentMethod()) {
            return PaymentService::getInstance()->getStripePaymentModel($this->getId());
        }
        return null;
    }
}
