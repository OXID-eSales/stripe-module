# Database Schema: Real-Time Monitoring SaaS Platform

**Version:** 1.0.0
**Date:** 2025-10-13
**Database:** PostgreSQL 14+ (OLTP) + InfluxDB 2.0+ (Time-Series)
**Purpose:** Store client data, alerts, and metrics for monitoring platform

---

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [PostgreSQL Schema (OLTP)](#postgresql-schema-oltp)
3. [InfluxDB Schema (Time-Series)](#influxdb-schema-time-series)
4. [Indexes & Performance](#indexes--performance)
5. [Data Retention Policies](#data-retention-policies)
6. [Backup & Recovery](#backup--recovery)
7. [Scaling Strategy](#scaling-strategy)

---

## Architecture Overview

### Database Strategy

```
┌─────────────────────────────────────────────────────────┐
│  PostgreSQL (OLTP)                                      │
│  • Client accounts, users, subscriptions               │
│  • Alert rules, notification settings                  │
│  • Security events, fraud incidents                    │
│  • Billing, invoices                                   │
│  • ~10GB/year growth                                   │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│  InfluxDB (Time-Series)                                 │
│  • Health metrics (every 60s)                          │
│  • Transaction telemetry (real-time)                   │
│  • Performance metrics                                 │
│  • ~1TB/year growth (compressed)                       │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│  Redis (Cache)                                          │
│  • Session storage                                     │
│  • Real-time dashboard data                            │
│  • Rate limiting counters                              │
│  • ~2GB total                                          │
└─────────────────────────────────────────────────────────┘
```

### Why Two Databases?

**PostgreSQL (OLTP):**
- ✅ ACID compliance for billing and user data
- ✅ Complex queries with JOINs
- ✅ Perfect for relational data
- ❌ Not optimized for time-series data

**InfluxDB (Time-Series):**
- ✅ Optimized for time-series data (100x faster writes)
- ✅ Built-in downsampling and retention policies
- ✅ Efficient compression (10:1 ratio)
- ✅ Query language optimized for time-series (Flux)
- ❌ Not suitable for relational data

---

## PostgreSQL Schema (OLTP)

### Entity Relationship Diagram

```
┌─────────────┐          ┌──────────────┐
│   users     │ 1      * │   clients    │
│─────────────│──────────│──────────────│
│ id (PK)     │          │ id (PK)      │
│ email       │          │ user_id (FK) │
│ password    │          │ name         │
│ role        │          │ status       │
│ created_at  │          │ tier         │
└─────────────┘          │ api_key      │
                         │ created_at   │
                         └──────────────┘
                                │
                                │ 1
                                │
                                │ *
┌─────────────────────────┐    │    ┌──────────────────┐
│   alert_rules           │    │    │   subscriptions  │
│─────────────────────────│────┘    │──────────────────│
│ id (PK)                 │         │ id (PK)          │
│ client_id (FK)          │         │ client_id (FK)   │
│ name                    │         │ plan             │
│ condition               │         │ status           │
│ severity                │         │ amount           │
│ channels                │         │ period_start     │
│ enabled                 │         │ period_end       │
│ created_at              │         │ stripe_sub_id    │
└─────────────────────────┘         └──────────────────┘

┌─────────────────────────┐         ┌──────────────────┐
│   alerts                │         │  fraud_incidents │
│─────────────────────────│         │──────────────────│
│ id (PK)                 │         │ id (PK)          │
│ client_id (FK)          │         │ client_id (FK)   │
│ rule_id (FK)            │         │ type             │
│ severity                │         │ severity         │
│ message                 │         │ score            │
│ status                  │         │ description      │
│ triggered_at            │         │ raw_data         │
│ resolved_at             │         │ detected_at      │
│ acknowledged_by         │         │ resolved_at      │
└─────────────────────────┘         └──────────────────┘

┌─────────────────────────┐         ┌──────────────────┐
│  security_events        │         │  audit_logs      │
│─────────────────────────│         │──────────────────│
│ id (PK)                 │         │ id (PK)          │
│ client_id (FK)          │         │ user_id (FK)     │
│ type                    │         │ action           │
│ severity                │         │ resource         │
│ ip_hash                 │         │ details          │
│ user_agent_hash         │         │ ip_address       │
│ description             │         │ created_at       │
│ blocked                 │         └──────────────────┘
│ occurred_at             │
└─────────────────────────┘
```

---

### Table Definitions

#### 1. `users` - Platform Users

```sql
CREATE TABLE users (
    id BIGSERIAL PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    role VARCHAR(50) NOT NULL DEFAULT 'admin', -- admin, member, viewer
    email_verified_at TIMESTAMP,
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    two_factor_secret VARCHAR(255),
    api_token VARCHAR(255) UNIQUE,
    last_login_at TIMESTAMP,
    last_login_ip INET,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW(),
    deleted_at TIMESTAMP -- Soft delete
);

CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_api_token ON users(api_token);
CREATE INDEX idx_users_deleted_at ON users(deleted_at);

COMMENT ON TABLE users IS 'Platform users (shop owners, admins)';
COMMENT ON COLUMN users.role IS 'User role: admin (full access), member (limited), viewer (read-only)';
```

#### 2. `clients` - Monitored Client Installations

```sql
CREATE TABLE clients (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL, -- Shop name
    domain VARCHAR(255), -- shop.example.com
    status VARCHAR(50) NOT NULL DEFAULT 'active', -- active, suspended, cancelled
    tier VARCHAR(50) NOT NULL DEFAULT 'basic', -- basic, professional, enterprise

    -- API credentials
    api_key VARCHAR(255) NOT NULL UNIQUE,
    api_secret VARCHAR(255) NOT NULL,

    -- Module info
    module_version VARCHAR(50),
    php_version VARCHAR(50),
    platform VARCHAR(50), -- oxid, shopware, magento, woocommerce
    platform_version VARCHAR(50),

    -- Last seen
    last_heartbeat_at TIMESTAMP,
    last_data_received_at TIMESTAMP,

    -- Health status
    health_status VARCHAR(50) DEFAULT 'unknown', -- healthy, degraded, critical, down, unknown

    -- Settings
    alert_email VARCHAR(255),
    alert_phone VARCHAR(50),
    slack_webhook_url TEXT,
    pagerduty_key VARCHAR(255),

    -- Metadata
    timezone VARCHAR(100) DEFAULT 'UTC',
    currency VARCHAR(10) DEFAULT 'EUR',
    country VARCHAR(10),

    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW(),
    deleted_at TIMESTAMP -- Soft delete
);

CREATE INDEX idx_clients_user_id ON clients(user_id);
CREATE INDEX idx_clients_api_key ON clients(api_key);
CREATE INDEX idx_clients_status ON clients(status);
CREATE INDEX idx_clients_tier ON clients(tier);
CREATE INDEX idx_clients_health_status ON clients(health_status);
CREATE INDEX idx_clients_last_heartbeat ON clients(last_heartbeat_at);
CREATE INDEX idx_clients_deleted_at ON clients(deleted_at);

COMMENT ON TABLE clients IS 'Client installations (shops being monitored)';
COMMENT ON COLUMN clients.tier IS 'Subscription tier: basic, professional, enterprise';
COMMENT ON COLUMN clients.health_status IS 'Current health: healthy, degraded, critical, down';
```

#### 3. `subscriptions` - Client Subscriptions

```sql
CREATE TABLE subscriptions (
    id BIGSERIAL PRIMARY KEY,
    client_id BIGINT NOT NULL REFERENCES clients(id) ON DELETE CASCADE,

    -- Subscription details
    plan VARCHAR(50) NOT NULL, -- basic, professional, enterprise
    status VARCHAR(50) NOT NULL DEFAULT 'active', -- active, cancelled, past_due, unpaid
    amount DECIMAL(10, 2) NOT NULL, -- Monthly amount in cents
    currency VARCHAR(10) NOT NULL DEFAULT 'EUR',

    -- Billing period
    period_start TIMESTAMP NOT NULL,
    period_end TIMESTAMP NOT NULL,
    trial_ends_at TIMESTAMP,

    -- Stripe integration
    stripe_customer_id VARCHAR(255),
    stripe_subscription_id VARCHAR(255),
    stripe_price_id VARCHAR(255),

    -- Payment method
    payment_method VARCHAR(50), -- card, sepa, invoice
    last_4_digits VARCHAR(4),
    card_brand VARCHAR(50),

    -- Cancellation
    cancelled_at TIMESTAMP,
    cancellation_reason TEXT,

    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_subscriptions_client_id ON subscriptions(client_id);
CREATE INDEX idx_subscriptions_status ON subscriptions(status);
CREATE INDEX idx_subscriptions_stripe_customer_id ON subscriptions(stripe_customer_id);
CREATE INDEX idx_subscriptions_period_end ON subscriptions(period_end);

COMMENT ON TABLE subscriptions IS 'Client subscription records';
```

#### 4. `invoices` - Billing Invoices

```sql
CREATE TABLE invoices (
    id BIGSERIAL PRIMARY KEY,
    client_id BIGINT NOT NULL REFERENCES clients(id) ON DELETE CASCADE,
    subscription_id BIGINT REFERENCES subscriptions(id) ON DELETE SET NULL,

    -- Invoice details
    invoice_number VARCHAR(50) NOT NULL UNIQUE,
    status VARCHAR(50) NOT NULL DEFAULT 'pending', -- pending, paid, failed, refunded
    amount DECIMAL(10, 2) NOT NULL,
    tax DECIMAL(10, 2) DEFAULT 0,
    total DECIMAL(10, 2) NOT NULL,
    currency VARCHAR(10) NOT NULL DEFAULT 'EUR',

    -- Billing info
    billing_name VARCHAR(255),
    billing_email VARCHAR(255),
    billing_address TEXT,
    vat_number VARCHAR(50),

    -- Payment
    payment_method VARCHAR(50),
    stripe_invoice_id VARCHAR(255),
    stripe_payment_intent_id VARCHAR(255),

    -- Dates
    invoice_date TIMESTAMP NOT NULL,
    due_date TIMESTAMP NOT NULL,
    paid_at TIMESTAMP,

    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_invoices_client_id ON invoices(client_id);
CREATE INDEX idx_invoices_status ON invoices(status);
CREATE INDEX idx_invoices_invoice_number ON invoices(invoice_number);
CREATE INDEX idx_invoices_due_date ON invoices(due_date);

COMMENT ON TABLE invoices IS 'Billing invoices for subscriptions';
```

#### 5. `alert_rules` - Alerting Rules

```sql
CREATE TABLE alert_rules (
    id BIGSERIAL PRIMARY KEY,
    client_id BIGINT NOT NULL REFERENCES clients(id) ON DELETE CASCADE,

    -- Rule details
    name VARCHAR(255) NOT NULL,
    description TEXT,
    rule_type VARCHAR(50) NOT NULL, -- threshold, anomaly, fraud, security

    -- Condition (JSON)
    condition JSONB NOT NULL, -- e.g. {"metric": "success_rate", "operator": "<", "value": 90}

    -- Severity
    severity VARCHAR(50) NOT NULL, -- critical, high, medium, low

    -- Notification channels (array)
    channels TEXT[] NOT NULL DEFAULT '{}', -- ['email', 'sms', 'slack']

    -- Settings
    enabled BOOLEAN DEFAULT TRUE,
    cooldown_minutes INTEGER DEFAULT 60, -- Don't re-alert for X minutes

    -- Metadata
    last_triggered_at TIMESTAMP,
    trigger_count INTEGER DEFAULT 0,

    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_alert_rules_client_id ON alert_rules(client_id);
CREATE INDEX idx_alert_rules_enabled ON alert_rules(enabled);
CREATE INDEX idx_alert_rules_rule_type ON alert_rules(rule_type);

COMMENT ON TABLE alert_rules IS 'Alert rules configuration';
COMMENT ON COLUMN alert_rules.condition IS 'Alert condition as JSON (flexible for different rule types)';
```

#### 6. `alerts` - Triggered Alerts

```sql
CREATE TABLE alerts (
    id BIGSERIAL PRIMARY KEY,
    client_id BIGINT NOT NULL REFERENCES clients(id) ON DELETE CASCADE,
    rule_id BIGINT REFERENCES alert_rules(id) ON DELETE SET NULL,

    -- Alert details
    severity VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,

    -- Status
    status VARCHAR(50) NOT NULL DEFAULT 'active', -- active, acknowledged, resolved, dismissed

    -- Data that triggered alert
    trigger_data JSONB,

    -- Timestamps
    triggered_at TIMESTAMP NOT NULL DEFAULT NOW(),
    acknowledged_at TIMESTAMP,
    acknowledged_by BIGINT REFERENCES users(id) ON DELETE SET NULL,
    resolved_at TIMESTAMP,
    resolved_by BIGINT REFERENCES users(id) ON DELETE SET NULL,

    -- Notification tracking
    notification_sent_at TIMESTAMP,
    notification_channels TEXT[],

    created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_alerts_client_id ON alerts(client_id);
CREATE INDEX idx_alerts_rule_id ON alerts(rule_id);
CREATE INDEX idx_alerts_status ON alerts(status);
CREATE INDEX idx_alerts_severity ON alerts(severity);
CREATE INDEX idx_alerts_triggered_at ON alerts(triggered_at);

COMMENT ON TABLE alerts IS 'Triggered alerts (history)';
```

#### 7. `fraud_incidents` - Detected Fraud

```sql
CREATE TABLE fraud_incidents (
    id BIGSERIAL PRIMARY KEY,
    client_id BIGINT NOT NULL REFERENCES clients(id) ON DELETE CASCADE,

    -- Incident type
    type VARCHAR(50) NOT NULL, -- card_testing, volume_spike, geo_anomaly, amount_anomaly, velocity_abuse
    severity VARCHAR(50) NOT NULL, -- critical, high, medium, low
    score INTEGER NOT NULL, -- 0-100 fraud score

    -- Description
    description TEXT NOT NULL,
    recommendation TEXT,

    -- Raw data
    raw_data JSONB NOT NULL, -- All details about the incident

    -- Status
    status VARCHAR(50) NOT NULL DEFAULT 'active', -- active, investigating, resolved, false_positive

    -- Actions taken
    auto_blocked BOOLEAN DEFAULT FALSE,
    blocked_ip_addresses TEXT[],

    -- Timestamps
    detected_at TIMESTAMP NOT NULL DEFAULT NOW(),
    resolved_at TIMESTAMP,
    resolved_by BIGINT REFERENCES users(id) ON DELETE SET NULL,

    created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_fraud_incidents_client_id ON fraud_incidents(client_id);
CREATE INDEX idx_fraud_incidents_type ON fraud_incidents(type);
CREATE INDEX idx_fraud_incidents_severity ON fraud_incidents(severity);
CREATE INDEX idx_fraud_incidents_status ON fraud_incidents(status);
CREATE INDEX idx_fraud_incidents_detected_at ON fraud_incidents(detected_at);

COMMENT ON TABLE fraud_incidents IS 'Detected fraud incidents';
```

#### 8. `security_events` - Security Incidents

```sql
CREATE TABLE security_events (
    id BIGSERIAL PRIMARY KEY,
    client_id BIGINT NOT NULL REFERENCES clients(id) ON DELETE CASCADE,

    -- Event type
    type VARCHAR(50) NOT NULL, -- sql_injection, xss, brute_force, unauthorized_access, webhook_replay
    severity VARCHAR(50) NOT NULL, -- critical, high, medium, low

    -- Details
    description TEXT NOT NULL,
    url TEXT,
    method VARCHAR(10), -- GET, POST, etc.

    -- Attacker info (anonymized)
    ip_hash VARCHAR(64), -- SHA-256 hash
    user_agent_hash VARCHAR(64), -- SHA-256 hash
    country VARCHAR(10),

    -- Request details
    request_payload JSONB, -- Sanitized suspicious data

    -- Action taken
    blocked BOOLEAN DEFAULT FALSE,
    block_duration_minutes INTEGER,

    occurred_at TIMESTAMP NOT NULL DEFAULT NOW(),
    created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_security_events_client_id ON security_events(client_id);
CREATE INDEX idx_security_events_type ON security_events(type);
CREATE INDEX idx_security_events_severity ON security_events(severity);
CREATE INDEX idx_security_events_occurred_at ON security_events(occurred_at);
CREATE INDEX idx_security_events_ip_hash ON security_events(ip_hash);

COMMENT ON TABLE security_events IS 'Security incidents and attacks';
```

#### 9. `audit_logs` - Audit Trail

```sql
CREATE TABLE audit_logs (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
    client_id BIGINT REFERENCES clients(id) ON DELETE CASCADE,

    -- Action details
    action VARCHAR(100) NOT NULL, -- e.g. 'alert.acknowledged', 'rule.created', 'client.suspended'
    resource_type VARCHAR(50), -- e.g. 'alert', 'rule', 'client'
    resource_id BIGINT,

    -- Details
    description TEXT,
    changes JSONB, -- Before/after data

    -- Context
    ip_address INET,
    user_agent TEXT,

    created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_audit_logs_user_id ON audit_logs(user_id);
CREATE INDEX idx_audit_logs_client_id ON audit_logs(client_id);
CREATE INDEX idx_audit_logs_action ON audit_logs(action);
CREATE INDEX idx_audit_logs_created_at ON audit_logs(created_at);

COMMENT ON TABLE audit_logs IS 'Audit trail for all platform actions';
```

#### 10. `api_keys` - API Access Keys

```sql
CREATE TABLE api_keys (
    id BIGSERIAL PRIMARY KEY,
    client_id BIGINT NOT NULL REFERENCES clients(id) ON DELETE CASCADE,

    -- Key details
    name VARCHAR(255) NOT NULL, -- User-friendly name
    key_hash VARCHAR(255) NOT NULL UNIQUE, -- SHA-256 hash of actual key
    prefix VARCHAR(10) NOT NULL, -- First 8 chars (for identification)

    -- Permissions
    scopes TEXT[] NOT NULL DEFAULT '{}', -- ['read:metrics', 'write:alerts']

    -- Usage tracking
    last_used_at TIMESTAMP,
    usage_count BIGINT DEFAULT 0,

    -- Status
    enabled BOOLEAN DEFAULT TRUE,
    expires_at TIMESTAMP,

    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    revoked_at TIMESTAMP
);

CREATE INDEX idx_api_keys_client_id ON api_keys(client_id);
CREATE INDEX idx_api_keys_key_hash ON api_keys(key_hash);
CREATE INDEX idx_api_keys_prefix ON api_keys(prefix);
CREATE INDEX idx_api_keys_enabled ON api_keys(enabled);

COMMENT ON TABLE api_keys IS 'API access keys for programmatic access';
```

---

## InfluxDB Schema (Time-Series)

### Bucket Structure

```
monitoring_platform/
├── health_metrics/           (Retention: 90 days)
│   └── measurement: health
│       ├── tags:
│       │   ├── client_id
│       │   ├── tier (basic/pro/enterprise)
│       │   └── region (eu/us/asia)
│       └── fields:
│           ├── memory_usage_mb
│           ├── disk_free_gb
│           ├── transaction_count
│           ├── success_rate
│           ├── avg_response_time_ms
│           ├── p95_response_time_ms
│           ├── error_count
│           └── provider_api_reachable (bool)
│
├── transaction_metrics/      (Retention: 365 days)
│   └── measurement: transactions
│       ├── tags:
│       │   ├── client_id
│       │   ├── payment_method (card/paymenter/etc)
│       │   ├── currency (EUR/USD/GBP)
│       │   ├── status (success/failure)
│       │   ├── customer_country
│       │   └── customer_type (new/returning)
│       └── fields:
│           ├── amount
│           ├── response_time_ms
│           ├── provider_response_time_ms
│           ├── risk_score
│           ├── 3ds_authenticated (bool)
│           └── fraud_check_passed (bool)
│
└── downsampled_metrics/      (Retention: 2 years)
    └── measurement: health_hourly
        ├── tags: (same as health_metrics)
        └── fields: (aggregated)
            ├── avg_success_rate
            ├── min_success_rate
            ├── max_success_rate
            ├── avg_response_time_ms
            ├── p95_response_time_ms
            ├── total_transactions
            └── total_errors
```

### InfluxDB Data Model

#### 1. Health Metrics (Every 60 seconds)

```flux
// Write example (Go SDK)
point := influxdb2.NewPoint(
    "health",
    map[string]string{
        "client_id": "12345",
        "tier":      "professional",
        "region":    "eu",
    },
    map[string]interface{}{
        "memory_usage_mb":       512.5,
        "disk_free_gb":          120.3,
        "transaction_count":     1234,
        "success_rate":          98.5,
        "avg_response_time_ms":  150,
        "p95_response_time_ms":  300,
        "error_count":           5,
        "provider_api_reachable": true,
    },
    time.Now(),
)
```

**Query Example (Last Hour Success Rate):**
```flux
from(bucket: "health_metrics")
  |> range(start: -1h)
  |> filter(fn: (r) => r._measurement == "health")
  |> filter(fn: (r) => r.client_id == "12345")
  |> filter(fn: (r) => r._field == "success_rate")
  |> aggregateWindow(every: 5m, fn: mean)
```

#### 2. Transaction Metrics (Real-time)

```flux
// Write example
point := influxdb2.NewPoint(
    "transactions",
    map[string]string{
        "client_id":        "12345",
        "payment_method":   "card",
        "currency":         "EUR",
        "status":           "success",
        "customer_country": "DE",
        "customer_type":    "returning",
    },
    map[string]interface{}{
        "amount":                  99.99,
        "response_time_ms":        250,
        "provider_response_time_ms": 180,
        "risk_score":              15,
        "3ds_authenticated":       true,
        "fraud_check_passed":      true,
    },
    time.Now(),
)
```

**Query Example (Transaction Volume Last 24h):**
```flux
from(bucket: "transaction_metrics")
  |> range(start: -24h)
  |> filter(fn: (r) => r._measurement == "transactions")
  |> filter(fn: (r) => r.client_id == "12345")
  |> aggregateWindow(every: 1h, fn: count)
```

#### 3. Downsampling Task (Automated)

```flux
// Downsample health metrics to hourly averages
option task = {name: "downsample_health_hourly", every: 1h}

from(bucket: "health_metrics")
  |> range(start: -1h)
  |> filter(fn: (r) => r._measurement == "health")
  |> aggregateWindow(every: 1h, fn: mean)
  |> to(bucket: "downsampled_metrics", org: "monitoring_platform")
```

---

## Indexes & Performance

### PostgreSQL Indexes

#### Composite Indexes for Common Queries

```sql
-- Dashboard: Get recent alerts for client
CREATE INDEX idx_alerts_client_status_triggered
ON alerts(client_id, status, triggered_at DESC);

-- Dashboard: Get fraud incidents by severity
CREATE INDEX idx_fraud_client_severity_detected
ON fraud_incidents(client_id, severity, detected_at DESC);

-- API: Get active subscriptions expiring soon
CREATE INDEX idx_subscriptions_status_period_end
ON subscriptions(status, period_end)
WHERE status = 'active';

-- Dashboard: Get security events by type
CREATE INDEX idx_security_events_client_type_occurred
ON security_events(client_id, type, occurred_at DESC);

-- Audit: Search audit logs by action
CREATE INDEX idx_audit_logs_action_created
ON audit_logs(action, created_at DESC);
```

#### Full-Text Search Indexes

```sql
-- Search alerts by message
CREATE INDEX idx_alerts_message_fts
ON alerts USING gin(to_tsvector('english', message));

-- Search fraud incidents by description
CREATE INDEX idx_fraud_description_fts
ON fraud_incidents USING gin(to_tsvector('english', description));
```

#### Partial Indexes (Better Performance)

```sql
-- Index only active alerts
CREATE INDEX idx_active_alerts
ON alerts(client_id, triggered_at)
WHERE status = 'active';

-- Index only unresolved fraud incidents
CREATE INDEX idx_unresolved_fraud
ON fraud_incidents(client_id, detected_at)
WHERE status IN ('active', 'investigating');

-- Index only enabled alert rules
CREATE INDEX idx_enabled_rules
ON alert_rules(client_id)
WHERE enabled = TRUE;
```

### InfluxDB Performance Tuning

#### 1. Tag Cardinality Management

```yaml
# Keep tag cardinality low (< 100K unique values per tag)
# Good tags (low cardinality):
- client_id: ~1,500 unique values
- tier: 3 unique values (basic/pro/enterprise)
- region: 3 unique values (eu/us/asia)
- payment_method: 10 unique values

# Bad tags (high cardinality):
- transaction_id: millions of unique values → Use field instead
- ip_address: thousands of unique values → Use field instead
```

#### 2. Shard Duration Configuration

```toml
# InfluxDB config
[data]
  # Health metrics (1-minute precision)
  wal-fsync-delay = "100ms"

  # Shard duration (optimize for query patterns)
  # 1 day shards for recent data (frequent queries)
  # 7 day shards for older data (rare queries)
```

---

## Data Retention Policies

### PostgreSQL Retention

```sql
-- Clean up old data (run monthly via cron)

-- Delete resolved alerts older than 90 days
DELETE FROM alerts
WHERE status = 'resolved'
  AND resolved_at < NOW() - INTERVAL '90 days';

-- Delete old audit logs older than 2 years
DELETE FROM audit_logs
WHERE created_at < NOW() - INTERVAL '2 years';

-- Delete old fraud incidents (resolved) older than 1 year
DELETE FROM fraud_incidents
WHERE status IN ('resolved', 'false_positive')
  AND resolved_at < NOW() - INTERVAL '1 year';

-- Archive old invoices (move to cold storage)
-- Keep 7 years for tax compliance
DELETE FROM invoices
WHERE status = 'paid'
  AND paid_at < NOW() - INTERVAL '7 years';
```

### InfluxDB Retention Policies

```flux
// Set retention policies per bucket

// Health metrics: 90 days full resolution
bucket: "health_metrics"
retention: 90d

// Transaction metrics: 1 year full resolution
bucket: "transaction_metrics"
retention: 365d

// Downsampled metrics: 2 years (hourly aggregates)
bucket: "downsampled_metrics"
retention: 730d
```

### Tier-Based Retention

| Tier | PostgreSQL (Alerts) | InfluxDB (Metrics) | Total Data/Client |
|------|---------------------|---------------------|-------------------|
| Basic | 30 days | 30 days | ~100 MB |
| Professional | 90 days | 90 days | ~300 MB |
| Enterprise | 365 days | 365 days | ~1.2 GB |

---

## Backup & Recovery

### PostgreSQL Backup Strategy

```bash
#!/bin/bash
# Daily backup script (run via cron)

# Full backup (daily)
pg_dump -U postgres -h localhost monitoring_db > /backups/daily/monitoring_$(date +%Y%m%d).sql

# Compress
gzip /backups/daily/monitoring_$(date +%Y%m%d).sql

# Upload to S3
aws s3 cp /backups/daily/monitoring_$(date +%Y%m%d).sql.gz s3://monitoring-backups/daily/

# Keep 30 daily backups, 12 monthly backups
find /backups/daily -mtime +30 -delete

# Weekly backup (every Sunday)
if [ $(date +%u) -eq 7 ]; then
    cp /backups/daily/monitoring_$(date +%Y%m%d).sql.gz /backups/weekly/
fi
```

### InfluxDB Backup Strategy

```bash
#!/bin/bash
# Backup InfluxDB buckets

# Backup all buckets
influx backup /backups/influx/$(date +%Y%m%d) --bucket health_metrics
influx backup /backups/influx/$(date +%Y%m%d) --bucket transaction_metrics

# Upload to S3
aws s3 sync /backups/influx/$(date +%Y%m%d) s3://monitoring-backups/influx/$(date +%Y%m%d)

# Keep 7 daily backups
find /backups/influx -mtime +7 -delete
```

### Disaster Recovery Plan

**RTO (Recovery Time Objective):** 4 hours
**RPO (Recovery Point Objective):** 1 hour

**Recovery Steps:**
1. Provision new database servers (AWS RDS, InfluxDB Cloud)
2. Restore latest PostgreSQL backup (30 minutes)
3. Restore latest InfluxDB backup (1 hour)
4. Update DNS to point to new servers (5 minutes)
5. Verify data integrity (30 minutes)
6. Resume monitoring (immediate)

---

## Scaling Strategy

### Vertical Scaling (Year 1-2)

**PostgreSQL:**
- Start: db.t3.medium (2 vCPU, 4GB RAM) - $60/mo
- Scale to: db.r5.large (2 vCPU, 16GB RAM) - $180/mo
- Sufficient for 1,000 clients

**InfluxDB:**
- Start: t3.large (2 vCPU, 8GB RAM) - $70/mo
- Scale to: r5.xlarge (4 vCPU, 32GB RAM) - $240/mo
- Sufficient for 1,500 clients

### Horizontal Scaling (Year 3+)

**PostgreSQL (Read Replicas):**
```
Primary (Write) ─┬─→ Replica 1 (Read)
                 ├─→ Replica 2 (Read)
                 └─→ Replica 3 (Read)
```

**InfluxDB (Clustering):**
```
Load Balancer
     ├─→ InfluxDB Node 1 (Bucket: health_metrics)
     ├─→ InfluxDB Node 2 (Bucket: transaction_metrics)
     └─→ InfluxDB Node 3 (Bucket: downsampled_metrics)
```

### Estimated Storage Growth

| Year | Clients | PostgreSQL Size | InfluxDB Size | Total |
|------|---------|-----------------|---------------|-------|
| 1 | 150 | 5 GB | 50 GB | 55 GB |
| 2 | 600 | 20 GB | 200 GB | 220 GB |
| 3 | 1,500 | 50 GB | 500 GB | 550 GB |

**Storage Costs (AWS):**
- PostgreSQL: $0.115/GB-month
- InfluxDB (EBS): $0.10/GB-month

Year 3 storage cost: ~$60/month

---

## Database Migration Scripts

### Initial Schema Setup

```sql
-- Run this script to set up the database

-- Enable extensions
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pg_trgm"; -- For fuzzy text search

-- Create enum types
CREATE TYPE user_role AS ENUM ('admin', 'member', 'viewer');
CREATE TYPE client_status AS ENUM ('active', 'suspended', 'cancelled');
CREATE TYPE subscription_plan AS ENUM ('basic', 'professional', 'enterprise');
CREATE TYPE alert_severity AS ENUM ('critical', 'high', 'medium', 'low');

-- Create all tables (see above)
-- ...

-- Create functions
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Add triggers for updated_at
CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_clients_updated_at BEFORE UPDATE ON clients
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Add more triggers for other tables...
```

---

## Summary

### Database Architecture Summary

✅ **PostgreSQL (OLTP)** - User accounts, billing, alerts, audit logs
✅ **InfluxDB (Time-Series)** - Health metrics, transaction telemetry
✅ **Redis (Cache)** - Sessions, real-time dashboard, rate limiting

### Performance Characteristics

| Database | Write Rate | Query Latency | Storage Efficiency |
|----------|------------|---------------|-------------------|
| PostgreSQL | 1K writes/sec | <10ms | 1x |
| InfluxDB | 100K writes/sec | <50ms | 10x (compression) |
| Redis | 1M ops/sec | <1ms | N/A (in-memory) |

### Estimated Costs (Year 3)

| Component | Size | Monthly Cost |
|-----------|------|--------------|
| PostgreSQL (RDS) | 50 GB | $180 |
| InfluxDB (EC2 + EBS) | 500 GB | $300 |
| Redis (ElastiCache) | 8 GB | $50 |
| Backups (S3) | 100 GB | $5 |
| **Total** | - | **$535/mo** |

**Cost per client:** $0.36/mo (at 1,500 clients)

---

**Version:** 1.0.0
**Last Updated:** 2025-10-13
**Author:** Payment Component Team
