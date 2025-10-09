# One-Page Checkout & Headless API

**Component Documentation - Part 5**
**Version:** 2.0.0
**Visual Diagram:** [puml/08-onepage-headless-checkout.puml](puml/08-onepage-headless-checkout.puml)

---

## Overview

The payment component provides a **configurable one-page checkout experience** that can operate in three modes:

1. **Traditional Multi-Step Checkout** (default OXID flow)
2. **One-Page Checkout** (SPA-like experience)
3. **Headless API** (for mobile apps, MCP, programmatic buying)

All three modes use the same event-driven backend architecture, ensuring consistency and maintainability.

**📊 See Visual Architecture:** [puml/08-onepage-headless-checkout.puml](puml/08-onepage-headless-checkout.puml) showing all three modes converging on the same event-driven backend.

---

## Architecture Modes

### Mode 1: Traditional Multi-Step Checkout (Default)

```
User Journey:
┌─────────────┐    ┌─────────────┐    ┌─────────────┐    ┌─────────────┐
│   Basket    │ -> │   Address   │ -> │   Payment   │ -> │   Review    │
│   Page      │    │   Page      │    │   Selection │    │   & Submit  │
└─────────────┘    └─────────────┘    └─────────────┘    └─────────────┘
     URL: /basket      URL: /address      URL: /payment      URL: /order

Each step = page reload, separate HTTP requests
```

**Characteristics:**
- Multiple page loads
- Traditional form submissions
- Session-based state management
- SEO-friendly (multiple URLs)

---

### Mode 2: One-Page Checkout (SPA Mode)

```
User Journey:
┌───────────────────────────────────────────────────────────────┐
│                   Single Page Checkout                         │
│                                                                │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐       │
│  │   Basket     │  │   Address    │  │   Payment    │       │
│  │   Section    │  │   Section    │  │   Section    │       │
│  └──────────────┘  └──────────────┘  └──────────────┘       │
│                                                                │
│  All sections on one page, no reload, dynamic updates        │
└───────────────────────────────────────────────────────────────┘
                    URL: /checkout (single page)

All steps on same page, AJAX/fetch requests, no page reload
```

**Characteristics:**
- Single page load
- AJAX-based communication
- Real-time validation
- Better UX (no page reloads)
- Uses GraphQL via oxAPI

---

### Mode 3: Headless API (Mobile Apps / MCP / Programmatic)

```
API Consumers:
┌─────────────┐    ┌─────────────┐    ┌─────────────┐
│ Mobile App  │    │   MCP Bot   │    │ 3rd Party   │
│  (iOS/      │    │ (Automated  │    │ Integration │
│  Android)   │    │  Buying)    │    │             │
└──────┬──────┘    └──────┬──────┘    └──────┬──────┘
       │                  │                   │
       └──────────────────┼───────────────────┘
                          │
                      GraphQL API
                      (oxAPI)
                          │
                   Payment Component
                   (Event-Driven Backend)
```

**Characteristics:**
- RESTful/GraphQL endpoints
- JSON responses
- Stateless (token-based auth)
- Programmatic access
- MCP (Model Context Protocol) support

---

## Configuration

### Component Settings

```yaml
# config/payment-component.yaml
payment_component:
  checkout:
    # Mode selection
    mode: 'onepage'  # Options: 'traditional', 'onepage', 'headless'

    # One-page checkout settings
    onepage:
      enabled: true
      template: 'page/checkout/onepage.tpl'
      validation_mode: 'realtime'  # or 'on_submit'
      auto_save: true  # Save progress automatically
      show_progress: true  # Show step progress bar

    # Headless API settings
    headless:
      enabled: true
      api_version: 'v1'
      authentication: 'jwt'  # or 'oauth2'
      rate_limit: 100  # requests per minute
      cors_origins:
        - 'https://mobile-app.example.com'
        - 'https://admin.example.com'

    # MCP (Model Context Protocol) settings
    mcp:
      enabled: true
      endpoints:
        - 'create_order'
        - 'update_order'
        - 'process_payment'
```

---

## One-Page Checkout Implementation

### Backend: Controller

The component provides a unified controller that serves all three modes:

