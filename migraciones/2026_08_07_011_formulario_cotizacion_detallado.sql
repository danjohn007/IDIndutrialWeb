-- Campos detallados y adjuntos privados para solicitudes de cotización web.
-- MySQL 5.7+; ejecutar sobre la base unificada del CRM/IoT.

SET NAMES utf8mb4;

SET @idind_has_request_type := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'opportunities' AND COLUMN_NAME = 'request_type'
);
SET @idind_sql := IF(
  @idind_has_request_type = 0,
  'ALTER TABLE opportunities ADD COLUMN request_type VARCHAR(40) NULL AFTER service',
  'SELECT 1'
);
PREPARE idind_stmt FROM @idind_sql;
EXECUTE idind_stmt;
DEALLOCATE PREPARE idind_stmt;

SET @idind_has_project_location := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'opportunities' AND COLUMN_NAME = 'project_location'
);
SET @idind_sql := IF(
  @idind_has_project_location = 0,
  'ALTER TABLE opportunities ADD COLUMN project_location VARCHAR(160) NULL AFTER request_type',
  'SELECT 1'
);
PREPARE idind_stmt FROM @idind_sql;
EXECUTE idind_stmt;
DEALLOCATE PREPARE idind_stmt;

SET @idind_has_desired_date := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'opportunities' AND COLUMN_NAME = 'desired_execution_date'
);
SET @idind_sql := IF(
  @idind_has_desired_date = 0,
  'ALTER TABLE opportunities ADD COLUMN desired_execution_date DATE NULL AFTER project_location',
  'SELECT 1'
);
PREPARE idind_stmt FROM @idind_sql;
EXECUTE idind_stmt;
DEALLOCATE PREPARE idind_stmt;

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