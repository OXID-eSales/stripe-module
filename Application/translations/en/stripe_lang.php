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
];
