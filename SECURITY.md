# Security Considerations

This document describes known security considerations that have been reviewed and intentionally left unfixed, either because they require architectural changes, have very low practical risk, or are mitigated by other factors.

## Session Hijacking via stripeReinitializePayment (Second Chance Feature)

**File:** `extend/Application/Model/Order.php` (method `stripeReinitializePayment`)
**Risk:** High (architectural)

The "Second Chance" email feature sends a URL containing the order ID to customers who have not completed their Stripe payment. When this URL is accessed, `stripeReinitializePayment()` sets `Registry::getSession()->setVariable('usr', ...)` — effectively logging in the visitor as the order's owner.

**Current partial fix:** Owner verification has been added to `StripeFinishPayment::getOrder()` so that logged-in users can only access their own orders. However, unauthenticated users (the primary use case for Second Chance emails) can still access any eligible order by ID.

**Remaining risk:** An attacker who obtains or guesses an order ID (32-character hex) for an unfinished Stripe order could:
1. Access `?cl=stripeFinishPayment&id=ORDER_ID`
2. Be logged in as the order's owner via the session variable
3. Access the victim's account data in another browser tab

**Mitigation:** Order IDs are 32-character hex strings (128-bit entropy) which are practically unguessable. The order must also be in `NOT_FINISHED` status with an unpaid Stripe payment. The window of vulnerability is limited to the time between order creation and payment completion.

**Recommendation for future improvement:** Replace the order ID in Second Chance URLs with a signed, time-limited token (e.g., HMAC of order ID + timestamp + secret). This would prevent any access without the token from the email.

## Session Tokens in Redirect URLs

**File:** `extend/Application/Model/PaymentGateway.php` (method `stripeGetAdditionalParameters`)
**Risk:** Low-Medium

The Stripe return URL includes the OXID session ID (`sid`), CSRF token (`stoken`), and remote access token (`rtoken`). These are visible to:
- Stripe servers (third party)
- Browser history and cache
- HTTP Referrer headers
- Proxy logs

**Mitigation:** The session tokens are transmitted over HTTPS. Stripe is a PCI-DSS compliant payment provider with strict data handling. The tokens are short-lived (session duration). Replacing them with a one-time callback token would require changes to the return-URL handling in the order controller.

## SQL Concatenation in Events.php

**File:** `Core/Events.php`
**Risk:** Very Low

SQL queries use string concatenation for table and column names. However, all values are hardcoded constants (payment IDs and titles from `Payment::getStripePaymentMethods()`). The code only runs during module activation in the admin panel.

**Mitigation:** No user input reaches these queries. The risk would only materialize if the method were extended to accept dynamic values in the future.

## Floating-Point Amount Comparison

**File:** `Application/Model/TransactionHandler/Payment.php`
**Risk:** Very Low

The payment amount comparison uses `abs(... - ...) < 0.01` instead of exact integer comparison. Both values (`amount_received` from Stripe and `priceInCent()`) are integers (cents), making the tolerance irrelevant in practice.

**Mitigation:** The comparison works correctly for integer values. An exact comparison (`=== 0`) would be more precise but the current implementation does not cause incorrect behavior.

---

*Last updated: 2026-03-06*
