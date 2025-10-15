# Event-Driven Capture & Refund Operations

**Version:** 2.0.0
**Last Updated:** 2025-10-09
**Status:** Architecture Documentation
**Visual Diagram:** [puml/09-capture-refund-operations.puml](puml/09-capture-refund-operations.puml)

---

**📊 See Visual Diagram:** [puml/09-capture-refund-operations.puml](puml/09-capture-refund-operations.puml) showing all four trigger channels (Webhook, Backend, API, MCP) converging on the same event-driven backend.

---

## Table of Contents

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Trigger Channels](#trigger-channels)
4. [Capture Operations](#capture-operations)
5. [Refund Operations](#refund-operations)
6. [Event Flow](#event-flow)
7. [Implementation Examples](#implementation-examples)
8. [Security & Idempotency](#security--idempotency)

---

## Overview

The Payment Component provides **event-driven capture and refund operations** that can be triggered through multiple channels:

- **Webhook-triggered** - Provider notifies of capture/refund
- **Backend-triggered** - Admin manually initiates operation
- **Programmatic** - API call via GraphQL
- **Agentic** - AI agent via MCP protocol

All channels converge on the same **event-driven backend**, ensuring:
- ✅ Consistent business logic
- ✅ Idempotent operations
- ✅ Full audit trail
- ✅ Multi-provider support

---

## Architecture

### Key Principle: Events, Not Direct Actions

```
❌ OLD (Controller-Driven):
Admin clicks "Capture" → Controller → Call Stripe API → Update DB

✅ NEW (Event-Driven):
Admin clicks "Capture" → Controller emits CaptureRequestedEvent → Handler calls provider API → Emits PaymentCapturedEvent → Multiple subscribers update DB/Email/Logs
```

### Benefits:

1. **Decoupled** - Controller doesn't know about Stripe/Paymenter/Adyen
2. **Testable** - Can test handlers without HTTP requests
3. **Extensible** - Add new subscribers without changing existing code
4. **Auditable** - Every operation is an event with full context
5. **Idempotent** - Events carry enough data to prevent duplicate operations

---

## Trigger Channels

### 1. Webhook-Triggered (Most Common)

**Use Case:** Provider processes payment and notifies via webhook

```
Stripe Webhook → WebhookController → PaymentCapturedEvent → Handlers update order
```

**Example Webhook Events:**
- Stripe: `payment_intent.succeeded`, `charge.captured`, `charge.refunded`
- Paymenter: `PAYMENT.CAPTURE.COMPLETED`, `PAYMENT.CAPTURE.REFUNDED`
- Adyen: `CAPTURE`, `REFUND`

**Flow:**
1. Provider sends webhook to `/webhook/stripe`
2. `WebhookController` validates signature
3. `StripeWebhookHandler` parses payload
4. Handler emits `PaymentCapturedEvent` or `RefundCompletedEvent`
5. Subscribers update order, send emails, log events

---

### 2. Backend-Triggered (Manual)

**Use Case:** Admin manually captures an authorized payment or initiates refund

```
Admin clicks "Capture" → GraphQL Mutation → CaptureRequestedEvent → Handler calls provider API
```

**Admin Actions:**
- Capture an authorized payment
- Partially capture an authorization
- Refund a completed payment
- Partially refund a payment

**Flow:**
1. Admin clicks "Capture" button in order details
2. Frontend sends GraphQL mutation: `capturePayment(orderId: "123")`
3. GraphQL resolver validates permissions
4. Resolver emits `CaptureRequestedEvent`
5. `PaymentCaptureHandler` calls provider API
6. Handler emits `PaymentCapturedEvent` on success
7. Subscribers update UI, order status, send notifications

---

### 3. Programmatic (GraphQL API)

**Use Case:** Third-party system or mobile app triggers capture/refund

```graphql
mutation {
  capturePayment(input: {
    orderId: "ORD-12345"
    amount: 99.99
    reason: "Goods shipped"
  }) {
    success
    captureId
    status
  }
}
```

**Flow:**
1. API client sends GraphQL mutation with JWT token
2. GraphQL resolver validates authentication & authorization
3. Resolver emits `CaptureRequestedEvent`
4. Handler processes capture at provider
5. Returns result to API client

**Use Cases:**
- ERP system triggers capture when order ships
- Mobile app allows customer-initiated partial refunds
- Partner integration captures after fulfillment

---

### 4. Agentic (MCP Protocol)

**Use Case:** AI agent autonomously processes captures/refunds based on business rules

```json
POST /mcp/tools
{
  "tool": "capture_payment",
  "parameters": {
    "order_id": "ORD-12345",
    "amount": 99.99,
    "reason": "Automatic capture after 3 days"
  }
}
```

**Flow:**
1. AI agent monitors orders in "AUTHORIZED" state
2. Agent decides to capture based on business rules
3. Agent calls MCP tool `capture_payment`
4. MCP endpoint emits `CaptureRequestedEvent`
5. Handler processes capture

**Agentic Use Cases:**
- Auto-capture after shipping label created
- Auto-refund if return received within window
- Smart partial captures based on inventory availability
- Fraud-detection triggered refunds

---

## Capture Operations

### Authorization vs Capture

**Authorization:** Reserve funds (payment_intent.authorize)
- Money is held but not transferred
- Can be captured later (usually within 7 days)
- Can be partially captured

**Capture:** Transfer the funds
- Money moves from customer to merchant
- Can be full or partial
- Cannot be reversed (only refunded)

---

### Event Flow: Capture

```
1. Trigger (any channel)
   ↓
2. CaptureRequestedEvent
   - order_id
   - amount (optional for partial)
   - reason
   - triggered_by (webhook/admin/api/mcp)
   ↓
3. PaymentCaptureHandler
   - Load order from DB
   - Validate order state (must be AUTHORIZED)
   - Call provider API (Stripe/Paymenter/Adyen)
   - Update osc_transaction table
   ↓
4. Provider processes capture
   ↓
5. PaymentCapturedEvent (success)
   - order_id
   - capture_id
   - amount_captured
   - provider_data
   ↓
6. Subscribers react:
   - OrderStatusSubscriber → Update order to COMPLETED
   - EmailSubscriber → Send "Payment received" email
   - LogSubscriber → Audit log
   - InventorySubscriber → Release inventory
   - AccountingSubscriber → Create accounting entry
```

---

### Capture Implementation Example

```php
class PaymentCaptureHandler
{
    public function handle(CaptureRequestedEvent $event): void
    {
        // 1. Load order and validate
        $order = $this->orderRepository->getById($event->getOrderId());

        if (!$order->isAwaitingCapture()) {
            throw new InvalidStateException('Order not in AUTHORIZED state');
        }

        // 2. Check idempotency (prevent duplicate captures)
        if ($this->wasAlreadyCaptured($order, $event->getIdempotencyKey())) {
            return; // Already captured, skip
        }

        // 3. Call provider API
        $providerOrderId = $order->getProviderOrderId();
        $amount = $event->getAmount() ?? $order->getTotalAmount();

        $captureResult = $this->paymentService->capturePayment(
            $providerOrderId,
            $amount
        );

        // 4. Track transaction
        $this->paymentService->trackTransaction(
            $order->getId(),
            $order->getProviderName(), // 'stripe', 'paymenter', etc.
            $captureResult->getCaptureId(),
            'CAPTURED',
            'capture',
            [
                'amount' => $amount,
                'triggered_by' => $event->getTriggeredBy(),
                'reason' => $event->getReason(),
            ]
        );

        // 5. Emit success event
        $this->dispatcher->dispatch(
            new PaymentCapturedEvent(
                $order->getId(),
                $captureResult->getCaptureId(),
                $amount,
                $event->getTriggeredBy(),
                $captureResult->getProviderData()
            )
        );
    }
}
```

---

## Refund Operations

### Full vs Partial Refund

**Full Refund:** Return entire payment amount
**Partial Refund:** Return portion of payment (e.g., one item from multi-item order)

---

### Event Flow: Refund

```
1. Trigger (any channel)
   ↓
2. RefundRequestedEvent
   - order_id
   - amount (required)
   - reason
   - triggered_by (webhook/admin/api/mcp)
   ↓
3. PaymentRefundHandler
   - Load order from DB
   - Validate order state (must be COMPLETED or CAPTURED)
   - Validate amount ≤ captured amount
   - Call provider API (Stripe/Paymenter/Adyen)
   - Update osc_transaction table
   ↓
4. Provider processes refund
   ↓
5. RefundCompletedEvent (success)
   - order_id
   - refund_id
   - amount_refunded
   - provider_data
   ↓
6. Subscribers react:
   - OrderStatusSubscriber → Update order to REFUNDED (full) or PARTIALLY_REFUNDED
   - EmailSubscriber → Send "Refund processed" email
   - LogSubscriber → Audit log
   - InventorySubscriber → Restore inventory
   - AccountingSubscriber → Create credit memo
```

---

### Refund Implementation Example

```php
class PaymentRefundHandler
{
    public function handle(RefundRequestedEvent $event): void
    {
        // 1. Load order and validate
        $order = $this->orderRepository->getById($event->getOrderId());

        if (!$order->canBeRefunded()) {
            throw new InvalidStateException('Order cannot be refunded');
        }

        // 2. Validate amount
        $requestedAmount = $event->getAmount();
        $maxRefundable = $order->getRefundableAmount();

        if ($requestedAmount > $maxRefundable) {
            throw new InvalidAmountException(
                "Cannot refund {$requestedAmount}. Max refundable: {$maxRefundable}"
            );
        }

        // 3. Check idempotency
        if ($this->wasAlreadyRefunded($order, $event->getIdempotencyKey())) {
            return; // Already refunded, skip
        }

        // 4. Call provider API
        $providerTransactionId = $order->getProviderTransactionId();

        $refundResult = $this->paymentService->refundPayment(
            $providerTransactionId,
            $requestedAmount,
            $event->getReason()
        );

        // 5. Track transaction
        $this->paymentService->trackTransaction(
            $order->getId(),
            $order->getProviderName(),
            $refundResult->getRefundId(),
            'REFUNDED',
            'refund',
            [
                'amount' => $requestedAmount,
                'triggered_by' => $event->getTriggeredBy(),
                'reason' => $event->getReason(),
            ]
        );

        // 6. Emit success event
        $this->dispatcher->dispatch(
            new RefundCompletedEvent(
                $order->getId(),
                $refundResult->getRefundId(),
                $requestedAmount,
                $event->getTriggeredBy(),
                $refundResult->getProviderData()
            )
        );
    }
}
```

---

## Event Flow Diagram

### All Channels → Same Backend

```
┌─────────────────────────────────────────────────────────┐
│               TRIGGER CHANNELS                          │
├─────────────────────────────────────────────────────────┤
│                                                          │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌─────────┐│
│  │ Webhook  │  │ Backend  │  │   API    │  │   MCP   ││
│  │ (Stripe) │  │ (Admin)  │  │(GraphQL) │  │(AI Bot) ││
│  └─────┬────┘  └─────┬────┘  └─────┬────┘  └────┬────┘│
│        │             │              │             │     │
└────────┼─────────────┼──────────────┼─────────────┼─────┘
         │             │              │             │
         └─────────────┴──────────────┴─────────────┘
                           │
         ┌─────────────────▼─────────────────┐
         │    EVENT DISPATCHER (PSR-14)      │
         └─────────────────┬─────────────────┘
                           │
         ┌─────────────────▼─────────────────┐
         │   CaptureRequestedEvent           │
         │   RefundRequestedEvent            │
         └─────────────────┬─────────────────┘
                           │
         ┌─────────────────▼─────────────────┐
         │   PAYMENT HANDLERS                │
         │   - PaymentCaptureHandler         │
         │   - PaymentRefundHandler          │
         └─────────────────┬─────────────────┘
                           │
                  ┌────────┴────────┐
                  │                 │
         ┌────────▼────────┐ ┌─────▼──────┐
         │ Call Provider   │ │  Update    │
         │ API             │ │  Database  │
         │ (Stripe/Paymenter) │ │  (osc_*)   │
         └────────┬────────┘ └─────┬──────┘
                  │                │
                  └────────┬───────┘
                           │
         ┌─────────────────▼─────────────────┐
         │   PaymentCapturedEvent            │
         │   RefundCompletedEvent            │
         └─────────────────┬─────────────────┘
                           │
         ┌─────────────────▼─────────────────┐
         │   SUBSCRIBERS (Multiple)          │
         │   - OrderStatusSubscriber         │
         │   - EmailSubscriber               │
         │   - LogSubscriber                 │
         │   - InventorySubscriber           │
         │   - AccountingSubscriber          │
         └───────────────────────────────────┘
```

---

## GraphQL Schema

### Capture Operations

```graphql
type Mutation {
  """
  Capture an authorized payment (full or partial)
  Triggers: CaptureRequestedEvent
  """
  capturePayment(input: CapturePaymentInput!): CapturePaymentResult!

  """
  Refund a captured payment (full or partial)
  Triggers: RefundRequestedEvent
  """
  refundPayment(input: RefundPaymentInput!): RefundPaymentResult!
}

input CapturePaymentInput {
  """Order ID to capture"""
  orderId: ID!

  """Amount to capture (optional, defaults to full authorized amount)"""
  amount: Float

  """Reason for capture"""
  reason: String

  """Idempotency key to prevent duplicate captures"""
  idempotencyKey: String!
}

type CapturePaymentResult {
  """Was the capture successful?"""
  success: Boolean!

  """Provider's capture ID"""
  captureId: String

  """Amount captured"""
  amountCaptured: Float

  """Current order status after capture"""
  orderStatus: OrderStatus!

  """Error message if failed"""
  errorMessage: String
}

input RefundPaymentInput {
  """Order ID to refund"""
  orderId: ID!

  """Amount to refund (required)"""
  amount: Float!

  """Reason for refund"""
  reason: String!

  """Idempotency key to prevent duplicate refunds"""
  idempotencyKey: String!
}

type RefundPaymentResult {
  """Was the refund successful?"""
  success: Boolean!

  """Provider's refund ID"""
  refundId: String

  """Amount refunded"""
  amountRefunded: Float

  """Current order status after refund"""
  orderStatus: OrderStatus!

  """Error message if failed"""
  errorMessage: String
}

enum OrderStatus {
  AUTHORIZED
  CAPTURED
  COMPLETED
  PARTIALLY_REFUNDED
  REFUNDED
  FAILED
}
```

---

## MCP Protocol Schema

### Capture Tool

```json
{
  "name": "capture_payment",
  "description": "Capture an authorized payment",
  "inputSchema": {
    "type": "object",
    "properties": {
      "order_id": {
        "type": "string",
        "description": "Order ID to capture"
      },
      "amount": {
        "type": "number",
        "description": "Amount to capture (optional, defaults to full authorized amount)"
      },
      "reason": {
        "type": "string",
        "description": "Reason for capture"
      },
      "idempotency_key": {
        "type": "string",
        "description": "Unique key to prevent duplicate operations"
      }
    },
    "required": ["order_id", "idempotency_key"]
  }
}
```

### Refund Tool

```json
{
  "name": "refund_payment",
  "description": "Refund a captured payment",
  "inputSchema": {
    "type": "object",
    "properties": {
      "order_id": {
        "type": "string",
        "description": "Order ID to refund"
      },
      "amount": {
        "type": "number",
        "description": "Amount to refund (required)"
      },
      "reason": {
        "type": "string",
        "description": "Reason for refund"
      },
      "idempotency_key": {
        "type": "string",
        "description": "Unique key to prevent duplicate operations"
      }
    },
    "required": ["order_id", "amount", "reason", "idempotency_key"]
  }
}
```

---

## Security & Idempotency

### Idempotency Keys

**Problem:** Network failures can cause duplicate requests

**Solution:** Idempotency keys ensure operations execute exactly once

```php
// GraphQL mutation with idempotency key
mutation {
  capturePayment(input: {
    orderId: "ORD-123"
    amount: 99.99
    idempotencyKey: "capture-ORD-123-20251009-143022"
  }) {
    success
    captureId
  }
}
```

**Implementation:**

```php
private function wasAlreadyCaptured(Order $order, string $idempotencyKey): bool
{
    // Check if this idempotency key was already processed
    return $this->transactionRepository->existsByIdempotencyKey(
        $order->getId(),
        $idempotencyKey,
        'capture'
    );
}
```

**Database:**

```sql
CREATE TABLE osc_transaction (
  ...
  idempotency_key VARCHAR(255),
  INDEX idx_idempotency (order_id, idempotency_key, transaction_type)
);
```

---

### Authorization & Permissions

**Webhook:** Validated via signature verification (HMAC-SHA256)

```php
class WebhookController
{
    public function handle(Request $request): Response
    {
        // 1. Verify signature
        if (!$this->verifySignature($request)) {
            return new Response('Invalid signature', 401);
        }

        // 2. Process webhook
        $this->dispatcher->dispatch(new WebhookReceivedEvent($request));
    }
}
```

**Backend Admin:** Checked via user permissions

```php
class CapturePaymentResolver
{
    public function resolve(array $args, Context $context): array
    {
        // 1. Check authentication
        $user = $context->getUser();
        if (!$user) {
            throw new AuthenticationException('Not authenticated');
        }

        // 2. Check authorization
        if (!$user->hasPermission('CAPTURE_PAYMENT')) {
            throw new AuthorizationException('Permission denied');
        }

        // 3. Emit event
        $this->dispatcher->dispatch(new CaptureRequestedEvent(...));
    }
}
```

**API/MCP:** Validated via JWT token

```php
class ApiAuthMiddleware
{
    public function authenticate(Request $request): User
    {
        $token = $request->getHeader('Authorization');

        if (!$token) {
            throw new AuthenticationException('Missing token');
        }

        $user = $this->jwtValidator->validate($token);

        if (!$user->hasApiAccess()) {
            throw new AuthorizationException('API access denied');
        }

        return $user;
    }
}
```

---

## Configuration Example

```yaml
# config/payment-component.yaml

payment_component:

  # Capture settings
  capture:
    # Auto-capture authorized payments after N hours
    auto_capture_after_hours: 72

    # Allow partial captures
    allow_partial_capture: true

    # Require admin approval for captures > threshold
    approval_threshold: 1000.00

  # Refund settings
  refund:
    # Allow refunds within N days of capture
    refund_window_days: 90

    # Allow partial refunds
    allow_partial_refund: true

    # Require reason for refunds > threshold
    reason_required_threshold: 100.00

  # Webhook settings
  webhook:
    # Retry failed webhook processing
    retry_failed: true
    retry_attempts: 3
    retry_delay_seconds: 60

  # MCP settings
  mcp:
    enabled: true
    tools:
      - capture_payment
      - refund_payment
      - check_capture_status
      - check_refund_status
```

---

## Benefits of Event-Driven Approach

### 1. **Multi-Channel Support**
- One backend serves webhook, admin, API, MCP
- No code duplication
- Consistent behavior across channels

### 2. **Extensibility**
- Add new subscribers without changing existing code
- Add new providers without changing core logic
- Add new channels (e.g., CLI, cron jobs) easily

### 3. **Testability**
- Test handlers without HTTP requests
- Mock event dispatcher for unit tests
- Test subscribers independently

### 4. **Auditability**
- Every operation is an event
- Full context captured in event data
- Easy to reconstruct what happened and why

### 5. **Idempotency**
- Events carry idempotency keys
- Prevent duplicate operations
- Safe retries on network failures

### 6. **Observability**
- Log all events for debugging
- Monitor event processing times
- Alert on failed handlers

---

## Summary

The Payment Component's **event-driven capture and refund operations** provide:

✅ **Multi-channel triggers:** Webhook, Backend, API, MCP
✅ **Unified backend:** Same business logic for all channels
✅ **Provider-agnostic:** Works with Stripe, Paymenter, Adyen, etc.
✅ **Idempotent:** Safe retries, no duplicate operations
✅ **Secure:** Signature verification, JWT auth, permission checks
✅ **Auditable:** Full event trail with context
✅ **Extensible:** Add new channels/subscribers easily

**Next:** See [08-onepage-headless-checkout.puml](puml/08-onepage-headless-checkout.puml) for visual architecture diagram.

---

**Version:** 2.0.0
**Author:** Payment Component Team
**License:** MIT
