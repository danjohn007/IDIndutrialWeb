-- Gestion auditable de alertas para MySQL 5.7.
-- Ejecuta este archivo una sola vez desde phpMyAdmin.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS alerta_gestiones (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  alerta_id BIGINT UNSIGNED NOT NULL,
  accion ENUM('RECONOCER', 'RESOLVER') NOT NULL,
  responsable VARCHAR(100) NOT NULL,
  comentario VARCHAR(500) NULL,
  fecha_hora TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_gestiones_alerta_fecha (alerta_id, fecha_hora, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SHOW TABLES LIKE 'alerta_gestiones';
