-- Migracion MySQL/MariaDB: notificaciones internas para reportes de Bitacora ID.
-- Admin recibe reportes nuevos; cliente recibe confirmaciones y seguimiento.

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