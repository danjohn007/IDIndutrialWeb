-- ID Industrial - Cola push generica y preferencias Shelly.
-- Compatible con MySQL 5.7 y seguro para volver a ejecutar.
-- Selecciona primero la base de datos de ID Industrial en phpMyAdmin.

SET NAMES utf8mb4;

SELECT DATABASE() AS base_seleccionada;

-- Esta modificacion es segura aunque alerta_id ya acepte NULL.
ALTER TABLE notificaciones_push
  MODIFY COLUMN alerta_id BIGINT UNSIGNED NULL;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notificaciones_push'
     AND COLUMN_NAME = 'origen_tipo') = 0,
  'ALTER TABLE notificaciones_push ADD COLUMN origen_tipo ENUM(''ALERTA'', ''SHELLY'', ''COTIZACION'') NOT NULL DEFAULT ''ALERTA'' AFTER alerta_id',
  'SELECT ''origen_tipo ya existe'' AS resultado'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE notificaciones_push
  MODIFY COLUMN origen_tipo ENUM('ALERTA', 'SHELLY', 'COTIZACION') NOT NULL DEFAULT 'ALERTA';

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notificaciones_push'
     AND COLUMN_NAME = 'evento_shelly_id') = 0,
  'ALTER TABLE notificaciones_push ADD COLUMN evento_shelly_id BIGINT UNSIGNED NULL AFTER origen_tipo',
  'SELECT ''evento_shelly_id ya existe'' AS resultado'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notificaciones_push'
     AND COLUMN_NAME = 'dedupe_key') = 0,
  'ALTER TABLE notificaciones_push ADD COLUMN dedupe_key CHAR(64) NULL AFTER evento_shelly_id',
  'SELECT ''dedupe_key ya existe'' AS resultado'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notificaciones_push'
     AND INDEX_NAME = 'uq_notificacion_dedupe_token') = 0,
  'ALTER TABLE notificaciones_push ADD UNIQUE KEY uq_notificacion_dedupe_token (dedupe_key, push_token_id)',
  'SELECT ''uq_notificacion_dedupe_token ya existe'' AS resultado'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notificaciones_push'
     AND INDEX_NAME = 'idx_notificaciones_origen') = 0,
  'ALTER TABLE notificaciones_push ADD INDEX idx_notificaciones_origen (origen_tipo, evento_shelly_id)',
  'SELECT ''idx_notificaciones_origen ya existe'' AS resultado'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'actuadores_shelly'
     AND COLUMN_NAME = 'notificar_cambios_externos') = 0,
  'ALTER TABLE actuadores_shelly ADD COLUMN notificar_cambios_externos TINYINT(1) NOT NULL DEFAULT 1 AFTER requiere_confirmacion',
  'ALTER TABLE actuadores_shelly MODIFY COLUMN notificar_cambios_externos TINYINT(1) NOT NULL DEFAULT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE actuadores_shelly
SET notificar_cambios_externos = 1
WHERE estado = 'Activo';

SHOW COLUMNS FROM notificaciones_push LIKE 'origen_tipo';
SHOW COLUMNS FROM notificaciones_push LIKE 'evento_shelly_id';
SHOW COLUMNS FROM notificaciones_push LIKE 'dedupe_key';
SHOW INDEX FROM notificaciones_push WHERE Key_name IN (
  'uq_notificacion_dedupe_token', 'idx_notificaciones_origen'
);
SHOW COLUMNS FROM actuadores_shelly LIKE 'notificar_cambios_externos';
