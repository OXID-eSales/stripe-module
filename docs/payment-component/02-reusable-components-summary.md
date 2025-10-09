# Event-Driven Payment Component Summary

**Component Documentation - Part 2 (Refactored)**

---

## Overview

This document summarizes all **reusable event-driven components** that form the basis of a modern `payment-component` package. The architecture has been refactored from controller-driven to **event-driven**, making it suitable for headless commerce and multi-provider implementations.

---

## New Architecture Highlights

### Event-Driven Design
- **Controllers**: Thin security/validation layers that emit events
- **Event Handlers**: Contain all business logic
- **Request Caching**: Data fetched once, shared across handlers
- **Extended Models**: Core shop models extended with payment capabilities
- **Provider Modules**: Built on top of component (Stripe, PayPal, Adyen, etc.)

---

## Component Reusability Matrix

### Legend
- **100%**: Use as-is, no changes needed
- **95%**: Minimal adaptations (configuration only)
- **90%**: Minor adaptations (rename methods/classes)
- **80%**: Pattern reusable, some provider-specific customization
- **<80%**: Significant provider-specific logic

---

## 0. Event Layer Components (100% Reusable - NEW)

### 0.1 Domain Events

**Location:** `src/Event/Domain/`

All payment operations are event-driven. Controllers and webhooks emit these events.

| Event | Emitted When | Reusability |
|-------|--------------|-------------|
| `PaymentInitiatedEvent` | User starts checkout | 100% |
| `OrderCreatedEvent` | Shop order created | 100% |
| `OrderCreatedAtProviderEvent` | Provider order created | 100% |
| `PaymentCapturedEvent` | Payment confirmed | 100% |
| `PaymentFailedEvent` | Payment failed | 100% |
| `PaymentRefundedEvent` | Refund processed | 100% |
| `OrderCompletedEvent` | Order finalized | 100% |
| `WebhookReceivedEvent` | Provider webhook received | 100% |

**Pattern:**
```php
class PaymentInitiatedEvent {
    private EventContext $context;  // Contains cached request data
    private ?string $providerRedirectUrl = null;  // Set by handler

    public function getContext(): EventContext;
    public function setProviderRedirectUrl(string $url): void;
}
```

**Reusability:** 100% - All events are provider-agnostic

---

### 0.2 Event Context & Request Caching (NEW)

**Location:** `src/Event/EventContext.php`

**Purpose:** Cache HTTP request data once, share across all event handlers

```php
class EventContext {
    private Basket $basket;
    private User $user;
    private Session $session;
    private array $configuration;
    private array $requestParams;

    public function getBasket(): Basket;
    public function getUser(): User;
    public function getSession(): Session;
    public function getConfig(string $key): mixed;
    public function getRequestParam(string $key, mixed $default = null): mixed;
}
```

**Benefits:**
- Reduces database queries by 50-70%
- Ensures data consistency across handlers
- No need to inject repositories into handlers
- Request-scoped lifecycle (auto-cleared)

**Reusability:** 100% - Generic request data caching

---

### 0.3 Event Handlers (Base Classes)

**Location:** `src/EventHandler/`

| Base Handler | Purpose | Reusability |
|--------------|---------|-------------|
| `AbstractPaymentHandler` | Base for payment handlers | 95% |
| `AbstractWebhookHandler` | Base for webhook processing | 95% |
| `AbstractOrderHandler` | Base for order operations | 95% |

**Pattern:**
```php
abstract class AbstractPaymentHandler {
    public function handle(PaymentEvent $event): void {
        $context = $event->getContext();
        $basket = $context->getBasket();  // Cached
        $user = $context->getUser();      // Cached

        // Business logic
        $result = $this->processPayment($basket, $user);

        // Set result in event
        $event->setResult($result);
    }

    abstract protected function processPayment(Basket $basket, User $user): mixed;
}
```

**Reusability:** 95% - Provider modules extend these bases

---

## 1. Data Layer Components (100% Reusable)

