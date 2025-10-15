# TDD Strategy for Event-Driven Payment Component

**Version:** 2.0.0
**Date:** 2025-10-13
**Status:** Implementation Guide
**Visual Diagram:** [puml/10-tdd-strategy.puml](puml/10-tdd-strategy.puml)

---

## Table of Contents

1. [🔴 Critical Priority Blocks](#-critical-priority-blocks)
2. [Development Priority Matrix](#development-priority-matrix)
3. [Overview](#overview)
4. [Test Pyramid Strategy](#test-pyramid-strategy)
5. [Unit Tests (60%)](#unit-tests-60)
6. [Integration Tests (30%)](#integration-tests-30)
7. [E2E Tests (10%)](#e2e-tests-10)
8. [Test Data & Fixtures](#test-data--fixtures)
9. [Mocking Strategy](#mocking-strategy)
10. [Coverage Goals](#coverage-goals)
11. [CI/CD Pipeline](#cicd-pipeline)
12. [Best Practices](#best-practices)

---

## 🔴 Critical Priority Blocks

### Priority Classification

**🔴 CRITICAL (P0)** - Must implement FIRST. Security, money handling, data integrity
**🟠 HIGH (P1)** - Core business logic. Required for system functionality
**🟡 MEDIUM (P2)** - Important features. Enhance reliability and user experience
**🟢 LOW (P3)** - Nice to have. Can be implemented later

---

## Development Priority Matrix

### Block 1: Payment Security & Money Handling 🔴 CRITICAL (P0)

**Why Critical:** Direct impact on financial transactions, PCI compliance, fraud prevention

#### 1.1 Transaction Integrity (P0-A)
- **Coverage Required:** 100%
- **Test Types:** Unit + Integration + E2E
- **Components:**
  - `PaymentTransaction` model - Track all money movements
  - `PaymentService::trackTransaction()` - Persist transactions atomically
  - `PaymentService::capturePayment()` - Ensure money capture
  - `PaymentService::refundPayment()` - Handle refunds correctly
  - Amount calculations and currency conversions

**Critical Test Scenarios:**
```php
// tests/Unit/Service/PaymentService_Transaction_CRITICAL_Test.php

✅ testTransactionAtomicity_NoPartialCaptures()
✅ testAmountPrecision_NoCentsLost()
✅ testDoubleCaptureViaIdempotencyKey_OnlyOneCharge()
✅ testRefundExceedsCapturedAmount_MustFail()
✅ testConcurrentCaptures_OnlyOneSucceeds()
✅ testTransactionRollback_OnProviderFailure()
✅ testCurrencyConversion_NoRoundingErrors()
```

**Implementation Order:**
1. Write tests for double-capture prevention (idempotency)
2. Implement transaction tracking with database constraints
3. Test atomic transaction rollback on errors
4. Implement amount validation (refunds ≤ captured amount)
5. Test concurrent access scenarios
6. Implement currency precision (no rounding errors)

---

#### 1.2 Idempotency System (P0-B)
- **Coverage Required:** 100%
- **Test Types:** Unit + Integration + E2E
- **Components:**
  - Idempotency key validation
  - Duplicate request detection
  - State consistency checks

**Critical Test Scenarios:**
```php
✅ testSameIdempotencyKey_ReturnsCachedResult()
✅ testNetworkRetry_NoDoubleCharge()
✅ testWebhookRedelivery_ProcessedOnce()
✅ testIdempotencyKeyExpiration_After24Hours()
✅ testConcurrentRequestsSameKey_OnlyOneProcessed()
```

**Implementation Order:**
1. Add `idempotency_key` column to `osc_transaction` table
2. Write tests for duplicate detection
3. Implement unique constraint on (order_id, idempotency_key, transaction_type)
4. Test webhook redelivery scenarios
5. Implement idempotency key expiration (24-48 hours)

---

#### 1.3 Order State Machine (P0-C)
- **Coverage Required:** 100%
- **Test Types:** Unit + Integration
- **Components:**
  - Order state transitions
  - State validation
  - Prevent invalid state jumps

**Critical Test Scenarios:**
```php
✅ testCannotCaptureUnauthorizedOrder_ThrowsException()
✅ testCannotRefundUncapturedOrder_ThrowsException()
✅ testStateTransitionValidation_EnforcesSequence()
✅ testConcurrentStateChanges_LastWriteWins()
✅ testOrderFinalization_ImmutableAfterComplete()
```

**Implementation Order:**
1. Define all valid state transitions
2. Write tests for invalid state transitions (must throw exceptions)
3. Implement state machine with validation
4. Test concurrent state changes
5. Implement order immutability after completion

---

#### 1.4 Webhook Signature Verification (P0-D)
- **Coverage Required:** 100%
- **Test Types:** Unit + Integration
- **Components:**
  - Signature verification
  - Replay attack prevention
  - Timestamp validation

**Critical Test Scenarios:**
```php
✅ testInvalidSignature_RejectsWebhook()
✅ testExpiredTimestamp_RejectsWebhook()
✅ testReplayAttack_DetectsAndRejects()
✅ testMalformedPayload_RejectsWebhook()
✅ testSignatureAlgorithmMismatch_RejectsWebhook()
```

**Implementation Order:**
1. Write tests for signature verification (HMAC-SHA256)
2. Implement signature validation
3. Test timestamp expiration (reject webhooks > 5 minutes old)
4. Implement replay attack detection (store webhook IDs)
5. Test malformed payload handling

---

### Block 2: Data Persistence & Integrity 🔴 CRITICAL (P0)

#### 2.1 Repository Layer (P0-E)
- **Coverage Required:** 100%
- **Test Types:** Unit + Integration
- **Components:**
  - `OrderRepository` - CRUD operations
  - Transaction queries
  - Database constraints
  - Data consistency

**Critical Test Scenarios:**
```php
✅ testSaveOrder_EnforcesRequiredFields()
✅ testGetTransactionsByOrderId_ReturnsChronological()
✅ testConcurrentOrderUpdate_VersionControl()
✅ testOrphanedTransaction_ForeignKeyPrevents()
✅ testDatabaseConstraints_EnforcedAtDBLevel()
```

**Implementation Order:**
1. Create database schema with constraints
2. Write tests for required fields validation
3. Implement repository with proper error handling
4. Test foreign key constraints
5. Test concurrent access with pessimistic locking
6. Implement cleanup of abandoned orders

---

#### 2.2 Transaction History & Audit Trail (P0-F)
- **Coverage Required:** 100%
- **Test Types:** Integration
- **Components:**
  - All transaction types (auth, capture, refund)
  - Immutable audit log
  - Reconciliation support

**Critical Test Scenarios:**
```php
✅ testTransactionHistory_ImmutableAfterCreation()
✅ testMultipleTransactionsPerOrder_AllTracked()
✅ testRefundLinksToOriginalCapture_AuditTrail()
✅ testTransactionTimestamps_AccurateToMillisecond()
```

**Implementation Order:**
1. Design transaction table schema (immutable records)
2. Write tests for transaction types (auth, capture, refund)
3. Implement transaction creation (insert-only, no updates)
4. Test transaction linking (refund → capture → authorization)
5. Implement reconciliation queries

---

### Block 3: Event System & Business Logic 🟠 HIGH (P1)

#### 3.1 Event Layer (P1-A)
- **Coverage Required:** 100%
- **Test Types:** Unit + Integration
- **Components:**
  - Event creation and dispatching
  - EventContext caching
  - Event immutability

**Test Scenarios:**
```php
✅ testEventImmutable_CannotModifyAfterCreation()
✅ testEventContext_CachesDataCorrectly()
✅ testEventDispatcher_InvokesAllSubscribers()
✅ testEventHandlerException_DoesNotAffectOtherSubscribers()
```

**Implementation Order:**
1. Define all domain events
2. Implement EventContext for request caching
3. Test event immutability
4. Implement event dispatcher integration
5. Test subscriber invocation order

---

#### 3.2 Event Handlers (P1-B)
- **Coverage Required:** 95%
- **Test Types:** Unit + Integration
- **Components:**
  - `PaymentCaptureHandler`
  - `PaymentRefundHandler`
  - `WebhookProcessingHandler`

**Test Scenarios:**
```php
✅ testCaptureHandler_ValidatesOrderState()
✅ testCaptureHandler_CallsProviderAPI()
✅ testCaptureHandler_EmitsSuccessEvent()
✅ testCaptureHandler_HandlesProviderErrors()
✅ testRefundHandler_ValidatesRefundableAmount()
```

**Implementation Order:**
1. Implement handler base class with common logic
2. Write tests for state validation
3. Implement payment capture handler
4. Write tests for error handling
5. Implement payment refund handler
6. Test event flow (event → handler → service → repository)

---

#### 3.3 Domain Layer (P1-C)
- **Coverage Required:** 95%
- **Test Types:** Unit + Integration
- **Components:**
  - `Order` model with payment methods
  - `PaymentTransaction` model
  - `Basket` amount calculations

**Test Scenarios:**
```php
✅ testOrderStateTransitions_ValidSequence()
✅ testRefundableAmount_CorrectCalculation()
✅ testBasketAmountCalculations_NoPrecisionLoss()
✅ testOrderFinalization_SetsAllRequiredFields()
```

**Implementation Order:**
1. Implement Order model extensions
2. Write tests for state transitions
3. Implement PaymentTransaction model
4. Test amount calculations (no rounding errors)
5. Implement Basket extensions

---

### Block 4: Service Layer 🟠 HIGH (P1)

#### 4.1 Payment Service (P1-D)
- **Coverage Required:** 90%
- **Test Types:** Unit + Integration
- **Components:**
  - Payment orchestration
  - Provider API calls
  - Error mapping

**Test Scenarios:**
```php
✅ testCreatePaymentOrder_CallsProviderAPI()
✅ testCapturePayment_UpdatesOrderState()
✅ testProviderError_MapsToComponentException()
✅ testRetryLogic_HandlesTransientFailures()
```

**Implementation Order:**
1. Define service interface
2. Write tests for API calls
3. Implement provider API client wrapper
4. Test error handling and mapping
5. Implement retry logic for transient failures

---

#### 4.2 Module Settings & Configuration (P1-E)
- **Coverage Required:** 90%
- **Test Types:** Unit
- **Components:**
  - Configuration validation
  - Environment-specific settings
  - Credential management

**Test Scenarios:**
```php
✅ testMissingCredentials_ThrowsConfigurationException()
✅ testSandboxMode_UsesTestEndpoints()
✅ testCaptureStrategy_ValidatesAllowedValues()
```

**Implementation Order:**
1. Define configuration schema
2. Write tests for required fields
3. Implement configuration validation
4. Test environment separation (sandbox/production)

---

### Block 5: Provider Integration 🟡 MEDIUM (P2)

#### 5.1 Request Factories (P2-A)
- **Coverage Required:** 85%
- **Test Types:** Unit
- **Components:**
  - Request builders
  - Response parsers
  - Data transformation

**Test Scenarios:**
```php
✅ testBuildRequest_CorrectFormat()
✅ testAmountConversion_ToCents()
✅ testParseResponse_HandlesAllFields()
```

**Implementation Order:**
1. Define request/response interfaces
2. Write tests for request building
3. Implement factory pattern
4. Test response parsing

---

#### 5.2 Error Mapping (P2-B)
- **Coverage Required:** 85%
- **Test Types:** Unit
- **Components:**
  - Provider error to component error mapping
  - User-friendly error messages

**Test Scenarios:**
```php
✅ testCardDeclined_MapsToPaymentDeclined()
✅ testInsufficientFunds_MapsToPaymentDeclined()
✅ testInvalidCard_MapsToInvalidPaymentMethod()
```

---

### Block 6: API Layer & Controllers 🟡 MEDIUM (P2)

#### 6.1 Controllers (P2-C)
- **Coverage Required:** 80%
- **Test Types:** Unit + E2E
- **Components:**
  - Input validation
  - Event emission
  - Response formatting

**Test Scenarios:**
```php
✅ testInvalidInput_Returns400()
✅ testValidInput_EmitsEvent()
✅ testAuthenticationFailure_Returns401()
```

---

### Block 7: User Interface & Experience 🟢 LOW (P3)

#### 7.1 E2E Checkout Flows (P3-A)
- **Coverage Required:** 80%
- **Test Types:** E2E
- **Components:**
  - Complete checkout flow
  - Payment method selection
  - Order confirmation

---

## Implementation Roadmap

### Phase 1: Foundation (Week 1-2) 🔴 CRITICAL
**Focus: Security & Money Handling**

```
Week 1:
□ Day 1-2: Transaction tracking & idempotency (P0-A, P0-B)
  - Implement database schema with constraints
  - Write tests for double-capture prevention
  - Implement idempotency key validation

□ Day 3-4: Order state machine (P0-C)
  - Define all valid state transitions
  - Write tests for invalid transitions
  - Implement state validation

□ Day 5: Webhook signature verification (P0-D)
  - Implement HMAC-SHA256 verification
  - Write tests for replay attacks
  - Test timestamp validation

Week 2:
□ Day 1-3: Repository layer (P0-E)
  - Complete OrderRepository implementation
  - Write integration tests with real database
  - Test concurrent access scenarios

□ Day 4-5: Transaction audit trail (P0-F)
  - Implement immutable transaction logging
  - Write reconciliation queries
  - Test transaction linking
```

**Exit Criteria:**
- ✅ All P0 tests passing
- ✅ 100% coverage on critical components
- ✅ No security vulnerabilities in code scan
- ✅ Manual security review completed

---

### Phase 2: Business Logic (Week 3-4) 🟠 HIGH
**Focus: Event System & Services**

```
Week 3:
□ Event layer implementation (P1-A)
□ Event handlers (P1-B)
□ Domain models (P1-C)

Week 4:
□ Payment service (P1-D)
□ Configuration management (P1-E)
□ Integration tests for event flows
```

**Exit Criteria:**
- ✅ All P1 tests passing
- ✅ 90%+ coverage on service layer
- ✅ Integration tests passing with real database

---

### Phase 3: Provider Integration (Week 5) 🟡 MEDIUM
**Focus: External APIs**

```
Week 5:
□ Request factories (P2-A)
□ Error mapping (P2-B)
□ Provider-specific implementations
```

**Exit Criteria:**
- ✅ All P2 tests passing
- ✅ Provider sandbox tests successful

---

### Phase 4: API & UI (Week 6) 🟡-🟢 MEDIUM-LOW
**Focus: User-facing features**

```
Week 6:
□ Controllers (P2-C)
□ E2E checkout flows (P3-A)
□ Performance optimization
```

**Exit Criteria:**
- ✅ All tests passing
- ✅ 85%+ overall coverage
- ✅ E2E tests successful

---

## Security-First Testing Checklist

### Before Any Code Goes to Production

#### Financial Security ✅
- [ ] Double-capture prevention tested with idempotency keys
- [ ] Amount validation (no negative amounts, refunds ≤ captures)
- [ ] Currency precision tested (no rounding errors)
- [ ] Concurrent transaction handling tested
- [ ] Transaction rollback on errors tested

#### Authentication & Authorization ✅
- [ ] Webhook signature verification tested
- [ ] Replay attack prevention tested
- [ ] Timestamp validation tested (reject old webhooks)
- [ ] API authentication tested
- [ ] Admin permission checks tested

#### Data Integrity ✅
- [ ] Database constraints tested (foreign keys, unique constraints)
- [ ] Transaction history immutability tested
- [ ] Audit trail completeness tested
- [ ] Concurrent access scenarios tested
- [ ] Data consistency across tables tested

#### Error Handling ✅
- [ ] All error paths tested
- [ ] No sensitive data in error messages
- [ ] Provider errors mapped correctly
- [ ] Graceful degradation tested
- [ ] Circuit breaker tested (if implemented)

#### Compliance ✅
- [ ] PCI-DSS requirements validated
- [ ] GDPR data handling tested
- [ ] Audit logging tested
- [ ] Data retention policies implemented

---

## Critical Test Coverage Requirements

### Minimum Coverage by Priority

| Priority | Line Coverage | Branch Coverage | Test Types |
|----------|---------------|-----------------|------------|
| **P0 (Critical)** | 100% | 100% | Unit + Integration + E2E |
| **P1 (High)** | 90-95% | 85-90% | Unit + Integration |
| **P2 (Medium)** | 80-85% | 75-80% | Unit + Integration |
| **P3 (Low)** | 70-80% | 65-75% | E2E |

### Critical Components Must Have

1. **Unit Tests** - Fast, isolated tests for logic
2. **Integration Tests** - Real database, event flow
3. **Security Tests** - Attack scenarios, edge cases
4. **Load Tests** - Concurrent access, race conditions
5. **E2E Tests** - Complete user flows with real providers

---

## Overview

This document provides a comprehensive **Test-Driven Development (TDD) strategy** for the event-driven payment component. The strategy follows the **test pyramid** principle, emphasizing fast, isolated unit tests while ensuring critical integration points and user flows are covered.

### Key Principles

- **Security First:** Critical components (P0) must have 100% coverage
- **Test First:** Write tests before implementation (Red → Green → Refactor)
- **Fast Feedback:** Unit tests run in < 5 seconds
- **Isolation:** Each test is independent and can run in parallel
- **Maintainability:** Tests are clear, self-contained, and easy to debug
- **Coverage:** Target 100% for P0, 90%+ for P1, 85%+ overall

---

## Test Pyramid Strategy

```
        ┌─────────────┐
        │  E2E (10%)  │  ← Slow, Full System, Real APIs
        ├─────────────┤
        │             │
        │ Integration │  ← Medium, Real DB, Mocked APIs
        │    (30%)    │
        │             │
        ├─────────────┤
        │             │
        │             │
        │  Unit Tests │  ← Fast, Isolated, All Mocked
        │    (60%)    │
        │             │
        └─────────────┘
```

### Distribution Rationale

| Test Type | Percentage | Count (~) | Speed | Purpose |
|-----------|------------|-----------|-------|---------|
| **Unit** | 60% | 300 | < 1ms | Verify individual component logic |
| **Integration** | 30% | 100 | 10-100ms | Verify component interactions |
| **E2E** | 10% | 20 | 1-10s | Verify critical user flows |

**Total:** ~420 tests, < 15 minutes full suite execution

---

## Unit Tests (60%)

### Purpose

Unit tests verify **individual component logic in isolation**. All dependencies are mocked to ensure:
- Fast execution (< 1ms per test)
- No external dependencies (DB, APIs, network)
- Predictable results
- Easy debugging

### Coverage by Layer

#### 1. Event Layer (100% coverage)

**Test Files:**
- `tests/Unit/Event/PaymentInitiatedEventTest.php`
- `tests/Unit/Event/PaymentCapturedEventTest.php`
- `tests/Unit/Event/EventContextTest.php`

**Example: EventContext Caching Test**

```php
<?php
// tests/Unit/Event/EventContextTest.php

namespace PaymentComponent\Tests\Unit\Event;

use PaymentComponent\Event\EventContext;
use PaymentComponent\Model\Basket;
use PaymentComponent\Model\User;
use PHPUnit\Framework\TestCase;

class EventContextTest extends TestCase
{
    public function testCachesBasketAndUser(): void
    {
        // Arrange
        $basket = $this->createMock(Basket::class);
        $user = $this->createMock(User::class);

        $context = new EventContext([
            'basket' => $basket,
            'user' => $user,
        ]);

        // Act
        $cachedBasket = $context->getBasket();
        $cachedUser = $context->getUser();

        // Assert
        $this->assertSame($basket, $cachedBasket);
        $this->assertSame($user, $cachedUser);
    }

    public function testGetRequestParamWithDefault(): void
    {
        // Arrange
        $context = new EventContext([
            'returnUrl' => '/success',
        ]);

        // Act
        $returnUrl = $context->getRequestParam('returnUrl');
        $cancelUrl = $context->getRequestParam('cancelUrl', '/cancel');

        // Assert
        $this->assertEquals('/success', $returnUrl);
        $this->assertEquals('/cancel', $cancelUrl);
    }

    public function testContextIsImmutable(): void
    {
        // Arrange
        $context = new EventContext(['key' => 'value']);

        // Act & Assert
        $this->expectException(\BadMethodCallException::class);
        $context->set('key', 'newValue');
    }
}
```

**Test Cases:**
- ✓ Event creation with required parameters
- ✓ Event getters return correct values
- ✓ EventContext caches data correctly
- ✓ EventContext handles missing keys with defaults
- ✓ Events are immutable after creation
- ✓ Event serialization for logging

---

#### 2. Domain Layer (95% coverage)

**Test Files:**
- `tests/Unit/Model/OrderTest.php`
- `tests/Unit/Model/PaymentTransactionTest.php`
- `tests/Unit/Model/BasketTest.php`

**Example: Order State Transitions Test**

```php
<?php
// tests/Unit/Model/OrderTest.php

namespace PaymentComponent\Tests\Unit\Model;

use PaymentComponent\Model\Order;
use PHPUnit\Framework\TestCase;

class OrderTest extends TestCase
{
    public function testMarkAsPaymentInProgress(): void
    {
        // Arrange
        $order = new Order();
        $order->setOxid('test-order-id');

        // Act
        $order->markAsPaymentInProgress();

        // Assert
        $this->assertEquals('IN_PROGRESS', $order->getPaymentState());
        $this->assertTrue($order->isAwaitingPayment());
    }

    public function testMarkAsPaymentCompleted(): void
    {
        // Arrange
        $order = new Order();
        $order->setOxid('test-order-id');
        $order->markAsPaymentInProgress();

        // Act
        $order->markAsPaymentCompleted();

        // Assert
        $this->assertEquals('COMPLETED', $order->getPaymentState());
        $this->assertFalse($order->isAwaitingPayment());
        $this->assertTrue($order->isOrderPaid());
        $this->assertNotNull($order->getOxpaid());
    }

    public function testCannotCaptureUnauthorizedOrder(): void
    {
        // Arrange
        $order = new Order();
        $order->setOxid('test-order-id');

        // Act & Assert
        $this->assertFalse($order->canBeCaptured());
    }

    public function testOrderStateTransitionValidation(): void
    {
        // Arrange
        $order = new Order();
        $order->setOxid('test-order-id');

        // Act & Assert
        $this->expectException(\InvalidStateException::class);
        $order->markAsPaymentCompleted(); // Cannot complete without in-progress state
    }

    /**
     * @dataProvider amountCalculationProvider
     */
    public function testRefundableAmountCalculation(
        float $totalAmount,
        float $refundedAmount,
        float $expectedRefundable
    ): void {
        // Arrange
        $order = new Order();
        $order->setOxtotalordersum($totalAmount);
        $order->setRefundedAmount($refundedAmount);

        // Act
        $refundable = $order->getRefundableAmount();

        // Assert
        $this->assertEquals($expectedRefundable, $refundable);
    }

    public function amountCalculationProvider(): array
    {
        return [
            'No refunds' => [100.00, 0.00, 100.00],
            'Partial refund' => [100.00, 30.00, 70.00],
            'Full refund' => [100.00, 100.00, 0.00],
        ];
    }
}
```

**Test Cases:**
- ✓ Order state transitions (NOT_FINISHED → IN_PROGRESS → COMPLETED)
- ✓ State validation (cannot skip states)
- ✓ Payment completion sets oxpaid timestamp
- ✓ Refundable amount calculation
- ✓ Order finalization workflow
- ✓ Transaction ID assignment
- ✓ Email sending flag

---

#### 3. Service Layer (90% coverage)

**Test Files:**
- `tests/Unit/Service/PaymentServiceTest.php`
- `tests/Unit/Service/OrderManagerTest.php`
- `tests/Unit/Service/ModuleSettingsTest.php`

**Example: PaymentService Create Order Test**

```php
<?php
// tests/Unit/Service/PaymentServiceTest.php

namespace PaymentComponent\Tests\Unit\Service;

use PaymentComponent\Service\PaymentService;
use PaymentComponent\Repository\OrderRepository;
use PaymentComponent\Service\ModuleSettings;
use PaymentComponent\Factory\OrderRequestFactory;
use PaymentComponent\Model\Basket;
use PaymentComponent\Model\ProviderOrder;
use Mockery;
use PHPUnit\Framework\TestCase;

class PaymentServiceTest extends TestCase
{
    use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    private PaymentService $service;
    private OrderRepository $orderRepoMock;
    private ModuleSettings $settingsMock;
    private OrderRequestFactory $factoryMock;

    protected function setUp(): void
    {
        $this->orderRepoMock = Mockery::mock(OrderRepository::class);
        $this->settingsMock = Mockery::mock(ModuleSettings::class);
        $this->factoryMock = Mockery::mock(OrderRequestFactory::class);

        $this->service = new PaymentService(
            $this->orderRepoMock,
            $this->settingsMock,
            $this->factoryMock
        );
    }

    public function testCreatePaymentOrderCallsProviderApi(): void
    {
        // Arrange
        $basket = $this->createMockBasket(99.99);
        $providerResponse = new ProviderOrder('pi_123', 'requires_capture', 99.99);

        $this->settingsMock
            ->shouldReceive('getCaptureStrategy')
            ->once()
            ->andReturn('direct');

        $this->factoryMock
            ->shouldReceive('buildOrderRequest')
            ->once()
            ->with($basket, 'capture', [])
            ->andReturn(['intent' => 'capture', 'amount' => 9999]);

        // Mock provider API client
        $apiClientMock = Mockery::mock('ApiClient');
        $apiClientMock
            ->shouldReceive('createOrder')
            ->once()
            ->with(['intent' => 'capture', 'amount' => 9999])
            ->andReturn($providerResponse);

        $this->service->setApiClient($apiClientMock);

        // Act
        $result = $this->service->createPaymentOrder($basket, 'capture', []);

        // Assert
        $this->assertInstanceOf(ProviderOrder::class, $result);
        $this->assertEquals('pi_123', $result->getId());
        $this->assertEquals(99.99, $result->getAmount());
    }

    public function testTrackTransactionPersistsToDatabase(): void
    {
        // Arrange
        $orderId = 'order-123';
        $providerOrderId = 'pi_123';
        $transactionId = 'ch_456';

        $this->orderRepoMock
            ->shouldReceive('saveTransaction')
            ->once()
            ->with(Mockery::on(function ($transaction) use ($orderId, $providerOrderId, $transactionId) {
                return $transaction->getShopOrderId() === $orderId
                    && $transaction->getProviderOrderId() === $providerOrderId
                    && $transaction->getTransactionId() === $transactionId
                    && $transaction->getStatus() === 'CAPTURED';
            }))
            ->andReturn(true);

        // Act
        $result = $this->service->trackTransaction(
            $orderId,
            $providerOrderId,
            'card',
            'CAPTURED',
            $transactionId,
            'capture'
        );

        // Assert
        $this->assertInstanceOf(PaymentTransaction::class, $result);
    }

    public function testCapturePaymentHandlesProviderErrors(): void
    {
        // Arrange
        $order = $this->createMockOrder('order-123');
        $providerOrderId = 'pi_123';

        $apiClientMock = Mockery::mock('ApiClient');
        $apiClientMock
            ->shouldReceive('capturePayment')
            ->once()
            ->with($providerOrderId)
            ->andThrow(new \PaymentProviderException('Insufficient funds'));

        $this->service->setApiClient($apiClientMock);

        // Act & Assert
        $this->expectException(\PaymentException::class);
        $this->expectExceptionMessage('Payment capture failed: Insufficient funds');

        $this->service->capturePayment($order, $providerOrderId, 'card');
    }

    private function createMockBasket(float $amount): Basket
    {
        $basket = Mockery::mock(Basket::class);
        $basket->shouldReceive('getPaymentTotal')->andReturn($amount);
        $basket->shouldReceive('getCurrency')->andReturn('USD');
        return $basket;
    }

    private function createMockOrder(string $orderId): Order
    {
        $order = Mockery::mock(Order::class);
        $order->shouldReceive('getId')->andReturn($orderId);
        $order->shouldReceive('getTotalAmount')->andReturn(99.99);
        return $order;
    }
}
```

**Test Cases:**
- ✓ Payment order creation calls provider API
- ✓ Transaction tracking persists to database
- ✓ Payment capture workflow
- ✓ Payment authorization workflow
- ✓ Provider API error handling
- ✓ SCA validation logic
- ✓ Capture strategy selection
- ✓ Session cleanup

---

#### 4. Event Handlers (95% coverage)

**Test Files:**
- `tests/Unit/EventHandler/PaymentInitiationHandlerTest.php`
- `tests/Unit/EventHandler/PaymentCaptureHandlerTest.php`
- `tests/Unit/EventHandler/PaymentRefundHandlerTest.php`

**Example: Payment Capture Handler Test**

```php
<?php
// tests/Unit/EventHandler/PaymentCaptureHandlerTest.php

namespace PaymentComponent\Tests\Unit\EventHandler;

use PaymentComponent\EventHandler\PaymentCaptureHandler;
use PaymentComponent\Event\CaptureRequestedEvent;
use PaymentComponent\Event\PaymentCapturedEvent;
use PaymentComponent\Service\PaymentService;
use PaymentComponent\Repository\OrderRepository;
use PaymentComponent\Model\Order;
use Mockery;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class PaymentCaptureHandlerTest extends TestCase
{
    use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    private PaymentCaptureHandler $handler;
    private PaymentService $paymentServiceMock;
    private OrderRepository $orderRepoMock;
    private EventDispatcherInterface $dispatcherMock;

    protected function setUp(): void
    {
        $this->paymentServiceMock = Mockery::mock(PaymentService::class);
        $this->orderRepoMock = Mockery::mock(OrderRepository::class);
        $this->dispatcherMock = Mockery::mock(EventDispatcherInterface::class);

        $this->handler = new PaymentCaptureHandler(
            $this->paymentServiceMock,
            $this->orderRepoMock,
            $this->dispatcherMock
        );
    }

    public function testHandleCapturesPaymentAndEmitsEvent(): void
    {
        // Arrange
        $orderId = 'order-123';
        $providerOrderId = 'pi_123';
        $captureId = 'ch_456';
        $amount = 99.99;

        $event = new CaptureRequestedEvent($orderId, $amount, 'admin', 'idempotency-key-123');

        $order = Mockery::mock(Order::class);
        $order->shouldReceive('isAwaitingCapture')->once()->andReturn(true);
        $order->shouldReceive('getProviderOrderId')->once()->andReturn($providerOrderId);
        $order->shouldReceive('getTotalAmount')->once()->andReturn(99.99);
        $order->shouldReceive('getId')->andReturn($orderId);

        $this->orderRepoMock
            ->shouldReceive('getById')
            ->once()
            ->with($orderId)
            ->andReturn($order);

        $captureResult = new CaptureResult($captureId, $amount, 'CAPTURED');

        $this->paymentServiceMock
            ->shouldReceive('capturePayment')
            ->once()
            ->with($providerOrderId, $amount)
            ->andReturn($captureResult);

        $this->paymentServiceMock
            ->shouldReceive('trackTransaction')
            ->once();

        $this->dispatcherMock
            ->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::type(PaymentCapturedEvent::class));

        // Act
        $this->handler->handle($event);

        // Assert - via Mockery expectations
    }

    public function testHandleThrowsExceptionForInvalidState(): void
    {
        // Arrange
        $orderId = 'order-123';
        $event = new CaptureRequestedEvent($orderId, 99.99, 'admin', 'key-123');

        $order = Mockery::mock(Order::class);
        $order->shouldReceive('isAwaitingCapture')->once()->andReturn(false);

        $this->orderRepoMock
            ->shouldReceive('getById')
            ->once()
            ->with($orderId)
            ->andReturn($order);

        // Act & Assert
        $this->expectException(\InvalidStateException::class);
        $this->expectExceptionMessage('Order not in AUTHORIZED state');

        $this->handler->handle($event);
    }

    public function testHandleSkipsDuplicateCaptureWithSameIdempotencyKey(): void
    {
        // Arrange
        $orderId = 'order-123';
        $idempotencyKey = 'key-123';
        $event = new CaptureRequestedEvent($orderId, 99.99, 'admin', $idempotencyKey);

        $order = Mockery::mock(Order::class);
        $order->shouldReceive('isAwaitingCapture')->once()->andReturn(true);
        $order->shouldReceive('getId')->andReturn($orderId);

        $this->orderRepoMock
            ->shouldReceive('getById')
            ->once()
            ->andReturn($order);

        $this->orderRepoMock
            ->shouldReceive('existsByIdempotencyKey')
            ->once()
            ->with($orderId, $idempotencyKey, 'capture')
            ->andReturn(true);

        // Should not call payment service
        $this->paymentServiceMock->shouldNotReceive('capturePayment');
        $this->dispatcherMock->shouldNotReceive('dispatch');

        // Act
        $this->handler->handle($event);

        // Assert - idempotency check prevents duplicate
    }
}
```

**Test Cases:**
- ✓ Handler captures payment and emits success event
- ✓ Handler validates order state before capture
- ✓ Handler prevents duplicate captures via idempotency key
- ✓ Handler handles provider API errors gracefully
- ✓ Handler emits PaymentFailedEvent on error
- ✓ Handler updates transaction status in database
- ✓ Handler logs operations for audit

---

#### 5. Factory Layer (85% coverage)

**Test Files:**
- `tests/Unit/Factory/OrderRequestFactoryTest.php`
- `tests/Unit/Factory/PurchaseUnitsFactoryTest.php`

**Example: OrderRequestFactory Test**

```php
<?php
// tests/Unit/Factory/OrderRequestFactoryTest.php

namespace PaymentComponent\Tests\Unit\Factory;

use PaymentComponent\Factory\OrderRequestFactory;
use PaymentComponent\Model\Basket;
use PHPUnit\Framework\TestCase;

class OrderRequestFactoryTest extends TestCase
{
    private OrderRequestFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new OrderRequestFactory();
    }

    public function testBuildOrderRequestWithCaptureIntent(): void
    {
        // Arrange
        $basket = $this->createMockBasket(99.99, 'USD');

        // Act
        $request = $this->factory->buildOrderRequest($basket, 'capture', []);

        // Assert
        $this->assertEquals('capture', $request['intent']);
        $this->assertEquals(9999, $request['amount']); // cents
        $this->assertEquals('USD', $request['currency']);
        $this->assertArrayHasKey('purchase_units', $request);
    }

    public function testBuildOrderRequestWithAuthorizeIntent(): void
    {
        // Arrange
        $basket = $this->createMockBasket(150.50, 'EUR');

        // Act
        $request = $this->factory->buildOrderRequest($basket, 'authorize', []);

        // Assert
        $this->assertEquals('authorize', $request['intent']);
        $this->assertEquals(15050, $request['amount']);
        $this->assertEquals('EUR', $request['currency']);
    }

    public function testIncludesReturnUrlsInRequest(): void
    {
        // Arrange
        $basket = $this->createMockBasket(99.99, 'USD');
        $options = [
            'return_url' => 'https://shop.com/success',
            'cancel_url' => 'https://shop.com/cancel',
        ];

        // Act
        $request = $this->factory->buildOrderRequest($basket, 'capture', $options);

        // Assert
        $this->assertEquals('https://shop.com/success', $request['return_url']);
        $this->assertEquals('https://shop.com/cancel', $request['cancel_url']);
    }

    private function createMockBasket(float $amount, string $currency): Basket
    {
        $basket = Mockery::mock(Basket::class);
        $basket->shouldReceive('getPaymentTotal')->andReturn($amount);
        $basket->shouldReceive('getCurrency')->andReturn($currency);
        $basket->shouldReceive('getItems')->andReturn([]);
        return $basket;
    }
}
```

**Test Cases:**
- ✓ Request building with capture intent
- ✓ Request building with authorize intent
- ✓ Amount conversion (dollars to cents)
- ✓ Currency code inclusion
- ✓ Return/cancel URLs inclusion
- ✓ Purchase units array structure
- ✓ Line items formatting

---

#### 6. Repository Layer (Unit Tests Only - No DB)

**Test Files:**
- `tests/Unit/Repository/OrderRepositoryTest.php`

**Example: OrderRepository Query Building Test**

```php
<?php
// tests/Unit/Repository/OrderRepositoryTest.php

namespace PaymentComponent\Tests\Unit\Repository;

use PaymentComponent\Repository\OrderRepository;
use Doctrine\DBAL\Query\QueryBuilder;
use Mockery;
use PHPUnit\Framework\TestCase;

class OrderRepositoryTest extends TestCase
{
    use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    private OrderRepository $repository;
    private QueryBuilder $queryBuilderMock;

    protected function setUp(): void
    {
        $this->queryBuilderMock = Mockery::mock(QueryBuilder::class);
        $this->repository = new OrderRepository($this->queryBuilderMock);
    }

    public function testGetByIdBuildsCorrectQuery(): void
    {
        // Arrange
        $orderId = 'order-123';

        $this->queryBuilderMock
            ->shouldReceive('select')->once()->with('*')->andReturnSelf()
            ->shouldReceive('from')->once()->with('oxorder')->andReturnSelf()
            ->shouldReceive('where')->once()->with('OXID = :oxid')->andReturnSelf()
            ->shouldReceive('setParameter')->once()->with('oxid', $orderId)->andReturnSelf()
            ->shouldReceive('executeQuery')->once()->andReturn($resultMock);

        $resultMock = Mockery::mock('Result');
        $resultMock->shouldReceive('fetchAssociative')->once()->andReturn([
            'OXID' => $orderId,
            'OXTOTALORDERSUM' => 99.99,
        ]);

        // Act
        $order = $this->repository->getById($orderId);

        // Assert
        $this->assertEquals($orderId, $order->getId());
    }

    // More query building tests...
}
```

**Note:** Repository layer will also have **integration tests with real database** (see Integration Tests section).

---

### Unit Test Best Practices

1. **Naming Convention:**
   - Test class: `{ClassName}Test.php`
   - Test method: `test{MethodName}{Scenario}(): void`
   - Example: `testCreatePaymentOrderCallsProviderApi()`

2. **AAA Pattern:**
   - **Arrange:** Set up test data and mocks
   - **Act:** Execute the method under test
   - **Assert:** Verify the expected outcome

3. **One Assertion Focus:**
   - Each test should focus on one behavior
   - Multiple assertions are OK if testing same behavior

4. **Use Data Providers:**
   - For testing multiple scenarios with same logic
   - Reduces code duplication

5. **Mock External Dependencies:**
   - Never hit real database, APIs, or filesystem
   - Use Mockery for method expectations

---

## Integration Tests (30%)

### Purpose

Integration tests verify **component interactions** with:
- Real database (using TestContainers)
- Mocked external APIs (using WireMock)
- Event dispatcher behavior
- Multi-step workflows

### Setup Requirements

```bash
# Install TestContainers for PHP
composer require --dev testcontainers/testcontainers

# Install WireMock for API mocking
docker pull wiremock/wiremock
```

### Test Database Configuration

```php
<?php
// tests/Integration/DatabaseTestCase.php

namespace PaymentComponent\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Testcontainers\Container\MySQLContainer;

abstract class DatabaseTestCase extends TestCase
{
    protected static MySQLContainer $dbContainer;
    protected static \PDO $pdo;

    public static function setUpBeforeClass(): void
    {
        // Start MySQL container
        self::$dbContainer = MySQLContainer::make('mysql:8.0')
            ->withDatabase('test_payment')
            ->withUsername('test')
            ->withPassword('test');

        self::$dbContainer->start();

        // Connect to database
        self::$pdo = new \PDO(
            self::$dbContainer->getConnectionString(),
            'test',
            'test'
        );

        // Run migrations
        self::runMigrations();
    }

    public static function tearDownAfterClass(): void
    {
        self::$dbContainer->stop();
    }

    protected function setUp(): void
    {
        // Start transaction
        self::$pdo->beginTransaction();
    }

    protected function tearDown(): void
    {
        // Rollback transaction (clean slate for next test)
        self::$pdo->rollBack();
    }

    private static function runMigrations(): void
    {
        // Read and execute migration SQL
        $sql = file_get_contents(__DIR__ . '/../../migrations/001_payment_transaction.sql');
        self::$pdo->exec($sql);
    }
}
```

---

### Integration Test Examples

#### 1. Repository Integration Tests

**Test File:** `tests/Integration/Repository/OrderRepositoryIntegrationTest.php`

```php
<?php
namespace PaymentComponent\Tests\Integration\Repository;

use PaymentComponent\Tests\Integration\DatabaseTestCase;
use PaymentComponent\Repository\OrderRepository;
use PaymentComponent\Model\Order;
use PaymentComponent\Model\PaymentTransaction;

class OrderRepositoryIntegrationTest extends DatabaseTestCase
{
    private OrderRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new OrderRepository(self::$pdo);
    }

    public function testSaveAndRetrieveOrder(): void
    {
        // Arrange
        $order = new Order();
        $order->setOxid('test-order-1');
        $order->setOxtotalordersum(99.99);
        $order->setOxordernr('100001');

        // Act
        $this->repository->save($order);
        $retrieved = $this->repository->getById('test-order-1');

        // Assert
        $this->assertNotNull($retrieved);
        $this->assertEquals('test-order-1', $retrieved->getId());
        $this->assertEquals(99.99, $retrieved->getTotalAmount());
    }

    public function testGetOrderByProviderOrderId(): void
    {
        // Arrange
        $order = $this->createTestOrder('order-1', 'pi_123');
        $this->repository->save($order);

        $transaction = new PaymentTransaction();
        $transaction->setShopOrderId('order-1');
        $transaction->setProviderOrderId('pi_123');
        $transaction->setStatus('CAPTURED');
        $this->repository->saveTransaction($transaction);

        // Act
        $retrieved = $this->repository->getOrderByProviderOrderId('pi_123');

        // Assert
        $this->assertNotNull($retrieved);
        $this->assertEquals('order-1', $retrieved->getId());
    }

    public function testCleanupAbandonedOrders(): void
    {
        // Arrange - Create old abandoned order
        $oldOrder = $this->createTestOrder('old-order', null);
        $oldOrder->setOxtransstatus('NOT_FINISHED');
        $oldOrder->setOxorderdate(date('Y-m-d H:i:s', strtotime('-2 days')));
        $this->repository->save($oldOrder);

        // Create recent order
        $recentOrder = $this->createTestOrder('recent-order', null);
        $recentOrder->setOxtransstatus('NOT_FINISHED');
        $this->repository->save($recentOrder);

        // Act
        $this->repository->cleanUpAbandonedOrders(24); // 24 hours threshold

        // Assert
        $this->assertNull($this->repository->getById('old-order'));
        $this->assertNotNull($this->repository->getById('recent-order'));
    }

    public function testGetTransactionsByOrderId(): void
    {
        // Arrange
        $orderId = 'order-multi-tx';
        $order = $this->createTestOrder($orderId, 'pi_123');
        $this->repository->save($order);

        // Create multiple transactions
        $this->createAndSaveTransaction($orderId, 'pi_123', 'auth_1', 'authorization');
        $this->createAndSaveTransaction($orderId, 'pi_123', 'cap_1', 'capture');

        // Act
        $transactions = $this->repository->getTransactionsByOrderId($orderId);

        // Assert
        $this->assertCount(2, $transactions);
        $this->assertEquals('authorization', $transactions[0]->getTransactionType());
        $this->assertEquals('capture', $transactions[1]->getTransactionType());
    }

    private function createTestOrder(string $id, ?string $providerOrderId): Order
    {
        $order = new Order();
        $order->setOxid($id);
        $order->setOxtotalordersum(99.99);
        $order->setOxordernr(rand(100000, 999999));
        if ($providerOrderId) {
            $order->setPaymentProviderOrderId($providerOrderId);
        }
        return $order;
    }

    private function createAndSaveTransaction(
        string $orderId,
        string $providerOrderId,
        string $transactionId,
        string $type
    ): void {
        $transaction = new PaymentTransaction();
        $transaction->setShopOrderId($orderId);
        $transaction->setProviderOrderId($providerOrderId);
        $transaction->setTransactionId($transactionId);
        $transaction->setTransactionType($type);
        $transaction->setStatus('CAPTURED');
        $this->repository->saveTransaction($transaction);
    }
}
```

**Test Cases:**
- ✓ Save and retrieve order from database
- ✓ Complex queries with joins
- ✓ Transaction history queries
- ✓ Cleanup operations
- ✓ Concurrent access scenarios
- ✓ Database constraint validation

---

#### 2. Event Flow Integration Tests

**Test File:** `tests/Integration/EventFlow/CaptureEventFlowTest.php`

```php
<?php
namespace PaymentComponent\Tests\Integration\EventFlow;

use PaymentComponent\Tests\Integration\DatabaseTestCase;
use PaymentComponent\Event\CaptureRequestedEvent;
use PaymentComponent\Event\PaymentCapturedEvent;
use PaymentComponent\EventHandler\PaymentCaptureHandler;
use PaymentComponent\Service\PaymentService;
use PaymentComponent\Repository\OrderRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;

class CaptureEventFlowTest extends DatabaseTestCase
{
    private EventDispatcher $dispatcher;
    private OrderRepository $orderRepository;
    private PaymentService $paymentService;
    private bool $capturedEventFired = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderRepository = new OrderRepository(self::$pdo);
        $this->paymentService = $this->createMockedPaymentService();
        $this->dispatcher = new EventDispatcher();

        // Register handler
        $handler = new PaymentCaptureHandler(
            $this->paymentService,
            $this->orderRepository,
            $this->dispatcher
        );

        $this->dispatcher->addListener(
            CaptureRequestedEvent::class,
            [$handler, 'handle']
        );

        // Register subscriber to verify event is emitted
        $this->dispatcher->addListener(
            PaymentCapturedEvent::class,
            function (PaymentCapturedEvent $event) {
                $this->capturedEventFired = true;
            }
        );
    }

    public function testFullCaptureEventFlow(): void
    {
        // Arrange - Create authorized order
        $order = new Order();
        $order->setOxid('order-123');
        $order->setPaymentProviderOrderId('pi_123');
        $order->setPaymentState('AUTHORIZED');
        $order->setOxtotalordersum(99.99);
        $this->orderRepository->save($order);

        // Act - Emit capture requested event
        $event = new CaptureRequestedEvent(
            'order-123',
            99.99,
            'admin',
            'idempotency-key-123'
        );

        $this->dispatcher->dispatch($event);

        // Assert - Check order updated
        $updatedOrder = $this->orderRepository->getById('order-123');
        $this->assertEquals('CAPTURED', $updatedOrder->getPaymentState());

        // Assert - Check transaction saved
        $transactions = $this->orderRepository->getTransactionsByOrderId('order-123');
        $this->assertCount(1, $transactions);
        $this->assertEquals('capture', $transactions[0]->getTransactionType());

        // Assert - Check event emitted
        $this->assertTrue($this->capturedEventFired);
    }

    public function testIdempotentCaptureFlow(): void
    {
        // Arrange - Create authorized order
        $order = new Order();
        $order->setOxid('order-456');
        $order->setPaymentProviderOrderId('pi_456');
        $order->setPaymentState('AUTHORIZED');
        $order->setOxtotalordersum(150.00);
        $this->orderRepository->save($order);

        $event = new CaptureRequestedEvent(
            'order-456',
            150.00,
            'api',
            'same-idempotency-key'
        );

        // Act - Dispatch same event twice
        $this->dispatcher->dispatch($event);
        $this->dispatcher->dispatch($event);

        // Assert - Only one transaction created
        $transactions = $this->orderRepository->getTransactionsByOrderId('order-456');
        $this->assertCount(1, $transactions);
    }

    private function createMockedPaymentService(): PaymentService
    {
        // Mock only the external API calls, not the business logic
        $apiClientMock = Mockery::mock('ApiClient');
        $apiClientMock->shouldReceive('capturePayment')->andReturn(
            new CaptureResult('ch_123', 99.99, 'CAPTURED')
        );

        $service = new PaymentService(...);
        $service->setApiClient($apiClientMock);
        return $service;
    }
}
```

**Test Cases:**
- ✓ Complete capture event flow (event → handler → service → repository)
- ✓ Refund event flow
- ✓ Multiple subscribers react to same event
- ✓ Event handler error propagation
- ✓ Idempotency across event flow

---

#### 3. Webhook Integration Tests

**Test File:** `tests/Integration/Webhook/WebhookProcessingTest.php`

```php
<?php
namespace PaymentComponent\Tests\Integration\Webhook;

use PaymentComponent\Tests\Integration\DatabaseTestCase;
use PaymentComponent\Webhook\RequestHandler;
use PaymentComponent\Webhook\EventVerifier;
use PaymentComponent\Repository\OrderRepository;

class WebhookProcessingTest extends DatabaseTestCase
{
    private RequestHandler $webhookHandler;
    private OrderRepository $orderRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderRepository = new OrderRepository(self::$pdo);
        $verifier = new EventVerifier('test-webhook-secret');
        $this->webhookHandler = new RequestHandler($verifier, $this->orderRepository);
    }

    public function testProcessValidWebhook(): void
    {
        // Arrange - Create order
        $order = new Order();
        $order->setOxid('order-123');
        $order->setPaymentProviderOrderId('pi_123');
        $order->setPaymentState('AUTHORIZED');
        $this->orderRepository->save($order);

        // Create webhook payload
        $payload = json_encode([
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_123',
                    'status' => 'succeeded',
                    'amount' => 9999,
                ],
            ],
        ]);

        $signature = $this->generateSignature($payload, 'test-webhook-secret');

        // Act
        $result = $this->webhookHandler->process($payload, $signature);

        // Assert
        $this->assertTrue($result->isSuccess());

        $updatedOrder = $this->orderRepository->getById('order-123');
        $this->assertEquals('CAPTURED', $updatedOrder->getPaymentState());
    }

    public function testRejectInvalidSignature(): void
    {
        // Arrange
        $payload = json_encode(['type' => 'payment_intent.succeeded']);
        $invalidSignature = 'invalid-signature';

        // Act & Assert
        $this->expectException(\SignatureVerificationException::class);
        $this->webhookHandler->process($payload, $invalidSignature);
    }

    private function generateSignature(string $payload, string $secret): string
    {
        return hash_hmac('sha256', $payload, $secret);
    }
}
```

**Test Cases:**
- ✓ Valid webhook processing
- ✓ Invalid signature rejection
- ✓ Duplicate webhook handling (idempotency)
- ✓ Unknown event type handling
- ✓ Concurrent webhook processing

---

### Integration Test Best Practices

1. **Use TestContainers:**
   - Real database in Docker
   - Consistent environment
   - Isolated from host

2. **Transactional Tests:**
   - Start transaction in setUp()
   - Rollback in tearDown()
   - Clean slate for each test

3. **Mock Only External APIs:**
   - Database: Real
   - Payment provider API: Mocked (WireMock)
   - Event dispatcher: Real

4. **Test Happy Path + Edge Cases:**
   - Normal flow
   - Error handling
   - Race conditions
   - Constraint violations

---

## E2E Tests (10%)

### Purpose

E2E tests verify **critical user flows** through the entire system:
- Real browser (Playwright/Codeception)
- Real database
- Provider sandbox environments
- Complete checkout flows

### Setup Requirements

```bash
# Install Codeception
composer require --dev codeception/codeception

# Or install Playwright for PHP
composer require --dev symfony/panther
```

### E2E Test Examples

#### 1. Complete Checkout Flow

**Test File:** `tests/E2E/CheckoutFlowTest.php`

```php
<?php
namespace PaymentComponent\Tests\E2E;

use Codeception\Test\Unit;

class CheckoutFlowTest extends Unit
{
    protected $tester;

    public function testCompleteStripeCheckoutFlow()
    {
        // 1. Browse shop and add product to cart
        $this->tester->amOnPage('/');
        $this->tester->click('Product 1');
        $this->tester->click('Add to Cart');
        $this->tester->see('Product added to cart');

        // 2. Go to checkout
        $this->tester->click('Checkout');
        $this->tester->seeInCurrentUrl('/checkout');

        // 3. Select payment method
        $this->tester->selectOption('payment_method', 'stripe_card');
        $this->tester->click('Continue');

        // 4. Fill payment details (Stripe test card)
        $this->tester->waitForElement('#card-element');
        $this->tester->fillStripeCard('4242424242424242', '12/25', '123');

        // 5. Complete payment
        $this->tester->click('Pay Now');
        $this->tester->waitForText('Payment successful', 30);

        // 6. Verify order confirmation
        $this->tester->seeInCurrentUrl('/order-confirmation');
        $this->tester->see('Order #');
        $this->tester->see('Total: $99.99');

        // 7. Verify order in database
        $orderId = $this->tester->grabTextFrom('.order-id');
        $this->tester->seeInDatabase('oxorder', [
            'OXORDERNR' => $orderId,
            'OXTRANSSTATUS' => 'OK',
        ]);

        // 8. Verify transaction tracked
        $this->tester->seeInDatabase('osc_transaction', [
            'order_id' => $orderId,
            'status' => 'CAPTURED',
        ]);
    }

    public function testCheckoutWithWebhookFlow()
    {
        // Similar flow but verify webhook processing
        $this->tester->amOnPage('/checkout');
        $this->tester->selectOption('payment_method', 'paymenter');
        $this->tester->click('Pay with Paymenter');

        // Redirected to Paymenter
        $this->tester->seeInCurrentUrl('paymenter.com');

        // Complete payment on Paymenter (sandbox)
        $this->tester->fillField('email', 'buyer@test.com');
        $this->tester->fillField('password', 'test123');
        $this->tester->click('Log In');
        $this->tester->click('Pay Now');

        // Redirected back to shop
        $this->tester->wait(5); // Wait for webhook
        $this->tester->seeInCurrentUrl('/order-confirmation');

        // Verify webhook processed
        $orderId = $this->tester->grabTextFrom('.order-id');
        $this->tester->seeInDatabase('oxorder', [
            'OXORDERNR' => $orderId,
            'OXTRANSSTATUS' => 'OK',
        ]);
    }
}
```

**Test Cases:**
- ✓ Complete checkout with card payment
- ✓ Complete checkout with redirect payment (Paymenter)
- ✓ Webhook processing after redirect
- ✓ Failed payment handling
- ✓ Abandoned cart recovery
- ✓ Capture from admin panel
- ✓ Refund from admin panel

---

#### 2. GraphQL API E2E Tests

**Test File:** `tests/E2E/GraphQLApiTest.php`

```php
<?php
namespace PaymentComponent\Tests\E2E;

use Codeception\Test\Unit;
use GuzzleHttp\Client;

class GraphQLApiTest extends Unit
{
    private Client $httpClient;
    private string $apiUrl = 'http://localhost:8000/graphql';
    private string $authToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->httpClient = new Client();
        $this->authToken = $this->getAdminAuthToken();
    }

    public function testCapturePaymentViaGraphQL()
    {
        // Arrange - Create authorized order
        $orderId = $this->createAuthorizedOrder();

        // Act - Call GraphQL mutation
        $mutation = <<<GQL
        mutation {
          capturePayment(input: {
            orderId: "{$orderId}"
            amount: 99.99
            reason: "Test capture"
            idempotencyKey: "test-key-123"
          }) {
            success
            captureId
            orderStatus
          }
        }
        GQL;

        $response = $this->httpClient->post($this->apiUrl, [
            'json' => ['query' => $mutation],
            'headers' => ['Authorization' => 'Bearer ' . $this->authToken],
        ]);

        $result = json_decode($response->getBody(), true);

        // Assert
        $this->assertTrue($result['data']['capturePayment']['success']);
        $this->assertNotEmpty($result['data']['capturePayment']['captureId']);
        $this->assertEquals('CAPTURED', $result['data']['capturePayment']['orderStatus']);

        // Verify in database
        $this->tester->seeInDatabase('oxorder', [
            'OXID' => $orderId,
            'OXTRANSSTATUS' => 'OK',
        ]);
    }

    public function testIdempotentCapture()
    {
        // Arrange
        $orderId = $this->createAuthorizedOrder();
        $idempotencyKey = 'same-key-' . uniqid();

        // Act - Call mutation twice with same idempotency key
        $mutation = $this->buildCaptureMutation($orderId, $idempotencyKey);

        $response1 = $this->httpClient->post($this->apiUrl, [
            'json' => ['query' => $mutation],
            'headers' => ['Authorization' => 'Bearer ' . $this->authToken],
        ]);

        $response2 = $this->httpClient->post($this->apiUrl, [
            'json' => ['query' => $mutation],
            'headers' => ['Authorization' => 'Bearer ' . $this->authToken],
        ]);

        // Assert - Both succeed but only one transaction created
        $result1 = json_decode($response1->getBody(), true);
        $result2 = json_decode($response2->getBody(), true);

        $this->assertTrue($result1['data']['capturePayment']['success']);
        $this->assertTrue($result2['data']['capturePayment']['success']);

        // Only one capture transaction
        $this->tester->seeNumRecords(1, 'osc_transaction', [
            'order_id' => $orderId,
            'transaction_type' => 'capture',
        ]);
    }

    private function getAdminAuthToken(): string
    {
        // Login and get JWT token
        $response = $this->httpClient->post('http://localhost:8000/auth/login', [
            'json' => [
                'username' => 'admin',
                'password' => 'test123',
            ],
        ]);

        $result = json_decode($response->getBody(), true);
        return $result['token'];
    }

    private function createAuthorizedOrder(): string
    {
        // Create test order via API or database
        $orderId = 'test-order-' . uniqid();

        $this->tester->haveInDatabase('oxorder', [
            'OXID' => $orderId,
            'OXTOTALORDERSUM' => 99.99,
            'OXTRANSSTATUS' => 'AUTHORIZED',
            'payment_provider_order_id' => 'pi_test_' . uniqid(),
        ]);

        return $orderId;
    }

    private function buildCaptureMutation(string $orderId, string $idempotencyKey): string
    {
        return <<<GQL
        mutation {
          capturePayment(input: {
            orderId: "{$orderId}"
            amount: 99.99
            idempotencyKey: "{$idempotencyKey}"
          }) {
            success
            captureId
          }
        }
        GQL;
    }
}
```

**Test Cases:**
- ✓ Capture payment via GraphQL
- ✓ Refund payment via GraphQL
- ✓ Idempotency key handling
- ✓ Authentication/authorization
- ✓ Error handling
- ✓ Rate limiting

---

### E2E Test Best Practices

1. **Test Critical Paths Only:**
   - Complete checkout flow
   - Webhook integration
   - Admin operations
   - API integrations

2. **Use Provider Sandboxes:**
   - Stripe: Test mode keys
   - Paymenter: Sandbox accounts
   - Adyen: Test environment

3. **Clean Up After Tests:**
   - Delete test orders
   - Clear test data
   - Reset database state

4. **Run E2E Tests Less Frequently:**
   - Before merge to main
   - Nightly builds
   - Not on every commit

---

## Test Data & Fixtures

### Fixture Strategy Overview

| Test Type | Fixture Type | Example |
|-----------|-------------|---------|
| Unit | In-memory objects (builders/mocks) | `OrderBuilder::new()->withAmount(99.99)->build()` |
| Integration | Database seeding (factories) | `OrderFactory::create(['amount' => 99.99])` |
| E2E | Full database snapshots | `DatabaseSeeder::seed('checkout-scenario')` |

---

### 1. Unit Test Fixtures (Builders)

**Location:** `tests/Fixtures/Builders/`

**Builder Pattern Example:**

```php
<?php
// tests/Fixtures/Builders/OrderBuilder.php

namespace PaymentComponent\Tests\Fixtures\Builders;

use PaymentComponent\Model\Order;

class OrderBuilder
{
    private string $id = 'test-order-1';
    private float $amount = 99.99;
    private string $state = 'NOT_FINISHED';
    private ?string $providerOrderId = null;
    private ?string $transactionId = null;

    public static function new(): self
    {
        return new self();
    }

    public function withId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function withAmount(float $amount): self
    {
        $this->amount = $amount;
        return $this;
    }

    public function withState(string $state): self
    {
        $this->state = $state;
        return $this;
    }

    public function authorized(): self
    {
        $this->state = 'AUTHORIZED';
        $this->providerOrderId = 'pi_' . uniqid();
        return $this;
    }

    public function captured(): self
    {
        $this->state = 'CAPTURED';
        $this->providerOrderId = 'pi_' . uniqid();
        $this->transactionId = 'ch_' . uniqid();
        return $this;
    }

    public function build(): Order
    {
        $order = new Order();
        $order->setOxid($this->id);
        $order->setOxtotalordersum($this->amount);
        $order->setPaymentState($this->state);

        if ($this->providerOrderId) {
            $order->setPaymentProviderOrderId($this->providerOrderId);
        }

        if ($this->transactionId) {
            $order->setOxtransid($this->transactionId);
        }

        return $order;
    }
}
```

**Usage in Tests:**

```php
// Create authorized order with custom amount
$order = OrderBuilder::new()
    ->withAmount(150.50)
    ->authorized()
    ->build();

// Create captured order
$order = OrderBuilder::new()->captured()->build();
```

**Additional Builders:**

```php
// tests/Fixtures/Builders/BasketBuilder.php
class BasketBuilder
{
    public static function new(): self { /* ... */ }
    public function withItems(array $items): self { /* ... */ }
    public function withTotal(float $total): self { /* ... */ }
    public function build(): Basket { /* ... */ }
}

// tests/Fixtures/Builders/UserBuilder.php
class UserBuilder
{
    public static function new(): self { /* ... */ }
    public function withEmail(string $email): self { /* ... */ }
    public function withAddress(Address $address): self { /* ... */ }
    public function build(): User { /* ... */ }
}

// tests/Fixtures/Builders/EventContextBuilder.php
class EventContextBuilder
{
    public static function new(): self { /* ... */ }
    public function withBasket(Basket $basket): self { /* ... */ }
    public function withUser(User $user): self { /* ... */ }
    public function build(): EventContext { /* ... */ }
}
```

---

### 2. Integration Test Fixtures (Factories)

**Location:** `tests/Fixtures/Factories/`

**Factory Pattern Example:**

```php
<?php
// tests/Fixtures/Factories/OrderFactory.php

namespace PaymentComponent\Tests\Fixtures\Factories;

use PaymentComponent\Model\Order;

class OrderFactory
{
    private static \PDO $pdo;

    public static function setPdo(\PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    public static function create(array $attributes = []): Order
    {
        $defaults = [
            'oxid' => 'order-' . uniqid(),
            'oxtotalordersum' => 99.99,
            'oxordernr' => rand(100000, 999999),
            'oxtransstatus' => 'NOT_FINISHED',
            'oxorderdate' => date('Y-m-d H:i:s'),
        ];

        $data = array_merge($defaults, $attributes);

        // Insert into database
        $sql = "INSERT INTO oxorder (OXID, OXTOTALORDERSUM, OXORDERNR, OXTRANSSTATUS, OXORDERDATE)
                VALUES (:oxid, :oxtotalordersum, :oxordernr, :oxtransstatus, :oxorderdate)";

        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($data);

        // Return order object
        $order = new Order();
        $order->load($data['oxid']);
        return $order;
    }

    public static function createAuthorized(array $attributes = []): Order
    {
        return self::create(array_merge([
            'oxtransstatus' => 'AUTHORIZED',
            'payment_provider_order_id' => 'pi_' . uniqid(),
        ], $attributes));
    }

    public static function createCaptured(array $attributes = []): Order
    {
        return self::create(array_merge([
            'oxtransstatus' => 'OK',
            'payment_provider_order_id' => 'pi_' . uniqid(),
            'oxtransid' => 'ch_' . uniqid(),
            'oxpaid' => date('Y-m-d H:i:s'),
        ], $attributes));
    }
}
```

**Usage in Integration Tests:**

```php
// Create order in database
$order = OrderFactory::create(['oxtotalordersum' => 150.50]);

// Create authorized order
$order = OrderFactory::createAuthorized();

// Create captured order
$order = OrderFactory::createCaptured();
```

**Additional Factories:**

```php
// tests/Fixtures/Factories/TransactionFactory.php
class TransactionFactory
{
    public static function create(array $attributes = []): PaymentTransaction { /* ... */ }
    public static function createCapture(Order $order): PaymentTransaction { /* ... */ }
    public static function createRefund(Order $order): PaymentTransaction { /* ... */ }
}

// tests/Fixtures/Factories/UserFactory.php
class UserFactory
{
    public static function create(array $attributes = []): User { /* ... */ }
    public static function createWithAddress(): User { /* ... */ }
}

// tests/Fixtures/Factories/BasketFactory.php
class BasketFactory
{
    public static function create(array $attributes = []): Basket { /* ... */ }
    public static function createWithItems(array $items): Basket { /* ... */ }
}
```

---

### 3. E2E Test Fixtures (Seeders)

**Location:** `tests/Fixtures/Seeders/`

**Database Seeder Example:**

```php
<?php
// tests/Fixtures/Seeders/DatabaseSeeder.php

namespace PaymentComponent\Tests\Fixtures\Seeders;

class DatabaseSeeder
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function seed(string $scenario = 'default'): void
    {
        $this->clearTables();

        match ($scenario) {
            'checkout' => $this->seedCheckoutScenario(),
            'admin-capture' => $this->seedAdminCaptureScenario(),
            'webhook' => $this->seedWebhookScenario(),
            default => $this->seedDefaultScenario(),
        };
    }

    private function seedCheckoutScenario(): void
    {
        // Create products
        $this->createProduct('prod-1', 'Test Product 1', 99.99);
        $this->createProduct('prod-2', 'Test Product 2', 149.99);

        // Create test user
        $this->createUser('test@example.com', 'Test User');

        // Create categories
        $this->createCategory('Electronics');
    }

    private function seedAdminCaptureScenario(): void
    {
        // Create admin user
        $this->createUser('admin@example.com', 'Admin', 'admin');

        // Create authorized orders
        for ($i = 1; $i <= 5; $i++) {
            $this->createOrder([
                'oxid' => "order-auth-{$i}",
                'oxtotalordersum' => 100.00 * $i,
                'oxtransstatus' => 'AUTHORIZED',
                'payment_provider_order_id' => "pi_test_{$i}",
            ]);
        }
    }

    private function clearTables(): void
    {
        $tables = [
            'oxorder',
            'oxorderarticles',
            'osc_transaction',
            'oxuser',
            'oxarticles',
            'oxcategories',
        ];

        foreach ($tables as $table) {
            $this->pdo->exec("TRUNCATE TABLE {$table}");
        }
    }

    private function createProduct(string $id, string $title, float $price): void
    {
        $sql = "INSERT INTO oxarticles (OXID, OXTITLE, OXPRICE) VALUES (:id, :title, :price)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id, 'title' => $title, 'price' => $price]);
    }

    private function createUser(string $email, string $name, string $role = 'user'): void
    {
        $sql = "INSERT INTO oxuser (OXID, OXUSERNAME, OXFNAME) VALUES (:id, :email, :name)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => uniqid('user_'), 'email' => $email, 'name' => $name]);
    }

    private function createOrder(array $data): void
    {
        $sql = "INSERT INTO oxorder (OXID, OXTOTALORDERSUM, OXTRANSSTATUS, payment_provider_order_id)
                VALUES (:oxid, :oxtotalordersum, :oxtransstatus, :payment_provider_order_id)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
    }

    private function createCategory(string $name): void
    {
        $sql = "INSERT INTO oxcategories (OXID, OXTITLE) VALUES (:id, :title)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => uniqid('cat_'), 'title' => $name]);
    }
}
```

**Usage in E2E Tests:**

```php
// Seed database for checkout scenario
$seeder = new DatabaseSeeder($pdo);
$seeder->seed('checkout');

// Seed for admin capture scenario
$seeder->seed('admin-capture');
```

---

## Mocking Strategy

### 1. Mock External APIs (Provider APIs)

**Strategy:**
- **Unit Tests:** Full mocks using Mockery
- **Integration Tests:** WireMock for HTTP mocking
- **E2E Tests:** Provider sandbox environments

**Example: Mocking Stripe API in Unit Tests**

```php
use Mockery;
use Stripe\StripeClient;
use Stripe\PaymentIntent;

// Mock Stripe client
$stripeMock = Mockery::mock(StripeClient::class);

// Mock createPaymentIntent method
$stripeMock->paymentIntents = Mockery::mock();
$stripeMock->paymentIntents
    ->shouldReceive('create')
    ->once()
    ->with([
        'amount' => 9999,
        'currency' => 'usd',
        'payment_method_types' => ['card'],
    ])
    ->andReturn(new PaymentIntent([
        'id' => 'pi_test_123',
        'status' => 'requires_capture',
        'amount' => 9999,
    ]));

// Inject into service
$paymentService->setStripeClient($stripeMock);
```

**Example: WireMock for Integration Tests**

```yaml
# tests/wiremock/stripe-create-payment-intent.json
{
  "request": {
    "method": "POST",
    "url": "/v1/payment_intents"
  },
  "response": {
    "status": 200,
    "headers": {
      "Content-Type": "application/json"
    },
    "jsonBody": {
      "id": "pi_test_123",
      "object": "payment_intent",
      "amount": 9999,
      "currency": "usd",
      "status": "requires_capture"
    }
  }
}
```

```bash
# Start WireMock
docker run -d -p 8080:8080 \
  -v $(pwd)/tests/wiremock:/home/wiremock \
  wiremock/wiremock
```

---

### 2. Mock Internal Services

**Example: Mocking EventDispatcher**

```php
use Mockery;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use PaymentComponent\Event\PaymentCapturedEvent;

$dispatcherMock = Mockery::mock(EventDispatcherInterface::class);

// Verify event is dispatched
$dispatcherMock
    ->shouldReceive('dispatch')
    ->once()
    ->with(Mockery::type(PaymentCapturedEvent::class))
    ->andReturnUsing(function ($event) {
        // Can also inspect event properties
        $this->assertEquals('order-123', $event->getOrderId());
        return $event;
    });

// Inject into handler
$handler = new PaymentCaptureHandler(
    $paymentService,
    $orderRepository,
    $dispatcherMock
);
```

**Example: Mocking Logger**

```php
use Psr\Log\LoggerInterface;
use Mockery;

$loggerMock = Mockery::mock(LoggerInterface::class);

// Verify logging calls
$loggerMock
    ->shouldReceive('info')
    ->once()
    ->with('Payment captured', Mockery::on(function ($context) {
        return isset($context['order_id']) && isset($context['capture_id']);
    }));

// Inject into service
$paymentService = new PaymentService($loggerMock, ...);
```

---

### 3. Mock Repositories (Unit Tests Only)

**Example: Mocking OrderRepository**

```php
use Mockery;
use PaymentComponent\Repository\OrderRepository;
use PaymentComponent\Model\Order;

$orderRepoMock = Mockery::mock(OrderRepository::class);

// Mock getById
$order = OrderBuilder::new()->authorized()->build();
$orderRepoMock
    ->shouldReceive('getById')
    ->once()
    ->with('order-123')
    ->andReturn($order);

// Mock save
$orderRepoMock
    ->shouldReceive('save')
    ->once()
    ->with(Mockery::type(Order::class))
    ->andReturn(true);

// Inject into handler
$handler = new PaymentCaptureHandler($paymentService, $orderRepoMock, $dispatcher);
```

---

## Coverage Goals

### Overall Coverage Targets

| Metric | Target | Current | Status |
|--------|--------|---------|--------|
| **Line Coverage** | 85% | TBD | 🟡 In Progress |
| **Branch Coverage** | 80% | TBD | 🟡 In Progress |
| **Method Coverage** | 90% | TBD | 🟡 In Progress |

### Coverage by Layer

| Layer | Line Coverage | Branch Coverage | Priority |
|-------|---------------|-----------------|----------|
| **Event Layer** | 100% | 100% | 🔴 Critical |
| **Domain Layer** | 95% | 90% | 🔴 Critical |
| **Service Layer** | 90% | 85% | 🔴 Critical |
| **Repository Layer** | 100% | 100% | 🔴 Critical |
| **Factory Layer** | 85% | 80% | 🟡 High |
| **Controller Layer** | 80% | 75% | 🟡 High |
| **Webhook System** | 100% | 100% | 🔴 Critical |

### Generating Coverage Reports

```bash
# Generate HTML coverage report
vendor/bin/phpunit --coverage-html coverage/

# Generate Clover XML for CI
vendor/bin/phpunit --coverage-clover coverage.xml

# Check coverage threshold (fail if < 85%)
vendor/bin/phpunit --coverage-text --coverage-clover=coverage.xml
php coverage-check.php coverage.xml 85
```

---

## CI/CD Pipeline

### GitHub Actions Workflow

```yaml
# .github/workflows/tests.yml

name: Test Suite

on: [push, pull_request]

jobs:
  unit-tests:
    runs-on: ubuntu-latest
    timeout-minutes: 5

    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: mbstring, xml, pdo, pdo_mysql
          coverage: xdebug

      - name: Install dependencies
        run: composer install --prefer-dist --no-progress

      - name: Run unit tests
        run: vendor/bin/phpunit --testsuite=Unit --coverage-clover=coverage.xml

      - name: Upload coverage
        uses: codecov/codecov-action@v3
        with:
          files: ./coverage.xml

  integration-tests:
    runs-on: ubuntu-latest
    timeout-minutes: 10

    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: test_payment
        ports:
          - 3306:3306

    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Install dependencies
        run: composer install --prefer-dist --no-progress

      - name: Run migrations
        run: php migrations/run.php

      - name: Run integration tests
        run: vendor/bin/phpunit --testsuite=Integration

  e2e-tests:
    runs-on: ubuntu-latest
    timeout-minutes: 20

    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Install dependencies
        run: composer install --prefer-dist --no-progress

      - name: Start application
        run: |
          php -S localhost:8000 -t public &
          sleep 5

      - name: Install Playwright
        run: npx playwright install --with-deps

      - name: Run E2E tests
        run: vendor/bin/codecept run e2e
        env:
          STRIPE_TEST_KEY: ${{ secrets.STRIPE_TEST_KEY }}
          PAYPAL_SANDBOX_CLIENT_ID: ${{ secrets.PAYPAL_SANDBOX_CLIENT_ID }}
```

---

## Best Practices

### 1. Test Naming Conventions

**Format:** `test{MethodName}{Scenario}{ExpectedOutcome}`

**Examples:**
- ✅ `testCreatePaymentOrderCallsProviderApi()`
- ✅ `testMarkAsPaymentCompletedSetsOxpaidTimestamp()`
- ✅ `testHandleCaptureThrowsExceptionForInvalidState()`

**Avoid:**
- ❌ `testOrder()` (too vague)
- ❌ `testPayment1()` (meaningless)

---

### 2. AAA Pattern (Arrange-Act-Assert)

```php
public function testExampleMethod(): void
{
    // Arrange - Set up test data and mocks
    $order = OrderBuilder::new()->authorized()->build();
    $service = new PaymentService(...);

    // Act - Execute the method under test
    $result = $service->capturePayment($order);

    // Assert - Verify the expected outcome
    $this->assertTrue($result->isSuccess());
    $this->assertEquals('CAPTURED', $result->getStatus());
}
```

---

### 3. One Assertion Focus Per Test

**Good:**
```php
public function testOrderStateTransitionToCompleted(): void
{
    $order = OrderBuilder::new()->authorized()->build();
    $order->markAsPaymentCompleted();

    $this->assertEquals('COMPLETED', $order->getPaymentState());
}

public function testOrderPaidTimestampSetOnCompletion(): void
{
    $order = OrderBuilder::new()->authorized()->build();
    $order->markAsPaymentCompleted();

    $this->assertNotNull($order->getOxpaid());
}
```

**Also Acceptable (related assertions):**
```php
public function testOrderCompletionSetsMultipleFields(): void
{
    $order = OrderBuilder::new()->authorized()->build();
    $order->markAsPaymentCompleted();

    $this->assertEquals('COMPLETED', $order->getPaymentState());
    $this->assertNotNull($order->getOxpaid());
    $this->assertTrue($order->isOrderPaid());
}
```

---

### 4. Use Data Providers for Multiple Scenarios

```php
/**
 * @dataProvider amountCalculationProvider
 */
public function testRefundableAmount(
    float $total,
    float $refunded,
    float $expected
): void {
    $order = OrderBuilder::new()
        ->withAmount($total)
        ->build();
    $order->setRefundedAmount($refunded);

    $this->assertEquals($expected, $order->getRefundableAmount());
}

public function amountCalculationProvider(): array
{
    return [
        'No refunds' => [100.00, 0.00, 100.00],
        'Partial refund' => [100.00, 30.00, 70.00],
        'Full refund' => [100.00, 100.00, 0.00],
        'Over-refund prevented' => [100.00, 150.00, 0.00],
    ];
}
```

---

### 5. Test Isolation

**Each test must be independent:**

```php
// ✅ Good - Test creates its own data
public function testExample(): void
{
    $order = OrderBuilder::new()->build();
    // Test logic
}

// ❌ Bad - Test depends on previous test
private Order $sharedOrder;

public function testCreate(): void
{
    $this->sharedOrder = new Order();
}

public function testUpdate(): void
{
    $this->sharedOrder->update(); // Depends on testCreate
}
```

---

### 6. Meaningful Assertion Messages

```php
// ✅ Good - Clear failure message
$this->assertEquals(
    'CAPTURED',
    $order->getPaymentState(),
    'Order state should be CAPTURED after successful payment'
);

// ✅ Good - Use assertSame for strict comparison
$this->assertSame(
    99.99,
    $order->getTotalAmount(),
    'Total amount should match exactly (float comparison)'
);

// ❌ Bad - No message
$this->assertEquals('CAPTURED', $order->getPaymentState());
```

---

### 7. Cleanup in tearDown()

```php
protected function tearDown(): void
{
    // Rollback database transaction (integration tests)
    if (self::$pdo && self::$pdo->inTransaction()) {
        self::$pdo->rollBack();
    }

    // Close Mockery (unit tests)
    Mockery::close();

    // Clean up files/temp data
    $this->cleanupTestFiles();

    parent::tearDown();
}
```

---

### 8. Skip Slow Tests in Development

```php
/**
 * @group slow
 * @group e2e
 */
public function testCompleteCheckoutFlow(): void
{
    // E2E test that takes 10 seconds
}
```

```bash
# Run only fast tests during development
vendor/bin/phpunit --exclude-group=slow

# Run all tests before committing
vendor/bin/phpunit
```

---

## Summary

This TDD strategy provides:

✅ **60% Unit Tests** - Fast, isolated, comprehensive coverage
✅ **30% Integration Tests** - Real database, event flow verification
✅ **10% E2E Tests** - Critical user flows, full system validation
✅ **85%+ Coverage** - High confidence in code quality
✅ **Fast Feedback** - Unit tests in < 5 seconds
✅ **CI/CD Pipeline** - Automated testing on every commit
✅ **Clear Fixtures** - Builders, factories, seeders for all test types
✅ **Mocking Strategy** - Appropriate mocking at each layer

**Next Steps:**

1. Set up test infrastructure (PHPUnit, TestContainers, Codeception)
2. Create fixture builders and factories
3. Write unit tests for critical components (TDD: Red → Green → Refactor)
4. Add integration tests for event flows
5. Implement E2E tests for critical paths
6. Set up CI/CD pipeline
7. Monitor coverage and maintain 85%+ target

---

**Visual Diagram:** [puml/10-tdd-strategy.puml](puml/10-tdd-strategy.puml)

**Version:** 1.0.0
**Last Updated:** 2025-10-13
**Author:** Payment Component Team
