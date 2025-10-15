# Payment Component - Quick Start Guide

## 5-Minute Quick Start

This guide gets you started with the generic payment component in 5 minutes.

---

## Step 1: Create Your Payment Service (5 min)

```php
<?php

namespace YourProvider;

use OxidSolutionCatalysts\Stripe\PaymentComponent\Service\AbstractPaymentService;
use OxidSolutionCatalysts\Stripe\PaymentComponent\ValueObject\ProviderOrder;

class YourProviderPaymentService extends AbstractPaymentService
{
    protected function createProviderOrder(object $basket, string $intent, array $options): ProviderOrder
    {
        // 1. Call your provider's API
        $response = $this->yourProviderClient->createOrder([
            'amount' => $basket->getTotal(),
            'currency' => $basket->getCurrency(),
            'intent' => $intent, // 'capture' or 'authorize'
        ]);

        // 2. Return ProviderOrder value object
        return new ProviderOrder(
            id: $response['id'],
            status: $response['status'],
            amount: $response['amount'],
            currency: $response['currency'],
            transactionId: $response['transaction_id'] ?? null,
            approvalUrl: $response['approval_url'] ?? null,
        );
    }

    protected function updateProviderOrder(object $basket, string $providerOrderId): void
    {
        $this->yourProviderClient->updateOrder($providerOrderId, [
            'amount' => $basket->getTotal(),
        ]);
    }

    protected function captureProviderPayment(
        object $order,
        string $providerOrderId,
        string $paymentMethodId
    ): ProviderOrder {
        $response = $this->yourProviderClient->capturePayment($providerOrderId);

        return new ProviderOrder(
            id: $response['id'],
            status: 'COMPLETED',
            amount: $response['amount'],
            currency: $response['currency'],
            transactionId: $response['transaction_id'],
        );
    }

    // Implement other abstract methods (authorize, refund, fetch)...
}
```

**Time:** ~5 minutes per method × 6 methods = **30 minutes**

---

## Step 2: Create Webhook Handler (3 min)

```php
<?php

namespace YourProvider;

use OxidSolutionCatalysts\Stripe\PaymentComponent\EventHandler\AbstractWebhookHandler;

class YourProviderWebhookHandler extends AbstractWebhookHandler
{
    // Only 3 methods to implement!

    protected function getProviderOrderIdFromPayload(array $payload): string
    {
        // Extract your provider's order ID from webhook payload
        return $payload['resource']['order_id'] ?? '';
    }

    protected function getTransactionIdFromPayload(array $payload): string
    {
        // Extract transaction/capture ID
        return $payload['resource']['transaction_id'] ?? '';
    }

    protected function getStatusFromPayload(array $payload): string
    {
        // Extract and normalize status
        $status = $payload['resource']['status'] ?? '';
        return strtoupper($status);
    }

    // That's it! Everything else is handled by AbstractWebhookHandler:
    // - Order lookup
    // - Transaction update
    // - Event emission
    // - Cleanup
}
```

**Time:** ~**3 minutes**

---

## Step 3: Create Payment Event Handler (5 min)

```php
<?php

namespace YourProvider;

use OxidSolutionCatalysts\Stripe\PaymentComponent\EventHandler\AbstractPaymentHandler;

class YourProviderPaymentHandler extends AbstractPaymentHandler
{
    public function handle(PaymentInitiatedEvent $event): void
    {
        // 1. Get cached data (no DB queries!)
        $basket = $this->getBasketFromContext($event->getContext());
        $user = $this->getUserFromContext($event->getContext());

        // 2. Create temporary order
        $order = $this->orderManager->createTemporaryOrder($user, $basket);

        // 3. Create payment at provider
        $providerOrder = $this->paymentService->createPaymentOrder($basket, 'capture', [
            'orderId' => $order->getId(),
            'returnUrl' => $this->getRequestParam($event->getContext(), 'returnUrl'),
            'cancelUrl' => $this->getRequestParam($event->getContext(), 'cancelUrl'),
        ]);

        // 4. Set result in event (controller will use this)
        $event->setProviderRedirectUrl($providerOrder->getApprovalUrl());
    }
}
```

**Time:** ~**5 minutes**

---

## Step 4: Configure Services (2 min)

```yaml
# services.yaml
services:
  your_provider.payment_service:
    class: YourProvider\YourProviderPaymentService
    arguments:
      - '@order_repository'
      - '@module_settings'
      - '@logger'

  your_provider.webhook_handler:
    class: YourProvider\YourProviderWebhookHandler
    arguments:
      - '@order_repository'
      - '@order_manager'
      - '@logger'
    tags:
      - { name: event_handler, event: WebhookReceivedEvent }

  your_provider.payment_handler:
    class: YourProvider\YourProviderPaymentHandler
    arguments:
      - '@your_provider.payment_service'
      - '@order_manager'
      - '@logger'
    tags:
      - { name: event_handler, event: PaymentInitiatedEvent }
```

**Time:** ~**2 minutes**

---

## Total Time: ~40 minutes

That's it! You've implemented a complete payment module in **40 minutes**.

Compare this to **120+ hours** building from scratch!

---

## What You Get For Free

### 1. Request-Scoped API Caching

```php
// Automatic caching via CachableApiTrait
$customer = $service->getCustomer('cus_123'); // API call
$customer = $service->getCustomer('cus_123'); // From cache!
```