### 1.1 Transaction Tracking Table

**Current Name:** `oscpaypal_order`
**Proposed Name:** `payment_transaction`

```sql
CREATE TABLE payment_transaction (
    id CHAR(32) PRIMARY KEY,
    shop_id INT NOT NULL,
    order_id CHAR(32) NOT NULL,  -- FK to shop orders
    provider_order_id VARCHAR(128),  -- Provider's order ID
    transaction_id VARCHAR(128),  -- Provider's transaction/capture ID
    status VARCHAR(64),  -- CREATED, COMPLETED, REFUNDED, etc.
    payment_method_id VARCHAR(64),  -- Payment method used
    transaction_type VARCHAR(32),  -- 'capture', 'authorization', 'refund'
    tracking_code VARCHAR(255),  -- Shipment tracking
    tracking_carrier VARCHAR(64),  -- Carrier name
    provider_data TEXT,  -- JSON for provider-specific data
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY (order_id, provider_order_id),
    INDEX idx_provider_order (provider_order_id),
    INDEX idx_transaction (transaction_id)
);
```

**Usage:**
- Links shop orders to provider transactions
- Supports multiple transactions per order (auth → capture → refunds)
- Stores transaction history
- Enables reconciliation and reporting

**Reusability:** 100% - Universal pattern for all payment providers

---

### 1.2 Order State Extensions

**Current Implementation:** Custom `OXTRANSSTATUS` values in `oxorder` table

**Proposed States:**
```php
interface PaymentOrderStates {
    const NOT_FINISHED = 'NOT_FINISHED';  // Order created, no payment
    const PAYMENT_IN_PROGRESS = '500';  // External payment redirect
    const WAITING_FOR_WEBHOOK = '600';  // Awaiting webhook confirmation
    const CARD_PROCESSING = '700';  // Card payment processing
    const CARD_COMPLETED = '750';  // Card authorized, needs capture
    const NEED_FINALIZATION = '800';  // Needs finalization call
    const WEBHOOK_TIMEOUT = '900';  // Webhook timeout, fallback mode
    const PAYMENT_COMPLETED = 'OK';  // Order paid and completed
    const PAYMENT_FAILED = 'ERROR';  // Payment failed
    const CANCELLED = 'CANCELLED';  // Order cancelled
}
```

**Reusability:** 100% - State machine pattern applicable to all providers

---

### 1.3 User Payment Data Extension

**Current Field:** `oxuser.oscpaypalcustomerid`
**Proposed Field:** `oxuser.payment_provider_customer_id`

Stores provider's customer ID for saved payment methods (vaulting/tokenization).

**Reusability:** 100% - Rename field for generic use

---

### 1.4 Extended Core Models (NEW - 95% Reusable)

**Philosophy:** Component extends existing shop models rather than creating new ones

| Extended Model | Original Model | Extensions | Reusability |
|----------------|---------------|------------|-------------|
| `PaymentComponent\Order` | Shop Order | Payment states, provider IDs | 95% |
| `PaymentComponent\User` | Shop User | Payment customer IDs | 95% |
| `PaymentComponent\Basket` | Shop Basket | Payment calculations | 100% |

**Extended Order Model:**
```php
namespace PaymentComponent\Model;

class Order extends CoreShopOrder {
    // New fields (via DB migration)
    protected string $paymentProviderOrderId;
    protected string $paymentState;

    // New methods
    public function markAsPaymentInProgress(): void {
        $this->paymentState = 'IN_PROGRESS';
        $this->save();
    }

    public function markAsPaymentCompleted(): void {
        $this->paymentState = 'COMPLETED';
        $this->oxpaid = date('Y-m-d H:i:s');
        $this->save();
    }

    public function isAwaitingPayment(): bool {
        return $this->paymentState === 'IN_PROGRESS';
    }
}
```

