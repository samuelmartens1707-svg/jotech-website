-- Ergänzt die "Passwort vergessen"-Funktion im Admin-Bereich.
-- Einspielen wie schema.sql: per phpMyAdmin (Datenbank auswählen, dann Tab "SQL")
-- oder: mariadb -u <user> -p <datenbank> < sql/002_password_reset.sql

ALTER TABLE admin_users
  ADD COLUMN email VARCHAR(190) NULL AFTER username;

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
