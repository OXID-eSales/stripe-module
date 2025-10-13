<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidSolutionCatalysts\Stripe\Application\Model\Payment;

use OxidSolutionCatalysts\Stripe\Application\Helper\Payment;
use OxidSolutionCatalysts\Stripe\Application\Helper\User;
use OxidEsales\Eshop\Application\Model\Order;

class Creditcard extends Base
{
    /**
     * Payment id in the oxid shop
     *
     * @var string
     */
    protected $sOxidPaymentId = 'stripecreditcard';

    /**
     * Method code used for API request
     *
     * @var string
     */
    protected $sStripePaymentCode = 'card';

    /**
     * Determines custom config template if existing, otherwise false
     *
     * @var string|bool
     */
    protected $sCustomConfigTemplate = '';

    /**
     * Determines custom frontend template if existing, otherwise false
     *
     * @var string|bool
     */
    protected $sCustomFrontendTemplate = 'stripecreditcard';

    /**
     * @return array
     */
    public function getPaymentMethodSpecificParameters()
    {
        $sCardToken = $this->getDynValueParameter('stripe_token_id');
        return ['token' => $sCardToken];
    }
}
