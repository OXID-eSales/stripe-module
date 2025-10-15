<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

/**
 * Metadata version
 */
$sMetadataVersion = '2.1';

/**
 * Module information
 */
$aModule = [
    'id'            => 'stripe',
    'title'         => [
        'de' => 'Stripe Payment',
        'en' => 'Stripe Payment',
        'fr' => 'Stripe Payment'
    ],
    'description'   => [
        'de' => 'Dieses Modul integriert STRIPE als Zahlungsanbieter in Ihren OXID Shop.',
        'en' => 'This module integrates STRIPE as payment provider in your OXID Shop.',
    ],
    'thumbnail'     => 'img/stripe_logo.png',
    'version'       => '2.0.3',
    'author'        => 'OXID eSales AG',
    'url'           => 'https://www.oxid-esales.com',
    'email'         => 'info@oxid-esales.com',
    'extend'        => [
        PaymentGateway::class => StripePaymentGateway::class,
        Order::class => StripeOrder::class,
        OrderArticle::class => StripeOrderArticle::class,
        Payment::class => StripePayment::class,
        ModuleConfiguration::class => StripeModuleConfiguration::class,
        ModuleMain::class => StripeModuleMain::class,
        PaymentMain::class => StripePaymentMain::class,
        OrderMain::class => StripeOrderMain::class,
        OrderOverview::class => StripeOrderOverview::class,
        PaymentController::class => StripePaymentController::class,
        OrderController::class => StripeOrderController::class,
        Email::class => StripeEmail::class,
        Session::class => StripeSession::class,
    ],
    'controllers'   => [
        'StripeWebhook' => OxidSolutionCatalysts\Stripe\Application\Controller\StripeWebhook::class,
        'StripeFinishPayment' => OxidSolutionCatalysts\Stripe\Application\Controller\StripeFinishPayment::class,
        'stripe_order_refund' => OxidSolutionCatalysts\Stripe\Application\Controller\Admin\OrderRefund::class,
        'StripeConnect' => \OxidSolutionCatalysts\Stripe\Application\Controller\Admin\StripeConnect::class,
    ],
    'events'        => [
        'onActivate' => \OxidSolutionCatalysts\Stripe\Core\Events::class.'::onActivate',
        'onDeactivate' => \OxidSolutionCatalysts\Stripe\Core\Events::class.'::onDeactivate',
    ],
    'settings'      => [
        ['group' => 'STRIPE_GENERAL',           'name' => 'sStripeMode',                        'type' => 'select',     'value' => 'test',      'position' => 10, 'constraints' => 'live|test'],
        ['group' => 'STRIPE_GENERAL',           'name' => 'sStripeTestToken',                   'type' => 'str',        'value' => '',          'position' => 20],
        ['group' => 'STRIPE_GENERAL',           'name' => 'sStripeTestPk',                      'type' => 'str',        'value' => '',          'position' => 21],
        ['group' => 'STRIPE_GENERAL',           'name' => 'sStripeLiveToken',                   'type' => 'str',        'value' => '',          'position' => 30],
        ['group' => 'STRIPE_GENERAL',           'name' => 'sStripeLivePk',                      'type' => 'str',        'value' => '',          'position' => 31],
        ['group' => 'STRIPE_GENERAL',           'name' => 'sStripeTestKey',                     'type' => 'str',        'value' => '',          'position' => 32],
        ['group' => 'STRIPE_GENERAL',           'name' => 'sStripeLiveKey',                     'type' => 'str',        'value' => '',          'position' => 33],
        ['group' => 'STRIPE_GENERAL',           'name' => 'blStripeLogTransactionInfo',         'type' => 'bool',       'value' => '1',         'position' => 34],
        ['group' => 'STRIPE_GENERAL',           'name' => 'blStripeRemoveByBillingCountry',     'type' => 'bool',       'value' => '1',         'position' => 35],
        ['group' => 'STRIPE_GENERAL',           'name' => 'blStripeRemoveByBasketCurrency',     'type' => 'bool',       'value' => '1',         'position' => 36],
        ['group' => 'STRIPE_GENERAL',           'name' => 'blStripeProvideCustomerEmailAddress','type' => 'bool',       'value' => '0',         'position' => 37],
        ['group' => 'STRIPE_STATUS_MAPPING',    'name' => 'sStripeStatusPending',               'type' => 'select',     'value' => '',          'position' => 50],
        ['group' => 'STRIPE_STATUS_MAPPING',    'name' => 'sStripeStatusProcessing',            'type' => 'select',     'value' => '',          'position' => 60],
        ['group' => 'STRIPE_STATUS_MAPPING',    'name' => 'sStripeStatusCancelled',             'type' => 'select',     'value' => '',          'position' => 70],
        ['group' => 'STRIPE_CRONJOBS',          'name' => 'sStripeCronFinishOrdersActive',      'type' => 'bool',       'value' => '0',         'position' => 80],
        ['group' => 'STRIPE_CRONJOBS',          'name' => 'sStripeCronSecondChanceActive',      'type' => 'bool',       'value' => '0',         'position' => 90],
        ['group' => 'STRIPE_CRONJOBS',          'name' => 'iStripeCronSecondChanceTimeDiff',    'type' => 'select',     'value' => '1',         'position' => 100],
        ['group' => 'STRIPE_CRONJOBS',          'name' => 'sStripeCronOrderShipmentActive',     'type' => 'bool',       'value' => '0',         'position' => 110],
        ['group' => 'STRIPE_CRONJOBS',          'name' => 'sStripeCronSecureKey',               'type' => 'str',        'value' => '',          'position' => 120],
        ['group' => 'STRIPE_WEBHOOKS',          'name' => 'sStripeWebhookEndpoint',             'type' => 'str',        'value' => '',          'position' => 130],
        ['group' => 'STRIPE_WEBHOOKS',          'name' => 'sStripeWebhookEndpointSecret',       'type' => 'str',        'value' => '',          'position' => 140],
    ]
];