```php
namespace PaymentComponent\Controller;

use PaymentComponent\Event\CheckoutStepCompletedEvent;
use PaymentComponent\Event\PaymentInitiatedEvent;

class OnePageCheckoutController extends AbstractCheckoutController
{
    /**
     * Main checkout page (renders template)
     */
    public function render(): string
    {
        // Check if one-page mode is enabled
        if (!$this->config->isOnePageCheckoutEnabled()) {
            // Redirect to traditional checkout
            return $this->redirect('/order');
        }

        // Prepare checkout data
        $checkoutData = [
            'basket' => $this->getBasket(),
            'user' => $this->getUser(),
            'addresses' => $this->getUserAddresses(),
            'paymentMethods' => $this->getAvailablePaymentMethods(),
            'shippingMethods' => $this->getAvailableShippingMethods(),
        ];

        // Render one-page template
        return $this->renderTemplate(
            'page/checkout/onepage.tpl',
            $checkoutData
        );
    }

    /**
     * AJAX endpoint: Update address
     * Used by one-page checkout via AJAX
     * Used by headless API via GraphQL
     */
    public function updateAddress(): JsonResponse
    {
        // 1. Validate input
        $address = $this->validateAddress($this->request->all());

        // 2. Emit event (event-driven!)
        $event = new AddressUpdatedEvent($address);
        $this->dispatcher->dispatch($event);

        // 3. Return JSON (works for AJAX and API)
        return $this->json([
            'success' => true,
            'address_id' => $event->getAddressId(),
            'validation_errors' => [],
        ]);
    }

    /**
     * AJAX endpoint: Process payment
     * Used by one-page checkout via AJAX
     * Used by headless API via GraphQL
     */
    public function processPayment(): JsonResponse
    {
        // 1. Get encrypted payment data
        $encryptedData = $this->request->get('encrypted_data');

        // 2. Decrypt sensitive data
        $paymentData = $this->encryptionService->decrypt($encryptedData);

        // 3. Create event context (cached data)
        $context = new EventContext([
            'basket' => $this->basketRepo->getCurrentBasket(),
            'user' => $this->userRepo->getCurrentUser(),
            'payment_data' => $paymentData,
        ]);

        // 4. Emit payment event
        $event = new PaymentInitiatedEvent($context);
        $this->dispatcher->dispatch($event);

        // 5. Return result (works for AJAX and API)
        if ($event->hasErrors()) {
            return $this->json([
                'success' => false,
                'errors' => $event->getErrors(),
            ], 400);
        }

        return $this->json([
            'success' => true,
            'order_id' => $event->getOrderId(),
            'redirect_url' => $event->getProviderRedirectUrl(),
            'requires_action' => $event->requiresUserAction(),
        ]);
    }

    /**
     * GraphQL resolver: Create order (headless mode)
     */
    public function createOrderGraphQL(array $args): array
    {
        // Same logic as processPayment(), but with GraphQL input format
        $context = new EventContext([
            'basket_id' => $args['basketId'],
            'payment_method' => $args['paymentMethod'],
            'encrypted_data' => $args['encryptedData'],
        ]);

        $event = new PaymentInitiatedEvent($context);
        $this->dispatcher->dispatch($event);

        return [
            'order' => [
                'id' => $event->getOrderId(),
                'status' => $event->getStatus(),
                'total' => $event->getTotal(),
            ],
            'payment' => [
                'redirect_url' => $event->getProviderRedirectUrl(),
                'requires_action' => $event->requiresUserAction(),
            ],
        ];
    }
}
```

**Key Points:**
- Same controller serves AJAX and GraphQL
- Event-driven architecture (emits events)
- JSON responses work for all modes
- Encrypted sensitive data support

---

### Frontend: One-Page Template

The component takes over the checkout template:

**Template Location:** `/Application/views/apex/tpl/page/checkout/onepage.tpl`

