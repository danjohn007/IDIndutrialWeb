-- Migracion MySQL/MariaDB compatible con phpMyAdmin/cPanel.
-- Normaliza columnas de cotizaciones para evitar celdas vacias en estatus, probabilidad y vigencia.

SET @db := DATABASE();

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'quotes' AND COLUMN_NAME = 'status') = 0,
  'ALTER TABLE quotes ADD COLUMN status VARCHAR(80) NOT NULL DEFAULT ''En elaboracion'' AFTER amount',
  'SELECT ''quotes.status ya existe'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'quotes' AND COLUMN_NAME = 'probability') = 0,
  'ALTER TABLE quotes ADD COLUMN probability TINYINT UNSIGNED NOT NULL DEFAULT 40 AFTER status',
  'SELECT ''quotes.probability ya existe'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'quotes' AND COLUMN_NAME = 'sent_at') = 0,
  'ALTER TABLE quotes ADD COLUMN sent_at DATE NULL AFTER probability',
  'SELECT ''quotes.sent_at ya existe'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'quotes' AND COLUMN_NAME = 'valid_until') = 0,
  'ALTER TABLE quotes ADD COLUMN valid_until DATE NULL AFTER sent_at',
  'SELECT ''quotes.valid_until ya existe'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'quotes' AND COLUMN_NAME = 'created_at') = 0,
  'ALTER TABLE quotes ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER valid_until',
  'SELECT ''quotes.created_at ya existe'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE quotes
SET status = 'En elaboracion'
WHERE status IS NULL
   OR TRIM(status) = '';

UPDATE quotes
SET probability = 40
WHERE probability IS NULL
   OR probability < 0
   OR probability > 100;

UPDATE quotes
SET amount = 0
WHERE amount IS NULL;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'quotes' AND INDEX_NAME = 'idx_quotes_status') = 0,
  'ALTER TABLE quotes ADD INDEX idx_quotes_status (status)',
  'SELECT ''idx_quotes_status ya existe'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;