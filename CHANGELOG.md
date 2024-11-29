# Change Log for Stripe for OXID

All notable changes to this project will be documented in this file.
The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

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