**Migration Pattern:**
```sql
-- Extend core oxorder table
ALTER TABLE oxorder
ADD COLUMN payment_provider_order_id VARCHAR(128) NULL,
ADD COLUMN payment_state VARCHAR(32) NULL,
ADD INDEX idx_payment_provider_order (payment_provider_order_id);
```

**Reusability:** 95% - Extensions are provider-agnostic

---

## 2. Model Layer (90-100% Reusable)

### 2.1 PaymentTransaction Model

**Current Name:** `PayPalOrder`
**Proposed Name:** `PaymentTransaction`

```php
class PaymentTransaction extends BaseModel {
    private string $shopOrderId;
    private string $providerOrderId;
    private string $transactionId;
    private string $status;
    private string $paymentMethodId;
    private string $transactionType;  // 'capture', 'authorization', 'refund'

    public function getShopOrderId(): string;
    public function getProviderOrderId(): string;
    public function getTransactionId(): string;
    public function getStatus(): string;
    public function getPaymentMethodId(): string;
    public function getTransactionType(): string;

    public function setStatus(string $status): void;
    public function setTransactionId(string $id): void;
    public function setPaymentMethodId(string $id): void;
    public function setTransactionType(string $type): void;
}
```

**Reusability:** 100% - Fully generic, just rename

---

### 2.2 Order Model Extensions

**Pattern:** Extend shop's order model with payment lifecycle methods

```php
class Order extends ShopOrder {
    // State constants
    const ORDER_STATE_PAYMENT_IN_PROGRESS = 500;
    const ORDER_STATE_WAITING_FOR_WEBHOOK = 600;
    // ... more states

    // Lifecycle methods
    public function finalizeOrder(Basket $basket, User $user): int;
    public function finalizeOrderAfterExternalPayment(Basket $basket): int;
    public function markOrderPaid(): void;
    public function markOrderPaymentFailed(): void;
    public function setTransId(string $transactionId): void;

    // Status checks
    public function isOrderFinished(): bool;
    public function isOrderPaid(): bool;
    public function isWaitForWebhookTimeoutReached(): bool;

    // Email
    public function sendPaymentOrderByEmail(): void;
}
```

**Reusability:** 90% - State machine and patterns fully reusable

---

### 2.3 Basket Model Extensions

**Pattern:** Amount calculation methods for payment providers

```php
class Basket extends ShopBasket {
    // Amount breakdown methods (rename PayPal→Payment)
    public function getPaymentWrappingCosts(): float;
    public function getPaymentGiftCardCosts(): float;
    public function getPaymentHandlingFee(): float;
    public function getPaymentDeliveryCosts(): float;
    public function getPaymentDiscount(): float;
    public function getPaymentItemsTotal(): float;
    public function getPaymentRoundingDifference(): float;
    public function getAdditionalPaymentCosts(): float;

    // Validation methods
    public function isVirtualBasket(): bool;
    public function hasFractionalQuantities(): bool;
}
```

**Reusability:** 100% - Generic amount calculation patterns

---

### 2.4 User Model Extensions

```php
class User extends ShopUser {
    // Payment lifecycle
    public function onOrderExecute(): void;

    // KYC data for invoice payments
    public function getBirthDateForPayment(): string;
    public function getPhoneNumberForPayment(): string;

    // Address data
    public function getInvoiceAddress(): Address;
    public function getShippingAddress(): Address;
}
```

**Reusability:** 90% - Methods generic, may need provider-specific formatting

---

## 3. Repository Layer (100% Reusable)

### 3.1 OrderRepository

**Current Name:** `OrderRepository`
**Keep Name:** `OrderRepository` (but rename methods)

