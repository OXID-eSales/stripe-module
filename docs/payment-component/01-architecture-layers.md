# Event-Driven Architecture Layers

**Component Documentation - Part 1 (Refactored)**
**Version:** 2.0.0
**Visual Diagram:** [puml/01-architecture-overview.puml](puml/01-architecture-overview.puml)

---

## Overview

The payment component follows an **event-driven layered architecture** where business logic is decoupled from presentation concerns. Controllers act as thin validation and security layers that emit domain events. Event handlers orchestrate business operations.

**📊 See Visual Diagram:** [puml/01-architecture-overview.puml](puml/01-architecture-overview.puml) for complete architecture visualization.

---

## Event-Driven Layer Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                    PRESENTATION LAYER                        │
│  Controllers (Frontend & CLI) - Security & Validation        │
│  ⚡ Emit Events, Don't Execute Business Logic                │
└────────────────────┬────────────────────────────────────────┘
                     │ emits events
┌────────────────────▼────────────────────────────────────────┐
│                      EVENT LAYER (NEW)                       │
│  Domain Events, Event Dispatcher, Event Context             │
│  PaymentInitiated, OrderCreated, PaymentCaptured...         │
└───────┬────────────────────────────────────────────┬────────┘
        │ triggers                          triggers │
┌───────▼────────────────────────────────────────────▼────────┐
│            EVENT HANDLERS & SUBSCRIBERS                      │
│  Business Logic, Workflow Orchestration                      │
│  Access cached request data, call services                   │
└────────────────────┬────────────────────────────────────────┘
                     │ uses
┌────────────────────▼────────────────────────────────────────┐
│                     SERVICE LAYER                            │
│  PaymentService, OrderRepository, ModuleSettings            │
│  OrderManager, Factories (Called by Event Handlers)         │
└────────────────────┬────────────────────────────────────────┘
                     │ uses
┌────────────────────▼────────────────────────────────────────┐
│                      DOMAIN LAYER                            │
│  Extended Models (Order, User, Basket)                       │
│  New Models (PaymentTransaction)                             │
│  Domain Events                                               │
└────────────────────┬────────────────────────────────────────┘
                     │ persists
┌────────────────────▼────────────────────────────────────────┐
│                 DATA ACCESS LAYER                            │
│  Repositories, QueryBuilder, Cache Layer                     │
└────────────────────┬────────────────────────────────────────┘
                     │ uses
┌────────────────────▼────────────────────────────────────────┐
│                  INFRASTRUCTURE LAYER                        │
│  Database, HTTP Client, Logger, Session, Cache              │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│                   EXTERNAL INTEGRATION                       │
│  Payment Provider API (Stripe, PayPal, Adyen, etc.)         │
│  Webhook Notifications (Also emit events!)                  │
└─────────────────────────────────────────────────────────────┘
```

**Key Architectural Principle**:
- **Controllers**: Thin, only validate & emit events
- **Event Handlers**: Fat, contain all business logic
- **Services**: Reusable, called by event handlers
- **Data flows through events, not direct method calls**

---

## 0. Event Layer (NEW - Primary Layer)

### Responsibilities
- Define domain events that represent business operations
- Dispatch events to registered handlers
- Carry event context (cached request data)
- Enable loose coupling between components

### Components

#### Domain Events
**Location:** `src/Event/Domain/`

| Event | Emitted By | Purpose |
|-------|-----------|---------|
| `PaymentInitiatedEvent` | Controller | User starts payment process |
| `OrderCreatedEvent` | Event Handler | Shop order created |
| `OrderCreatedAtProviderEvent` | Event Handler | Provider order created |
| `PaymentCapturedEvent` | Webhook Controller | Payment confirmed |
| `PaymentFailedEvent` | Event Handler | Payment failed |
| `OrderCompletedEvent` | Event Handler | Order finalized |
| `RefundInitiatedEvent` | Admin Controller | Refund started |

#### Event Context (NEW)
**Location:** `src/Event/EventContext.php`

```php
class EventContext {
    private Basket $basket;
    private User $user;
    private Session $session;
    private array $requestData;

