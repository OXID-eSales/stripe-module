<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

$sLangName  = "Deutsch";

// -------------------------------
// RESOURCE IDENTIFIER = STRING
// -------------------------------
$aLang = [
    'charset'                                   => 'UTF-8',
    'STRIPE_LOCALE'                             => 'de_DE',
    'STRIPE_SHIPPINGCOST'                       => "Versandkosten",
    'STRIPE_PAYMENTTYPE'                        => "Zahlungsart-Aufschlag",
    'STRIPE_WRAPPING'                           => "Geschenkverpackung",
    'STRIPE_GIFTCARD'                           => "Grußkarte",
    'STRIPE_VOUCHER'                            => 'Gutschein',
    'STRIPE_DISCOUNT'                           => 'Rabatt',
    'STRIPE_ROUNDINGCORRECTION'                 => 'Rundungskorrektur',
    'STRIPE_SELECT_BANK'                        => 'Bank ausw&auml;hlen',
    'STRIPE_PLEASE_SELECT'                      => '-- Bitte w&auml;hlen --',
    'STRIPE_SOFORT_COUNTRY'                     => 'Land',
    'STRIPE_COUNTRY_AT'                         => 'Österreich',
    'STRIPE_COUNTRY_BE'                         => 'Belgien',
    'STRIPE_COUNTRY_DE'                         => 'Deutschland',
    'STRIPE_COUNTRY_ES'                         => 'Spanien',
    'STRIPE_COUNTRY_IT'                         => 'Italien',
    'STRIPE_COUNTRY_NL'                         => 'Niederlande',

    'STRIPE_ERROR_ORDER_NOT_FOUND'              => 'Bestellung konnte nicht gefunden werden',
    'STRIPE_ERROR_TRANSACTIONID_NOT_FOUND'      => 'Transaktions-Id konnte nicht gefunden werden',
    'STRIPE_ERROR_SOMETHING_WENT_WRONG'         => 'Ein unbekannter Fehler ist aufgetreten',
    'STRIPE_ERROR_ORDER_CANCELED'               => 'Die Bezahlung wurde storniert, bitte versuchen Sie es erneut',
    'STRIPE_ERROR_ORDER_FAILED'                 => 'Die Bezahlung ist fehlgeschlagen, bitte versuchen Sie es erneut',
    'STRIPE_SECOND_CHANCE_MAIL_SUBJECT'         => 'Abschluss Ihrer Bestellung bei',
    'STRIPE_ERROR_ORDER_CONFIG_PUBKEY'          => 'Bitte konfigurieren Sie den ver&ouml;ffentlichbaren Stripe-Schl&uuml;ssel, um diese Zahlungsmethode zu verwenden.',
    'STRIPE_WEBHOOK_CREATE_ERROR'               => 'Der Webhook-Endpunkt konnte nicht erstellt werden.',
    'STRIPE_WEBHOOK_CREATE_ERROR_DELETE_FAILED' => 'Der Webhook-Endpunkt konnte nicht erstellt werden. Das Löschen des vorhandenen WH-Endpunkts ist fehlgeschlagen.',
    'STRIPE_CREDIT_CARD'                        => 'Kreditkarte',
    'STRIPE_REFUND_MAIL_TITLE'                => 'Rückerstattung zu Ihrer Bestellung',
    'STRIPE_REFUND_MAIL_SUBJECT'              => 'Rückerstattung zu Ihrer Bestellung %s',
    'STRIPE_REFUND_MAIL_SUBJECT_OWNER'        => 'Stripe: Rückerstattung zur Bestellung %s veranlasst',
    'STRIPE_REFUND_MAIL_SALUTATION'           => 'Guten Tag',
    'STRIPE_REFUND_MAIL_INTRO'                => 'wir haben eine Rückerstattung über Stripe für Sie veranlasst.',
    'STRIPE_REFUND_MAIL_INTRO_OWNER'          => 'Für die folgende Bestellung wurde über Stripe eine Rückerstattung '
        . 'veranlasst.',
    'STRIPE_REFUND_MAIL_AMOUNT'               => 'Erstatteter Betrag',
    'STRIPE_REFUND_MAIL_ORDER_TOTAL'          => 'Bestellwert',
    'STRIPE_REFUND_MAIL_NOTE'                 => 'Die Gutschrift erfolgt über Stripe auf das von Ihnen bei Stripe '
        . 'verwendete Zahlungsmittel. Wie lange das dauert, hängt von Ihrem Zahlungsmittel und Ihrer Bank ab.',
    'STRIPE_CANCEL_MAIL_TITLE'                => 'Stornierung Ihrer Bestellung',
    'STRIPE_CANCEL_MAIL_SUBJECT'              => 'Stornierung Ihrer Bestellung %s',
    'STRIPE_CANCEL_MAIL_SUBJECT_OWNER'        => 'Stripe: Bestellung %s storniert',
    'STRIPE_CANCEL_MAIL_SALUTATION'           => 'Guten Tag',
    'STRIPE_CANCEL_MAIL_INTRO'                => 'Ihre Bestellung wurde storniert.',
    'STRIPE_CANCEL_MAIL_INTRO_OWNER'          => 'Die folgende Bestellung wurde storniert.',
    'STRIPE_CANCEL_MAIL_ORDER_TOTAL'          => 'Bestellwert',
    'STRIPE_CANCEL_MAIL_REFUNDED'             => 'Erstatteter Betrag',
    'STRIPE_CANCEL_MAIL_NOTE_NO_REFUND'       => 'Sollte für diese Bestellung bereits eine Zahlung erfolgt sein, '
        . 'erhalten Sie die Rückerstattung in einer separaten Nachricht bestätigt.',
];
