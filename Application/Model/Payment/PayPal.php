<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidSolutionCatalysts\Stripe\Application\Model\Payment;

class PayPal extends Base
{
    /**
     * Payment id in the oxid shop
     *
     * @var string
     */
    protected $sOxidPaymentId = 'stripepaypal';

    /**
     * Method code used for API request
     *
     * @var string
     */
    protected $sStripePaymentCode = 'paypal';

    /** @var array */
    protected $aCurrencyRestrictedTo = ['EUR', 'GBP', 'USD', 'CHF', 'CZK', 'DKK', 'NOK', 'PLN', 'SEK', 'AUD', 'CAD', 'HKD', 'NZD', 'SGD'];
}
