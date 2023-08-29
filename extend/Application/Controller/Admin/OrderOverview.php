<?php
/**
 * Copyright © FATCHIP GmbH. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\stripe\extend\Application\Controller\Admin;

class OrderOverview extends OrderOverview_parent
{
    /**
     * Sends order.
     */
    public function sendorder()
    {
        parent::sendorder();

        $oOrder = oxNew(\OxidEsales\Eshop\Application\Model\Order::class);
        if ($oOrder->load($this->getEditObjectId()) && $oOrder->stripeIsStripePaymentUsed()) {
            $oOrder->stripeMarkOrderAsShipped();
        }
    }
}
