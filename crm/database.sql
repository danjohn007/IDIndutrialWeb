-- Base de datos MySQL/MariaDB para importar en phpMyAdmin.
-- Selecciona primero la base idindust_crm_idindustrial y luego ejecuta este archivo.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(160) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(60) NOT NULL DEFAULT 'admin',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clients (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(190) NOT NULL,
  segment VARCHAR(120) NOT NULL DEFAULT 'Industrial',
  lifecycle_stage VARCHAR(40) NOT NULL DEFAULT 'Cliente',
  city VARCHAR(120) NULL,
  contact_name VARCHAR(160) NULL,
  contact_email VARCHAR(190) NULL,
  contact_phone VARCHAR(60) NULL,
  notes TEXT NULL,
  is_public TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_clients_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS opportunities (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_id INT UNSIGNED NULL,
  company_name VARCHAR(190) NOT NULL,
  contact_name VARCHAR(160) NOT NULL,
  contact_email VARCHAR(190) NULL,
  contact_phone VARCHAR(60) NULL,
  service VARCHAR(160) NOT NULL,
  source VARCHAR(120) NOT NULL DEFAULT 'Sitio web',
  status VARCHAR(80) NOT NULL DEFAULT 'Nueva solicitud',
  priority VARCHAR(30) NOT NULL DEFAULT 'Media',
  estimated_value DECIMAL(12,2) NOT NULL DEFAULT 0,
  next_action_date DATE NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_opportunities_client (client_id),
  KEY idx_opportunities_status (status),
  KEY idx_opportunities_next_action (next_action_date),
  CONSTRAINT fk_opportunities_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quotes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  opportunity_id INT UNSIGNED NOT NULL,
  quote_code VARCHAR(60) NOT NULL,
  amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  status VARCHAR(80) NOT NULL DEFAULT 'En elaboracion',
  probability TINYINT UNSIGNED NOT NULL DEFAULT 40,
  sent_at DATE NULL,
  valid_until DATE NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_quotes_code (quote_code),
  KEY idx_quotes_opportunity (opportunity_id),
  KEY idx_quotes_status (status),
  CONSTRAINT fk_quotes_opportunity FOREIGN KEY (opportunity_id) REFERENCES opportunities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activities (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  opportunity_id INT UNSIGNED NOT NULL,
  type VARCHAR(80) NOT NULL DEFAULT 'Seguimiento',
  summary TEXT NOT NULL,
  due_date DATE NULL,
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_activities_opportunity (opportunity_id),
  KEY idx_activities_due (completed_at, due_date),
  CONSTRAINT fk_activities_opportunity FOREIGN KEY (opportunity_id) REFERENCES opportunities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS client_portal_users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  opportunity_id INT UNSIGNED NOT NULL,
  client_id INT UNSIGNED NULL,
  username VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  password_change_required TINYINT(1) NOT NULL DEFAULT 1,
  password_changed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  last_login_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_client_portal_opportunity (opportunity_id),
  UNIQUE KEY uq_client_portal_username (username),
  KEY idx_client_portal_client (client_id),
  CONSTRAINT fk_client_portal_opportunity FOREIGN KEY (opportunity_id) REFERENCES opportunities(id) ON DELETE CASCADE,
  CONSTRAINT fk_client_portal_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS maintenance_logs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  opportunity_id INT UNSIGNED NOT NULL,
  portal_user_id INT UNSIGNED NULL,
  type VARCHAR(80) NOT NULL DEFAULT 'Mantenimiento',
  title VARCHAR(190) NOT NULL,
  status VARCHAR(80) NOT NULL DEFAULT 'Programado',
  scheduled_date DATE NULL,
  completed_at DATETIME NULL,
  notes TEXT NULL,
  visible_to_client TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_maintenance_logs_opportunity (opportunity_id, scheduled_date),
  KEY idx_maintenance_logs_portal (portal_user_id),
  CONSTRAINT fk_maintenance_logs_opportunity FOREIGN KEY (opportunity_id) REFERENCES opportunities(id) ON DELETE CASCADE,
  CONSTRAINT fk_maintenance_logs_portal FOREIGN KEY (portal_user_id) REFERENCES client_portal_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS client_requests (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  opportunity_id INT UNSIGNED NOT NULL,
  portal_user_id INT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  message TEXT NOT NULL,
  status VARCHAR(80) NOT NULL DEFAULT 'Recibida',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_client_requests_portal (portal_user_id, created_at),
  KEY idx_client_requests_opportunity (opportunity_id),
  CONSTRAINT fk_client_requests_opportunity FOREIGN KEY (opportunity_id) REFERENCES opportunities(id) ON DELETE CASCADE,
  CONSTRAINT fk_client_requests_portal FOREIGN KEY (portal_user_id) REFERENCES client_portal_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS notifications (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  recipient_type VARCHAR(20) NOT NULL,
  recipient_user_id INT UNSIGNED NULL,
  portal_user_id INT UNSIGNED NULL,
  opportunity_id INT UNSIGNED NULL,
  client_request_id INT UNSIGNED NULL,
  event_type VARCHAR(80) NOT NULL DEFAULT 'general',
  title VARCHAR(190) NOT NULL,
  message TEXT NOT NULL,
  target_url VARCHAR(255) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notifications_recipient (recipient_type, portal_user_id, is_read, created_at),
  KEY idx_notifications_request (client_request_id),
  KEY idx_notifications_opportunity (opportunity_id),
  CONSTRAINT fk_notifications_portal FOREIGN KEY (portal_user_id) REFERENCES client_portal_users(id) ON DELETE CASCADE,
  CONSTRAINT fk_notifications_opportunity FOREIGN KEY (opportunity_id) REFERENCES opportunities(id) ON DELETE CASCADE,
  CONSTRAINT fk_notifications_request FOREIGN KEY (client_request_id) REFERENCES client_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO users (name, email, password_hash, role)
VALUES (
  'Administrador',
  'admin@idindustrial.com.mx',
  '$2y$10$rwvvn7OEgovO6E76JAKIhOqH7jKBFSs3tJYdg0HK97JlOnULOYAxe',
  'superadmin'
);

INSERT IGNORE INTO clients (name, segment, city, is_public, notes) VALUES
  ('Daechang', 'Automotriz', 'Queretaro', 1, 'Cliente de referencia para credibilidad comercial.'),
  ('DR-ENC', 'Manufactura', 'Queretaro', 1, 'Cliente de referencia para credibilidad comercial.'),
  ('Pollux', 'Industrial', 'Bajio', 1, 'Cliente de referencia para credibilidad comercial.'),
  ('PSSL Seguridad', 'Seguridad', 'Queretaro', 1, 'Cliente de referencia para credibilidad comercial.'),
  ('AB Mexco', 'Manufactura', 'Queretaro', 1, 'Cliente de referencia para credibilidad comercial.'),
  ('Deadong HEMEX', 'Automotriz', 'Bajio', 1, 'Cliente de referencia para credibilidad comercial.'),
  ('Harman', 'Electronica', 'Queretaro', 1, 'Cliente de referencia para credibilidad comercial.'),
  ('Samsung', 'Electronica', 'Queretaro', 1, 'Cliente de referencia para credibilidad comercial.'),
  ('Michelin', 'Manufactura', 'Queretaro', 1, 'Cliente de referencia para credibilidad comercial.'),
  ('AIQ', 'Infraestructura', 'Queretaro', 1, 'Cliente de referencia para credibilidad comercial.');