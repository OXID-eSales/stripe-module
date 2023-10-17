<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidSolutionCatalysts\Stripe\Application\Model\Payment;

class Eps extends Base
{
    /**
     * Payment id in the oxid shop
     *
     * @var string
     */
    protected $sOxidPaymentId = 'stripeeps';

    /**
     * Method code used for API request
     *
     * @var string
     */
    protected $sStripePaymentCode = 'eps';

    /** @var array */
    protected $aBillingCountryRestrictedTo = ['AT'];

    /** @var array */
    protected $aCurrencyRestrictedTo = ['EUR'];

    /**
     * Determines custom frontend template if existing, otherwise false
     *
     * @var string|bool
     */
    protected $sCustomFrontendTemplate = 'stripeeps.tpl';

    /**
     * @return array
     */
    public function getPaymentMethodSpecificParameters()
    {
        $sSelectedBank = $this->getDynValueParameter('stripe_eps_bank');
        return ['bank' => $sSelectedBank];
    }
}
