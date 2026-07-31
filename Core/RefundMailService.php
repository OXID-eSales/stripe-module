<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

declare(strict_types=1);

namespace OxidSolutionCatalysts\Stripe\Core;

use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Core\Registry;
use OxidSolutionCatalysts\Stripe\extend\Core\Email;
use Psr\Log\LogLevel;
use Throwable;

/**
 * Sends the confirmation mails for refunds and order cancellations that were
 * triggered in the backend.
 *
 * This class and Core\Email are the only places holding the mail logic, so that
 * the same feature can be moved to the central payment base module later without
 * touching the trigger points. Everything the module-specific side has to do is
 * to call sendRefundMail() at the point where the refund was confirmed by the
 * payment provider, and sendCancelMail() when an order was cancelled.
 */
class RefundMailService
{
    /**
     * Confirmation of a refund. Does nothing when the merchant did not choose a
     * recipient for it, and nothing for the cancellation flow, which sends its
     * own mail covering the cancellation and the refunded amount together.
     *
     * @param Order $order
     * @param float $refundedAmount amount the Stripe confirmed
     * @param string $currency currency code of the refunded amount
     * @param string $context one of the RefundMailConfig::REFUND_CONTEXT_* values
     * @return void
     */
    public function sendRefundMail(
        Order $order,
        float $refundedAmount,
        string $currency,
        string $context = RefundMailConfig::REFUND_CONTEXT_REFUND
    ): void {
        if ($context === RefundMailConfig::REFUND_CONTEXT_CANCEL) {
            return;
        }

        $recipients = $this->resolveRecipients(
            $this->getRecipientMode(RefundMailConfig::REFUND_CONTEXT_REFUND)
        );

        foreach ($recipients as $recipient) {
            $this->deliver(
                $order,
                $recipient,
                'refund',
                static function (Email $mailer) use ($order, $refundedAmount, $currency, $recipient): bool {
                    return $recipient === RefundMailConfig::MAIL_RECIPIENT_OWNER
                        ? $mailer->stripeSendRefundMailToOwner($order, $refundedAmount, $currency)
                        : $mailer->stripeSendRefundMailToCustomer($order, $refundedAmount, $currency);
                }
            );
        }
    }

    /**
     * Confirmation of an order cancellation. The refunded amount is part of this
     * mail when the cancellation triggered a refund the payment provider
     * confirmed, and omitted when no money was moved.
     *
     * @param Order $order
     * @param float|null $refundedAmount null if the cancellation refunded nothing
     * @param string $currency currency code of the refunded amount
     * @return void
     */
    public function sendCancelMail(Order $order, ?float $refundedAmount, string $currency): void
    {
        $recipients = $this->resolveRecipients(
            $this->getRecipientMode(RefundMailConfig::REFUND_CONTEXT_CANCEL)
        );

        foreach ($recipients as $recipient) {
            $this->deliver(
                $order,
                $recipient,
                'cancel',
                static function (Email $mailer) use ($order, $refundedAmount, $currency, $recipient): bool {
                    return $recipient === RefundMailConfig::MAIL_RECIPIENT_OWNER
                        ? $mailer->stripeSendCancelMailToOwner($order, $refundedAmount, $currency)
                        : $mailer->stripeSendCancelMailToCustomer($order, $refundedAmount, $currency);
                }
            );
        }
    }

    /**
     * Configured recipients for an event. A settings service that cannot be
     * resolved - for instance because the module configuration was not installed
     * after an update - means "no mail" rather than an error in the backend.
     *
     * @param string $event one of the RefundMailConfig::REFUND_CONTEXT_* values
     * @return string one of the RefundMailConfig::MAIL_RECIPIENT_* modes
     */
    protected function getRecipientMode(string $event): string
    {
        try {
            $config = oxNew(RefundMailConfig::class);

            return $event === RefundMailConfig::REFUND_CONTEXT_CANCEL
                ? $config->getCancelMailRecipient()
                : $config->getRefundMailRecipient();
        } catch (Throwable $throwable) {
            return RefundMailConfig::MAIL_RECIPIENT_NONE;
        }
    }

    /**
     * The single recipients a configured mode expands to, in sending order.
     *
     * @param string $mode one of the RefundMailConfig::MAIL_RECIPIENT_* modes
     * @return string[] MAIL_RECIPIENT_CUSTOMER / MAIL_RECIPIENT_OWNER, empty when
     *                  no mail should be sent
     */
    protected function resolveRecipients(string $mode): array
    {
        if ($mode === RefundMailConfig::MAIL_RECIPIENT_CUSTOMER) {
            return [RefundMailConfig::MAIL_RECIPIENT_CUSTOMER];
        }

        if ($mode === RefundMailConfig::MAIL_RECIPIENT_OWNER) {
            return [RefundMailConfig::MAIL_RECIPIENT_OWNER];
        }

        if ($mode === RefundMailConfig::MAIL_RECIPIENT_BOTH) {
            return [RefundMailConfig::MAIL_RECIPIENT_CUSTOMER, RefundMailConfig::MAIL_RECIPIENT_OWNER];
        }

        return [];
    }

    /**
     * Sends one mail with a fresh mailer instance and logs the result. A failing
     * mailer must never abort the backend action that triggered it: the refund or
     * cancellation has already happened at this point, and letting a mail problem
     * bubble up would leave the merchant with an error page for an action that
     * actually succeeded.
     *
     * @param Order $order
     * @param string $recipient one of the RefundMailConfig::MAIL_RECIPIENT_* recipients
     * @param string $type refund|cancel, for the log entry
     * @param callable $send receives the mailer, returns the send result
     * @return void
     */
    protected function deliver(Order $order, string $recipient, string $type, callable $send): void
    {
        $recipientName = $recipient === RefundMailConfig::MAIL_RECIPIENT_OWNER ? 'shop owner' : 'customer';

        try {
            /** @var Email $mailer */
            $mailer = oxNew(Email::class);
            $sent = $send($mailer);

            $this->log(
                $sent ? LogLevel::INFO : LogLevel::WARNING,
                sprintf(
                    'Stripe %s confirmation mail to %s for order %s: %s',
                    $type,
                    $recipientName,
                    $this->orderNumber($order),
                    $sent ? 'sent' : 'not sent'
                ),
                $order
            );
        } catch (Throwable $throwable) {
            $this->log(
                LogLevel::ERROR,
                sprintf(
                    'Stripe %s confirmation mail to %s for order %s failed: %s',
                    $type,
                    $recipientName,
                    $this->orderNumber($order),
                    $throwable->getMessage()
                ),
                $order
            );
        }
    }

    /**
     * Order number for log messages. getFieldData() is untyped, so anything that
     * is not a plain value is reported as an empty number instead of being cast.
     *
     * @param Order $order
     * @return string
     */
    protected function orderNumber(Order $order): string
    {
        $orderNr = $order->getFieldData('oxordernr');

        return is_scalar($orderNr) ? (string)$orderNr : '';
    }

    /**
     * @param string $level
     * @param string $message
     * @param Order $order
     * @return void
     */
    protected function log(string $level, string $message, Order $order): void
    {
        try {
            Registry::getLogger()->log($level, $message, ['orderId' => $order->getId()]);
        } catch (Throwable $throwable) {
            // logging must not break the backend action either
        }
    }
}
