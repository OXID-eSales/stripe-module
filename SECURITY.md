# Security Considerations

This document describes known security considerations that have been reviewed and intentionally left unfixed, either because they require architectural changes, have very low practical risk, or are mitigated by other factors.

## Session Hijacking via stripeReinitializePayment (Second Chance Feature) - FIXED

**File:** `extend/Application/Model/Order.php` (method `stripeReinitializePayment`),
`Application/Controller/StripeFinishPayment.php`
**Status:** fixed - see the security entry in the CHANGELOG

The "Second Chance" email feature sends a URL containing the order id to customers who have not
completed their Stripe payment. Opening that URL used to call `stripeReinitializePayment()`, which
wrote the order owner's id into the session variable `usr` - the variable `User::loadActiveUser()`
reads to decide who is signed in. Anybody holding the link, or an order id obtained some other way,
was therefore signed in as that customer without ever entering a password, with full access to the
account: order history, addresses, and placing orders in the customer's name. Changing the account
email or password was not possible (the shop asks for the current password for both), so the account
could not be taken over permanently, but everything inside the session was exposed.

**Fix:** the auto sign-in is gone. `stripeReinitializePayment()` returns false when no user is signed
in, and `StripeFinishPayment::getOrder()` now requires a signed-in user who owns the order - the
previous check only compared ownership *if* somebody happened to be signed in, which left the
unauthenticated case, the actual use case of the second chance mail, wide open. Visitors who are not
signed in are sent to the login page and can use the link again afterwards.

**Known consequence:** guest orders (accounts without a password) can no longer use the second chance
link, because there is no account to sign in to. Supporting those again needs the signed token
described below.

**Still recommended:** replace the order id in second chance URLs with a signed, time-limited token
(HMAC of order id + timestamp + secret). That would remove the remaining reliance on the order id
being unguessable, close the gap for guest orders, and give the link an expiry - today an order that
is never paid stays eligible indefinitely.

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
