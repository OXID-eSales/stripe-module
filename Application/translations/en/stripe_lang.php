<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

$sLangName  = "English";

// -------------------------------
// RESOURCE IDENTIFIER = STRING
// -------------------------------
$aLang = [
    'charset'                                   => 'UTF-8',
    'STRIPE_LOCALE'                             => 'en_US',
    'STRIPE_SHIPPINGCOST'                       => "Shipping cost",
    'STRIPE_PAYMENTTYPE'                        => "Payment type",
    'STRIPE_WRAPPING'                           => "Gift wrapping",
    'STRIPE_GIFTCARD'                           => "Giftcard",
    'STRIPE_VOUCHER'                            => 'Voucher',
    'STRIPE_DISCOUNT'                           => 'Discount',
    'STRIPE_ROUNDINGCORRECTION'                 => 'Rounding correction',
    'STRIPE_SELECT_BANK'                        => 'Select bank',
    'STRIPE_PLEASE_SELECT'                      => '-- Please select --',
    'STRIPE_SOFORT_COUNTRY'                     => 'Country',
    'STRIPE_COUNTRY_AT'                         => 'Austria',
    'STRIPE_COUNTRY_BE'                         => 'Belgium',
    'STRIPE_COUNTRY_DE'                         => 'Germany',
    'STRIPE_COUNTRY_ES'                         => 'Spain',
    'STRIPE_COUNTRY_IT'                         => 'Italy',
    'STRIPE_COUNTRY_NL'                         => 'Netherlands',
    'STRIPE_CARDS_USED'                         => 'credit cards used',
    'STRIPE_NEW_CARD'                           => 'new credit card',

    'STRIPE_ERROR_ORDER_NOT_FOUND'              => 'Order not found',
    'STRIPE_ERROR_TRANSACTIONID_NOT_FOUND'      => 'Transaction id not found',
    'STRIPE_ERROR_SOMETHING_WENT_WRONG'         => 'An unknown error occured',
    'STRIPE_ERROR_ORDER_CANCELED'               => 'Payment was canceled, please try again',
    'STRIPE_ERROR_ORDER_FAILED'                 => 'Payment failed, please try again',
    'STRIPE_ERROR_CARD_DECLINED'                => 'The payment failed because your card was declined. Please try with a different card or payment method.',
    'STRIPE_ERROR_CARD_EXPIRED'                 => 'The payment failed because your card is expired. Please try with a different card or payment method.',
    'STRIPE_ERROR_INCORRECT_CVC'                => 'The payment failed because CVC code is incorrect. Please correct and try again or use different card or payment method.',
    'STRIPE_ERROR_PROCESSING_ERROR'             => 'The payment failed. Please try with a different card or payment method.',
    'STRIPE_ERROR_INCORRECT_NUMBER'             => 'The payment failed because your card number is incorrect. Please try with a different card or payment method.',
    'STRIPE_SECOND_CHANCE_MAIL_SUBJECT'         => 'Completion of your order at',
    'STRIPE_ERROR_ORDER_CONFIG_PUBKEY'          => 'Please configure Stripe publishable key to use this payment method.',
    'STRIPE_WEBHOOK_CREATE_ERROR'               => 'The Webhook Endpoint could not be created.',
    'STRIPE_WEBHOOK_CREATE_ERROR_DELETE_FAILED' => 'The Webhook Endpoint could not be created. Deletion of existing WH Endpoint failed.',
    'STRIPE_REFUND_MAIL_TITLE'                => 'Refund for your order',
    'STRIPE_REFUND_MAIL_SUBJECT'              => 'Refund for your order %s',
    'STRIPE_REFUND_MAIL_SUBJECT_OWNER'        => 'Stripe: refund issued for order %s',
    'STRIPE_REFUND_MAIL_SALUTATION'           => 'Dear',
    'STRIPE_REFUND_MAIL_INTRO'                => 'we have issued a refund for you.',
    'STRIPE_REFUND_MAIL_INTRO_OWNER'          => 'A refund has been issued for the following order (Stripe Payment '
        . 'Provider).',
    'STRIPE_REFUND_MAIL_AMOUNT'               => 'Refunded amount',
    'STRIPE_REFUND_MAIL_ORDER_TOTAL'          => 'Order total',
    'STRIPE_REFUND_MAIL_NOTE'                 => 'The refund has been credited to the payment method you originally '
        . 'used. When the amount becomes available depends on your payment method and your bank.',
    'STRIPE_CANCEL_MAIL_TITLE'                => 'Cancellation of your order',
    'STRIPE_CANCEL_MAIL_SUBJECT'              => 'Cancellation of your order %s',
    'STRIPE_CANCEL_MAIL_SUBJECT_OWNER'        => 'Stripe: order %s cancelled',
    'STRIPE_CANCEL_MAIL_SALUTATION'           => 'Dear',
    'STRIPE_CANCEL_MAIL_INTRO'                => 'your order has been cancelled.',
    'STRIPE_CANCEL_MAIL_INTRO_OWNER'          => 'The following order has been cancelled.',
    'STRIPE_CANCEL_MAIL_ORDER_TOTAL'          => 'Order total',
    'STRIPE_CANCEL_MAIL_REFUNDED'             => 'Refunded amount',
    'STRIPE_CANCEL_MAIL_NOTE_NO_REFUND'       => 'If a payment has already been made for this order, you will '
        . 'receive a separate message confirming the refund.',
];
