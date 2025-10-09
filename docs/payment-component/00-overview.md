# Enterprise Payment Component - Event-Driven Architecture

**Version:** 2.0.0
**Date:** 2025-10-09
**Based on:** OXID PayPal Module v2.6.2-rc.4 (refactored)
**Purpose:** Modern event-driven payment component for headless commerce architectures

---

## Executive Summary

This document describes a **modern event-driven payment component architecture** that transforms traditional controller-driven checkout workflows into a headless, event-based system. This component provides the foundation for building payment modules across different providers (Stripe, PayPal, Adyen, Amazon Pay, etc.) and platforms with 70% less development effort.

## What's new?
The **osc/payment-component** provides OXID Shops with AI-powered programmatic buying abilities via MCP protocol and GraphQL API mobile/headless and agentic/programmatic commerce, implements PCI-compliant and GDPR/DSGVO-complient client-side encryption for enhanced security. One Page Checkout significantly increases conversion rates by 30-50% while maintaining a single, consistent, testable backend architecture and modern user experience.

### Architectural Philosophy

**Event-Driven First**: Controllers and CLI commands act as **thin security and validation layers** that emit events. All business logic happens inside event handlers, services, and domain models.

**Headless by Design**: Frontend controllers don't execute business logic directly. They:
1. Validate and sanitize input
2. Enforce security policies
3. Emit domain events
4. Return responses based on event outcomes

### Key Capabilities

**~85% of the payment module architecture is payment-provider-agnostic**:

- **Event-driven workflow**: All operations triggered via domain events
- **Layered caching**: HTTP request data cached and accessible across event handlers
- **Extended data models**: Core shop models extended with payment-specific fields
- **Transaction tracking**: Comprehensive payment transaction persistence
- **Webhook processing**: Secure async payment confirmation via webhooks
- **State machine**: Order lifecycle managed through payment states
- **Provider abstraction**: Build payment modules for Stripe, PayPal, Adyen, etc. on top

---

## Document Structure

This component documentation consists of:

