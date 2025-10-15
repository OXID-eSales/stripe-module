# Real-Time Monitoring & Security System for Payment Modules

**Version:** 1.0.0
**Date:** 2025-10-13
**Status:** Enterprise Feature - Paid Service
**Visual Diagram:** [puml/11-monitoring-system.puml](puml/11-monitoring-system.puml)

---

## Executive Summary

This document describes a **real-time monitoring and security system** for payment modules deployed at VIP client sites. The system provides:

- **Real-time health monitoring** - Payment system availability and performance
- **Fraud detection** - AI-powered anomaly detection and attack prevention
- **Security monitoring** - Intrusion detection, suspicious activity alerts
- **SaaS dashboard** - Centralized monitoring for all client installations
- **Automated alerting** - Instant notifications for critical events
- **Compliance reporting** - PCI-DSS, GDPR audit trails

**Business Model:** Paid enterprise feature with tiered pricing (Basic/Pro/Enterprise)

---

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Monitoring Components](#monitoring-components)
3. [Fraud Detection System](#fraud-detection-system)
4. [Security Monitoring](#security-monitoring)
5. [Data Collection & Transmission](#data-collection--transmission)
6. [Central Monitoring Dashboard](#central-monitoring-dashboard)
7. [Alerting System](#alerting-system)
8. [Privacy & Compliance](#privacy--compliance)
9. [Pricing Tiers](#pricing-tiers)
10. [Implementation Guide](#implementation-guide)

---

## Architecture Overview

### System Components

```
┌─────────────────────────────────────────────────────────────────┐
│                    Client Installation (VIP Shop)               │
│                                                                 │
│  ┌──────────────────────────────────────────────────────────┐ │
│  │  Payment Module with Monitoring Agent                     │ │
│  │                                                            │ │
│  │  • Health Collector (CPU, Memory, Transactions)          │ │
│  │  • Fraud Detector (Anomaly Detection)                    │ │
│  │  • Security Monitor (Intrusion Detection)                │ │
│  │  • Event Logger (Transaction Log, Webhook Log)           │ │
│  │                                                            │ │
│  │  └─→ Data Anonymizer (PCI-DSS Compliant)                │ │
│  └──────────────────────────────────────────────────────────┘ │
│                           │                                     │
│                           │ Encrypted HTTPS/TLS 1.3            │
│                           ▼                                     │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│            Your SaaS Monitoring Platform (Central Hub)          │
│                                                                 │
│  ┌──────────────────────────────────────────────────────────┐ │
│  │  Data Ingestion Service (Queue-based)                     │ │
│  │  └─→ Kafka/RabbitMQ → Time-Series DB (InfluxDB/TimescaleDB)│
│  └──────────────────────────────────────────────────────────┘ │
│                                                                 │
│  ┌──────────────────────────────────────────────────────────┐ │
│  │  AI/ML Processing Pipeline                                │ │
│  │  • Anomaly Detection (Isolation Forest, LSTM)            │ │
│  │  • Fraud Pattern Recognition (XGBoost)                   │ │
│  │  • Baseline Learning (Normal Behavior)                   │ │
│  └──────────────────────────────────────────────────────────┘ │
│                                                                 │
│  ┌──────────────────────────────────────────────────────────┐ │
│  │  Alert Engine                                             │ │
│  │  • Rule-based Alerts (Threshold Violations)              │ │
│  │  • ML-based Alerts (Anomalies)                           │ │
│  │  • Notification Dispatcher (Email, SMS, Slack, PagerDuty)│ │
│  └──────────────────────────────────────────────────────────┘ │
│                                                                 │
│  ┌──────────────────────────────────────────────────────────┐ │
│  │  Web Dashboard (React/Vue)                                │ │
│  │  • Real-time Metrics (Grafana-style)                     │ │
│  │  • Client List & Health Status                           │ │
│  │  • Fraud Incidents & Alerts                              │ │
│  │  • Security Events Timeline                              │ │
│  └──────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
```

---

## Monitoring Components

### 1. Health Monitoring

**Purpose:** Track system availability, performance, and transaction success rates

#### Metrics Collected

```php
<?php
// src/Monitoring/HealthCollector.php

namespace PaymentComponent\Monitoring;

class HealthCollector
{
    private MetricsStorage $storage;
    private ModuleSettings $settings;

    /**
     * Collect health metrics every 60 seconds
     */
    public function collect(): HealthMetrics
    {
        return new HealthMetrics([
            // System Health
            'timestamp' => time(),
            'module_version' => $this->getModuleVersion(),
            'php_version' => PHP_VERSION,
            'memory_usage_mb' => memory_get_usage(true) / 1024 / 1024,
            'memory_peak_mb' => memory_get_peak_usage(true) / 1024 / 1024,
            'disk_free_gb' => disk_free_space('/') / 1024 / 1024 / 1024,

            // Transaction Health (last 5 minutes)
            'transaction_count' => $this->getTransactionCount(300),
            'successful_transactions' => $this->getSuccessfulCount(300),
            'failed_transactions' => $this->getFailedCount(300),
            'success_rate' => $this->calculateSuccessRate(300),
            'average_response_time_ms' => $this->getAverageResponseTime(300),
            'p95_response_time_ms' => $this->getP95ResponseTime(300),

            // Payment Provider Status
            'provider_api_reachable' => $this->checkProviderApiHealth(),
            'webhook_queue_length' => $this->getWebhookQueueLength(),
            'pending_captures' => $this->getPendingCaptureCount(),

            // Database Health
            'db_connection_pool_size' => $this->getDbPoolSize(),
            'db_query_avg_time_ms' => $this->getDbQueryAvgTime(300),
            'db_slow_queries_count' => $this->getSlowQueryCount(300),

            // Error Rates
            'http_4xx_count' => $this->getHttpErrorCount(400, 499, 300),
            'http_5xx_count' => $this->getHttpErrorCount(500, 599, 300),
            'exception_count' => $this->getExceptionCount(300),
            'critical_errors' => $this->getCriticalErrorCount(300),
        ]);
    }

    /**
     * Calculate transaction success rate
     */
    private function calculateSuccessRate(int $seconds): float
    {
        $total = $this->getTransactionCount($seconds);
        if ($total === 0) {
            return 100.0;
        }

        $successful = $this->getSuccessfulCount($seconds);
        return ($successful / $total) * 100;
    }

    /**
     * Check if provider API is reachable
     */
    private function checkProviderApiHealth(): bool
    {
        try {
            $client = new HealthCheckClient($this->settings->getProviderApiUrl());
            $response = $client->ping(timeout: 5);
            return $response->isOk();
        } catch (\Exception $e) {
            return false;
        }
    }
}
```

#### Health Status Levels

```php
<?php
namespace PaymentComponent\Monitoring;

enum HealthStatus: string
{
    case HEALTHY = 'healthy';      // All systems operational
    case DEGRADED = 'degraded';    // Some issues but operational
    case CRITICAL = 'critical';    // Major issues, needs attention
    case DOWN = 'down';            // System not responding

    public function getColor(): string
    {
        return match($this) {
            self::HEALTHY => '#00C851',   // Green
            self::DEGRADED => '#FF8800',  // Orange
            self::CRITICAL => '#FF4444',  // Red
            self::DOWN => '#000000',      // Black
        };
    }
}

class HealthStatusCalculator
{
    public function calculate(HealthMetrics $metrics): HealthStatus
    {
        // DOWN: Provider API unreachable or critical errors
        if (!$metrics->provider_api_reachable || $metrics->critical_errors > 0) {
            return HealthStatus::DOWN;
        }

        // CRITICAL: Success rate < 90% or response time > 5s
        if ($metrics->success_rate < 90.0 || $metrics->p95_response_time_ms > 5000) {
            return HealthStatus::CRITICAL;
        }

        // DEGRADED: Success rate < 98% or response time > 2s
        if ($metrics->success_rate < 98.0 || $metrics->p95_response_time_ms > 2000) {
            return HealthStatus::DEGRADED;
        }

        // HEALTHY: All metrics within normal range
        return HealthStatus::HEALTHY;
    }
}
```

---

### 2. Transaction Monitoring

**Purpose:** Track all payment transactions with detailed telemetry

```php
<?php
// src/Monitoring/TransactionMonitor.php

namespace PaymentComponent\Monitoring;

class TransactionMonitor
{
    private MetricsStorage $storage;

    /**
     * Track transaction lifecycle
     */
    public function trackTransaction(string $orderId, TransactionEvent $event): void
    {
        $telemetry = new TransactionTelemetry([
            'transaction_id' => $event->getTransactionId(),
            'shop_order_id' => $orderId,
            'event_type' => $event->getType(), // initiated, authorized, captured, failed
            'timestamp' => microtime(true),

            // Anonymized customer data (PCI-compliant)
            'customer_country' => $event->getCustomerCountry(),
            'customer_type' => $event->isReturningCustomer() ? 'returning' : 'new',

            // Payment method (anonymized)
            'payment_method' => $event->getPaymentMethod(), // card, paymenter, etc.
            'card_brand' => $event->getCardBrand(), // visa, mastercard (no card number)
            'card_last4' => $event->getCardLast4(), // only last 4 digits

            // Transaction details
            'amount' => $event->getAmount(),
            'currency' => $event->getCurrency(),
            'transaction_type' => $event->getTransactionType(), // authorization, capture, refund
            'status' => $event->getStatus(),

            // Performance metrics
            'response_time_ms' => $event->getResponseTime(),
            'provider_response_time_ms' => $event->getProviderResponseTime(),

            // Error tracking
            'error_code' => $event->getErrorCode(),
            'error_message' => $event->getErrorMessage(),
            'provider_error_code' => $event->getProviderErrorCode(),

            // Security indicators
            '3ds_authenticated' => $event->is3DSecureAuthenticated(),
            'risk_score' => $event->getRiskScore(), // 0-100
            'fraud_check_passed' => $event->isFraudCheckPassed(),

            // Context
            'ip_address_hash' => hash('sha256', $event->getIpAddress()), // Hashed for privacy
            'user_agent_hash' => hash('sha256', $event->getUserAgent()),
            'checkout_session_duration_sec' => $event->getCheckoutDuration(),
        ]);

        $this->storage->save($telemetry);

        // Real-time fraud detection
        $this->detectFraud($telemetry);
    }
}
```

---

## Fraud Detection System

### 1. Anomaly Detection Engine

**Purpose:** Detect suspicious patterns using machine learning

```php
<?php
// src/Monitoring/Fraud/AnomalyDetector.php

namespace PaymentComponent\Monitoring\Fraud;

class AnomalyDetector
{
    private MachineLearningClient $mlClient;
    private BaselineStorage $baseline;

    /**
     * Detect anomalies in real-time
     */
    public function detect(TransactionTelemetry $transaction): FraudDetectionResult
    {
        $features = $this->extractFeatures($transaction);
        $baseline = $this->baseline->get($transaction->shop_order_id);

        $anomalies = [];

        // 1. Transaction Volume Spike
        if ($this->isVolumeSpikeAnomaly($features, $baseline)) {
            $anomalies[] = new Anomaly(
                type: 'volume_spike',
                severity: 'high',
                description: 'Unusual transaction volume detected',
                score: 85
            );
        }

        // 2. Unusual Amount Pattern
        if ($this->isAmountAnomalous($features, $baseline)) {
            $anomalies[] = new Anomaly(
                type: 'amount_anomaly',
                severity: 'medium',
                description: 'Transaction amount deviates from normal pattern',
                score: 70
            );
        }

        // 3. Rapid Succession Attacks (Card Testing)
        if ($this->isCardTestingAttack($features)) {
            $anomalies[] = new Anomaly(
                type: 'card_testing',
                severity: 'critical',
                description: 'Possible card testing attack detected',
                score: 95
            );
        }

        // 4. Geo-Location Anomaly
        if ($this->isGeoAnomalous($features, $baseline)) {
            $anomalies[] = new Anomaly(
                type: 'geo_anomaly',
                severity: 'medium',
                description: 'Transaction from unusual location',
                score: 65
            );
        }

        // 5. ML-Based Anomaly Detection
        $mlScore = $this->mlClient->predict($features);
        if ($mlScore > 0.8) { // 80% probability of fraud
            $anomalies[] = new Anomaly(
                type: 'ml_detected',
                severity: 'high',
                description: 'ML model detected suspicious pattern',
                score: $mlScore * 100
            );
        }

        return new FraudDetectionResult(
            is_fraud: count($anomalies) > 0,
            anomalies: $anomalies,
            overall_score: $this->calculateOverallScore($anomalies),
            recommendation: $this->getRecommendation($anomalies)
        );
    }

    /**
     * Detect card testing attacks (rapid failed transactions)
     */
    private function isCardTestingAttack(array $features): bool
    {
        // Check last 5 minutes for multiple failed transactions from same IP
        $recentFailures = $this->getRecentFailedTransactions(
            ip_hash: $features['ip_address_hash'],
            minutes: 5
        );

        // Card testing pattern: >5 failed transactions in 5 minutes
        if (count($recentFailures) >= 5) {
            // Check if amounts are small and incremental (typical card testing)
            $amounts = array_map(fn($tx) => $tx->amount, $recentFailures);
            if (max($amounts) < 10.0 && $this->isIncrementalPattern($amounts)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect volume spike (DoS or brute force attack)
     */
    private function isVolumeSpikeAnomaly(array $features, Baseline $baseline): bool
    {
        $currentRate = $this->getTransactionRate(minutes: 5);
        $normalRate = $baseline->average_transaction_rate;
        $stdDev = $baseline->transaction_rate_std_dev;

        // Spike detection: current rate > 3 standard deviations above normal
        return ($currentRate > $normalRate + (3 * $stdDev));
    }

    /**
     * Extract features for ML model
     */
    private function extractFeatures(TransactionTelemetry $transaction): array
    {
        return [
            'hour_of_day' => (int)date('H', $transaction->timestamp),
            'day_of_week' => (int)date('N', $transaction->timestamp),
            'amount' => $transaction->amount,
            'currency' => $transaction->currency,
            'payment_method' => $transaction->payment_method,
            'customer_type' => $transaction->customer_type,
            'country' => $transaction->customer_country,
            'response_time_ms' => $transaction->response_time_ms,
            '3ds_authenticated' => $transaction->{'3ds_authenticated'} ? 1 : 0,
            'risk_score' => $transaction->risk_score,
            'checkout_duration' => $transaction->checkout_session_duration_sec,
            'ip_address_hash' => $transaction->ip_address_hash,
            'user_agent_hash' => $transaction->user_agent_hash,

            // Calculated features
            'is_business_hours' => $this->isBusinessHours($transaction->timestamp),
            'recent_failure_count' => $this->getRecentFailureCount($transaction),
            'velocity_score' => $this->calculateVelocityScore($transaction),
        ];
    }
}
```

### 2. Fraud Rules Engine

**Purpose:** Rule-based fraud detection for known attack patterns

```php
<?php
// src/Monitoring/Fraud/FraudRulesEngine.php

namespace PaymentComponent\Monitoring\Fraud;

class FraudRulesEngine
{
    private array $rules;

    public function __construct()
    {
        $this->rules = [
            new CardTestingRule(),
            new HighValueTransactionRule(),
            new MultipleFailedAttemptsRule(),
            new SuspiciousGeoLocationRule(),
            new VelocityAbuseRule(),
            new WebhookReplayAttackRule(),
            new StolenCardRule(),
        ];
    }

    public function evaluate(TransactionTelemetry $transaction): array
    {
        $violations = [];

        foreach ($this->rules as $rule) {
            if ($rule->matches($transaction)) {
                $violations[] = new RuleViolation(
                    rule_name: $rule->getName(),
                    severity: $rule->getSeverity(),
                    description: $rule->getDescription(),
                    recommendation: $rule->getRecommendation(),
                    triggered_at: time()
                );
            }
        }

        return $violations;
    }
}

/**
 * Rule: Multiple failed payment attempts in short time
 */
class MultipleFailedAttemptsRule implements FraudRule
{
    public function matches(TransactionTelemetry $transaction): bool
    {
        // Check if this transaction failed
        if ($transaction->status !== 'failed') {
            return false;
        }

        // Count recent failed attempts from same customer
        $recentFailures = $this->getRecentFailedAttempts(
            customer_hash: $transaction->customer_hash,
            minutes: 10
        );

        // Trigger if >= 3 failures in 10 minutes
        return count($recentFailures) >= 3;
    }

    public function getSeverity(): string
    {
        return 'high';
    }

    public function getRecommendation(): string
    {
        return 'Block customer temporarily and require additional verification';
    }
}

/**
 * Rule: High-value transaction from new customer
 */
class HighValueTransactionRule implements FraudRule
{
    private const HIGH_VALUE_THRESHOLD = 1000.0;

    public function matches(TransactionTelemetry $transaction): bool
    {
        return $transaction->amount >= self::HIGH_VALUE_THRESHOLD
            && $transaction->customer_type === 'new'
            && !$transaction->{'3ds_authenticated'};
    }

    public function getSeverity(): string
    {
        return 'medium';
    }

    public function getRecommendation(): string
    {
        return 'Require 3D Secure authentication for high-value transactions from new customers';
    }
}

/**
 * Rule: Webhook replay attack detection
 */
class WebhookReplayAttackRule implements FraudRule
{
    public function matches(WebhookEvent $event): bool
    {
        // Check if webhook ID already processed
        if ($this->isWebhookProcessed($event->webhook_id)) {
            return true; // Duplicate webhook = replay attack
        }

        // Check webhook timestamp (reject if older than 5 minutes)
        $age_seconds = time() - $event->timestamp;
        if ($age_seconds > 300) {
            return true; // Old webhook = possible replay
        }

        return false;
    }

    public function getSeverity(): string
    {
        return 'critical';
    }

    public function getRecommendation(): string
    {
        return 'Reject webhook and investigate possible security breach';
    }
}
```

---

## Security Monitoring

### 1. Intrusion Detection System

**Purpose:** Detect unauthorized access attempts, SQL injection, XSS attacks

```php
<?php
// src/Monitoring/Security/IntrusionDetector.php

namespace PaymentComponent\Monitoring\Security;

class IntrusionDetector
{
    private array $detectors;

    public function __construct()
    {
        $this->detectors = [
            new SqlInjectionDetector(),
            new XssDetector(),
            new PathTraversalDetector(),
            new BruteForceDetector(),
            new UnauthorizedAccessDetector(),
        ];
    }

    /**
     * Monitor HTTP request for security threats
     */
    public function monitorRequest(HttpRequest $request): ?SecurityIncident
    {
        foreach ($this->detectors as $detector) {
            if ($incident = $detector->detect($request)) {
                $this->logSecurityIncident($incident);
                $this->sendAlert($incident);

                if ($incident->getSeverity() === 'critical') {
                    $this->blockIpAddress($request->getIpAddress());
                }

                return $incident;
            }
        }

        return null;
    }
}

/**
 * SQL Injection Detector
 */
class SqlInjectionDetector implements SecurityDetector
{
    private array $sqlPatterns = [
        '/(\bUNION\b.*\bSELECT\b)/i',
        '/(\bSELECT\b.*\bFROM\b.*\bWHERE\b)/i',
        '/(\'|\")(\s)*(\bOR\b|\bAND\b)(\s)*(\'|\")?(\s)*=(\s)*(\'|\")?/i',
        '/(\bDROP\b|\bDELETE\b|\bUPDATE\b).*(\bTABLE\b|\bFROM\b)/i',
        '/(\bEXEC\b|\bEXECUTE\b)(\s)+(\bsp_|xp_)/i',
        '/(;(\s)*\bDROP\b)/i',
    ];

    public function detect(HttpRequest $request): ?SecurityIncident
    {
        $suspicious_params = [];

        // Check all input parameters
        foreach ($request->getAllParams() as $key => $value) {
            foreach ($this->sqlPatterns as $pattern) {
                if (preg_match($pattern, $value)) {
                    $suspicious_params[$key] = $value;
                    break;
                }
            }
        }

        if (count($suspicious_params) > 0) {
            return new SecurityIncident(
                type: 'sql_injection',
                severity: 'critical',
                description: 'SQL injection attempt detected',
                ip_address: $request->getIpAddress(),
                user_agent: $request->getUserAgent(),
                url: $request->getUrl(),
                suspicious_data: $suspicious_params,
                timestamp: time()
            );
        }

        return null;
    }
}

/**
 * Brute Force Attack Detector
 */
class BruteForceDetector implements SecurityDetector
{
    private const MAX_FAILED_ATTEMPTS = 5;
    private const TIME_WINDOW_SECONDS = 300; // 5 minutes

    public function detect(HttpRequest $request): ?SecurityIncident
    {
        // Check if this is a login/authentication endpoint
        if (!$this->isAuthenticationEndpoint($request)) {
            return null;
        }

        // Count failed authentication attempts from this IP
        $failedAttempts = $this->getFailedAttempts(
            ip_address: $request->getIpAddress(),
            seconds: self::TIME_WINDOW_SECONDS
        );

        if (count($failedAttempts) >= self::MAX_FAILED_ATTEMPTS) {
            return new SecurityIncident(
                type: 'brute_force',
                severity: 'high',
                description: "Brute force attack: {$failedAttempts} failed login attempts",
                ip_address: $request->getIpAddress(),
                timestamp: time()
            );
        }

        return null;
    }
}
```

### 2. Security Event Logger

```php
<?php
// src/Monitoring/Security/SecurityEventLogger.php

namespace PaymentComponent\Monitoring\Security;

class SecurityEventLogger
{
    private LoggerInterface $logger;
    private MonitoringClient $monitoringClient;

    /**
     * Log security event and send to central monitoring
     */
    public function log(SecurityEvent $event): void
    {
        // Local log (for immediate access)
        $this->logger->warning('Security event detected', [
            'event_type' => $event->getType(),
            'severity' => $event->getSeverity(),
            'ip_address' => $event->getIpAddress(),
            'url' => $event->getUrl(),
            'user_agent' => $event->getUserAgent(),
            'description' => $event->getDescription(),
            'timestamp' => date('Y-m-d H:i:s', $event->getTimestamp()),
        ]);

        // Send to central monitoring (asynchronous)
        $this->monitoringClient->sendSecurityEvent([
            'client_id' => $this->getClientId(),
            'event' => $event->toArray(),
        ]);
    }
}
```

---

## Data Collection & Transmission

### 1. Monitoring Agent

**Purpose:** Collect and transmit data to central monitoring platform

```php
<?php
// src/Monitoring/MonitoringAgent.php

namespace PaymentComponent\Monitoring;

class MonitoringAgent
{
    private MonitoringClient $client;
    private ModuleSettings $settings;
    private DataAnonymizer $anonymizer;

    private const COLLECTION_INTERVAL_SECONDS = 60;

    /**
     * Start monitoring agent (background process)
     */
    public function start(): void
    {
        // Check if monitoring is enabled
        if (!$this->settings->isMonitoringEnabled()) {
            return;
        }

        // Verify license
        if (!$this->verifyLicense()) {
            $this->logger->error('Monitoring license invalid');
            return;
        }

        // Start collection loop
        while (true) {
            try {
                $this->collectAndSend();
            } catch (\Exception $e) {
                $this->logger->error('Monitoring agent error', [
                    'error' => $e->getMessage(),
                ]);
            }

            sleep(self::COLLECTION_INTERVAL_SECONDS);
        }
    }

    /**
     * Collect metrics and send to central platform
     */
    private function collectAndSend(): void
    {
        $data = [
            'client_id' => $this->settings->getMonitoringClientId(),
            'timestamp' => time(),
            'metrics' => $this->collectMetrics(),
            'events' => $this->collectEvents(),
        ];

        // Anonymize sensitive data (PCI-DSS compliance)
        $anonymized = $this->anonymizer->anonymize($data);

        // Compress data
        $compressed = gzencode(json_encode($anonymized), 9);

        // Send via HTTPS
        $this->client->send($compressed);
    }

    private function collectMetrics(): array
    {
        return [
            'health' => (new HealthCollector())->collect(),
            'transactions' => (new TransactionCollector())->collect(),
            'performance' => (new PerformanceCollector())->collect(),
            'errors' => (new ErrorCollector())->collect(),
        ];
    }

    private function collectEvents(): array
    {
        return [
            'security_events' => (new SecurityEventCollector())->collect(),
            'fraud_alerts' => (new FraudAlertCollector())->collect(),
        ];
    }
}
```

### 2. Data Anonymizer (PCI-DSS Compliance)

```php
<?php
// src/Monitoring/DataAnonymizer.php

namespace PaymentComponent\Monitoring;

class DataAnonymizer
{
    /**
     * Anonymize sensitive data before transmission
     */
    public function anonymize(array $data): array
    {
        return array_map(function ($value) {
            if (is_array($value)) {
                return $this->anonymize($value);
            }

            // Anonymize specific fields
            if (isset($value['card_number'])) {
                $value['card_number'] = 'REDACTED';
            }

            if (isset($value['cvv'])) {
                $value['cvv'] = 'REDACTED';
            }

            if (isset($value['customer_email'])) {
                // Hash email for privacy
                $value['customer_email_hash'] = hash('sha256', $value['customer_email']);
                unset($value['customer_email']);
            }

            if (isset($value['customer_name'])) {
                unset($value['customer_name']); // Never send names
            }

            if (isset($value['ip_address'])) {
                // Hash IP address
                $value['ip_address_hash'] = hash('sha256', $value['ip_address']);
                unset($value['ip_address']);
            }

            return $value;
        }, $data);
    }
}
```

### 3. Monitoring Client (HTTP/HTTPS)

```php
<?php
// src/Monitoring/MonitoringClient.php

namespace PaymentComponent\Monitoring;

class MonitoringClient
{
    private const CENTRAL_API_URL = 'https://monitoring.your-saas-platform.com/api/v1/ingest';

    private HttpClient $httpClient;
    private string $apiKey;

    /**
     * Send monitoring data to central platform
     */
    public function send(string $compressedData): void
    {
        try {
            $response = $this->httpClient->post(self::CENTRAL_API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/gzip',
                    'X-Client-Version' => $this->getModuleVersion(),
                ],
                'body' => $compressedData,
                'timeout' => 10,
            ]);

            if ($response->getStatusCode() !== 202) {
                throw new MonitoringException('Failed to send data: ' . $response->getBody());
            }
        } catch (\Exception $e) {
            // Queue for retry if send fails
            $this->queueForRetry($compressedData);
            throw $e;
        }
    }

    /**
     * Queue data for retry if transmission fails
     */
    private function queueForRetry(string $data): void
    {
        file_put_contents(
            '/var/tmp/monitoring_queue_' . uniqid() . '.gz',
            $data
        );
    }
}
```

---

## Central Monitoring Dashboard

### 1. Dashboard Features

```
┌─────────────────────────────────────────────────────────────────┐
│  Your SaaS Monitoring Dashboard                                 │
│  https://monitoring.your-saas-platform.com                      │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│  CLIENT OVERVIEW                                                 │
│                                                                 │
│  Active Clients: 127                                            │
│  Healthy: 120 🟢  Degraded: 5 🟠  Critical: 2 🔴  Down: 0 ⚫    │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│  CLIENT LIST                                                     │
│  ┌──────────────────────────────────────────────────────────┐ │
│  │ Client Name          Status    Transactions  Alerts     │ │
│  │ VIP Shop #1          🟢        1,234/hr      0          │ │
│  │ VIP Shop #2          🟠        456/hr        2          │ │
│  │ VIP Shop #3          🔴        89/hr         5 ⚠️       │ │
│  └──────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│  REAL-TIME METRICS (VIP Shop #3)                                │
│                                                                 │
│  📊 Transaction Volume:    [Graph showing spike]                │
│  ⏱️  Response Time:         [Graph showing degradation]         │
│  ✅ Success Rate:           87.3% (↓ from 98.5%)               │
│  🚨 Active Alerts:          5 critical, 12 warnings            │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│  FRAUD ALERTS                                                    │
│  ┌──────────────────────────────────────────────────────────┐ │
│  │ 🚨 VIP Shop #3: Possible card testing attack detected   │ │
│  │    15 failed transactions in 5 minutes from same IP      │ │
│  │    Severity: CRITICAL                                    │ │
│  │    Action: IP blocked automatically                      │ │
│  │    [View Details] [Dismiss] [Contact Client]            │ │
│  │                                                           │ │
│  │ ⚠️  VIP Shop #5: Unusual transaction volume              │ │
│  │    Transaction rate 3x above baseline                    │ │
│  │    Severity: MEDIUM                                      │ │
│  │    [Investigate] [Mark as False Positive]                │ │
│  └──────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│  SECURITY EVENTS                                                 │
│  ┌──────────────────────────────────────────────────────────┐ │
│  │ [2025-10-13 14:32:15] SQL Injection attempt blocked      │ │
│  │ Client: VIP Shop #7                                      │ │
│  │ IP: 192.168.1.100 (hashed)                               │ │
│  │                                                           │ │
│  │ [2025-10-13 14:25:03] Brute force attack detected        │ │
│  │ Client: VIP Shop #3                                      │ │
│  │ IP: 10.0.0.50 (hashed) - Blocked                         │ │
│  └──────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
```

### 2. Dashboard API Endpoints

```php
<?php
// Central Platform API

/**
 * GET /api/v1/clients
 * List all monitored clients
 */
Route::get('/api/v1/clients', function (Request $request) {
    return response()->json([
        'clients' => Client::with('latest_metrics')
            ->where('user_id', $request->user()->id)
            ->get()
            ->map(fn($client) => [
                'id' => $client->id,
                'name' => $client->name,
                'status' => $client->calculateHealthStatus(),
                'last_seen' => $client->last_seen_at,
                'transactions_per_hour' => $client->latest_metrics->transaction_rate,
                'active_alerts' => $client->active_alerts_count,
            ]),
    ]);
});

/**
 * GET /api/v1/clients/{id}/metrics
 * Get real-time metrics for specific client
 */
Route::get('/api/v1/clients/{id}/metrics', function (Request $request, $id) {
    $client = Client::findOrFail($id);

    // Verify ownership
    if ($client->user_id !== $request->user()->id) {
        abort(403);
    }

    $timeRange = $request->query('range', '1h'); // 1h, 24h, 7d, 30d

    return response()->json([
        'current' => $client->getCurrentMetrics(),
        'history' => $client->getMetricsHistory($timeRange),
        'alerts' => $client->getActiveAlerts(),
    ]);
});

/**
 * GET /api/v1/clients/{id}/fraud-alerts
 * Get fraud alerts for specific client
 */
Route::get('/api/v1/clients/{id}/fraud-alerts', function (Request $request, $id) {
    $client = Client::findOrFail($id);

    return response()->json([
        'alerts' => FraudAlert::where('client_id', $id)
            ->where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get(),
    ]);
});
```

---

## Alerting System

### 1. Alert Rules

```php
<?php
// src/Monitoring/Alerting/AlertRuleEngine.php

namespace PaymentComponent\Monitoring\Alerting;

class AlertRuleEngine
{
    private array $rules;

    public function __construct()
    {
        $this->rules = [
            // Critical: System down
            new AlertRule(
                name: 'system_down',
                severity: 'critical',
                condition: fn($m) => $m->health_status === 'down',
                message: '🔴 Payment system is DOWN',
                channels: ['email', 'sms', 'pagerduty']
            ),

            // Critical: Success rate below 90%
            new AlertRule(
                name: 'low_success_rate',
                severity: 'critical',
                condition: fn($m) => $m->success_rate < 90.0,
                message: '🔴 Transaction success rate dropped to {success_rate}%',
                channels: ['email', 'sms', 'slack']
            ),

            // Critical: Fraud detected
            new AlertRule(
                name: 'fraud_detected',
                severity: 'critical',
                condition: fn($e) => $e->type === 'fraud' && $e->score > 85,
                message: '🚨 FRAUD ALERT: {description}',
                channels: ['email', 'sms', 'slack', 'pagerduty']
            ),

            // High: Performance degradation
            new AlertRule(
                name: 'slow_response_time',
                severity: 'high',
                condition: fn($m) => $m->p95_response_time_ms > 5000,
                message: '⚠️ Slow response time: {p95_response_time_ms}ms',
                channels: ['email', 'slack']
            ),

            // High: Security incident
            new AlertRule(
                name: 'security_incident',
                severity: 'high',
                condition: fn($e) => $e->type === 'security',
                message: '🛡️ Security incident: {description}',
                channels: ['email', 'slack']
            ),

            // Medium: High error rate
            new AlertRule(
                name: 'high_error_rate',
                severity: 'medium',
                condition: fn($m) => $m->http_5xx_count > 100,
                message: 'High error rate: {http_5xx_count} 5xx errors in last 5 minutes',
                channels: ['email']
            ),
        ];
    }

    /**
     * Evaluate rules and send alerts
     */
    public function evaluate(Client $client, $data): void
    {
        foreach ($this->rules as $rule) {
            if ($rule->matches($data)) {
                $this->sendAlert($client, $rule, $data);
            }
        }
    }

    private function sendAlert(Client $client, AlertRule $rule, $data): void
    {
        $alert = new Alert([
            'client_id' => $client->id,
            'rule_name' => $rule->name,
            'severity' => $rule->severity,
            'message' => $this->interpolateMessage($rule->message, $data),
            'data' => $data,
            'triggered_at' => now(),
        ]);

        $alert->save();

        // Send notifications
        foreach ($rule->channels as $channel) {
            $this->sendNotification($client, $alert, $channel);
        }
    }
}
```

### 2. Notification Channels

```php
<?php
// src/Monitoring/Alerting/NotificationDispatcher.php

namespace PaymentComponent\Monitoring\Alerting;

class NotificationDispatcher
{
    public function send(Client $client, Alert $alert, string $channel): void
    {
        match($channel) {
            'email' => $this->sendEmail($client, $alert),
            'sms' => $this->sendSms($client, $alert),
            'slack' => $this->sendSlack($client, $alert),
            'pagerduty' => $this->sendPagerDuty($client, $alert),
            'webhook' => $this->sendWebhook($client, $alert),
        };
    }

    private function sendEmail(Client $client, Alert $alert): void
    {
        Mail::to($client->notification_email)
            ->send(new AlertNotification($alert, [
                'subject' => "[{$alert->severity}] {$alert->message}",
                'view' => 'emails.alert',
                'data' => [
                    'client_name' => $client->name,
                    'alert' => $alert,
                    'dashboard_url' => $this->getDashboardUrl($client),
                ],
            ]));
    }

    private function sendSms(Client $client, Alert $alert): void
    {
        // Send SMS via Twilio
        $twilio = new TwilioClient();
        $twilio->messages->create(
            $client->notification_phone,
            [
                'from' => config('twilio.phone_number'),
                'body' => "[{$client->name}] {$alert->message}",
            ]
        );
    }

    private function sendSlack(Client $client, Alert $alert): void
    {
        // Send to Slack webhook
        Http::post($client->slack_webhook_url, [
            'text' => "[{$alert->severity}] {$alert->message}",
            'attachments' => [
                [
                    'color' => $this->getSeverityColor($alert->severity),
                    'fields' => [
                        ['title' => 'Client', 'value' => $client->name, 'short' => true],
                        ['title' => 'Time', 'value' => $alert->triggered_at, 'short' => true],
                        ['title' => 'Dashboard', 'value' => $this->getDashboardUrl($client)],
                    ],
                ],
            ],
        ]);
    }

    private function sendPagerDuty(Client $client, Alert $alert): void
    {
        // Create PagerDuty incident
        Http::post('https://events.pagerduty.com/v2/enqueue', [
            'routing_key' => $client->pagerduty_key,
            'event_action' => 'trigger',
            'payload' => [
                'summary' => $alert->message,
                'severity' => $alert->severity,
                'source' => $client->name,
                'custom_details' => $alert->data,
            ],
        ]);
    }
}
```

---

## Privacy & Compliance

### 1. PCI-DSS Compliance

**Requirements:**

✅ **No cardholder data transmitted** - Only last 4 digits and card brand
✅ **Data anonymization** - All PII hashed before transmission
✅ **Encrypted transmission** - TLS 1.3 with certificate pinning
✅ **No storage of CVV** - Never logged or transmitted
✅ **Access control** - Role-based access to monitoring dashboard
✅ **Audit logging** - All access to client data logged

### 2. GDPR Compliance

```php
<?php
// src/Monitoring/Privacy/GdprCompliance.php

namespace PaymentComponent\Monitoring\Privacy;

class GdprCompliance
{
    /**
     * Data minimization - collect only necessary data
     */
    public function minimizeData(array $data): array
    {
        $allowedFields = [
            'transaction_count',
            'success_rate',
            'response_time_ms',
            'error_count',
            'country', // aggregated, not per customer
            'payment_method', // type only, no details
            'currency',
        ];

        return array_intersect_key($data, array_flip($allowedFields));
    }

    /**
     * Right to be forgotten - delete all client data
     */
    public function forgetClient(Client $client): void
    {
        // Delete all metrics
        ClientMetric::where('client_id', $client->id)->delete();

        // Delete all alerts
        Alert::where('client_id', $client->id)->delete();

        // Delete all events
        SecurityEvent::where('client_id', $client->id)->delete();

        // Delete client record
        $client->delete();
    }

    /**
     * Data export - provide all collected data to client
     */
    public function exportClientData(Client $client): array
    {
        return [
            'client_info' => $client->toArray(),
            'metrics' => ClientMetric::where('client_id', $client->id)->get(),
            'alerts' => Alert::where('client_id', $client->id)->get(),
            'security_events' => SecurityEvent::where('client_id', $client->id)->get(),
        ];
    }
}
```

### 3. Data Retention Policy

```php
<?php
namespace PaymentComponent\Monitoring\Privacy;

class DataRetentionPolicy
{
    // Retention periods
    private const METRICS_RETENTION_DAYS = 90;
    private const ALERTS_RETENTION_DAYS = 365;
    private const SECURITY_EVENTS_RETENTION_DAYS = 730; // 2 years

    /**
     * Delete old data according to retention policy
     */
    public function cleanup(): void
    {
        // Delete old metrics
        ClientMetric::where('created_at', '<', now()->subDays(self::METRICS_RETENTION_DAYS))
            ->delete();

        // Delete old alerts
        Alert::where('created_at', '<', now()->subDays(self::ALERTS_RETENTION_DAYS))
            ->where('status', 'resolved')
            ->delete();

        // Delete old security events (keep longer for compliance)
        SecurityEvent::where('created_at', '<', now()->subDays(self::SECURITY_EVENTS_RETENTION_DAYS))
            ->delete();
    }
}
```

---

## Pricing Tiers

### Tier 1: Basic Monitoring ($99/month)

✅ Health monitoring (uptime, response time)
✅ Transaction success rate tracking
✅ Email alerts
✅ 30-day data retention
✅ Daily reports
❌ Fraud detection
❌ Security monitoring
❌ Real-time alerts

**Target:** Small shops (<1000 transactions/month)

---

### Tier 2: Professional Monitoring ($299/month)

✅ Everything in Basic
✅ **Fraud detection** (rule-based)
✅ **Security monitoring** (intrusion detection)
✅ **Real-time alerts** (Email + SMS)
✅ Slack/webhook integration
✅ 90-day data retention
✅ Weekly reports
❌ ML-based fraud detection
❌ Custom alerting rules

**Target:** Medium shops (1000-10,000 transactions/month)

---

### Tier 3: Enterprise Monitoring ($999/month)

✅ Everything in Professional
✅ **ML-based fraud detection** (anomaly detection)
✅ **Custom alerting rules**
✅ PagerDuty integration
✅ Dedicated support
✅ 365-day data retention
✅ Custom reports
✅ SLA: 99.9% uptime
✅ API access to monitoring data

**Target:** Large shops (>10,000 transactions/month)

---

## Implementation Guide

### Step 1: Install Monitoring Module

```bash
# Install via Composer
composer require your-company/payment-monitoring

# Or download and extract
wget https://releases.your-company.com/monitoring-agent-v1.0.0.zip
unzip monitoring-agent-v1.0.0.zip -d vendor/
```

### Step 2: Configure Monitoring

```php
<?php
// config/monitoring.php

return [
    // Enable/disable monitoring
    'enabled' => env('MONITORING_ENABLED', false),

    // Monitoring credentials (get from dashboard)
    'client_id' => env('MONITORING_CLIENT_ID'),
    'api_key' => env('MONITORING_API_KEY'),

    // Central platform URL
    'platform_url' => 'https://monitoring.your-saas-platform.com/api/v1',

    // Collection interval (seconds)
    'collection_interval' => 60,

    // Features (based on tier)
    'features' => [
        'health_monitoring' => true,
        'fraud_detection' => env('MONITORING_TIER') !== 'basic',
        'security_monitoring' => env('MONITORING_TIER') !== 'basic',
        'ml_fraud_detection' => env('MONITORING_TIER') === 'enterprise',
    ],

    // Alert channels
    'alert_channels' => [
        'email' => env('MONITORING_ALERT_EMAIL'),
        'sms' => env('MONITORING_ALERT_PHONE'),
        'slack' => env('MONITORING_SLACK_WEBHOOK'),
        'pagerduty' => env('MONITORING_PAGERDUTY_KEY'),
    ],

    // Privacy settings
    'anonymize_data' => true, // PCI-DSS compliance
    'exclude_fields' => ['customer_name', 'customer_email', 'card_number', 'cvv'],
];
```

### Step 3: Start Monitoring Agent

```bash
# Start agent as background service
php artisan monitoring:start

# Or add to cron (if service not available)
* * * * * php /path/to/shop/bin/console monitoring:collect
```

### Step 4: Verify Installation

```bash
# Test connection to monitoring platform
php artisan monitoring:test

# Expected output:
# ✅ Connection successful
# ✅ Authentication successful
# ✅ Data transmission test passed
# ℹ️ Monitoring tier: Professional
# ℹ️ Next data transmission in 60 seconds
```

### Step 5: Access Dashboard

1. Go to https://monitoring.your-saas-platform.com
2. Login with your credentials
3. See your shop in the client list
4. Configure alert rules
5. Set up notification channels

---

## Summary

This monitoring system provides:

✅ **Real-time health monitoring** - Know instantly if payment system is down
✅ **Fraud detection** - AI-powered detection of card testing, brute force attacks
✅ **Security monitoring** - Intrusion detection, SQL injection prevention
✅ **Automated alerting** - Email, SMS, Slack, PagerDuty notifications
✅ **PCI-DSS compliant** - No cardholder data transmitted or stored
✅ **GDPR compliant** - Data minimization, anonymization, right to be forgotten
✅ **SaaS dashboard** - Centralized monitoring for all VIP clients
✅ **Tiered pricing** - Basic ($99), Professional ($299), Enterprise ($999)

**Next Steps:**

1. Build central monitoring platform (SaaS)
2. Implement monitoring agent in payment module
3. Create monitoring dashboard (React/Vue)
4. Set up alerting infrastructure
5. Launch with VIP clients
6. Market as premium feature

---

**Version:** 1.0.0
**Last Updated:** 2025-10-13
**Author:** Payment Component Team
