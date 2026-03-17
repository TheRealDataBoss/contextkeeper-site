-- contextkeeper Stripe Billing Migration
-- Run this in phpMyAdmin at contextkeeper.org:2083
-- Database: ckmatt_contextkeeper

-- Add subscription tracking columns to users table
-- (stripe_customer_id and stripe_subscription_id may already exist, use IGNORE)
ALTER TABLE users ADD COLUMN IF NOT EXISTS subscription_status VARCHAR(20) DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS current_period_end TIMESTAMP NULL DEFAULT NULL;

-- Webhook event log for idempotency and audit
CREATE TABLE IF NOT EXISTS webhook_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  stripe_event_id VARCHAR(255) UNIQUE NOT NULL,
  event_type VARCHAR(100) NOT NULL,
  payload LONGTEXT,
  processed TINYINT(1) DEFAULT 0,
  processed_at TIMESTAMP NULL DEFAULT NULL,
  error_message TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_event_id (stripe_event_id),
  INDEX idx_type (event_type),
  INDEX idx_processed (processed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
