-- Completa la instalacion del formulario detallado de cotizacion.
-- MySQL 5.7+ y hosting compartido sin acceso a information_schema.
-- El CRM crea automaticamente las columnas y la tabla de adjuntos.
-- Ejecutar UNA SOLA VEZ para agregar los indices de consulta.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS opportunity_attachments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  opportunity_id INT UNSIGNED NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  original_name VARCHAR(190) NOT NULL,
  mime VARCHAR(100) NOT NULL,
  size INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_opportunity_attachments_opportunity (opportunity_id),
  CONSTRAINT fk_opportunity_attachments_opportunity
    FOREIGN KEY (opportunity_id) REFERENCES opportunities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE opportunities
  ADD INDEX idx_opportunities_request_type (request_type),
  ADD INDEX idx_opportunities_desired_execution_date (desired_execution_date);

-- Verificacion compatible con usuarios restringidos de cPanel/phpMyAdmin.
SHOW COLUMNS FROM opportunities LIKE 'request_type';
SHOW COLUMNS FROM opportunities LIKE 'project_location';
SHOW COLUMNS FROM opportunities LIKE 'desired_execution_date';
SHOW TABLES LIKE 'opportunity_attachments';
SHOW INDEX FROM opportunities;