```smarty
{* One-Page Checkout Template *}
{* Managed by Payment Component *}

<div id="onepage-checkout" data-config='{
    "api_endpoint": "[{$oViewConf->getBaseDir()}]checkout/api",
    "encryption_public_key": "[{$publicKey}]",
    "basket_id": "[{$basket->getId()}]"
}'>

    {* Progress Indicator *}
    <div class="checkout-progress">
        <div class="step active" data-step="basket">
            <span class="step-number">1</span>
            <span class="step-label">Basket</span>
        </div>
        <div class="step" data-step="address">
            <span class="step-number">2</span>
            <span class="step-label">Address</span>
        </div>
        <div class="step" data-step="payment">
            <span class="step-number">3</span>
            <span class="step-label">Payment</span>
        </div>
        <div class="step" data-step="review">
            <span class="step-number">4</span>
            <span class="step-label">Review</span>
        </div>
    </div>

    {* Section 1: Basket Summary *}
    <div class="checkout-section" data-section="basket">
        <h2>Your Basket</h2>
        <div id="basket-items">
            {include file="page/checkout/inc/basket_items.tpl"}
        </div>
        <button class="btn-next" data-next="address">Continue to Address</button>
    </div>

    {* Section 2: Address *}
    <div class="checkout-section hidden" data-section="address">
        <h2>Delivery Address</h2>
        <form id="address-form">
            <input type="text" name="street" placeholder="Street" required>
            <input type="text" name="city" placeholder="City" required>
            <input type="text" name="zip" placeholder="ZIP Code" required>
            <select name="country" required>
                [{foreach from=$countries item=country}]
                    <option value="[{$country->getId()}]">[{$country->getTitle()}]</option>
                [{/foreach}]
            </select>
        </form>
        <button class="btn-next" data-next="payment">Continue to Payment</button>
    </div>

    {* Section 3: Payment Method *}
    <div class="checkout-section hidden" data-section="payment">
        <h2>Payment Method</h2>
        <div id="payment-methods">
            [{foreach from=$paymentMethods item=method}]
                <div class="payment-method" data-method="[{$method->getId()}]">
                    <input type="radio" name="payment" value="[{$method->getId()}]">
                    <label>[{$method->getTitle()}]</label>
                    <div class="payment-form" data-provider="[{$method->getProvider()}]">
                        {* Dynamic payment forms loaded here *}
                    </div>
                </div>
            [{/foreach}]
        </div>
        <button class="btn-next" data-next="review">Continue to Review</button>
    </div>

    {* Section 4: Review & Submit *}
    <div class="checkout-section hidden" data-section="review">
        <h2>Review Your Order</h2>
        <div id="order-summary">
            {* Summary loaded dynamically *}
        </div>
        <button id="submit-order" class="btn-submit">Place Order</button>
    </div>

</div>

{* Component JavaScript *}
<script src="[{$oViewConf->getModuleUrl('payment-component', 'js/onepage-checkout.js')}]"></script>
```

---

### Frontend: JavaScript (One-Page Logic)

```javascript
// onepage-checkout.js
class OnePageCheckout {
    constructor(config) {
        this.config = config;
        this.currentStep = 'basket';
        this.encryption = new PaymentComponentEncryption(config.encryption_public_key);
        this.init();
    }

    init() {
        // Initialize step navigation
        this.initStepNavigation();

        // Initialize form validation
        this.initValidation();

        // Initialize payment methods
        this.initPaymentMethods();

        // Initialize submit handler
        this.initSubmit();
    }

    /**
     * Navigate between checkout steps
     * No page reload!
     */
    navigateToStep(step) {
        // Hide current section
        document.querySelector(`[data-section="${this.currentStep}"]`).classList.add('hidden');

        // Show next section
        document.querySelector(`[data-section="${step}"]`).classList.remove('hidden');

        // Update progress indicator
        document.querySelectorAll('.checkout-progress .step').forEach(stepEl => {
            stepEl.classList.remove('active');
        });
        document.querySelector(`[data-step="${step}"]`).classList.add('active');

        this.currentStep = step;
    }

    /**
     * Save address via AJAX
     */
    async saveAddress(addressData) {
        const response = await fetch(`${this.config.api_endpoint}/address`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': this.getCsrfToken(),
            },
            body: JSON.stringify(addressData),
        });

        const result = await response.json();

        if (result.success) {
            // Move to next step
            this.navigateToStep('payment');
        } else {
            // Show validation errors
            this.showErrors(result.validation_errors);
        }
    }

    /**
     * Process payment with encryption
     */
    async processPayment(paymentData) {
        // Encrypt sensitive data (card number, CVV, etc.)
        const encryptedData = await this.encryption.encryptPaymentData({
            cardNumber: paymentData.cardNumber,
            cvv: paymentData.cvv,
            expiryDate: paymentData.expiryDate,
        });

        // Send to backend (only encrypted data)
        const response = await fetch(`${this.config.api_endpoint}/payment`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': this.getCsrfToken(),
            },
            body: JSON.stringify({
                encrypted_data: encryptedData,
                basket_id: this.config.basket_id,
                payment_method: paymentData.paymentMethod,
            }),
        });

        const result = await response.json();

        if (result.success) {
            if (result.redirect_url) {
                // Redirect to provider (e.g., PayPal, Stripe 3DS)
                window.location.href = result.redirect_url;
            } else {
                // Payment completed, show confirmation
                this.showConfirmation(result.order_id);
            }
        } else {
            // Show errors
            this.showErrors(result.errors);
        }
    }

    /**
     * Real-time validation
     */
    async validateField(field, value) {
        const response = await fetch(`${this.config.api_endpoint}/validate`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                field: field,
                value: value,
            }),
        });

        const result = await response.json();
        return result.valid;
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    const config = JSON.parse(
        document.getElementById('onepage-checkout').dataset.config
    );

    new OnePageCheckout(config);
});
```

