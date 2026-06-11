# Change Log for Stripe for OXID

All notable changes to this project will be documented in this file.
The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [2.0.5] - 2026-06-11

### Fixed

- Use frontend shop URL instead of admin URL for webhook endpoint registration
- Payment date (oxpaid) stayed empty for synchronously completed payments (e.g. credit card with instant capture): the paid-marking was restricted to the webhook, whose update could arrive too early (order not committed yet) or be overwritten by the order finalization on customer return. The transaction is now processed in the checkout/return flow as well, the webhook acts as fallback
- Orders with an already succeeded payment are no longer eligible for payment finish or second chance mails (Stripe status 'succeeded' was missing in the status blacklist)

## [2.0.4] - 2026-04-10

### Security

- Add owner verification to StripeFinishPayment::getOrder() to prevent IDOR (logged-in users can only access their own orders)
- Add CSRF protection (checkSessionChallenge) to webhook endpoint creation
- Move createWebhookEndpoint() from StripeWebhook (frontend) to ModuleConfiguration (admin) to fix session mismatch causing 403 on webhook creation
- Add null check for Stripe signature header in webhook handler
- Stop exposing exception messages in webhook error responses
- Add Content-Type: text/plain header to webhook error responses
- Add request body size limit (1 MB) to webhook endpoint
- Fix DOM-XSS: replace innerHTML with textContent for error display in stripe_issuers template
- Replace getRawValue() with ->value in Email headers to prevent potential header injection
- Use hash_equals() for timing-safe cron secureKey comparison
- Add SECURITY.md documenting known security considerations and intentionally unfixed items

## [2.0.3] - 2024-11-29

- Submitting the customer email address is optional

## [2.0.2] - 2024-02-09

- Fix onBording in OXID-Enterprise-Environment

## [2.0.1] - 2024-01-16

### Fixed
- Fixed: Deleted Stripe customer is not updated in Oxid customer field, leads to fail payment

## [2.0.0] - 2023-11-23

- split version for OXID 7

## [1.0.0] - 2023-11-03

### Changed
- initial release
