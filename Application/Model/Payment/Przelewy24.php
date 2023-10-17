<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidSolutionCatalysts\Stripe\Application\Model\Payment;

class Przelewy24 extends Base
{
    /**
     * Payment id in the oxid shop
     *
     * @var string
     */
    protected $sOxidPaymentId = 'stripep24';

    /**
     * Method code used for API request
     *
     * @var string
     */
    protected $sStripePaymentCode = 'p24';

    /** @var array */
    protected $aBillingCountryRestrictedTo = ['PL'];

    /** @var array */
    protected $aCurrencyRestrictedTo = ['EUR', 'PLN'];

    /**
     * Determines custom frontend template if existing, otherwise false
     *
     * @var string|bool
     */
    protected $sCustomFrontendTemplate = 'stripep24.tpl';

    /**
     * @return array
     */
    public function getPaymentMethodSpecificParameters()
    {
        $sSelectedBank = $this->getDynValueParameter('stripe_p24_bank');
        return ['bank' => $sSelectedBank];
    }
}
