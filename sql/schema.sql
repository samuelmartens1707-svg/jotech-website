-- JOTECH Admin-Bereich — Datenbankschema
-- Einspielen mit: mariadb -u <user> -p <datenbank> < sql/schema.sql

CREATE TABLE IF NOT EXISTS admin_users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(64) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
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

CREATE TABLE IF NOT EXISTS product_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  filename VARCHAR(255) NOT NULL,
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

-- Shop-Bestellungen (Warenkorb-Checkout) und ihr Lexware-Office-Rechnungs-Sync.
-- `status` ist der Bestell-Lifecycle (neu/storniert/abgeschlossen), losgelöst vom
-- Integrations-Lifecycle `lexoffice_sync_status` (pending/synced/failed).
CREATE TABLE IF NOT EXISTS orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(80) NOT NULL,
  last_name VARCHAR(80) NOT NULL,
  customer_email VARCHAR(190) NOT NULL,
  customer_phone VARCHAR(64) NOT NULL DEFAULT '',
  billing_street VARCHAR(160) NOT NULL,
  billing_zip VARCHAR(16) NOT NULL,
  billing_city VARCHAR(120) NOT NULL,
  billing_country_code VARCHAR(2) NOT NULL DEFAULT 'DE',
  total_net DECIMAL(10,2) NOT NULL,
  total_gross DECIMAL(10,2) NOT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'new',
  lexoffice_contact_id VARCHAR(64) NOT NULL DEFAULT '',
  lexoffice_invoice_id VARCHAR(64) NOT NULL DEFAULT '',
  lexoffice_sync_status VARCHAR(16) NOT NULL DEFAULT 'pending',
  lexoffice_last_error TEXT NULL,
  lexoffice_attempts INT UNSIGNED NOT NULL DEFAULT 0,
  lexoffice_last_attempt_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_orders_lexoffice_sync_status (lexoffice_sync_status)
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
