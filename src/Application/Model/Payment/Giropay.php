<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidSolutionCatalysts\Stripe\Application\Model\Payment;

class Giropay extends Base
{
    /**
     * Payment id in the oxid shop
     *
     * @var string
     */
    protected $sOxidPaymentId = 'stripegiropay';

    /**
     * Method code used for API request
     *
     * @var string
     */
    protected $sStripePaymentCode = 'giropay';

    /** @var array */
    protected $aBillingCountryRestrictedTo = ['DE'];

    /** @var array */
    protected $aCurrencyRestrictedTo = ['EUR'];
}
