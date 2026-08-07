-- Habilita notificaciones push para solicitudes de cotizacion del CRM.
-- Compatible con la cola original y con la base unificada actual (MySQL 5.7+).

SET NAMES utf8mb4;

ALTER TABLE notificaciones_push
  MODIFY alerta_id BIGINT UNSIGNED NULL;

SET @idind_has_origin := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'notificaciones_push'
    AND COLUMN_NAME = 'origen_tipo'
);
SET @idind_sql := IF(
  @idind_has_origin = 0,
  'ALTER TABLE notificaciones_push ADD COLUMN origen_tipo ENUM(''ALERTA'', ''SHELLY'', ''COTIZACION'') NOT NULL DEFAULT ''ALERTA'' AFTER alerta_id',
  'ALTER TABLE notificaciones_push MODIFY origen_tipo ENUM(''ALERTA'', ''SHELLY'', ''COTIZACION'') NOT NULL DEFAULT ''ALERTA'''
);
PREPARE idind_stmt FROM @idind_sql;
EXECUTE idind_stmt;
DEALLOCATE PREPARE idind_stmt;

SET @idind_has_dedupe := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'notificaciones_push'
    AND COLUMN_NAME = 'dedupe_key'
);
SET @idind_sql := IF(
  @idind_has_dedupe = 0,
  'ALTER TABLE notificaciones_push ADD COLUMN dedupe_key CHAR(64) NULL AFTER origen_tipo',
  'SELECT 1'
);
PREPARE idind_stmt FROM @idind_sql;
EXECUTE idind_stmt;
DEALLOCATE PREPARE idind_stmt;

SET @idind_has_dedupe_index := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'notificaciones_push'
    AND INDEX_NAME = 'uq_notificacion_dedupe_token'
);
SET @idind_sql := IF(
  @idind_has_dedupe_index = 0,
  'ALTER TABLE notificaciones_push ADD UNIQUE KEY uq_notificacion_dedupe_token (dedupe_key, push_token_id)',
  'SELECT 1'
);
PREPARE idind_stmt FROM @idind_sql;
EXECUTE idind_stmt;
DEALLOCATE PREPARE idind_stmt;