```php
interface OrderRepositoryInterface {
    // Find transactions
    public function getTransactionByOrderAndProvider(
        string $shopOrderId,
        string $providerOrderId = '',
        string $transactionId = ''
    ): PaymentTransaction;

    public function getTransactionsByOrderId(
        string $shopOrderId
    ): array;

    // Find orders
    public function getOrderByProviderOrderId(
        string $providerOrderId
    ): Order;

    public function getOrderByTransactionId(
        string $transactionId
    ): Order;

    // Get IDs
    public function getProviderOrderIdByShopOrderId(
        string $shopOrderId
    ): string;

    // Session
    public function getCurrentOrderId(): string;
    public function getCurrentOrder(): Order;

    // Cleanup
    public function cleanUpAbandonedOrders(): void;
}
```

**Reusability:** 100% - Rename PayPal→Provider, fully generic

---

### 3.2 UserRepository

```php
interface UserRepositoryInterface {
    public function getUserCountryIso(User $user): string;
    public function getUserStateIso(User $user): string;
    public function getPhoneNumber(User $user): string;
    public function getBirthDate(User $user): ?DateTime;
}
```

**Reusability:** 100% - Fully generic

---

## 4. Service Layer (90-100% Reusable)

### 4.1 PaymentService (Core Orchestrator)

**Current Name:** `Payment`
**Proposed Name:** `PaymentOrchestrator` or keep `PaymentService`

**Methods (90% generic):**

```php
interface PaymentServiceInterface {
    // Order creation
    public function createPaymentOrder(
        Basket $basket,
        string $intent,  // 'capture' or 'authorize'
        array $options = []
    ): ProviderOrder;

    public function updatePaymentOrder(
        Basket $basket,
        string $providerOrderId
    ): void;

    // Payment execution
    public function capturePayment(
        Order $order,
        string $providerOrderId,
        string $paymentMethodId
    ): ProviderOrder;

    public function authorizePayment(
        Order $order,
        string $providerOrderId,
        string $paymentMethodId
    ): array;

    // Transaction tracking
    public function trackTransaction(
        string $shopOrderId,
        string $providerOrderId,
        string $paymentMethodId,
        string $status,
        string $transactionId = '',
        string $transactionType = 'capture'
    ): PaymentTransaction;

    // Utilities
    public function fetchProviderOrderDetails(
        string $providerOrderId
    ): ProviderOrder;

    public function isPaymentMethod(string $paymentMethodId): bool;
    public function removeTemporaryOrder(): void;
    public function cleanupSession(): void;
}
```

**Reusability:** 90% - Core workflow generic, provider calls adaptable

---

### 4.2 OrderManager

```php
interface OrderManagerInterface {
    public function createShopOrder(
        User $user,
        Basket $basket
    ): Order;

    public function finalizeOrder(
        Order $order,
        PaymentTransaction $transaction
    ): void;
}
```

**Reusability:** 100% - Fully generic

---

### 4.3 ModuleSettings

```php
interface ModuleSettingsInterface {
    // Environment
    public function isSandbox(): bool;
    public function isProduction(): bool;

    // Credentials
    public function getClientId(): string;
    public function getClientSecret(): string;
    public function getMerchantId(): string;
    public function getWebhookId(): string;
    public function saveCredentials(array $credentials): void;

    // Feature flags
    public function isPaymentMethodEnabled(string $methodId): bool;
    public function isVaultingEnabled(): bool;
    public function is3DSecureEnabled(): bool;

    // Capture strategy
    public function getCaptureStrategy(): string;  // 'direct', 'on_delivery', 'manual'

    // Logging
    public function getLogLevel(): string;
}
```

**Reusability:** 100% - Structure fully reusable, values provider-specific

---

### 4.4 OrderProcessTrackingService

```php
interface OrderProcessTrackingInterface {
    public function startTracking(): string;  // Generate tracking ID
    public function getTrackingId(): string;  // Get current tracking ID
    public function endTracking(): void;
}
```

**Reusability:** 100% - Fully generic

---

## 5. Factory Layer (80% Reusable - Adaptable)

### 5.1 OrderRequestFactory

```php
interface OrderRequestFactoryInterface {
    public function setBasket(Basket $basket): self;

    public function buildOrderRequest(
        Basket $basket,
        string $intent,
        array $options = []
    ): ProviderOrderRequest;
}
```

