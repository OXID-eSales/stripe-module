# Testing Framework for Provider-Specific Payment Modules

**Version:** 1.0.0
**Date:** 2025-10-13
**Purpose:** Testing strategy for Stripe, Paymenter, Adyen, and other provider modules built on the payment component

---

## Table of Contents

1. [Overview](#overview)
2. [Provider Module Test Structure](#provider-module-test-structure)
3. [Test Categories](#test-categories)
4. [Provider API Mocking](#provider-api-mocking)
5. [Stripe Module Tests](#stripe-module-tests)
6. [Paymenter Module Tests](#paymenter-module-tests)
7. [Cross-Provider Test Suite](#cross-provider-test-suite)
8. [Sandbox Testing](#sandbox-testing)
9. [Test Data & Fixtures](#test-data--fixtures)
10. [CI/CD Integration](#cicd-integration)

---

## Overview

Provider-specific modules (Stripe, Paymenter, Adyen, etc.) extend the base payment component with provider-specific implementations. Testing these modules requires a combination of:

- **Component Tests**: Verify provider module integrates correctly with base component
- **API Contract Tests**: Ensure provider API integration is correct
- **Sandbox Tests**: Validate against real provider sandbox environments
- **Cross-Provider Tests**: Ensure consistent behavior across all providers

### Testing Philosophy

```
┌─────────────────────────────────────────────────────┐
│         Payment Component (Base)                    │
│         ↑ Tested independently (see 08-tdd-strategy)│
└─────────────────────────────────────────────────────┘
                        ↑
                        │ extends
                        │
┌─────────────┬─────────────┬─────────────┬───────────┐
│   Stripe    │   Paymenter    │   Adyen     │  Amazon   │
│   Module    │   Module    │   Module    │  Module   │
│             │             │             │           │
│   Provider-specific tests focus on:                 │
│   • API integration                                 │
│   • Request/response mapping                        │
│   • Provider-specific features                      │
│   • Error handling                                  │
└─────────────────────────────────────────────────────┘
```

---

## Provider Module Test Structure

### Directory Structure

```
tests/
├── Component/              # Base component tests (existing)
│   ├── Unit/
│   ├── Integration/
│   └── E2E/
│
├── Providers/             # Provider-specific tests
│   ├── Stripe/
│   │   ├── Unit/
│   │   │   ├── StripePaymentServiceTest.php
│   │   │   ├── StripeRequestFactoryTest.php
│   │   │   ├── StripeWebhookHandlerTest.php
│   │   │   └── StripeErrorMapperTest.php
│   │   ├── Integration/
│   │   │   ├── StripeApiClientTest.php
│   │   │   ├── StripePaymentFlowTest.php
│   │   │   └── StripeWebhookProcessingTest.php
│   │   ├── Sandbox/
│   │   │   ├── StripeCheckoutFlowTest.php
│   │   │   ├── Stripe3DSecureTest.php
│   │   │   └── StripeRefundFlowTest.php
│   │   └── Fixtures/
│   │       ├── StripeApiResponseFactory.php
│   │       └── StripeTestCards.php
│   │
│   ├── Paymenter/
│   │   ├── Unit/
│   │   ├── Integration/
│   │   ├── Sandbox/
│   │   └── Fixtures/
│   │
│   ├── Adyen/
│   │   └── [same structure]
│   │
│   └── CrossProvider/     # Tests that run across all providers
│       ├── ConsistencyTest.php
│       ├── FeatureParityTest.php
│       └── PerformanceComparisonTest.php
│
└── Fixtures/
    ├── Providers/
    │   ├── StripeFixtures.php
    │   ├── PaymenterFixtures.php
    │   └── AdyenFixtures.php
    └── WireMock/
        ├── stripe/
        │   ├── create-payment-intent.json
        │   ├── capture-payment.json
        │   └── webhook-payment-succeeded.json
        ├── paymenter/
        └── adyen/
```

---

## Test Categories

### 1. Provider-Specific Unit Tests

**Focus:** Test provider-specific code in isolation

**What to Test:**
- API request builders
- API response parsers
- Error code mapping
- Provider-specific validation
- Webhook signature verification
- Provider data transformations

**Example: Stripe Request Factory Test**

```php
<?php
// tests/Providers/Stripe/Unit/StripeRequestFactoryTest.php

namespace PaymentComponent\Tests\Providers\Stripe\Unit;

use PaymentComponent\Providers\Stripe\Factory\StripeRequestFactory;
use PaymentComponent\Model\Basket;
use PaymentComponent\Tests\Fixtures\Builders\BasketBuilder;
use PHPUnit\Framework\TestCase;

class StripeRequestFactoryTest extends TestCase
{
    private StripeRequestFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new StripeRequestFactory();
    }

    public function testBuildPaymentIntentRequest(): void
    {
        // Arrange
        $basket = BasketBuilder::new()
            ->withTotal(99.99)
            ->withCurrency('USD')
            ->withItems([
                ['name' => 'Product 1', 'price' => 99.99, 'quantity' => 1]
            ])
            ->build();

        // Act
        $request = $this->factory->buildPaymentIntentRequest($basket, 'card');

        // Assert
        $this->assertEquals(9999, $request['amount']); // cents
        $this->assertEquals('usd', $request['currency']);
        $this->assertArrayHasKey('payment_method_types', $request);
        $this->assertContains('card', $request['payment_method_types']);
        $this->assertArrayHasKey('metadata', $request);
        $this->assertEquals('Product 1', $request['metadata']['line_items'][0]['description']);
    }

    public function testBuildCaptureRequest(): void
    {
        // Arrange
        $paymentIntentId = 'pi_test_123';
        $amount = 50.00;

        // Act
        $request = $this->factory->buildCaptureRequest($paymentIntentId, $amount);

        // Assert
        $this->assertEquals(5000, $request['amount_to_capture']);
    }

    public function testConvertAmountToCents(): void
    {
        // Test various amounts
        $this->assertEquals(9999, $this->factory->convertToCents(99.99));
        $this->assertEquals(10000, $this->factory->convertToCents(100.00));
        $this->assertEquals(1, $this->factory->convertToCents(0.01));
    }

    /**
     * @dataProvider errorCodeProvider
     */
    public function testMapStripeErrorToComponentError(
        string $stripeErrorCode,
        string $expectedComponentError
    ): void {
        // Arrange
        $stripeError = ['code' => $stripeErrorCode, 'message' => 'Test error'];

        // Act
        $componentError = $this->factory->mapError($stripeError);

        // Assert
        $this->assertEquals($expectedComponentError, $componentError->getCode());
    }

    public function errorCodeProvider(): array
    {
        return [
            'Card declined' => ['card_declined', 'PAYMENT_DECLINED'],
            'Insufficient funds' => ['insufficient_funds', 'PAYMENT_DECLINED'],
            'Invalid card' => ['invalid_card_number', 'INVALID_PAYMENT_METHOD'],
            'Expired card' => ['expired_card', 'INVALID_PAYMENT_METHOD'],
            'Generic error' => ['api_error', 'PROVIDER_ERROR'],
        ];
    }
}
```

---

### 2. Provider-Specific Integration Tests

**Focus:** Test provider API integration with mocked HTTP

**What to Test:**
- API client communication
- Request/response cycle
- Authentication
- Error handling
- Retry logic
- Idempotency

**Example: Stripe API Client Test with WireMock**

```php
<?php
// tests/Providers/Stripe/Integration/StripeApiClientTest.php

namespace PaymentComponent\Tests\Providers\Stripe\Integration;

use PaymentComponent\Providers\Stripe\Client\StripeApiClient;
use PaymentComponent\Tests\Integration\DatabaseTestCase;
use PaymentComponent\Tests\Fixtures\WireMock\WireMockServer;

class StripeApiClientTest extends DatabaseTestCase
{
    private StripeApiClient $client;
    private WireMockServer $wireMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Start WireMock server
        $this->wireMock = new WireMockServer('http://localhost:8080');
        $this->wireMock->start();

        // Create client pointing to WireMock
        $this->client = new StripeApiClient([
            'api_key' => 'sk_test_mock',
            'base_url' => 'http://localhost:8080',
        ]);
    }

    protected function tearDown(): void
    {
        $this->wireMock->stop();
        parent::tearDown();
    }

    public function testCreatePaymentIntent(): void
    {
        // Arrange - Load WireMock stub
        $this->wireMock->stubFor([
            'request' => [
                'method' => 'POST',
                'url' => '/v1/payment_intents',
            ],
            'response' => [
                'status' => 200,
                'jsonBody' => [
                    'id' => 'pi_test_123',
                    'object' => 'payment_intent',
                    'amount' => 9999,
                    'currency' => 'usd',
                    'status' => 'requires_payment_method',
                    'client_secret' => 'pi_test_123_secret',
                ],
            ],
        ]);

        // Act
        $paymentIntent = $this->client->createPaymentIntent([
            'amount' => 9999,
            'currency' => 'usd',
        ]);

        // Assert
        $this->assertEquals('pi_test_123', $paymentIntent->id);
        $this->assertEquals(9999, $paymentIntent->amount);
        $this->assertEquals('requires_payment_method', $paymentIntent->status);
    }

    public function testHandlesApiError(): void
    {
        // Arrange - Mock API error response
        $this->wireMock->stubFor([
            'request' => [
                'method' => 'POST',
                'url' => '/v1/payment_intents',
            ],
            'response' => [
                'status' => 402,
                'jsonBody' => [
                    'error' => [
                        'type' => 'card_error',
                        'code' => 'card_declined',
                        'message' => 'Your card was declined.',
                    ],
                ],
            ],
        ]);

        // Act & Assert
        $this->expectException(\PaymentException::class);
        $this->expectExceptionMessage('Your card was declined');

        $this->client->createPaymentIntent([
            'amount' => 9999,
            'currency' => 'usd',
        ]);
    }

    public function testRetriesOnNetworkError(): void
    {
        // Arrange - First call fails, second succeeds
        $this->wireMock->stubFor([
            'request' => [
                'method' => 'POST',
                'url' => '/v1/payment_intents',
            ],
            'response' => [
                'status' => 500,
            ],
            'atRequest' => 1, // First request
        ]);

        $this->wireMock->stubFor([
            'request' => [
                'method' => 'POST',
                'url' => '/v1/payment_intents',
            ],
            'response' => [
                'status' => 200,
                'jsonBody' => [
                    'id' => 'pi_test_retry',
                    'status' => 'requires_payment_method',
                ],
            ],
            'atRequest' => 2, // Second request (retry)
        ]);

        // Act
        $paymentIntent = $this->client->createPaymentIntent([
            'amount' => 9999,
            'currency' => 'usd',
        ]);

        // Assert
        $this->assertEquals('pi_test_retry', $paymentIntent->id);
        $this->assertEquals(2, $this->wireMock->getRequestCount('/v1/payment_intents'));
    }

    public function testIdempotencyKeyPreventsDoubleCharge(): void
    {
        // Arrange
        $idempotencyKey = 'test-idempotency-key-123';

        $this->wireMock->stubFor([
            'request' => [
                'method' => 'POST',
                'url' => '/v1/payment_intents',
                'headers' => [
                    'Idempotency-Key' => ['equalTo' => $idempotencyKey],
                ],
            ],
            'response' => [
                'status' => 200,
                'jsonBody' => ['id' => 'pi_test_same'],
            ],
        ]);

        // Act - Make same request twice with same idempotency key
        $result1 = $this->client->createPaymentIntent(
            ['amount' => 9999],
            ['idempotency_key' => $idempotencyKey]
        );

        $result2 = $this->client->createPaymentIntent(
            ['amount' => 9999],
            ['idempotency_key' => $idempotencyKey]
        );

        // Assert - Same result returned
        $this->assertEquals($result1->id, $result2->id);
    }
}
```

---

### 3. Provider Sandbox Tests (E2E)

**Focus:** Test against real provider sandbox/test environments

**What to Test:**
- Complete payment flows with real API
- 3D Secure / SCA flows
- Webhook delivery
- Refund operations
- Multi-currency support

**Example: Stripe Sandbox Test**

```php
<?php
// tests/Providers/Stripe/Sandbox/StripeCheckoutFlowTest.php

namespace PaymentComponent\Tests\Providers\Stripe\Sandbox;

use PaymentComponent\Tests\E2E\SandboxTestCase;
use PaymentComponent\Providers\Stripe\StripePaymentService;

/**
 * @group sandbox
 * @group stripe
 * @group slow
 */
class StripeCheckoutFlowTest extends SandboxTestCase
{
    private StripePaymentService $paymentService;

    protected function setUp(): void
    {
        parent::setUp();

        // Use real Stripe test API keys
        $this->paymentService = new StripePaymentService([
            'api_key' => getenv('STRIPE_TEST_SECRET_KEY'),
            'publishable_key' => getenv('STRIPE_TEST_PUBLISHABLE_KEY'),
        ]);
    }

    public function testCompletePaymentFlowWithTestCard(): void
    {
        // Arrange - Create test order
        $order = $this->createTestOrder(99.99);

        // Act - Step 1: Create Payment Intent
        $paymentIntent = $this->paymentService->createPaymentOrder(
            $order->getBasket(),
            'capture',
            ['return_url' => 'https://test.example.com/success']
        );

        $this->assertNotNull($paymentIntent->getId());
        $this->assertEquals('requires_payment_method', $paymentIntent->getStatus());

        // Act - Step 2: Attach payment method (simulate customer entering card)
        $paymentMethod = $this->createTestPaymentMethod('4242424242424242'); // Visa test card

        $paymentIntent = $this->paymentService->attachPaymentMethod(
            $paymentIntent->getId(),
            $paymentMethod->id
        );

        // Act - Step 3: Confirm payment
        $paymentIntent = $this->paymentService->confirmPayment(
            $paymentIntent->getId()
        );

        // Assert
        $this->assertEquals('succeeded', $paymentIntent->getStatus());

        // Verify order updated
        $updatedOrder = $this->orderRepository->getById($order->getId());
        $this->assertEquals('CAPTURED', $updatedOrder->getPaymentState());
        $this->assertNotNull($updatedOrder->getPaymentProviderOrderId());
    }

    public function testDeclinedCard(): void
    {
        // Arrange
        $order = $this->createTestOrder(99.99);

        // Create payment intent
        $paymentIntent = $this->paymentService->createPaymentOrder(
            $order->getBasket(),
            'capture',
            []
        );

        // Act - Use card that will be declined (Stripe test card)
        $paymentMethod = $this->createTestPaymentMethod('4000000000000002'); // Declined card

        // Assert
        $this->expectException(\PaymentDeclinedException::class);
        $this->expectExceptionMessage('card was declined');

        $this->paymentService->attachPaymentMethod(
            $paymentIntent->getId(),
            $paymentMethod->id
        );
    }

    public function test3DSecureAuthentication(): void
    {
        // Arrange
        $order = $this->createTestOrder(150.00); // Amount that triggers 3DS

        // Act - Create payment intent
        $paymentIntent = $this->paymentService->createPaymentOrder(
            $order->getBasket(),
            'capture',
            ['return_url' => 'https://test.example.com/success']
        );

        // Use 3DS test card
        $paymentMethod = $this->createTestPaymentMethod('4000002500003155'); // 3DS required

        $paymentIntent = $this->paymentService->attachPaymentMethod(
            $paymentIntent->getId(),
            $paymentMethod->id
        );

        // Assert - Requires authentication
        $this->assertEquals('requires_action', $paymentIntent->getStatus());
        $this->assertNotNull($paymentIntent->getNextAction());
        $this->assertEquals('redirect_to_url', $paymentIntent->getNextAction()->type);

        // Simulate 3DS completion (in real test, this would be done via browser automation)
        // For now, we just verify the flow requires authentication
    }

    public function testRefundFlow(): void
    {
        // Arrange - Complete a successful payment first
        $order = $this->createTestOrder(99.99);
        $paymentIntent = $this->completeTestPayment($order, '4242424242424242');

        // Act - Issue refund
        $refund = $this->paymentService->refundPayment(
            $paymentIntent->getId(),
            99.99,
            'Customer requested refund'
        );

        // Assert
        $this->assertEquals('succeeded', $refund->getStatus());
        $this->assertEquals(9999, $refund->getAmount());

        // Verify order updated
        $updatedOrder = $this->orderRepository->getById($order->getId());
        $this->assertEquals('REFUNDED', $updatedOrder->getPaymentState());
    }

    public function testPartialRefund(): void
    {
        // Arrange - Complete payment
        $order = $this->createTestOrder(100.00);
        $paymentIntent = $this->completeTestPayment($order, '4242424242424242');

        // Act - Partial refund
        $refund = $this->paymentService->refundPayment(
            $paymentIntent->getId(),
            50.00,
            'Partial refund - one item'
        );

        // Assert
        $this->assertEquals('succeeded', $refund->getStatus());
        $this->assertEquals(5000, $refund->getAmount());

        // Verify order state
        $updatedOrder = $this->orderRepository->getById($order->getId());
        $this->assertEquals('PARTIALLY_REFUNDED', $updatedOrder->getPaymentState());
        $this->assertEquals(50.00, $updatedOrder->getRefundableAmount());
    }

    private function createTestPaymentMethod(string $cardNumber): object
    {
        // Create payment method using Stripe API
        return $this->paymentService->getStripeClient()->paymentMethods->create([
            'type' => 'card',
            'card' => [
                'number' => $cardNumber,
                'exp_month' => 12,
                'exp_year' => date('Y') + 1,
                'cvc' => '123',
            ],
        ]);
    }

    private function completeTestPayment(Order $order, string $cardNumber): object
    {
        $paymentIntent = $this->paymentService->createPaymentOrder(
            $order->getBasket(),
            'capture',
            []
        );

        $paymentMethod = $this->createTestPaymentMethod($cardNumber);

        $paymentIntent = $this->paymentService->attachPaymentMethod(
            $paymentIntent->getId(),
            $paymentMethod->id
        );

        return $this->paymentService->confirmPayment($paymentIntent->getId());
    }
}
```

---

## Provider API Mocking

### WireMock Setup

**Installation:**

```bash
# Start WireMock in Docker
docker run -d -p 8080:8080 \
  -v $(pwd)/tests/Fixtures/WireMock:/home/wiremock \
  --name wiremock \
  wiremock/wiremock
```

**Stripe API Mock Example:**

```json
// tests/Fixtures/WireMock/stripe/create-payment-intent.json
{
  "request": {
    "method": "POST",
    "urlPath": "/v1/payment_intents",
    "headers": {
      "Authorization": {
        "matches": "Bearer sk_test_.*"
      }
    },
    "bodyPatterns": [
      {
        "matchesJsonPath": "$.amount"
      },
      {
        "matchesJsonPath": "$.currency"
      }
    ]
  },
  "response": {
    "status": 200,
    "headers": {
      "Content-Type": "application/json"
    },
    "jsonBody": {
      "id": "pi_mock_{{randomValue type='UUID'}}",
      "object": "payment_intent",
      "amount": "{{jsonPath request.body '$.amount'}}",
      "currency": "{{jsonPath request.body '$.currency'}}",
      "status": "requires_payment_method",
      "client_secret": "pi_mock_secret_{{randomValue type='ALPHANUMERIC' length=32}}",
      "created": "{{now}}",
      "livemode": false
    }
  }
}
```

**Paymenter API Mock Example:**

```json
// tests/Fixtures/WireMock/paymenter/create-order.json
{
  "request": {
    "method": "POST",
    "urlPath": "/v2/checkout/orders"
  },
  "response": {
    "status": 201,
    "jsonBody": {
      "id": "{{randomValue type='ALPHANUMERIC' length=17}}",
      "status": "CREATED",
      "links": [
        {
          "href": "https://api.sandbox.paymenter.com/v2/checkout/orders/{{randomValue}}",
          "rel": "self",
          "method": "GET"
        },
        {
          "href": "https://www.sandbox.paymenter.com/checkoutnow?token={{randomValue}}",
          "rel": "approve",
          "method": "GET"
        }
      ]
    }
  }
}
```

---

## Cross-Provider Test Suite

Tests that ensure consistent behavior across all providers.

```php
<?php
// tests/Providers/CrossProvider/ConsistencyTest.php

namespace PaymentComponent\Tests\Providers\CrossProvider;

use PaymentComponent\Tests\Integration\DatabaseTestCase;
use PaymentComponent\Providers\Stripe\StripePaymentService;
use PaymentComponent\Providers\Paymenter\PaymenterPaymentService;
use PaymentComponent\Providers\Adyen\AdyenPaymentService;

/**
 * Tests that ensure all providers behave consistently
 */
class ConsistencyTest extends DatabaseTestCase
{
    /**
     * @dataProvider providerServiceProvider
     */
    public function testCreatePaymentOrderReturnsConsistentStructure($service): void
    {
        // Arrange
        $basket = $this->createTestBasket(99.99);

        // Act
        $result = $service->createPaymentOrder($basket, 'capture', []);

        // Assert - All providers must return same interface
        $this->assertInstanceOf(ProviderOrder::class, $result);
        $this->assertNotNull($result->getId());
        $this->assertEquals(99.99, $result->getAmount());
        $this->assertNotNull($result->getStatus());
        $this->assertIsString($result->getApprovalUrl());
    }

    /**
     * @dataProvider providerServiceProvider
     */
    public function testCapturePaymentReturnsConsistentStructure($service): void
    {
        // Arrange
        $order = $this->createAuthorizedOrder($service);

        // Act
        $result = $service->capturePayment($order->getProviderOrderId());

        // Assert
        $this->assertInstanceOf(CaptureResult::class, $result);
        $this->assertNotNull($result->getCaptureId());
        $this->assertEquals('CAPTURED', $result->getStatus());
    }

    /**
     * @dataProvider providerServiceProvider
     */
    public function testErrorCodesAreMappedConsistently($service): void
    {
        // Test that provider-specific errors are mapped to component errors

        // Card declined
        try {
            $this->attemptPaymentWithDeclinedCard($service);
            $this->fail('Expected PaymentDeclinedException');
        } catch (\PaymentDeclinedException $e) {
            $this->assertEquals('PAYMENT_DECLINED', $e->getCode());
        }

        // Insufficient funds
        try {
            $this->attemptPaymentWithInsufficientFunds($service);
            $this->fail('Expected PaymentDeclinedException');
        } catch (\PaymentDeclinedException $e) {
            $this->assertEquals('PAYMENT_DECLINED', $e->getCode());
        }

        // Invalid card
        try {
            $this->attemptPaymentWithInvalidCard($service);
            $this->fail('Expected InvalidPaymentMethodException');
        } catch (\InvalidPaymentMethodException $e) {
            $this->assertEquals('INVALID_PAYMENT_METHOD', $e->getCode());
        }
    }

    public function providerServiceProvider(): array
    {
        return [
            'Stripe' => [$this->createStripeService()],
            'Paymenter' => [$this->createPaymenterService()],
            'Adyen' => [$this->createAdyenService()],
        ];
    }

    private function createStripeService(): StripePaymentService
    {
        return new StripePaymentService([
            'api_key' => 'sk_test_mock',
            'base_url' => 'http://localhost:8080/stripe',
        ]);
    }

    // Similar for other providers...
}
```

---

## Test Data & Fixtures

### Provider-Specific Test Cards

```php
<?php
// tests/Fixtures/Providers/StripeTestCards.php

namespace PaymentComponent\Tests\Fixtures\Providers;

class StripeTestCards
{
    // Successful cards
    const VISA_SUCCESS = '4242424242424242';
    const VISA_DEBIT_SUCCESS = '4000056655665556';
    const MASTERCARD_SUCCESS = '5555555555554444';

    // Declined cards
    const CARD_DECLINED = '4000000000000002';
    const INSUFFICIENT_FUNDS = '4000000000009995';
    const LOST_CARD = '4000000000009987';
    const STOLEN_CARD = '4000000000009979';

    // Authentication required (3D Secure)
    const AUTHENTICATION_REQUIRED = '4000002500003155';
    const AUTHENTICATION_UNAVAILABLE = '4000008400001629';

    // Processing errors
    const PROCESSING_ERROR = '4000000000000119';
    const INCORRECT_CVC = '4000000000000127';

    public static function getSuccessCard(): array
    {
        return [
            'number' => self::VISA_SUCCESS,
            'exp_month' => 12,
            'exp_year' => date('Y') + 1,
            'cvc' => '123',
        ];
    }

    public static function getDeclinedCard(): array
    {
        return [
            'number' => self::CARD_DECLINED,
            'exp_month' => 12,
            'exp_year' => date('Y') + 1,
            'cvc' => '123',
        ];
    }

    public static function get3DSCard(): array
    {
        return [
            'number' => self::AUTHENTICATION_REQUIRED,
            'exp_month' => 12,
            'exp_year' => date('Y') + 1,
            'cvc' => '123',
        ];
    }
}
```

```php
<?php
// tests/Fixtures/Providers/PaymenterTestAccounts.php

namespace PaymentComponent\Tests\Fixtures\Providers;

class PaymenterTestAccounts
{
    // Sandbox buyer accounts
    const BUYER_EMAIL = 'buyer@test.paymenter.com';
    const BUYER_PASSWORD = 'test12345';

    // Sandbox business accounts
    const MERCHANT_EMAIL = 'merchant@test.paymenter.com';
    const MERCHANT_PASSWORD = 'merchant12345';

    public static function getBuyerCredentials(): array
    {
        return [
            'email' => self::BUYER_EMAIL,
            'password' => self::BUYER_PASSWORD,
        ];
    }
}
```

### Provider Response Factories

```php
<?php
// tests/Fixtures/Providers/StripeApiResponseFactory.php

namespace PaymentComponent\Tests\Fixtures\Providers;

class StripeApiResponseFactory
{
    public static function createPaymentIntentResponse(array $overrides = []): array
    {
        return array_merge([
            'id' => 'pi_test_' . uniqid(),
            'object' => 'payment_intent',
            'amount' => 9999,
            'currency' => 'usd',
            'status' => 'requires_payment_method',
            'client_secret' => 'pi_test_secret_' . bin2hex(random_bytes(16)),
            'created' => time(),
            'livemode' => false,
            'payment_method_types' => ['card'],
            'metadata' => [],
        ], $overrides);
    }

    public static function createChargeResponse(array $overrides = []): array
    {
        return array_merge([
            'id' => 'ch_test_' . uniqid(),
            'object' => 'charge',
            'amount' => 9999,
            'currency' => 'usd',
            'status' => 'succeeded',
            'paid' => true,
            'captured' => true,
            'created' => time(),
        ], $overrides);
    }

    public static function createRefundResponse(array $overrides = []): array
    {
        return array_merge([
            'id' => 're_test_' . uniqid(),
            'object' => 'refund',
            'amount' => 9999,
            'currency' => 'usd',
            'status' => 'succeeded',
            'charge' => 'ch_test_123',
            'created' => time(),
        ], $overrides);
    }

    public static function createErrorResponse(
        string $type,
        string $code,
        string $message
    ): array {
        return [
            'error' => [
                'type' => $type,
                'code' => $code,
                'message' => $message,
                'param' => null,
            ],
        ];
    }
}
```

---

## CI/CD Integration

### GitHub Actions Workflow

```yaml
# .github/workflows/provider-tests.yml

name: Provider Module Tests

on: [push, pull_request]

jobs:
  # Unit tests for all providers
  provider-unit-tests:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        provider: [stripe, paymenter, adyen]

    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Install dependencies
        run: composer install

      - name: Run ${{ matrix.provider }} unit tests
        run: |
          vendor/bin/phpunit \
            --testsuite=Providers \
            --filter=${{ matrix.provider }} \
            --group=unit

  # Integration tests with WireMock
  provider-integration-tests:
    runs-on: ubuntu-latest

    services:
      wiremock:
        image: wiremock/wiremock
        ports:
          - 8080:8080
        volumes:
          - ./tests/Fixtures/WireMock:/home/wiremock

    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Install dependencies
        run: composer install

      - name: Wait for WireMock
        run: |
          timeout 30 bash -c 'until curl -f http://localhost:8080/__admin/; do sleep 1; done'

      - name: Run integration tests
        run: |
          vendor/bin/phpunit \
            --testsuite=Providers \
            --group=integration

  # Sandbox tests (only on main branch)
  provider-sandbox-tests:
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'

    strategy:
      matrix:
        provider: [stripe, paymenter]

    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Install dependencies
        run: composer install

      - name: Run ${{ matrix.provider }} sandbox tests
        env:
          STRIPE_TEST_SECRET_KEY: ${{ secrets.STRIPE_TEST_SECRET_KEY }}
          STRIPE_TEST_PUBLISHABLE_KEY: ${{ secrets.STRIPE_TEST_PUBLISHABLE_KEY }}
          PAYPAL_SANDBOX_CLIENT_ID: ${{ secrets.PAYPAL_SANDBOX_CLIENT_ID }}
          PAYPAL_SANDBOX_SECRET: ${{ secrets.PAYPAL_SANDBOX_SECRET }}
        run: |
          vendor/bin/phpunit \
            --testsuite=Providers \
            --filter=${{ matrix.provider }} \
            --group=sandbox
```

### PHPUnit Configuration

```xml
<!-- phpunit.xml -->
<phpunit>
    <testsuites>
        <!-- Base component tests -->
        <testsuite name="Component">
            <directory>tests/Component</directory>
        </testsuite>

        <!-- Provider-specific tests -->
        <testsuite name="Providers">
            <directory>tests/Providers</directory>
        </testsuite>

        <!-- Individual provider suites -->
        <testsuite name="Stripe">
            <directory>tests/Providers/Stripe</directory>
        </testsuite>

        <testsuite name="Paymenter">
            <directory>tests/Providers/Paymenter</directory>
        </testsuite>

        <testsuite name="Adyen">
            <directory>tests/Providers/Adyen</directory>
        </testsuite>

        <!-- Cross-provider tests -->
        <testsuite name="CrossProvider">
            <directory>tests/Providers/CrossProvider</directory>
        </testsuite>
    </testsuites>

    <groups>
        <exclude>
            <group>sandbox</group>
            <group>slow</group>
        </exclude>
    </groups>
</phpunit>
```

---

## Best Practices

### 1. Provider Isolation

- Each provider module should be testable independently
- Don't create dependencies between provider modules
- Share common test utilities via traits/base classes

### 2. Test Data Management

```php
<?php
// Use provider-specific fixture classes
use PaymentComponent\Tests\Fixtures\Providers\StripeFixtures;

// Good
$paymentIntent = StripeFixtures::createPaymentIntentResponse([
    'amount' => 9999,
    'status' => 'succeeded',
]);

// Bad - hardcoding provider responses in tests
$paymentIntent = [
    'id' => 'pi_123',
    'amount' => 9999,
    // ... lots of fields
];
```

### 3. Sandbox Test Stability

```php
<?php
// Always clean up after sandbox tests
protected function tearDown(): void
{
    // Cancel any pending payment intents
    $this->cleanupTestPaymentIntents();

    // Delete test customers
    $this->cleanupTestCustomers();

    parent::tearDown();
}
```

### 4. API Version Testing

```php
<?php
// Test against multiple API versions if provider supports it
/**
 * @dataProvider apiVersionProvider
 */
public function testPaymentFlowAcrossApiVersions(string $apiVersion): void
{
    $service = new StripePaymentService([
        'api_key' => 'sk_test_...',
        'api_version' => $apiVersion,
    ]);

    // Test payment flow
    // ...
}

public function apiVersionProvider(): array
{
    return [
        '2023-10-16' => ['2023-10-16'],
        '2024-06-20' => ['2024-06-20'],
        'latest' => ['latest'],
    ];
}
```

---

## Summary

This testing framework provides:

✅ **Provider-Specific Tests** - Unit, integration, and E2E tests for each provider
✅ **API Mocking** - WireMock stubs for fast integration tests
✅ **Sandbox Testing** - Real API tests with provider test environments
✅ **Cross-Provider Tests** - Ensure consistent behavior across all providers
✅ **Test Fixtures** - Provider-specific test cards, accounts, and response factories
✅ **CI/CD Integration** - Automated testing in GitHub Actions

**Test Coverage Goals:**
- Provider modules: 85%+
- API integration: 90%+
- Critical payment flows: 100%

**Next Steps:**
1. Implement base test classes for provider modules
2. Create WireMock stubs for each provider
3. Set up sandbox test accounts
4. Add provider tests to CI/CD pipeline
5. Monitor test stability and flakiness

---

**Version:** 1.0.0
**Last Updated:** 2025-10-13
**Author:** Payment Component Team