**Key Features:**
- No page reloads (SPA experience)
- Real-time validation
- Encrypted sensitive data
- AJAX communication with backend
- Progress tracking

---

## GraphQL API (oxAPI Integration)

### Schema Definition

```graphql
# Payment Component GraphQL Schema
type Query {
    # Get checkout data
    checkout(basketId: ID!): CheckoutData!

    # Get available payment methods
    paymentMethods(basketId: ID!): [PaymentMethod!]!

    # Get order status
    order(orderId: ID!): Order
}

type Mutation {
    # Update delivery address
    updateAddress(input: AddressInput!): AddressResult!

    # Select payment method
    selectPayment(input: PaymentSelectionInput!): PaymentSelectionResult!

    # Process payment (create order)
    processPayment(input: PaymentInput!): PaymentResult!

    # Complete order after provider redirect
    completeOrder(orderId: ID!, token: String!): OrderResult!
}

# Input Types
input AddressInput {
    basketId: ID!
    street: String!
    streetNumber: String
    city: String!
    zip: String!
    countryId: ID!
    phone: String
}

input PaymentInput {
    basketId: ID!
    paymentMethodId: ID!
    encryptedData: String!  # Encrypted sensitive data
    returnUrl: String!
    cancelUrl: String
}

# Output Types
type CheckoutData {
    basket: Basket!
    user: User!
    addresses: [Address!]!
    paymentMethods: [PaymentMethod!]!
    shippingMethods: [ShippingMethod!]!
}

type PaymentResult {
    success: Boolean!
    orderId: ID
    redirectUrl: String  # For provider redirect (PayPal, Stripe 3DS)
    requiresAction: Boolean!
    errors: [PaymentError!]
}

type Order {
    id: ID!
    orderNumber: String!
    status: OrderStatus!
    total: Money!
    items: [OrderItem!]!
    paymentMethod: PaymentMethod!
    shippingAddress: Address!
    createdAt: DateTime!
}

enum OrderStatus {
    NOT_FINISHED
    IN_PROGRESS
    AWAITING_WEBHOOK
    COMPLETED
    CANCELLED
    FAILED
}
```

---

### GraphQL Resolvers

```php
namespace PaymentComponent\GraphQL\Resolver;

class CheckoutResolver
{
    /**
     * Resolve: processPayment mutation
     */
    public function processPayment($root, array $args, $context): array
    {
        // 1. Validate authentication (for API access)
        $user = $context->getAuthenticatedUser();

        // 2. Get basket
        $basket = $this->basketRepo->getById($args['input']['basketId']);

        // 3. Decrypt sensitive data
        $paymentData = $this->encryptionService->decrypt(
            $args['input']['encryptedData']
        );

        // 4. Create event context
        $eventContext = new EventContext([
            'basket' => $basket,
            'user' => $user,
            'payment_data' => $paymentData,
            'payment_method' => $args['input']['paymentMethodId'],
        ]);

        // 5. Emit payment event (same as web controller!)
        $event = new PaymentInitiatedEvent($eventContext);
        $this->dispatcher->dispatch($event);

        // 6. Return GraphQL result
        return [
            'success' => !$event->hasErrors(),
            'orderId' => $event->getOrderId(),
            'redirectUrl' => $event->getProviderRedirectUrl(),
            'requiresAction' => $event->requiresUserAction(),
            'errors' => $event->getErrors(),
        ];
    }

    /**
     * Resolve: order query
     */
    public function order($root, array $args, $context): ?array
    {
        $order = $this->orderRepo->getById($args['orderId']);

        if (!$order) {
            return null;
        }

        return [
            'id' => $order->getId(),
            'orderNumber' => $order->getOrderNumber(),
            'status' => $order->getPaymentState(),
            'total' => [
                'amount' => $order->getTotalOrderSum(),
                'currency' => $order->getCurrency(),
            ],
            'items' => $order->getOrderArticles(),
            'paymentMethod' => $order->getPaymentType(),
            'createdAt' => $order->getOrderDate(),
        ];
    }
}
```

**Key Points:**
- Same event-driven backend
- GraphQL is just another entry point
- Encrypted data support
- JWT/OAuth authentication
- Works for mobile apps, MCP, etc.

---

## MCP (Model Context Protocol) Support

### MCP Endpoint Configuration

