<h1 style="text-align: center">Stripe for OXID eShop</h1>

## Installation

- Open a shell and change to the root directory of the store (where the composer.json file is located).

   Example:
 
   <code>cd /var/www/oxideshop/</code>


- Execute the following command:

  <code>composer require oxid-esales/stripe-module</code>
   

## Configuration
To use the module after activation : \
Navigate to : Admin > Extensions > Modules > Stripe Payment > Settings

- Basic configuration :
  - Set up the operation mode
  - Use the according OnBoarding Button to connect to Stripe and fill automatically the token/ public key fields.
  - Fill the Private Key field(s) to allow the mandatory creation of Webhook. **Here you have to enter the connected account private key, not the onboarding main token.**

Save config at that point.

- Webhooks :
  - Use the button "Create Webhooks" to generate a webhook and register it in the config.


- Status Mapping, Cronjob and other unmentioned configuration are optional and self-explanatory.


Payment methods can be then activated as any other Oxid payment method.