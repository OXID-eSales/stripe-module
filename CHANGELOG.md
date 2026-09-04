# Change Log for Stripe for OXID

All notable changes to this project will be documented in this file.
The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [1.1.0] - unreleased

### Added

- [0007987](https://bugs.oxid-esales.com/view.php?id=7987): Confirmation mails for refunds and cancellations triggered in the backend. Two new module settings in the module configuration (group "Grundkonfiguration") decide who is notified, separately per event: `sStripeRefundMailRecipient` and `sStripeCancelMailRecipient`, each with `0` no mail (default), `1` customer, `2` shop owner, `3` both. Defaults are `0`, so updating the module does not start sending mail to existing customers unannounced. The refund mail is sent from `Application/Controller/Admin/OrderRefund::fullRefund()` once Stripe accepted the refund (right where `_blSuccessfulRefund` is set), naming order number, refunded amount and order total; the amount now comes from the new `getRefundAmount()`, which is the single source of truth for both the API parameters and the mail. The cancellation mail is sent by the new `extend/Application/Controller/Admin/OrderList::cancelOrder()` override after the order was cancelled — deliberately **not** from `extend/Application/Model/Order::cancelOrder()`, because that model override is also reached by customer aborts in the checkout (`OrderController`, `PaymentController` via `Application/Helper/Order::cancelCurrentOrder()`) and by Stripe's transaction handler (`Application/Model/TransactionHandler/Payment`), none of which must mail anybody. A cancellation moves no money here (an uncaptured payment is merely cancelled at Stripe), so that mail confirms the cancellation only and points out that a refund, if any, is confirmed separately. New `Core/RefundMailConfig` (setting access, unknown values map to "no mail") and `Core/RefundMailService`, which is the only place deciding whether and to whom a mail goes out; mail or logging failures are caught there, because the refund or cancellation has already happened and must not surface as an error page in the backend. The mails themselves are four new methods on the existing `extend/Core/Email` extension plus four templates under `Application/views/email/tpl/`, next to the second chance mail. Rendering switches the admin mode off and back on, the same handling `stripeSendSecondChanceEmail()` needs, because these mails are triggered from the backend but use frontend templates. `RefundMailService` and the mail methods are deliberately free of trigger logic so they can move to the central payment base module later; the same feature is being rolled out to PayPal (0007984), Amazon Pay (0007985), Adyen (0007986) and Unzer (0007989).
- Follow-up on the confirmation mails above, after the first customer feedback: the customer facing texts named the payment provider inside the sentence ("we have issued a refund for you via Stripe." and "Stripe credits the amount to the payment method you used at Stripe."). For a provider that covers several payment methods that is too specific to be correct — the amount goes back to the card, bank account or wallet the customer actually paid with, not to "Stripe" as the customer reads it. `STRIPE_REFUND_MAIL_INTRO` and `STRIPE_REFUND_MAIL_NOTE` are therefore provider neutral now and word for word identical across all payment modules that offer refunds (PayPal, Amazon Pay, Adyen, Stripe, Unzer), so a shop running more than one of them no longer sends differently worded refund mails: "we have issued a refund for you." / "The refund has been credited to the payment method you originally used. When the amount becomes available depends on your payment method and your bank." `STRIPE_REFUND_MAIL_NOTE` is rendered by the cancellation mail as well (whenever an amount was refunded along with the cancellation), so that mail follows the same wording without a second ident. The shop owner copy keeps the provider information, but takes it out of the sentence: `STRIPE_REFUND_MAIL_INTRO_OWNER` now reads "A refund has been issued for the following order (Stripe Payment Provider)." — the mail is read next to the order in the backend, where knowing which provider moved the money is the point. The shop owner subjects (`STRIPE_REFUND_MAIL_SUBJECT_OWNER`, `STRIPE_CANCEL_MAIL_SUBJECT_OWNER`) keep their "Stripe:" prefix on purpose: a merchant who runs several payment modules sorts and filters these mails by that prefix, and a trailing parenthesis would not survive a truncated subject line in the inbox list. No code and no template change was needed — the provider names only ever lived in the language files (`Application/translations/de/stripe_lang.php`, `Application/translations/en/stripe_lang.php`); the mail templates address the idents only. The same wording change was made in the OXID 7 module `osc/stripe-module`.
- New extension `OrderList::class => extend/Application/Controller/Admin/OrderList::class` in `metadata.php`; the module had no order-list extension before. Non-Stripe orders (checked via `stripeIsStripePaymentUsed()`) and orders that cannot be loaded pass straight through to the parent implementation, so the cancel behaviour of other payment methods is untouched.

### Fixed

- The second chance mail built two strings with a stray `" Email.php"` where a separator was meant, producing subjects like `Abschluss Ihrer Bestellung bei Email.phpMein Shop (#12345)` and recipient names like `Max Email.phpMustermann`. Both are a single space now. The same method also reset the admin mode to a hard `true` after rendering instead of restoring the previous value, which left the shop in admin mode whenever the mail was triggered outside the backend — it is sent from `Application/Model/Cronjob/SecondChance` as well as from the order screen in the backend, and the cron job processes further orders in the same run. It restores the previous value now, as do the new refund and cancellation mails.

## [1.0.9] - 2026-06-11

- Payment date (oxpaid) stayed empty for synchronously completed payments (e.g. credit card with instant capture): the paid-marking was restricted to the webhook, whose update could arrive too early (order not committed yet) or be overwritten by the order finalization on customer return. The transaction is now processed in the checkout/return flow as well, the webhook acts as fallback
- Orders with an already succeeded payment are no longer eligible for payment finish or second chance mails (Stripe status 'succeeded' was missing in the status blacklist)

## [1.0.8] - 2026-04-10

### Security

- Add owner verification to StripeFinishPayment::getOrder() to prevent IDOR (logged-in users can only access their own orders)
- Add CSRF protection (checkSessionChallenge) to webhook endpoint creation
- Move createWebhookEndpoint() from StripeWebhook (frontend) to ModuleConfiguration (admin) to fix session mismatch causing 403 on webhook creation
- Add null check for Stripe signature header in webhook handler
- Stop exposing exception messages in webhook error responses
- Add Content-Type: text/plain header to webhook error responses
- Add request body size limit (1 MB) to webhook endpoint
- Fix DOM-XSS: replace innerHTML with textContent for error display in stripe_issuers template
- Add |escape to Stripe API card data in stripecreditcard template (card.id, card.title, card.holder)
- Replace getRawValue() with ->value in Email headers to prevent potential header injection
- Use hash_equals() for timing-safe cron secureKey comparison
- Add SECURITY.md documenting known security considerations and intentionally unfixed items

## [1.0.7] - 2024-11-29

### Fixed

- [0007731](https://bugs.oxid-esales.com/view.php?id=7731): More Errorcodes
- Submitting the customer email address is optional

## [1.0.6] - 2024-11-15

### Fixed

- [0007731](https://bugs.oxid-esales.com/view.php?id=7731): If a credit card is declined, the payment page throws now a error-message instead of only reload the page
- Activating card details form after load if stripe card is selected

## [1.0.5] - 2024-09-20

### Fixed

- [0007694](https://bugs.oxid-esales.com/view.php?id=7694): Fix Webhook generation for multi-shops
- [0007722](https://bugs.oxid-esales.com/view.php?id=7722): Correct Webhook-Return-URL in multi-shop-systems

## [1.0.4] - 2024-07-09

### Fixed

- [0007692](https://bugs.oxid-esales.com/view.php?id=7692): When switching to the OXID summary page (OrderController), a message appears briefly: Card number incomplete

## [1.0.3] - 2024-06-27

### NEW

- reuse of credit card data

## [1.0.2] - 2024-02-09

- Fix onBording in OXID-Enterprise-Environment

## [1.0.1] - 2024-01-16

### Fixed
- Fixed: Deleted Stripe customer is not updated in Oxid customer field, leads to fail payment

## [1.0.0] - 2023-11-03

### Changed
- initial release
