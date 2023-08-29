<?php
/**
 * Copyright © FATCHIP GmbH. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\stripe\Application\Model\Payment;

class Bancontact extends Base
{
    /**
     * Payment id in the oxid shop
     *
     * @var string
     */
    protected $sOxidPaymentId = 'stripebancontact';

    /**
     * Method code used for API request
     *
     * @var string
     */
    protected $sStripePaymentCode = 'bancontact';

    /** @var array */
    protected $aBillingCountryRestrictedTo = ['BE'];

    /** @var array */
    protected $aCurrencyRestrictedTo = ['EUR'];
}
