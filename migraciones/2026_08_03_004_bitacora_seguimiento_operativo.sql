-- Migracion MySQL/MariaDB: seguimiento operativo de Bitacora ID.
-- Agrega SLA, programacion, responsable y notas internas a reportes del cliente.
-- Ejecutar en phpMyAdmin sobre la base del CRM.

SET @db_name := DATABASE();

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE client_requests ADD COLUMN due_date DATE NULL AFTER priority',
    'SELECT 1'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'client_requests'
    AND COLUMN_NAME = 'due_date'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE client_requests ADD COLUMN scheduled_date DATE NULL AFTER due_date',
    'SELECT 1'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'client_requests'
    AND COLUMN_NAME = 'scheduled_date'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE client_requests ADD COLUMN assigned_to VARCHAR(160) NULL AFTER scheduled_date',
    'SELECT 1'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'client_requests'
    AND COLUMN_NAME = 'assigned_to'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE client_requests ADD COLUMN internal_notes TEXT NULL AFTER admin_response',
    'SELECT 1'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'client_requests'
    AND COLUMN_NAME = 'internal_notes'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE client_requests
SET due_date = DATE_ADD(
  DATE(created_at),
  INTERVAL CASE
    WHEN priority = 'Urgente' THEN 1
    WHEN priority = 'Alta' THEN 2
    WHEN priority = 'Baja' THEN 10
    ELSE 5
  END DAY
)
WHERE due_date IS NULL;

SET @idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'client_requests'
    AND INDEX_NAME = 'idx_client_requests_due_status'
);
SET @sql := IF(
  @idx_exists = 0,
  'CREATE INDEX idx_client_requests_due_status ON client_requests (status, due_date)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;