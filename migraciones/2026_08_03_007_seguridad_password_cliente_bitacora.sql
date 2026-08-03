-- Migracion MySQL/MariaDB: seguridad de password para usuarios Bitacora ID.
-- Marca accesos nuevos/regenerados para cambio obligatorio y registra cuando el cliente ya cambio su password.

SET @db := DATABASE();

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'client_portal_users' AND COLUMN_NAME = 'password_change_required') = 0,
  'ALTER TABLE client_portal_users ADD COLUMN password_change_required TINYINT(1) NOT NULL DEFAULT 1 AFTER is_active',
  'SELECT ''client_portal_users.password_change_required ya existe'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'client_portal_users' AND COLUMN_NAME = 'password_changed_at') = 0,
  'ALTER TABLE client_portal_users ADD COLUMN password_changed_at DATETIME NULL AFTER password_change_required',
  'SELECT ''client_portal_users.password_changed_at ya existe'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE client_portal_users
SET password_change_required = 1
WHERE password_changed_at IS NULL;