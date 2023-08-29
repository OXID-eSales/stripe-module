<?php
/**
 * Copyright © FATCHIP GmbH. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\stripe\Application\Model\Payment;

class Sofort extends Base
{
    /**
     * Payment id in the oxid shop
     *
     * @var string
     */
    protected $sOxidPaymentId = 'stripesofort';

    /**
     * Method code used for API request
     *
     * @var string
     */
    protected $sStripePaymentCode = 'sofort';

    /** @var array */
    protected $aBillingCountryRestrictedTo = ['AT','BE','DE','ES','IT','NL'];

    /** @var array */
    protected $aCurrencyRestrictedTo = ['EUR'];

    /**
     * Determines custom frontend template if existing, otherwise false
     *
     * @var string|bool
     */
    protected $sCustomFrontendTemplate = 'stripesofort.tpl';

    /**
     * @return array
     */
    public function getPaymentMethodSpecificParameters()
    {
        $sCountryCode = $this->getDynValueParameter('stripe_sofort_country');
        return ['country' => $sCountryCode];
    }
}