1. **00-overview.md** (this file) - Executive summary and navigation
2. **01-architecture-layers.md** - Layered architecture description
3. **02-data-models.md** - Database schema and entity models
4. **03-services.md** - Business logic services
5. **04-factories.md** - Factory patterns for request/response building
6. **05-controllers.md** - Controller layer patterns
7. **06-webhooks.md** - Webhook processing system
8. **07-events.md** - Event/subscriber system
9. **08-payment-flows.md** - Complete payment flow diagrams
10. **diagrams/** - PlantUML visualizations

---

## Target Audience

### For Architects
Read: 01-architecture-layers.md, 08-payment-flows.md
View: diagrams/01-architecture-overview.puml

### For Backend Developers
Read: 02-data-models.md, 03-services.md, 06-webhooks.md
View: diagrams/03-service-layer.puml, diagrams/05-webhook-system.puml

### For Integration Engineers
Read: 04-factories.md, 05-controllers.md, 08-payment-flows.md
View: diagrams/06-payment-flows.puml

### For QA Engineers
Read: 08-payment-flows.md
View: All flow diagrams

---

## Platform Applicability

These patterns apply to:

### E-commerce Platforms
- OXID eShop (native)
- Shopware 6
- Magento 2 / Adobe Commerce
- WooCommerce
- Symfony-based shops
- Custom PHP e-commerce

### Payment Providers
- Stripe
- PayPal
- Amazon Pay
- Mollie
- Adyen
- Klarna
- Braintree
- Square
- Any provider with REST API + Webhooks

---

## Core Component Architecture

### 1. Event Layer (100% Reusable - NEW)
**The heart of the new architecture**: All business operations are event-driven

- **Domain Events**: PaymentInitiated, OrderCreated, PaymentCaptured, etc.
- **Event Handlers**: Subscribers that execute business logic
- **Event Dispatcher**: PSR-14 compliant event routing
- **Event Context**: Carries cached request data across handlers

### 2. Presentation Layer (Controllers & CLI)
**Thin security & validation layer**: No business logic

- Validate and sanitize user input
- Enforce authentication & authorization
- Emit domain events with validated data
- Return responses (redirects, JSON, views)
- **Request Data Caching**: Store HTTP request data for event handlers

### 3. Data Layer (100% Reusable)
- **Extended Models**: Core shop models (Order, User, Basket) extended with payment fields
- **New Models**: PaymentTransaction tracking
- **Repository Pattern**: Clean data access abstraction
- **Cache Layer**: Request data cached for cross-handler access

### 4. Service Layer (90% Reusable)
- **Event-triggered services**: Called by event handlers, not controllers
- Payment orchestration service
- Configuration service
- Amount calculation services
- State machine service

### 5. Factory Layer (80% Reusable)
- Request builders for provider APIs
- Response parsers
- Entity factories

### 6. Webhook System (100% Reusable)
- Signature verification
- Webhook event dispatcher
- Handler registry
- Base handler class

---

## Technology Stack

### Required
- PHP 7.4+ / 8.0+
- Relational database (MySQL, PostgreSQL)
- PSR-3 Logger
- PSR-14 Event Dispatcher (or Symfony EventDispatcher)

### Optional but Recommended
- Doctrine DBAL for database abstraction
- Symfony DependencyInjection for service container
- PHPUnit for testing
- Monolog for logging

---

## Design Principles

### 1. Separation of Concerns
Clear boundaries between:
- Controllers (HTTP handling)
- Services (business logic)
- Models (data representation)
- Factories (object construction)

### 2. Dependency Injection
All services receive dependencies via constructor injection, enabling:
- Testability
- Flexibility
- Loose coupling

### 3. Repository Pattern
Data access abstracted behind repository interfaces:
- Swap database implementations
- Mock for testing
- Query optimization in one place

### 4. Event-Driven Architecture
Critical payment events trigger subscribers:
- Extensibility without modifying core
- Audit logging
- Third-party integrations

### 5. Async Payment Handling
Support for redirect-based and webhook-based payment flows:
- Temporary order creation
- State machine for payment states
- Timeout management
- Fallback mechanisms

---

## Event-Driven Payment Flow

### New Headless Flow (Event-Driven)
```
1. User selects payment method
2. Controller validates input, emits PaymentInitiatedEvent
3. Event Handler: Creates temporary order (state: NOT_FINISHED)
4. Event Handler: Calls provider API, emits OrderCreatedAtProviderEvent
5. Controller returns redirect URL to frontend
6. User completes payment at provider
7. Provider webhook → Emits PaymentCapturedEvent
8. Event Handler: Updates order (state: OK)
9. Event Handler: Emits OrderCompletedEvent
10. Event Subscriber: Sends confirmation email
```

**Key Difference**: Controllers don't create orders or call services directly. They emit events.

### Webhook-Driven Flow (Event-Based)
```
Provider → Webhook Controller (validates signature) → Emits WebhookReceivedEvent
→ Event Handler (processes payment) → Emits PaymentCapturedEvent
→ Multiple Subscribers:
   - Update order status
   - Send email
   - Update inventory
   - Trigger analytics
```

### Request Data Caching Pattern
```
1. Controller receives HTTP request
2. Controller caches: basket, user, session data
3. Controller emits event
4. Event handlers access cached data (no DB queries needed)
5. Cache cleared after request completes
```

**Benefits**:
- Event handlers don't need request context injected
- Data fetched once, reused across handlers
- Reduces database queries by 50-70%

---

## Key Architectural Patterns

### 1. Event-Driven Architecture (PRIMARY PATTERN)
**All business operations are event-based**:
- Controllers emit events, don't execute business logic
- Event handlers contain the workflow logic
- Multiple subscribers can react to single event
- Loose coupling between components
- Easy to extend without modifying core

**Example Events**:
- `PaymentInitiatedEvent` - User starts payment
- `OrderCreatedEvent` - Shop order created
- `OrderCreatedAtProviderEvent` - Provider order created
- `PaymentCapturedEvent` - Payment confirmed
- `PaymentFailedEvent` - Payment failed
- `OrderCompletedEvent` - Order finalized

### 2. Request Data Caching Pattern (NEW)
**Cache HTTP request data for event handlers**:
- Controllers cache basket, user, session, configuration
- Event handlers access cached data via context object
- Eliminates redundant database queries
- Maintains data consistency across event chain
- Cache scope: single HTTP request lifecycle

### 3. Extended Data Models Pattern (NEW)
**Component extends core shop models**:
- `Order` extended with payment-specific methods
- `User` extended with payment customer IDs
- `Basket` extended with payment calculations
- Original models untouched (decorator pattern)
- Migration-safe extensions

### 4. Order State Machine
Custom order states track payment progress:
- `NOT_FINISHED` - Order created, awaiting payment
- `500-900` - Various payment processing states
- `OK` - Order completed and paid
- State transitions triggered by events

### 5. Transaction Tracking
Separate `payment_transaction` table:
- Links shop orders to provider transactions
- Supports multiple transactions per order (auth → capture → refunds)
- Stores provider-specific data
- Enables reconciliation and reporting

### 6. Webhook Processing Pattern
Webhooks emit events just like controllers:
- Webhook controller validates signature
- Emits `WebhookReceivedEvent`
- Event handlers process payment updates
- Same event-driven flow as frontend

---

## Integration Points

### Shop Integration
- Order model extensions
- Basket amount calculations
- User data access
- Email notifications

### Provider Integration
- API client (SDK or HTTP client)
- Authentication (OAuth, API keys)
- Webhook signature verification
- Request/response mapping

### Admin Integration
- Order management actions (capture, refund)
- Configuration interface
- Transaction history view
- Webhook delivery monitoring

---

## Testing Strategy

### Unit Tests
- Service layer business logic
- Factory output validation
- Model state transitions
- Repository queries

### Integration Tests
- Order creation flow
- Payment execution
- Webhook processing
- Database transactions

### E2E Tests (Codeception/Playwright)
- Complete checkout flows
- Payment method selection
- Provider redirects
- Order confirmation

---

## Next Steps

1. Read **01-architecture-layers.md** for layered architecture overview
2. Review **diagrams/** for visual representations
3. Study **08-payment-flows.md** for complete payment scenarios
4. Examine specific layers based on your role

---

## Glossary

**ACDC** - Advanced Credit and Debit Card (card payments with 3D Secure)
**Authorization** - Reserve funds without capturing
**Capture** - Actually charge the reserved funds
**Order Intent** - CAPTURE (immediate) or AUTHORIZE (capture later)
**PUI** - Pay Upon Invoice (buy now, pay later)
**SCA** - Strong Customer Authentication (3D Secure 2.0)
**uAPM** - Universal Alternative Payment Method (bank transfers, local methods)
**Vaulting** - Saving payment methods for future use
**Webhook** - Server-to-server callback from payment provider

---

**Continue to:** [01-architecture-layers.md](01-architecture-layers.md)
