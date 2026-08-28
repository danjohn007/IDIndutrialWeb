-- Estado operativo, comandos y auditoria Shelly para MySQL 5.7.
-- Selecciona primero la base de datos de ID Industrial en phpMyAdmin.
-- Requiere haber ejecutado migracion_shelly_mysql57.sql.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS estado_shelly (
  actuador_id VARCHAR(64) NOT NULL,
  online TINYINT(1) NOT NULL DEFAULT 0,
  salida_encendida TINYINT(1) NULL,
  potencia_w DECIMAL(12,3) NULL,
  voltaje_v DECIMAL(10,3) NULL,
  corriente_a DECIMAL(10,4) NULL,
  temperatura_c DECIMAL(7,2) NULL,
  errores_json TEXT NULL,
  fuente ENUM('CLOUD', 'WEBHOOK', 'LOCAL') NOT NULL DEFAULT 'CLOUD',
  ultimo_error VARCHAR(500) NULL,
  sincronizado_en DATETIME NULL,
  apagado_programado_en DATETIME NULL,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (actuador_id),
  INDEX idx_estado_shelly_online (online, sincronizado_en),
  INDEX idx_estado_shelly_apagado (apagado_programado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comandos_shelly (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actuador_id VARCHAR(64) NOT NULL,
  alerta_id BIGINT UNSIGNED NULL,
  accion ENUM('ENCENDER', 'APAGAR') NOT NULL,
  origen ENUM('AUTOMATICO', 'WEB', 'APP', 'CRON') NOT NULL,
  solicitado_por INT UNSIGNED NULL,
  motivo VARCHAR(255) NULL,
  estado ENUM(
    'PENDIENTE', 'PROCESANDO', 'APLICADO', 'REINTENTAR', 'FALLIDO'
  ) NOT NULL DEFAULT 'PENDIENTE',
  intentos TINYINT UNSIGNED NOT NULL DEFAULT 0,
  disponible_en DATETIME NOT NULL,
  respuesta_json TEXT NULL,
  ultimo_error VARCHAR(500) NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  procesado_en DATETIME NULL,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_comandos_shelly_cola (estado, disponible_en, id),
  INDEX idx_comandos_shelly_actuador (actuador_id, creado_en),
  INDEX idx_comandos_shelly_alerta (alerta_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS eventos_shelly (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actuador_id VARCHAR(64) NOT NULL,
  comando_id BIGINT UNSIGNED NULL,
  evento VARCHAR(80) NOT NULL,
  origen ENUM('CLOUD', 'WEBHOOK', 'SISTEMA', 'USUARIO') NOT NULL,
  salida_encendida TINYINT(1) NULL,
  detalle_json TEXT NULL,
  fecha_hora TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_eventos_shelly_actuador_fecha (actuador_id, fecha_hora, id),
  INDEX idx_eventos_shelly_comando (comando_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