```yaml
# MCP configuration
mcp:
  enabled: true
  server:
    name: "OXID Payment Component"
    version: "1.0.0"

  tools:
    - name: "create_order"
      description: "Create a new order with payment"
      parameters:
        basket_items:
          type: array
          description: "List of items to purchase"
        payment_method:
          type: string
          description: "Payment method ID"
        encrypted_payment_data:
          type: string
          description: "Encrypted payment details"

    - name: "check_order_status"
      description: "Check the status of an order"
      parameters:
        order_id:
          type: string
          description: "Order ID to check"

    - name: "list_payment_methods"
      description: "Get available payment methods"
      parameters:
        country:
          type: string
          description: "Country code"
```

---

### MCP Handler

```php
namespace PaymentComponent\MCP;

class MCPHandler
{
    /**
     * Handle MCP tool calls
     */
    public function handleToolCall(string $tool, array $parameters): array
    {
        return match($tool) {
            'create_order' => $this->createOrder($parameters),
            'check_order_status' => $this->checkOrderStatus($parameters),
            'list_payment_methods' => $this->listPaymentMethods($parameters),
            default => throw new \InvalidArgumentException("Unknown tool: {$tool}"),
        };
    }

    private function createOrder(array $params): array
    {
        // Same event-driven flow!
        $context = new EventContext([
            'basket_items' => $params['basket_items'],
            'payment_method' => $params['payment_method'],
            'encrypted_data' => $params['encrypted_payment_data'],
        ]);

        $event = new PaymentInitiatedEvent($context);
        $this->dispatcher->dispatch($event);

        return [
            'order_id' => $event->getOrderId(),
            'status' => $event->getStatus(),
            'total' => $event->getTotal(),
            'requires_action' => $event->requiresUserAction(),
            'action_url' => $event->getProviderRedirectUrl(),
        ];
    }
}
```

---

## Benefits Summary

### One-Page Checkout
- ✅ **Better UX**: No page reloads, faster checkout
- ✅ **Higher conversion**: Reduced cart abandonment
- ✅ **Real-time validation**: Catch errors immediately
- ✅ **Mobile-optimized**: Single-page perfect for mobile
- ✅ **Configurable**: Enable/disable per shop

### Headless API (GraphQL)
- ✅ **Mobile apps**: Native iOS/Android apps
- ✅ **Third-party integrations**: Partner systems
- ✅ **Programmatic buying**: Automated purchases
- ✅ **Flexible**: RESTful or GraphQL
- ✅ **Same backend**: Reuses event-driven architecture

### MCP Support
- ✅ **AI agents**: Let AI buy on behalf of users
- ✅ **Automation**: Integrate with automation tools
- ✅ **Voice commerce**: "Alexa, buy this"
- ✅ **Future-proof**: Ready for AI revolution

---

## Comparison Table

| Feature | Traditional | One-Page | Headless |
|---------|------------|----------|----------|
| **Page Loads** | Multiple (4-5) | Single | N/A (API) |
| **User Experience** | Standard | Excellent | N/A |
| **Mobile Friendly** | Good | Excellent | Native |
| **Conversion Rate** | Baseline | +15-30% | N/A |
| **API Access** | No | Limited | Full |
| **MCP Support** | No | No | Yes |
| **Development Effort** | Low | Medium | Medium |
| **SEO** | Excellent | Good | N/A |
| **Real-time Validation** | No | Yes | Yes |
| **Encrypted Data** | Yes | Yes | Yes |
| **Event-Driven** | Yes | Yes | Yes |

---

## Configuration Examples

### Enable One-Page Checkout

```php
// config/payment-component.php
return [
    'checkout' => [
        'mode' => 'onepage',  // Switch to one-page mode
        'onepage' => [
            'enabled' => true,
            'template' => 'page/checkout/onepage.tpl',
            'validation_mode' => 'realtime',
            'auto_save' => true,
        ],
    ],
];
```

### Enable Headless API

```php
return [
    'checkout' => [
        'mode' => 'headless',  // Enable API mode
        'headless' => [
            'enabled' => true,
            'api_version' => 'v1',
            'authentication' => 'jwt',
            'cors_origins' => [
                'https://mobile.example.com',
            ],
        ],
    ],
];
```

### Enable Both (Hybrid)

```php
return [
    'checkout' => [
        'mode' => 'onepage',  // Default for web
        'onepage' => ['enabled' => true],
        'headless' => ['enabled' => true],  // Also enable API
    ],
];
```

---

## Next Steps

1. **Configure mode** in `payment-component.yaml`
2. **Customize template** if using one-page mode
3. **Setup GraphQL** if using headless mode
4. **Test thoroughly** (web, mobile, API)
5. **Monitor metrics** (conversion rate, API usage)

The component handles all the complexity - you just configure and go!
