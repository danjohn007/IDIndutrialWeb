-- ID Industrial - Alertas externas Shelly y temporizador sincronizado con Alexa.
-- Compatible con MySQL 5.7 y seguro para volver a ejecutar.
-- Ejecuta antes migracion_eventos_push_mysql57.sql.

SET NAMES utf8mb4;

SELECT DATABASE() AS base_seleccionada;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'estado_shelly'
     AND COLUMN_NAME = 'apagado_programado_en') = 0,
  'ALTER TABLE estado_shelly ADD COLUMN apagado_programado_en DATETIME NULL AFTER sincronizado_en',
  'SELECT ''apagado_programado_en ya existe'' AS resultado'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'estado_shelly'
     AND INDEX_NAME = 'idx_estado_shelly_apagado') = 0,
  'ALTER TABLE estado_shelly ADD INDEX idx_estado_shelly_apagado (apagado_programado_en)',
  'SELECT ''idx_estado_shelly_apagado ya existe'' AS resultado'
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

SHOW COLUMNS FROM estado_shelly LIKE 'apagado_programado_en';
SHOW INDEX FROM estado_shelly WHERE Key_name = 'idx_estado_shelly_apagado';
SHOW COLUMNS FROM actuadores_shelly LIKE 'notificar_cambios_externos';