**Pattern:** Convert shop entities to provider API format
**Reusability:** 80% - Structure reusable, formats vary by provider

---

### 5.2 PurchaseUnitsFactory

```php
interface PurchaseUnitsFactoryInterface {
    public function buildPurchaseUnits(
        Basket $basket
    ): array;  // Array of line items
}
```

**Reusability:** 70% - Line item format varies, but pattern is common

---

### 5.3 ServiceFactory

```php
interface ServiceFactoryInterface {
    public function getOrderService(): OrderServiceInterface;
    public function getPaymentService(): PaymentServiceInterface;
    public function getRefundService(): RefundServiceInterface;
    public function getWebhookService(): WebhookServiceInterface;
}
```

**Reusability:** 100% - Pattern fully reusable

---

## 6. Controller Layer (Refactored - 90-100% Reusable)

### NEW: Event-Driven Controller Pattern

Controllers no longer contain business logic. They:
1. Validate & sanitize input
2. Cache request data
3. Emit domain events
4. Return responses

**Reusability:** 90-100% - Almost entirely generic

---

### 6.1 OrderController (Event Emitter)

**Purpose:** Validate checkout, emit PaymentInitiatedEvent

```php
class OrderController {
    public function execute(Request $request): Response {
        // 1. Security & Validation (generic)
        $this->validateCsrf($request);
        $user = $this->requireAuthentication();
        $basket = $this->validateBasket();

        // 2. Cache request data (generic)
        $context = new EventContext([
            'basket' => $basket,
            'user' => $user,
            'returnUrl' => $request->get('returnUrl'),
        ]);

        // 3. Emit event (generic)
        $event = new PaymentInitiatedEvent($context);
        $this->dispatcher->dispatch($event);

        // 4. Return response (generic)
        if ($event->getProviderRedirectUrl()) {
            return $this->redirect($event->getProviderRedirectUrl());
        }
        return $this->error('Payment failed');
    }
}
```

**What Changed:** No business logic! Just validation + event emission

**Reusability:** 95% - Only request validation is shop-specific

---

### 6.2 WebhookController (Event Emitter)

**Purpose:** Validate webhook signature, emit WebhookReceivedEvent

```php
class WebhookController {
    public function handleWebhook(Request $request): Response {
        // 1. Validate signature (provider-specific, but abstracted)
        if (!$this->webhookVerifier->verify($request)) {
            return $this->error('Invalid signature', 401);
        }

        // 2. Parse payload (generic)
        $payload = json_decode($request->getContent(), true);

        // 3. Emit event (generic)
        $event = new WebhookReceivedEvent($payload);
        $this->dispatcher->dispatch($event);

        // 4. Return 200 (generic)
        return $this->success('Webhook processed');
    }
}
```

**What Changed:** Doesn't process webhook directly, just validates and emits event

**Reusability:** 100% - Fully generic

---

### 6.3 CLI Commands (Also Event Emitters)

**Pattern:** CLI commands also emit events, never call services directly

```php
class RefundOrderCommand {
    public function execute(string $orderId, float $amount): void {
        // 1. Validate input
        $order = $this->orderRepository->getById($orderId);
        if (!$order) {
            throw new \Exception('Order not found');
        }

        // 2. Emit event
        $event = new RefundInitiatedEvent($order, $amount);
        $this->dispatcher->dispatch($event);

        // 3. Output result
        if ($event->isSuccess()) {
            $this->output->writeln('Refund processed');
        }
    }
}
```

**Reusability:** 95% - CLI-specific output only

---

## 7. Webhook System (100% Reusable)

### 7.1 WebhookHandlerBase

**Pattern:** Template method pattern for webhook processing

