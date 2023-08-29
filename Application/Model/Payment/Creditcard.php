<?php
/**
 * Copyright © FATCHIP GmbH. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\stripe\Application\Model\Payment;

use FC\stripe\Application\Helper\Payment;
use FC\stripe\Application\Helper\User;
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
    protected $sCustomFrontendTemplate = 'stripecreditcard.tpl';

    /**
     * @return array
     */
    public function getPaymentMethodSpecificParameters()
    {
        $sCardToken = $this->getDynValueParameter('stripe_token_id');
        return ['token' => $sCardToken];
    }
}
