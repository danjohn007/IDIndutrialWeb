-- Control remoto y estado fisico de la alarma.
-- Compatible con MySQL 5.7.
-- Selecciona la base correcta en phpMyAdmin y ejecuta este archivo una sola vez.
-- No usa claves foraneas para evitar errores #1215 en instalaciones existentes.

SET NAMES utf8mb4;

ALTER TABLE estado_sensores
  ADD COLUMN peligro_activo TINYINT(1) NOT NULL DEFAULT 0 AFTER estado_general,
  ADD COLUMN alarma_enclavada TINYINT(1) NOT NULL DEFAULT 0 AFTER peligro_activo,
  ADD COLUMN alarma_silenciada TINYINT(1) NOT NULL DEFAULT 0 AFTER alarma_enclavada,
  ADD COLUMN revision_fisica_pendiente TINYINT(1) NOT NULL DEFAULT 0 AFTER alarma_silenciada,
  ADD COLUMN buzzer_encendido TINYINT(1) NOT NULL DEFAULT 0 AFTER revision_fisica_pendiente,
  ADD COLUMN modo_operacion ENUM(
    'NORMAL', 'ALERTA', 'ALARMA_SONORA', 'ALARMA_SILENCIADA', 'REVISION_PENDIENTE'
  ) NOT NULL DEFAULT 'NORMAL' AFTER buzzer_encendido,
  ADD COLUMN silenciada_por ENUM(
    'NINGUNO', 'APP_MOVIL', 'BOTON_FISICO'
  ) NOT NULL DEFAULT 'NINGUNO' AFTER modo_operacion,
  ADD COLUMN contador_silencios_en_linea INT UNSIGNED NOT NULL DEFAULT 0 AFTER contador_alarmas,
  ADD COLUMN contador_silencios_fisicos INT UNSIGNED NOT NULL DEFAULT 0 AFTER contador_silencios_en_linea,
  ADD COLUMN contador_resets_fisicos INT UNSIGNED NOT NULL DEFAULT 0 AFTER contador_silencios_fisicos;

CREATE TABLE IF NOT EXISTS comandos_dispositivo (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dispositivo_id VARCHAR(64) NOT NULL,
  alerta_id BIGINT UNSIGNED NULL,
  tipo ENUM('SILENCIAR_ALARMA') NOT NULL,
  estado ENUM('PENDIENTE', 'ENTREGADO', 'APLICADO', 'EXPIRADO') NOT NULL DEFAULT 'PENDIENTE',
  solicitado_por INT UNSIGNED NOT NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expira_en DATETIME NOT NULL,
  entregado_en DATETIME NULL,
  aplicado_en DATETIME NULL,
  intentos_entrega SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  INDEX idx_comando_dispositivo_estado (dispositivo_id, estado, expira_en, id),
  INDEX idx_comando_alerta (alerta_id),
  INDEX idx_comando_usuario (solicitado_por, creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SHOW TABLES LIKE 'comandos_dispositivo';
