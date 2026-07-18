-- JOTECH Admin-Bereich — Datenbankschema
-- Einspielen mit: mariadb -u <user> -p <datenbank> < sql/schema.sql

CREATE TABLE IF NOT EXISTS admin_users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(64) NOT NULL UNIQUE,
  email VARCHAR(190) NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE CASCADE,
  INDEX idx_password_resets_token_hash (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category VARCHAR(32) NOT NULL,
  title VARCHAR(160) NOT NULL,
  specs TEXT NOT NULL,
  price DECIMAL(8,2) NOT NULL,
  price_note VARCHAR(64) NOT NULL DEFAULT '',
  stock_label VARCHAR(64) NOT NULL DEFAULT 'Verfügbar',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bilddaten liegen als BLOB in der DB (nicht als Datei auf dem Container-
-- Dateisystem), da Container-Deploys das Dateisystem jedes Mal frisch aus dem
-- Git-Repo aufbauen und lokal gespeicherte Uploads sonst verloren gehen.
CREATE TABLE IF NOT EXISTS product_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  filename VARCHAR(255) NOT NULL,
  mime_type VARCHAR(64) NULL,
  data MEDIUMBLOB NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inquiries (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  form_type VARCHAR(16) NOT NULL,
  name VARCHAR(160) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(64) NOT NULL DEFAULT '',
  location VARCHAR(120) NOT NULL DEFAULT '',
  subject VARCHAR(160) NOT NULL DEFAULT '',
  details_json TEXT NOT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'neu',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Shop-Bestellungen (Warenkorb-Checkout mit Stripe-Zahlung) und ihr Lexware-Office-
-- Rechnungs-Sync. `status` ist der Bestell-Lifecycle (pending_payment/new/storniert/
-- abgeschlossen), `payment_status` der Stripe-Zahlungsstatus (pending/paid), und
-- `lexoffice_sync_status` der davon unabhängige Integrations-Lifecycle
-- (pending/synced/failed). Kundendaten sind NULL-fähig, weil sie erst nach der
-- Zahlung aus dem Stripe-Checkout-Event übernommen werden.
CREATE TABLE IF NOT EXISTS orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(80) NULL,
  last_name VARCHAR(80) NULL,
  customer_email VARCHAR(190) NULL,
  customer_phone VARCHAR(64) NOT NULL DEFAULT '',
  billing_street VARCHAR(160) NULL,
  billing_zip VARCHAR(16) NULL,
  billing_city VARCHAR(120) NULL,
  billing_country_code VARCHAR(2) NOT NULL DEFAULT 'DE',
  total_net DECIMAL(10,2) NOT NULL,
  total_gross DECIMAL(10,2) NOT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'pending_payment',
  payment_status VARCHAR(16) NOT NULL DEFAULT 'pending',
  stripe_checkout_session_id VARCHAR(255) NULL,
  stripe_payment_intent_id VARCHAR(64) NULL,
  paid_at DATETIME NULL,
  lexoffice_contact_id VARCHAR(64) NOT NULL DEFAULT '',
  lexoffice_invoice_id VARCHAR(64) NOT NULL DEFAULT '',
  lexoffice_sync_status VARCHAR(16) NOT NULL DEFAULT 'pending',
  lexoffice_last_error TEXT NULL,
  lexoffice_attempts INT UNSIGNED NOT NULL DEFAULT 0,
  lexoffice_last_attempt_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_orders_stripe_checkout_session_id (stripe_checkout_session_id),
  INDEX idx_orders_lexoffice_sync_status (lexoffice_sync_status),
  INDEX idx_orders_payment_status (payment_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NULL,
  name VARCHAR(160) NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  unit_name VARCHAR(32) NOT NULL DEFAULT 'Stück',
  unit_price_net DECIMAL(8,2) NOT NULL,
  unit_price_gross DECIMAL(8,2) NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Idempotenz-Schutz gegen von Stripe mehrfach zugestellte Webhook-Events: die
-- Event-ID wird per INSERT reserviert, bevor ein Event verarbeitet wird.
CREATE TABLE IF NOT EXISTS stripe_webhook_events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  stripe_event_id VARCHAR(255) NOT NULL,
  received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_stripe_webhook_events_event_id (stripe_event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