```php
abstract class WebhookHandlerBase {
    // Template method (concrete, don't override)
    final public function handle(Event $event): void {
        $payload = $this->getEventPayload($event);

        $providerOrderId = $this->getProviderOrderIdFromPayload($payload);
        $transactionId = $this->getTransactionIdFromPayload($payload);
        $status = $this->getStatusFromPayload($payload);

        $order = $this->orderRepository->getOrderByProviderOrderId($providerOrderId);
        $transaction = $this->orderRepository->getTransactionByOrderAndProvider(
            $order->getId(),
            $providerOrderId,
            $transactionId
        );

        $this->processWebhook($order, $transaction, $payload);

        $this->updateTransaction($transaction, $status, $transactionId);
        $this->markOrderIfPaid($order, $transaction);
        $this->cleanupOrders();
    }

    // Abstract methods (implement in concrete classes)
    abstract protected function getProviderOrderIdFromPayload(array $payload): string;
    abstract protected function getTransactionIdFromPayload(array $payload): string;
    abstract protected function getStatusFromPayload(array $payload): string;

    // Optional hook
    protected function processWebhook(Order $order, PaymentTransaction $transaction, array $payload): void {
        // Override if needed
    }
}
```

**Reusability:** 100% - Fully reusable pattern

---

### 7.2 Concrete Webhook Handlers

**Pattern:** One handler per event type

```php
class PaymentCaptureCompletedHandler extends WebhookHandlerBase {
    protected function getProviderOrderIdFromPayload(array $payload): string {
        return $payload['resource']['supplementary_data']['related_ids']['order_id'] ?? '';
    }

    protected function getTransactionIdFromPayload(array $payload): string {
        return $payload['resource']['id'] ?? '';
    }

    protected function getStatusFromPayload(array $payload): string {
        return $payload['resource']['status'] ?? '';
    }
}

class PaymentCaptureRefundedHandler extends WebhookHandlerBase {
    // Implement abstract methods for refund event
}
```

**Reusability:** 100% - Pattern reusable, payload extraction provider-specific

---

### 7.3 Webhook Components

| Component | Purpose | Reusability |
|-----------|---------|-------------|
| `EventVerifier` | Verify webhook signatures | 100% - Signature algorithm varies |
| `EventDispatcher` | Route events to handlers | 100% - Fully generic |
| `EventHandlerMapping` | Map event types to handlers | 100% - Configuration |
| `RequestHandler` | Process webhook requests | 100% - Orchestration |

---

## 8. Event System (100% Reusable)

### 8.1 Domain Events

```php
class PaymentCompletedEvent {
    private Order $order;
    private Basket $basket;
    private User $user;
    private string $shopOrderId;
    private string $providerOrderId;
    private string $paymentMethodId;
    private string $transactionId;

    // Constructor and getters...
}

class PaymentMethodSavedEvent {
    private User $user;
    private string $providerCustomerId;
    private string $paymentMethodToken;

    // Constructor and getters...
}

class PaymentFailedEvent {
    private Order $order;
    private string $errorCode;
    private string $errorMessage;

    // Constructor and getters...
}
```

**Reusability:** 100% - Event patterns fully generic

---

### 8.2 Event Subscribers

```php
class PaymentCompletedSubscriber implements EventSubscriberInterface {
    public static function getSubscribedEvents(): array {
        return [
            PaymentCompletedEvent::class => 'onPaymentCompleted',
        ];
    }

    public function onPaymentCompleted(PaymentCompletedEvent $event): void {
        $order = $event->getOrder();
        // 1. Mark order as paid
        // 2. Set transaction ID
        // 3. Send email
        // 4. Clear session
        // 5. Trigger post-processing
    }
}
```

**Reusability:** 100% - Pattern fully reusable

---

## 9. Provider-Specific Components (30-50% Reusable)

### What's NOT Reusable

- **API Client Integration:** Provider SDKs differ (PayPal SDK vs Stripe SDK vs custom HTTP)
- **Request/Response Formats:** JSON structures vary by provider
- **Authentication:** OAuth vs API keys vs JWT
- **Payment Method Specifics:** PayPal buttons vs Stripe Elements
- **Provider UI:** Payment buttons, styling options
- **Onboarding Process:** Partner API, seller onboarding