### 2. EventContext Data Caching

```php
// Controller caches data once
$context = new EventContext(
    basket: $basket,  // One DB query
    user: $user,      // One DB query
);

// All handlers access cached data (no more DB queries)
```

### 3. Logging

```php
// Built-in via LoggableTrait
$this->logDebug('Creating order', ['intent' => $intent]);
$this->logInfo('Order created', ['orderId' => $orderId]);
$this->logException($e, 'Failed to capture');
```

### 4. Validation

```php
// Built-in via ValidationTrait
$this->validateRequired($data, 'orderId');
$this->validateEnum($intent, ['capture', 'authorize'], 'intent');
$this->validateRange($amount, 0.01, 999999.99, 'amount');
```

### 5. Transaction Tracking

```php
// Built-in via PaymentTransaction model
$transaction = new PaymentTransaction();
$transaction->setShopOrderId($orderId);
$transaction->setProviderOrderId($providerOrderId);
$transaction->setStatus('COMPLETED');
$transaction->save();
```

### 6. Complete Webhook Workflow

- Signature verification
- Order lookup
- Transaction update
- Event emission (PaymentCapturedEvent)
- Multiple subscribers (email, inventory, analytics)
- Cleanup of abandoned orders

---

## Testing

### Mock Interfaces

```php
use PHPUnit\Framework\TestCase;

class YourProviderPaymentServiceTest extends TestCase
{
    public function testCreatePaymentOrder(): void
    {
        // Mock dependencies
        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $moduleSettings = $this->createMock(ModuleSettingsInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        // Create service
        $service = new YourProviderPaymentService(
            $orderRepository,
            $moduleSettings,
            $logger
        );

        // Test your provider-specific logic
        $result = $service->createPaymentOrder($basket, 'capture', []);

        $this->assertInstanceOf(ProviderOrder::class, $result);
        $this->assertEquals('CREATED', $result->getStatus());
    }
}
```

---

## Common Patterns

### Pattern 1: API Client with Caching

```php
use OxidSolutionCatalysts\Stripe\PaymentComponent\Trait\CachableApiTrait;
use OxidSolutionCatalysts\Stripe\PaymentComponent\Trait\LoggableTrait;

class YourProviderApiClient
{
    use CachableApiTrait;
    use LoggableTrait;

    public function getCustomer(string $customerId): array
    {
        return $this->getCachedOrFetch(
            "customer:{$customerId}",
            fn() => $this->httpClient->get("/customers/{$customerId}")
        );
    }
}
```

### Pattern 2: Request Factory

```php
use OxidSolutionCatalysts\Stripe\PaymentComponent\Factory\AbstractRequestFactory;

class YourProviderRequestFactory extends AbstractRequestFactory
{
    protected function formatLineItem(object $basketItem): array
    {
        return [
            'name' => $basketItem->getTitle(),
            'quantity' => $basketItem->getAmount(),
            'unit_amount' => $this->formatAmount($basketItem->getPrice()),
        ];
    }

    protected function getUserAddress(object $user): array
    {
        return [
            'line1' => $user->getStreet(),
            'city' => $user->getCity(),
            'postal_code' => $user->getZip(),
            'country' => $user->getCountryIso(),
        ];
    }
}
```

### Pattern 3: Configuration Service

```php
use OxidSolutionCatalysts\Stripe\PaymentComponent\Contract\ModuleSettingsInterface;

class YourProviderSettings implements ModuleSettingsInterface
{
    public function isSandbox(): bool
    {
        return $this->config->get('your_provider_sandbox_mode') === true;
    }

    public function getClientId(): string
    {
        return $this->config->get('your_provider_client_id');
    }

    // Implement other methods...
}
```

---

## Troubleshooting

### Q: "Context does not have getBasket() method"
**A:** Make sure your event context implements the EventContext value object:
```php
use OxidSolutionCatalysts\Stripe\PaymentComponent\ValueObject\EventContext;

$context = new EventContext(
    basket: $basket,
    user: $user,
);
```

### Q: "save() method must be implemented"
**A:** Extend PaymentTransaction for your shop platform:
```php
class OxidPaymentTransaction extends PaymentTransaction
{
    public function save(): bool
    {
        // Use OXID's database access
        $query = "INSERT INTO payment_transaction ...";
        // ...
        return true;
    }
}
```

### Q: How do I add provider-specific metadata?
**A:** Use the `metadata` property of ProviderOrder:
```php
return new ProviderOrder(
    id: $response['id'],
    status: $response['status'],
    amount: $response['amount'],
    currency: $response['currency'],
    metadata: [
        'provider_customer_id' => $response['customer_id'],
        'provider_payment_method' => $response['payment_method'],
        // Any provider-specific data
    ],
);
```

---

## Next Steps

1. **Read the full documentation:** `README.md` in this directory
2. **Review the file structure:** `STRUCTURE.md`
3. **Check the architecture docs:** `/docs/payment-component/`
4. **Implement remaining abstract methods** (authorize, refund)
5. **Add unit tests** using provided interfaces
6. **Deploy and test** with real transactions

---

## Support

- **Documentation:** `/docs/payment-component/`
- **Examples:** See Stripe module in `/examples/`
- **Issues:** GitHub repository

---

**Remember:** You write ~250 lines of code, the component provides ~3,500 lines of reusable code!
