-- Migracion MySQL/MariaDB compatible con phpMyAdmin/cPanel.
-- Clientes con multiples proyectos, Bitacora ID por cliente y recordatorios de mantenimiento.
-- Esta version evita ALTER TABLE ... IF NOT EXISTS porque algunas versiones de MySQL/MariaDB no lo soportan.

SET @db := DATABASE();

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'lifecycle_stage') = 0,
  'ALTER TABLE clients ADD COLUMN lifecycle_stage VARCHAR(40) NOT NULL DEFAULT ''Prospecto'' AFTER segment',
  'SELECT ''clients.lifecycle_stage ya existe'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'converted_at') = 0,
  'ALTER TABLE clients ADD COLUMN converted_at DATETIME NULL AFTER created_at',
  'SELECT ''clients.converted_at ya existe'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE clients
SET lifecycle_stage = 'Cliente'
WHERE is_public = 1
  AND lifecycle_stage = 'Prospecto';

CREATE TABLE IF NOT EXISTS projects (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_id INT UNSIGNED NOT NULL,
  opportunity_id INT UNSIGNED NULL,
  project_code VARCHAR(60) NOT NULL,
  name VARCHAR(190) NOT NULL,
  service VARCHAR(160) NOT NULL,
  status VARCHAR(80) NOT NULL DEFAULT 'Activo',
  start_date DATE NULL,
  delivered_at DATE NULL,
  maintenance_enabled TINYINT(1) NOT NULL DEFAULT 1,
  maintenance_frequency VARCHAR(40) NOT NULL DEFAULT 'Mensual',
  next_maintenance_date DATE NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_projects_code (project_code),
  KEY idx_projects_client (client_id),
  KEY idx_projects_opportunity (opportunity_id),
  KEY idx_projects_maintenance (maintenance_enabled, next_maintenance_date),
  CONSTRAINT fk_projects_client
    FOREIGN KEY (client_id) REFERENCES clients(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_projects_opportunity
    FOREIGN KEY (opportunity_id) REFERENCES opportunities(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO projects (
  client_id,
  opportunity_id,
  project_code,
  name,
  service,
  status,
  delivered_at,
  maintenance_enabled,
  maintenance_frequency,
  next_maintenance_date,
  notes
)
SELECT
  o.client_id,
  o.id,
  CONCAT('PR-', YEAR(o.created_at), '-', LPAD(o.id, 4, '0')),
  CONCAT(o.company_name, ' - ', o.service),
  o.service,
  CASE
    WHEN o.status = 'Proyecto entregado' THEN 'Entregado'
    WHEN o.status = 'Proyecto ganado' THEN 'Activo'
    WHEN o.status = 'Proyecto perdido' THEN 'Cancelado'
    ELSE 'En proceso'
  END,
  CASE WHEN o.status = 'Proyecto entregado' THEN DATE(o.updated_at) ELSE NULL END,
  CASE WHEN o.status IN ('Proyecto ganado', 'Proyecto entregado') THEN 1 ELSE 0 END,
  'Mensual',
  CASE WHEN o.status IN ('Proyecto ganado', 'Proyecto entregado') THEN DATE_ADD(CURDATE(), INTERVAL 30 DAY) ELSE NULL END,
  o.notes
FROM opportunities o
WHERE o.client_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1
    FROM projects p
    WHERE p.opportunity_id = o.id
  );

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'quotes' AND COLUMN_NAME = 'project_id') = 0,
  'ALTER TABLE quotes ADD COLUMN project_id INT UNSIGNED NULL AFTER opportunity_id',
  'SELECT ''quotes.project_id ya existe'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'quotes' AND INDEX_NAME = 'idx_quotes_project') = 0,
  'ALTER TABLE quotes ADD INDEX idx_quotes_project (project_id)',
  'SELECT ''idx_quotes_project ya existe'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE quotes q
