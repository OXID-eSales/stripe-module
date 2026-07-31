<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidSolutionCatalysts\Stripe\Core;

use OxidEsales\Eshop\Core\Registry;

/**
 * Reads the module settings for the refund / cancellation confirmation mails.
 * Anything but a known recipient mode counts as "no mail", so a broken or
 * missing setting can never start sending mail.
 */
class RefundMailConfig
{
    public const MAIL_RECIPIENT_NONE = '0';
    public const MAIL_RECIPIENT_CUSTOMER = '1';
    public const MAIL_RECIPIENT_OWNER = '2';
    public const MAIL_RECIPIENT_BOTH = '3';

    /**
     * Backend action a refund was triggered by. The cancellation flow sends its
     * own mail, so it suppresses the refund mail and the customer receives one
     * mail, not two.
     */
    public const REFUND_CONTEXT_REFUND = 'refund';
    public const REFUND_CONTEXT_CANCEL = 'cancel';

    public const SETTING_REFUND_RECIPIENT = 'sStripeRefundMailRecipient';
    public const SETTING_CANCEL_RECIPIENT = 'sStripeCancelMailRecipient';

    /**
     * @return string one of the MAIL_RECIPIENT_* modes
     */
    public function getRefundMailRecipient(): string
    {
        return $this->sanitize(Registry::getConfig()->getConfigParam(self::SETTING_REFUND_RECIPIENT));
    }

    /**
     * @return string one of the MAIL_RECIPIENT_* modes
     */
    public function getCancelMailRecipient(): string
    {
        return $this->sanitize(Registry::getConfig()->getConfigParam(self::SETTING_CANCEL_RECIPIENT));
    }

    /**
     * @param mixed $mode
     * @return string
     */
    protected function sanitize($mode): string
    {
        $mode = is_scalar($mode) ? (string)$mode : '';

        return in_array(
            $mode,
            [
                self::MAIL_RECIPIENT_CUSTOMER,
                self::MAIL_RECIPIENT_OWNER,
                self::MAIL_RECIPIENT_BOTH,
            ],
            true
        ) ? $mode : self::MAIL_RECIPIENT_NONE;
    }
}
