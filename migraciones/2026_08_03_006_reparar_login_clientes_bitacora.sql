-- Migracion MySQL/MariaDB: reparar accesos Bitacora ID para clientes con varios proyectos.
-- Quita la llave unica incorrecta por client_id, conserva usuario unico y proyecto unico,
-- sincroniza client_id desde opportunities y agrega columnas operativas si faltan.

SET @db := DATABASE();


SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'client_portal_users' AND INDEX_NAME = 'idx_client_portal_client') = 0,
  'ALTER TABLE client_portal_users ADD INDEX idx_client_portal_client (client_id)',
  'SELECT ''idx_client_portal_client ya existe'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'client_portal_users' AND INDEX_NAME = 'uq_client_portal_client') > 0,
  'ALTER TABLE client_portal_users DROP INDEX uq_client_portal_client',
  'SELECT ''uq_client_portal_client no existe'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'client_portal_users' AND INDEX_NAME = 'uq_client_portal_opportunity') = 0,
  'ALTER TABLE client_portal_users ADD UNIQUE KEY uq_client_portal_opportunity (opportunity_id)',
  'SELECT ''uq_client_portal_opportunity ya existe'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE client_portal_users cpu
JOIN opportunities o ON o.id = cpu.opportunity_id
SET cpu.client_id = o.client_id
WHERE cpu.client_id IS NULL
  AND o.client_id IS NOT NULL;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'client_requests' AND COLUMN_NAME = 'due_date') = 0,
  'ALTER TABLE client_requests ADD COLUMN due_date DATE NULL AFTER priority',
  'SELECT ''client_requests.due_date ya existe'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'client_requests' AND COLUMN_NAME = 'scheduled_date') = 0,
  'ALTER TABLE client_requests ADD COLUMN scheduled_date DATE NULL AFTER due_date',
  'SELECT ''client_requests.scheduled_date ya existe'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'client_requests' AND COLUMN_NAME = 'assigned_to') = 0,
  'ALTER TABLE client_requests ADD COLUMN assigned_to VARCHAR(160) NULL AFTER scheduled_date',
  'SELECT ''client_requests.assigned_to ya existe'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'client_requests' AND COLUMN_NAME = 'internal_notes') = 0,
  'ALTER TABLE client_requests ADD COLUMN internal_notes TEXT NULL AFTER admin_response',
  'SELECT ''client_requests.internal_notes ya existe'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'client_requests' AND INDEX_NAME = 'idx_client_requests_due_status') = 0,
  'CREATE INDEX idx_client_requests_due_status ON client_requests (status, due_date)',
  'SELECT ''idx_client_requests_due_status ya existe'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;