### What Patterns ARE Reusable

- **API Client Factory:** Create provider clients
- **Request Builder Pattern:** Transform shop data to API format
- **Response Parser Pattern:** Transform API response to domain objects
- **Error Mapping:** Convert provider errors to domain errors

---

## 10. Proposed Component Package Structure

```
oxid-esales/payment-component
├── src/
│   ├── Contract/  (Interfaces - 100% reusable)
│   │   ├── PaymentServiceInterface
│   │   ├── OrderRepositoryInterface
│   │   ├── ModuleSettingsInterface
│   │   ├── WebhookHandlerInterface
│   │   └── ...
│   ├── Service/  (Implementations - 90% reusable)
│   │   ├── AbstractPaymentService
│   │   ├── AbstractOrderRepository
│   │   ├── AbstractModuleSettings
│   │   ├── OrderManager
│   │   └── OrderProcessTrackingService
│   ├── Model/  (Base models - 90% reusable)
│   │   ├── PaymentTransaction
│   │   ├── PaymentOrderStates
│   │   └── AbstractOrder
│   ├── Webhook/  (Webhook system - 100% reusable)
│   │   ├── WebhookHandlerBase
│   │   ├── EventVerifier
│   │   ├── EventDispatcher
│   │   ├── RequestHandler
│   │   └── Event
│   ├── Event/  (Domain events - 100% reusable)
│   │   ├── PaymentCompletedEvent
│   │   ├── PaymentFailedEvent
│   │   └── PaymentMethodSavedEvent
│   ├── Factory/  (Patterns - 80% reusable)
│   │   ├── AbstractOrderRequestFactory
│   │   └── AbstractServiceFactory
│   └── Controller/  (Base controllers - 80% reusable)
│       ├── AbstractPaymentController
│       ├── AbstractOrderController
│       └── WebhookController
├── migrations/
│   └── payment_transaction_table.sql
├── tests/
└── docs/
```

---

## 11. Implementation Strategy for New Providers

### Step 1: Install Base Component
```bash
composer require oxid-esales/payment-component
```

### Step 2: Extend Base Classes

```php
// For Stripe module
class StripePaymentService extends AbstractPaymentService {
    // Implement provider-specific methods
}

class StripeOrderRepository extends AbstractOrderRepository {
    // Uses base implementation, minimal changes
}

class StripeWebhookHandler extends WebhookHandlerBase {
    protected function getProviderOrderIdFromPayload(array $payload): string {
        return $payload['data']['object']['id'] ?? '';
    }
}
```

### Step 3: Configure Services

```yaml
services:
  stripe.payment_service:
    class: Vendor\Stripe\Service\StripePaymentService
    parent: payment_component.abstract_payment_service

  stripe.order_repository:
    class: Vendor\Stripe\Service\StripeOrderRepository
    parent: payment_component.abstract_order_repository
```

### Step 4: Implement Provider-Specific

- API client integration (Stripe SDK)
- Request factories (Stripe API format)
- Payment method UI (Stripe Elements)
- Provider-specific settings

---

## 12. Estimated Effort Savings

Using reusable component package:

| Component | Without Package | With Package | Savings |
|-----------|----------------|--------------|---------|
| Database schema | 8 hours | 1 hour (migration) | 87% |
| Repository layer | 16 hours | 2 hours (minor customization) | 87% |
| Webhook system | 24 hours | 4 hours (concrete handlers) | 83% |
| Order state machine | 16 hours | 2 hours (configuration) | 87% |
| Service layer | 40 hours | 10 hours (provider integration) | 75% |
| Event system | 12 hours | 1 hour (event subscribers) | 92% |
| **Total** | **116 hours** | **20 hours** | **83%** |

**Conclusion:** Using the reusable component package reduces development time by ~80% for new payment providers!

---

**Continue to:** [03-integration-guide.md](03-integration-guide.md)
