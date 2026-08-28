-- ID Industrial - Auditoria y diagnostico de webhooks Shelly.
-- Compatible con MySQL 5.7 y seguro para volver a ejecutar.
-- Requiere migracion_shelly_operacion_mysql57.sql.

SET NAMES utf8mb4;

SELECT DATABASE() AS base_seleccionada;

CREATE TABLE IF NOT EXISTS entregas_webhook_shelly (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actuador_id VARCHAR(64) NOT NULL,
  evento ENUM('ENCENDIDO', 'APAGADO') NOT NULL,
  salida_encendida TINYINT(1) NOT NULL,
  metodo ENUM('GET', 'POST') NOT NULL,
  estado ENUM('RECIBIDA', 'PROCESADA', 'ERROR') NOT NULL DEFAULT 'RECIBIDA',
  cambio_estado TINYINT(1) NOT NULL DEFAULT 0,
  cambio_externo TINYINT(1) NOT NULL DEFAULT 0,
  alexa_enviados SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  alexa_errores_json TEXT NULL,
  detalle_json TEXT NULL,
  ultimo_error VARCHAR(500) NULL,
  recibido_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  procesado_en DATETIME NULL,
  PRIMARY KEY (id),
  INDEX idx_webhook_actuador_fecha (actuador_id, recibido_en, id),
  INDEX idx_webhook_estado_fecha (estado, recibido_en),
  INDEX idx_webhook_evento_fecha (evento, recibido_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SHOW TABLES LIKE 'entregas_webhook_shelly';
