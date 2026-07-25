-- Ergänzt Stripe-Checkout-Zahlung für den Shop-Bestellprozess.
-- Einspielen wie schema.sql: per phpMyAdmin (Datenbank auswählen, dann Tab "SQL")
-- oder: mariadb -u <user> -p <datenbank> < sql/003_stripe_checkout.sql

ALTER TABLE orders
  MODIFY COLUMN first_name VARCHAR(80) NULL,
  MODIFY COLUMN last_name VARCHAR(80) NULL,
  MODIFY COLUMN customer_email VARCHAR(190) NULL,
  MODIFY COLUMN billing_street VARCHAR(160) NULL,
  MODIFY COLUMN billing_zip VARCHAR(16) NULL,
  MODIFY COLUMN billing_city VARCHAR(120) NULL,
  MODIFY COLUMN status VARCHAR(16) NOT NULL DEFAULT 'pending_payment',
  ADD COLUMN IF NOT EXISTS payment_status VARCHAR(16) NOT NULL DEFAULT 'pending' AFTER status,
  ADD COLUMN IF NOT EXISTS stripe_checkout_session_id VARCHAR(255) NULL AFTER payment_status,
  ADD COLUMN IF NOT EXISTS stripe_payment_intent_id VARCHAR(64) NULL AFTER stripe_checkout_session_id,
  ADD COLUMN IF NOT EXISTS paid_at DATETIME NULL AFTER stripe_payment_intent_id;

ALTER TABLE orders
  ADD UNIQUE INDEX IF NOT EXISTS uq_orders_stripe_checkout_session_id (stripe_checkout_session_id),
  ADD INDEX IF NOT EXISTS idx_orders_payment_status (payment_status);

CREATE TABLE IF NOT EXISTS stripe_webhook_events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  stripe_event_id VARCHAR(255) NOT NULL,
  received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_stripe_webhook_events_event_id (stripe_event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