    // Cached data accessible by all event handlers
    public function getBasket(): Basket;
    public function getUser(): User;
    public function getRequestParam(string $key): mixed;
}
```

**Purpose**: Cache HTTP request data once, share across all event handlers

#### Event Dispatcher
**Standard PSR-14 EventDispatcher**

---

## 1. Presentation Layer (Refactored)

### NEW Responsibilities (Event-Driven)
- **Validate & sanitize** user input
- **Enforce security**: authentication, authorization, CSRF
- **Cache request data** (basket, user, session)
- **Emit domain events** with validated data
- **Return responses** based on event outcomes
- **NO business logic** - just thin coordination

### Components

#### Controllers (Event Emitters)
**Location:** `src/Controller/`

| Controller | Purpose | Reusability |
|------------|---------|-------------|
| `PaymentController` | Validate payment selection, emit event | 90% |
| `OrderController` | Validate order, emit PaymentInitiatedEvent | 95% |
| `WebhookController` | Validate signature, emit WebhookReceivedEvent | 100% |
| `AjaxPaymentController` | Validate AJAX requests, emit events | 90% |
| `Admin/*` | Validate admin actions, emit events | 90% |

#### NEW Event-Driven Patterns

**Payment Initiation (Event-Driven):**
```php
OrderController::execute()
  → Validate basket, user session
  → Cache request data (basket, user, session)
  → Emit PaymentInitiatedEvent($eventContext)
  → Event Handler: Creates order, calls provider
  → Event Handler: Emits OrderCreatedAtProviderEvent
  → Controller receives provider redirect URL from event
  → Return redirect response
```

**Webhook Processing (Event-Driven):**
```php
WebhookController::handleRequest()
  → Validate webhook signature
  → Emit WebhookReceivedEvent($payload)
  → Event Handler: Processes payment, updates order
  → Event Handler: Emits PaymentCapturedEvent
  → Multiple Subscribers: Email, inventory, analytics
  → Return HTTP 200
```

**Controller Pseudo-Code Pattern:**
```php
class OrderController {
    public function execute(Request $request): Response {
        // 1. Security & Validation
        $this->validateCsrfToken($request);
        $user = $this->requireAuthenticatedUser();
        $basket = $this->validateBasket();

        // 2. Cache request data
        $context = new EventContext([
            'basket' => $basket,
            'user' => $user,
            'session' => $this->session,
            'returnUrl' => $request->get('returnUrl'),
        ]);

        // 3. Emit event
        $event = new PaymentInitiatedEvent($context);
        $this->dispatcher->dispatch($event);

        // 4. Return response based on event outcome
        if ($event->hasProviderRedirectUrl()) {
            return $this->redirect($event->getProviderRedirectUrl());
        }

        return $this->error('Payment initiation failed');
    }
}
```

### Templates
**Location:** `views/`

- `views/blocks/page/checkout/` - Checkout integration points
- `views/blocks/page/details/` - Product page buttons
- `views/admin/tpl/` - Admin interface
- `views/blocks/email/` - Email templates

**Pattern:** Template blocks extend shop templates at specific extension points

---

## 1.5 Event Handlers & Subscribers (NEW - Core Business Logic)

### Responsibilities
- **Primary business logic layer** in event-driven architecture
- Listen to domain events
- Execute workflows (order creation, payment capture, etc.)
- Call services to perform operations
- Access cached request data via EventContext
- Emit new events to trigger downstream processes

### Components

#### Event Handlers
**Location:** `src/EventHandler/`

| Handler | Listens To | Purpose |
|---------|-----------|---------|
| `PaymentInitiationHandler` | PaymentInitiatedEvent | Create order, call provider API |
| `PaymentCaptureHandler` | PaymentCapturedEvent | Update order status, finalize |
| `OrderCompletionHandler` | OrderCompletedEvent | Send email, clear cart |
| `PaymentFailureHandler` | PaymentFailedEvent | Rollback, notify user |
| `WebhookProcessingHandler` | WebhookReceivedEvent | Process provider webhooks |

**Handler Pattern:**
```php
class PaymentInitiationHandler {
    public function handle(PaymentInitiatedEvent $event): void {
        // 1. Get cached data from context
        $basket = $event->getContext()->getBasket();
        $user = $event->getContext()->getUser();

        // 2. Execute business logic
        $order = $this->orderManager->createTemporaryOrder($basket, $user);

        // 3. Call provider API via service
        $providerOrder = $this->paymentService->createProviderOrder($order);

        // 4. Emit new event
        $this->dispatcher->dispatch(
            new OrderCreatedAtProviderEvent($order, $providerOrder)
        );

        // 5. Store result in original event for controller
        $event->setProviderRedirectUrl($providerOrder->getApprovalUrl());
    }
}
```

#### Event Subscribers (Side Effects)
**Location:** `src/EventSubscriber/`

| Subscriber | Listens To | Purpose |
|------------|-----------|---------|
| `EmailNotificationSubscriber` | OrderCompletedEvent | Send order confirmation |
| `InventorySubscriber` | OrderCompletedEvent | Reduce stock |
| `AnalyticsSubscriber` | Multiple | Track conversion events |
| `AuditLogSubscriber` | All payment events | Audit logging |

**Benefits**:
- Multiple subscribers can react to same event
- Easy to add new features without modifying core
- Decoupled from business logic

---

## 2. Service Layer (Refactored)

### NEW Responsibilities (Called by Event Handlers)
- Implement reusable business operations
- NO direct controller access - called by event handlers
- Integrate with external APIs (payment providers)
- Enforce business rules
- Stateless operations

### Components

#### Core Services
**Location:** `src/Service/`

| Service | Purpose | Called By | Reusability |
|---------|---------|-----------|-------------|
| `PaymentService` | Provider API operations | Event Handlers | 90% |
| `OrderManager` | Order lifecycle | Event Handlers | 100% |
| `OrderRepository` | Order data access | Event Handlers | 100% |
| `ModuleSettings` | Configuration | Event Handlers | 100% |
| `OrderProcessTracking` | Process tracking | Event Handlers | 100% |
| `BasketSummary` | Amount calculations | Event Handlers | 100% |
| `UserRepository` | User data access | Event Handlers | 100% |

#### Payment Service - Core Methods

**Order Creation:**
```php
doCreatePayPalOrder(
    basket, intent, userAction, processingInstruction,
    paymentSource, clientMetadataId, partnerAttributionId,
    returnUrl, cancelUrl, setProvidedAddress
): Order

// Creates payment order at provider
// Returns provider order object
```

**Order Updates:**
```php
doPatchPayPalOrder(basket, payPalOrderId, shopOrderId): void

// Updates existing payment order with new basket data
```

**Payment Capture:**
```php
doCapturePayPalOrder(
    order, checkoutOrderId, paymentId, payPalOrder
): Order

// Captures/completes payment
// Handles authorization → capture flow
// Manages 3D Secure verification
// Updates order status
```

**Payment Authorization:**
```php
doAuthorizePayment(
    checkoutOrderId, shopOrderId, paymentId
): array

// Authorizes payment without capturing
// Returns authorization result with status
```

**Transaction Tracking:**
```php
trackPayPalOrder(
    shopOrderId, payPalOrderId, paymentMethodId,
    status, payPalTransactionId, transactionType
): PayPalOrder

// Persists transaction to database
```

### Service Dependencies

```
PaymentService
  ├─ uses: Session (manage state)
  ├─ uses: OrderRepository (data access)
  ├─ uses: ModuleSettings (configuration)
  ├─ uses: SCAValidator (3D Secure)
  ├─ uses: Logger (debugging/errors)
  ├─ uses: ServiceFactory (API clients)
  ├─ uses: OrderRequestFactory (build requests)
  └─ uses: PatchRequestFactory (build patches)
```

### Service Layer Patterns

#### 1. Repository Pattern
```php
interface OrderRepository {
    paypalOrderByOrderIdAndPayPalId(
        shopOrderId, paypalOrderId, transactionId
    ): PayPalOrder

    getShopOrderByPayPalOrderId(paypalOrderId): Order

    cleanUpNotFinishedOrders(): void
}
```

**Benefits:**
- Abstracts data access
- Testable with mocks
- Centralized query logic

#### 2. Configuration Service Pattern
```php
class ModuleSettings {
    isSandbox(): bool
    getClientId(): string
    getClientSecret(): string
    getPayPalStandardCaptureStrategy(): string  // 'directly', 'delivery', 'manually'
    isAcdcEligibility(): bool
    isPuiEligibility(): bool
    // ... 50+ configuration methods
}
```

**Benefits:**
- Centralized configuration
- Environment separation (sandbox/production)
- Type-safe access

#### 3. Factory Service Pattern
```php
class OrderRequestFactory {
    setBasket(basket): self

    getRequest(
        basket, intent, userAction, customId,
        processingInstruction, paymentSource,
        payPalClientMetadataId, returnUrl, cancelUrl,
        setProvidedAddress
    ): OrderRequest
}
```

**Benefits:**
- Separates construction from business logic
- Reusable request building
- Testable independently

---

## 3. Domain Layer (Refactored)

### Responsibilities
- Define business entities and value objects
- Encapsulate business rules
- **Extend core shop models** with payment-specific behavior
- Define domain events
- Maintain payment state machine

### Components

#### Extended Core Models (NEW Pattern)
**Location:** `src/Model/Extended/`

**Philosophy**: Payment component extends existing shop models rather than replacing them

| Extended Model | Extends | New Capabilities | Reusability |
|----------------|---------|------------------|-------------|
| `Order` | Core Shop Order | Payment states, finalization methods | 95% |
| `User` | Core Shop User | Payment customer ID, saved methods | 95% |
| `Basket` | Core Shop Basket | Payment calculations, provider data | 100% |

**Example - Extended Order Model:**
```php
namespace PaymentComponent\Model\Extended;

class Order extends CoreShopOrder {
    // Payment-specific fields (via database extension)
    private ?string $paymentProviderOrderId;
    private ?string $paymentState;

    // Payment-specific methods
    public function markAsPaymentInProgress(): void;
    public function markAsPaymentCompleted(): void;
    public function isAwaitingPayment(): bool;
    public function getPaymentProviderOrderId(): ?string;

    // Override finalization to support payment states
    public function finalizeOrder(): int;
}
```

#### New Domain Models
**Location:** `src/Model/`

| Model | Purpose | Reusability |
|-------|---------|-------------|
| `PaymentTransaction` | Track provider transactions | 100% |
| `PaymentMethod` | Payment method definition | 90% |
| `ProviderOrder` | Provider order value object | 90% |

**Key Model: PaymentTransaction** (formerly PayPalOrder)
```php
class PaymentTransaction {
    private string $shopOrderId;
    private string $providerOrderId;
    private string $transactionId;
    private string $status;
    private string $paymentMethodId;
    private string $transactionType; // capture, authorization, refund

    // State management
    public function markAsCompleted(): void;
    public function markAsRefunded(): void;
}
```

#### Key Model Patterns

**Order State Management:**
```php
class Order extends EshopModelOrder {
    // Payment states
    const ORDER_STATE_SESSIONPAYMENT_INPROGRESS = 500;
    const ORDER_STATE_WAIT_FOR_WEBHOOK_EVENTS = 600;
    const ORDER_STATE_ACDCINPROGRESS = 700;
    const ORDER_STATE_ACDCCOMPLETED = 750;
    const ORDER_STATE_NEED_CALL_ACDC_FINALIZE = 800;
    const ORDER_STATE_TIMEOUT_FOR_WEBHOOK_EVENTS = 900;

    finalizeOrder(): int
    finalizeOrderAfterExternalPayment(basket): int
    markOrderPaid(): void
    setTransId(transactionId): void
    isOrderFinished(): bool
    isWaitForWebhookTimeoutReached(): bool
}
```

**Transaction Tracking Model:**
```php
class PayPalOrder extends EshopCoreModel {
    getPayPalOrderId(): string
    getTransactionId(): string
    getShopOrderId(): string
    getStatus(): string
    getPaymentMethodId(): string

    setStatus(status): void
    setTransactionId(id): void
    setPaymentMethodId(id): void
    setTransactionType(type): void  // 'capture' or 'authorization'
}
```

**Basket Amount Methods:**
```php
class Basket extends EshopModelBasket {
    getPayPalCheckoutWrapping(): float
    getPayPalCheckoutGiftCard(): float
    getPayPalCheckoutPayment(): float
    getPayPalCheckoutDeliveryCosts(): float
    getPayPalCheckoutDiscount(): float
    getPayPalCheckoutItems(): float
    isVirtualPayPalBasket(): bool
    isFractionQuantityItemsPresent(): bool
}
```

#### Events
**Location:** `src/Event/`

```php
class PayPalOrderCompletedEvent extends Event {
    private Order $order;
    private Basket $basket;
    private User $user;
    private string $shopOrderId;
    private string $payPalOrderId;
    private string $paymentsId;
    private string $transactionId;
    private string $payPalCustomerId;

    // Getters...
}

class PayPalVaultingSucceededEvent extends Event {
    private User $user;
    private string $payPalCustomerId;

    // Getters...
}
```

**Pattern:** Domain events represent important business occurrences

---

## 4. Data Access Layer (Enhanced with Caching)

### Responsibilities
- Execute database queries
- Map database rows to domain objects
- **Cache frequently accessed data** (NEW)
- Manage transactions
- Optimize queries

### Components

#### Repositories (Enhanced)
**Location:** `src/Repository/`

**OrderRepository Methods:**
```php
getTransactionByOrderAndProvider(
    shopOrderId, providerOrderId, transactionId
): PaymentTransaction

getOrderByProviderOrderId(providerOrderId): Order

getOrderByTransactionId(transactionId): Order

getProviderOrderIdByShopOrderId(shopOrderId): string

getCurrentOrderId(): string
getCurrentOrder(): Order

cleanUpAbandonedOrders(): void
```

**UserRepository Methods:**
```php
getUserById(userId): User
getUserCountryIso(user): string
getUserStateIso(user): string
```

#### Request Data Cache (NEW)
**Location:** `src/Cache/RequestDataCache.php`

**Purpose**: Cache expensive data fetches within a single HTTP request

```php
class RequestDataCache {
    private array $cache = [];

    // Cache basket for request lifetime
    public function cacheBasket(Basket $basket): void;
    public function getBasket(): ?Basket;

    // Cache user for request lifetime
    public function cacheUser(User $user): void;
    public function getUser(): ?User;

    // Cache session data
    public function cacheSessionData(array $data): void;
    public function getSessionData(): array;

    // Clear cache after request
    public function clear(): void;
}
```

**Usage Pattern (in Controllers):**
```php
class OrderController {
    public function execute() {
        // Fetch once, cache for all event handlers
        $basket = $this->basketRepository->getCurrentBasket();
        $user = $this->userRepository->getCurrentUser();

        $this->requestCache->cacheBasket($basket);
        $this->requestCache->cacheUser($user);

        // Event handlers access cached data
        $event = new PaymentInitiatedEvent($this->requestCache);
        $this->dispatcher->dispatch($event);
    }
}
```

**Benefits**:
- Reduces database queries by 50-70%
- Ensures data consistency across event handlers
- No need to pass objects through multiple layers

### Database Schema

**Transaction Tracking Table:**
```sql
CREATE TABLE oscpaypal_order (
    OXID CHAR(32) PRIMARY KEY,
    OXSHOPID INT NOT NULL,
    OXORDERID CHAR(32) NOT NULL,  -- FK to oxorder
    OXPAYPALORDERID VARCHAR(128),  -- Provider order ID
    OSCPAYPALSTATUS VARCHAR(64),   -- Payment status
    OSCPAYMENTMETHODID VARCHAR(64), -- Payment method
    OSCPAYPALTRANSACTIONID VARCHAR(128), -- Transaction/capture ID
    OSCPAYPALTRACKINGID VARCHAR(255),    -- Shipment tracking
    OSCPAYPALTRACKINGTYPE VARCHAR(64),   -- Carrier
    OSCPAYPALTRANSACTIONTYPE VARCHAR(32), -- 'capture' or 'authorization'
    OXTIMESTAMP TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY (OXORDERID, OXPAYPALORDERID)
);
```

**Pattern:** One shop order can have multiple payment transactions (authorization → capture → refunds)

---

## 5. Infrastructure Layer

### Responsibilities
- Provide low-level services
- Manage external resources
- Handle cross-cutting concerns

### Components

- **Database Connection:** Doctrine DBAL QueryBuilder
- **HTTP Client:** Guzzle / cURL
- **Logger:** PSR-3 Logger (Monolog)
- **Session:** Shop session management
- **Config:** Shop configuration access
- **Event Dispatcher:** PSR-14 or Symfony EventDispatcher

### Service Factory Pattern

```php
class ServiceFactory {
    getOrderService(): ApiOrderService
    getPaymentService(): ApiPaymentService
    getVaultingService(): VaultingService
    // Creates provider API clients
}
```

---

## 6. External Integration Layer

### Responsibilities
- Communicate with payment provider API
- Handle webhook notifications
- Verify signatures
- Map provider responses to domain objects

### Components

#### API Client Integration
**Location:** `src/Core/`, external SDK

- **Order API:** Create, update, capture, authorize orders
- **Payment API:** Capture, refund, reauthorize payments
- **Vaulting API:** Save/retrieve payment methods
- **Identity API:** User information
- **Webhook API:** Register/manage webhooks

#### Webhook Processing
**Location:** `src/Core/Webhook/`

**Components:**
- `Event` - Webhook event representation
- `EventVerifier` - Signature verification
- `EventDispatcher` - Route to handlers
- `EventHandlerMapping` - Event type → handler class
- `RequestHandler` - Process webhook request
- `Handler/WebhookHandlerBase` - Base handler class
- `Handler/*Handler` - Concrete event handlers

---

## Layer Communication Rules

### Allowed Dependencies
```
Presentation → Service → Domain → Data Access → Infrastructure
                      ↓
                   External
```

### Forbidden Dependencies
- Domain layer MUST NOT depend on Service layer
- Service layer MUST NOT depend on Presentation layer
- External systems accessed only through Service layer

### Dependency Injection

All layers use constructor injection:

```php
class PaymentService {
    public function __construct(
        Session $session,
        OrderRepository $orderRepository,
        SCAValidatorInterface $scaValidator,
        ModuleSettings $moduleSettings,
        LoggerInterface $logger,
        OrderProcessTrackingService $trackingService,
        ServiceFactory $serviceFactory,
        PatchRequestFactory $patchFactory,
        OrderRequestFactory $requestFactory
    ) {
        // Store dependencies
    }
}
```

**Benefits:**
- Testability (inject mocks)
- Flexibility (swap implementations)
- Explicit dependencies (no hidden coupling)

---

## Cross-Cutting Concerns

### Logging
```php
$this->logger->log('debug', 'Payment order created', [
    'orderId' => $orderId,
    'amount' => $amount
]);
```

**Pattern:** PSR-3 Logger injected into services

### Error Handling
```php
try {
    $response = $orderService->createOrder($request);
} catch (ApiException $e) {
    $this->handlePayPalApiError($e);
    $this->setPaymentExecutionError(self::PAYMENT_ERROR_GENERIC);
}
```

**Pattern:** Catch provider-specific exceptions, convert to domain errors

### Configuration
```php
$captureStrategy = $this->moduleSettings->getPayPalStandardCaptureStrategy();
$isSandbox = $this->moduleSettings->isSandbox();
```

**Pattern:** Centralized configuration service

### Session Management
```php
$sessionOrderId = $this->session->getVariable('sess_challenge');
PayPalSession::storePayPalOrderId($payPalOrderId);
```

**Pattern:** Wrapper around shop session

---

## Summary: Layer Reusability

| Layer | Reusability | Notes |
|-------|-------------|-------|
| Presentation | 70-90% | Controller patterns reusable, templates vary |
| Service | 90-100% | Core business logic is generic |
| Domain | 90-100% | State machine and patterns reusable |
| Data Access | 100% | Fully generic repositories |
| Infrastructure | 100% | Standard interfaces (PSR) |
| External | 30-50% | Provider-specific, but patterns apply |

---

**Continue to:** [02-data-models.md](02-data-models.md)
