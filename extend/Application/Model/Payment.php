<?php
/**
 * Copyright © FATCHIP GmbH. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\stripe\extend\Application\Model;

use FC\stripe\Application\Helper\Payment as PaymentHelper;

class Payment extends Payment_parent
{
    /**
     * Check if given payment method is a Stripe method
     *
     * @return bool
     */
    public function isStripePaymentMethod()
    {
        return PaymentHelper::getInstance()->isStripePaymentMethod($this->getId());
    }

    /**
     * Return Stripe payment model
     *
     * @return \FC\stripe\Application\Model\Payment\Base
     */
    public function getStripePaymentModel()
    {
        if ($this->isStripePaymentMethod()) {
            return PaymentHelper::getInstance()->getStripePaymentModel($this->getId());
        }
        return null;
    }
}
