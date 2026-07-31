-- Migracion MySQL/MariaDB: seguimiento de reportes de mantenimiento del cliente.
-- Ejecutar en phpMyAdmin sobre la base del CRM.

SET @db_name := DATABASE();

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE client_requests ADD COLUMN priority VARCHAR(40) NOT NULL DEFAULT ''Media'' AFTER status',
    'SELECT 1'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'client_requests'
    AND COLUMN_NAME = 'priority'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE client_requests ADD COLUMN admin_response TEXT NULL AFTER message',
    'SELECT 1'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'client_requests'
    AND COLUMN_NAME = 'admin_response'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE client_requests ADD COLUMN resolved_at DATETIME NULL AFTER updated_at',
    'SELECT 1'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'client_requests'
    AND COLUMN_NAME = 'resolved_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE client_requests ADD COLUMN last_admin_update_at DATETIME NULL AFTER resolved_at',
    'SELECT 1'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'client_requests'
    AND COLUMN_NAME = 'last_admin_update_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE client_requests
SET priority = 'Media'
WHERE priority IS NULL OR priority = '';

SET @idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'client_requests'
    AND INDEX_NAME = 'idx_client_requests_status'
);
SET @sql := IF(
  @idx_exists = 0,
  'CREATE INDEX idx_client_requests_status ON client_requests (status, updated_at)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;