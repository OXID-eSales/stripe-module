<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidSolutionCatalysts\Stripe\extend\Core;

class Session extends Session_parent
{
    /**
     * returns configuration array with info which parameters require session
     * start
     *
     * @return array
     */
    protected function _getRequireSessionWithParams()
    {
        $this->_aRequireSessionWithParams['cl']['stripeFinishPayment'] = true;

        return parent::_getRequireSessionWithParams();
    }
}