JOIN projects p ON p.opportunity_id = q.opportunity_id
SET q.project_id = p.id
WHERE q.project_id IS NULL;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = @db AND CONSTRAINT_NAME = 'fk_quotes_project') = 0,
  'ALTER TABLE quotes ADD CONSTRAINT fk_quotes_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL',
  'SELECT ''fk_quotes_project ya existe'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'client_portal_users' AND INDEX_NAME = 'idx_client_portal_opportunity') = 0,
  'ALTER TABLE client_portal_users ADD INDEX idx_client_portal_opportunity (opportunity_id)',
  'SELECT ''idx_client_portal_opportunity ya existe'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'client_portal_users' AND INDEX_NAME = 'uq_client_portal_opportunity') > 0,
  'ALTER TABLE client_portal_users DROP INDEX uq_client_portal_opportunity',
  'SELECT ''uq_client_portal_opportunity no existe'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'client_portal_users' AND INDEX_NAME = 'uq_client_portal_client') = 0,
  'ALTER TABLE client_portal_users ADD UNIQUE KEY uq_client_portal_client (client_id)',
  'SELECT ''uq_client_portal_client ya existe'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'maintenance_logs' AND COLUMN_NAME = 'client_id') = 0,
  'ALTER TABLE maintenance_logs ADD COLUMN client_id INT UNSIGNED NULL AFTER id',
  'SELECT ''maintenance_logs.client_id ya existe'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'maintenance_logs' AND COLUMN_NAME = 'project_id') = 0,
  'ALTER TABLE maintenance_logs ADD COLUMN project_id INT UNSIGNED NULL AFTER client_id',
  'SELECT ''maintenance_logs.project_id ya existe'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'maintenance_logs' AND INDEX_NAME = 'idx_maintenance_logs_client') = 0,
  'ALTER TABLE maintenance_logs ADD INDEX idx_maintenance_logs_client (client_id)',
  'SELECT ''idx_maintenance_logs_client ya existe'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'maintenance_logs' AND INDEX_NAME = 'idx_maintenance_logs_project') = 0,
  'ALTER TABLE maintenance_logs ADD INDEX idx_maintenance_logs_project (project_id)',
  'SELECT ''idx_maintenance_logs_project ya existe'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE maintenance_logs ml
JOIN opportunities o ON o.id = ml.opportunity_id
LEFT JOIN projects p ON p.opportunity_id = o.id
SET
  ml.client_id = o.client_id,
  ml.project_id = p.id
WHERE ml.client_id IS NULL
   OR ml.project_id IS NULL;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = @db AND CONSTRAINT_NAME = 'fk_maintenance_logs_client') = 0,
  'ALTER TABLE maintenance_logs ADD CONSTRAINT fk_maintenance_logs_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE',
  'SELECT ''fk_maintenance_logs_client ya existe'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = @db AND CONSTRAINT_NAME = 'fk_maintenance_logs_project') = 0,
  'ALTER TABLE maintenance_logs ADD CONSTRAINT fk_maintenance_logs_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE',
  'SELECT ''fk_maintenance_logs_project ya existe'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS maintenance_reminders (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_id INT UNSIGNED NOT NULL,
  project_id INT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  frequency VARCHAR(40) NOT NULL DEFAULT 'Mensual',
  next_due_date DATE NOT NULL,
  last_sent_at DATETIME NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'Pendiente',
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_reminders_client (client_id),
  KEY idx_reminders_project (project_id),
  KEY idx_reminders_due (status, next_due_date),
  CONSTRAINT fk_reminders_client
    FOREIGN KEY (client_id) REFERENCES clients(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_reminders_project
    FOREIGN KEY (project_id) REFERENCES projects(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO maintenance_reminders (
  client_id,
  project_id,
  title,
  frequency,
  next_due_date,
  status,
  notes
)
SELECT
  p.client_id,
  p.id,
  CONCAT('Mantenimiento preventivo - ', p.service),
  p.maintenance_frequency,
  COALESCE(p.next_maintenance_date, DATE_ADD(CURDATE(), INTERVAL 30 DAY)),
  'Pendiente',
  'Recordatorio automatico generado al activar Bitacora ID.'
FROM projects p
WHERE p.maintenance_enabled = 1
  AND NOT EXISTS (
    SELECT 1
    FROM maintenance_reminders mr
    WHERE mr.project_id = p.id
      AND mr.status = 'Pendiente'
  );