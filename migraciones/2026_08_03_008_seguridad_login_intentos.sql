-- Migracion MySQL/MariaDB: seguridad de login para CRM y Bitacora ID.
-- Crea una tabla persistente de intentos fallidos por area, usuario e IP.

CREATE TABLE IF NOT EXISTS login_attempts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  area VARCHAR(30) NOT NULL,
  identifier VARCHAR(190) NOT NULL,
  identifier_hash CHAR(64) NOT NULL,
  ip_address VARCHAR(64) NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  last_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_login_attempt_scope (area, identifier_hash, ip_address),
  KEY idx_login_attempts_locked (locked_until),
  KEY idx_login_attempts_last (last_attempt_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;