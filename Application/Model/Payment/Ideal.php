<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidSolutionCatalysts\Stripe\Application\Model\Payment;

class Ideal extends Base
{
    /**
     * Payment id in the oxid shop
     *
     * @var string
     */
    protected $sOxidPaymentId = 'stripeideal';

    /**
     * Method code used for API request
     *
     * @var string
     */
    protected $sStripePaymentCode = 'ideal';
    
    /** @var array */
    protected $aBillingCountryRestrictedTo = ['NL'];

    /** @var array */
    protected $aCurrencyRestrictedTo = ['EUR'];

    /**
     * Determines custom frontend template if existing, otherwise false
     *
     * @var string|bool
     */
    protected $sCustomFrontendTemplate = 'stripeideal';

    /**
     * @return array
     */
    public function getPaymentMethodSpecificParameters()
    {
        $sSelectedBank = $this->getDynValueParameter('stripe_ideal_bank');
        return ['bank' => $sSelectedBank];
    }
}
