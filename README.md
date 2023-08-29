<h1 style="text-align: center">Stripe for OXID eShop</h1>

## Installation

In the root shop directory :

- Copy content of module repository into : **< shoproot >/source/modules/fc/stripe**

- Edit <shoproot>/composer.json
  - Add the section
<pre>
    "autoload": {
      "psr-4": {
        "FC\\": "./source/modules/fc"
      }
    },
</pre>
- Run command : <pre>composer require stripe/stripe-php</pre>

- Run commands : 
<pre>
  vendor/bin/oe-console oe:module:install-configuration source/modules/fc/stripe
  vendor/bin/oe-console oe:module:apply-configuration
  vendor/bin/oe-console oe:module:activate stripe
</pre>

If class not found issue : <pre>composer dump-autoload</pre>

## Configuration
To use the module after activation : \
Navigate to : Admin > Extensions > Modules > Stripe Payment > Settings

- Basic configuration :
  - Set up the operation mode
  - Use the according OnBoarding Button to connect to Stripe and fill automatically the token/ public key fields.
  - Fill the Private Key field(s) to allow the mandatory creation of Webhook. **Here must be the connected account private key, not the onboarding main token.**

Save config at that point.

- Webhooks :
  - Use the button "Create Webhooks" to generate a webhook and register it in the config.


- Status Mapping, Cronjob and other unmentioned configuration are optional and self-explanatory.


Payment methods can be then activated as any other Oxid payment method.