-- Enlace CRM -> IoT para SSO desde Bitacora ID.
-- Ejecuta este archivo despues de schema.sql o sobre una base IoT existente.

SET NAMES utf8mb4;
SET @db := DATABASE();

SET @sql := IF(
  (SELECT COUNT(*)
   FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @db
     AND TABLE_NAME = 'clientes'
     AND COLUMN_NAME = 'crm_client_id') = 0,
  'ALTER TABLE `clientes` ADD COLUMN `crm_client_id` INT UNSIGNED NULL AFTER `id`',
  'SELECT ''clientes.crm_client_id ya existe'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*)
   FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = @db
     AND TABLE_NAME = 'clientes'
     AND INDEX_NAME = 'uq_clientes_crm_client_id') = 0,
  'ALTER TABLE `clientes` ADD UNIQUE KEY `uq_clientes_crm_client_id` (`crm_client_id`)',
  'SELECT ''uq_clientes_crm_client_id ya existe'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*)
   FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @db
     AND TABLE_NAME = 'usuarios'
     AND COLUMN_NAME = 'crm_portal_user_id') = 0,
  'ALTER TABLE `usuarios` ADD COLUMN `crm_portal_user_id` INT UNSIGNED NULL AFTER `cliente_id`',
  'SELECT ''usuarios.crm_portal_user_id ya existe'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*)
   FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = @db
     AND TABLE_NAME = 'usuarios'
     AND INDEX_NAME = 'uq_usuarios_crm_portal_user') = 0,
  'ALTER TABLE `usuarios` ADD UNIQUE KEY `uq_usuarios_crm_portal_user` (`crm_portal_user_id`)',
  'SELECT ''uq_usuarios_crm_portal_user ya existe'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
