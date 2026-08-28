-- Integracion Hikvision para ID Industrial.
-- Compatible con MySQL 5.7 y seguro para volver a ejecutar.
-- Selecciona primero la base de ID Industrial en phpMyAdmin.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS equipos_hikvision (
  id VARCHAR(64) NOT NULL,
  cliente_id INT UNSIGNED NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  ubicacion VARCHAR(160) NOT NULL,
  categoria ENUM('CAMARA', 'NVR_DVR', 'CONTROL_ACCESO', 'INTERCOM', 'OTRO') NOT NULL DEFAULT 'OTRO',
  modelo VARCHAR(100) NULL,
  numero_serie VARCHAR(100) NULL,
  ip_local VARCHAR(255) NOT NULL,
  puerto SMALLINT UNSIGNED NOT NULL DEFAULT 80,
  protocolo ENUM('HTTP', 'HTTPS') NOT NULL DEFAULT 'HTTP',
  estado ENUM('Activo', 'Mantenimiento', 'Inactivo') NOT NULL DEFAULT 'Activo',
  ultima_conexion TIMESTAMP NULL DEFAULT NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_hikvision_cliente_estado (cliente_id, estado),
  INDEX idx_hikvision_ultima_conexion (ultima_conexion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS estado_hikvision (
  equipo_id VARCHAR(64) NOT NULL,
  online TINYINT(1) NOT NULL DEFAULT 0,
  nombre_detectado VARCHAR(100) NULL,
  modelo_detectado VARCHAR(100) NULL,
  serial_detectado VARCHAR(100) NULL,
  firmware VARCHAR(100) NULL,
  mac VARCHAR(32) NULL,
  uptime_s BIGINT UNSIGNED NULL,
  fuente VARCHAR(30) NOT NULL DEFAULT 'CONECTOR_LOCAL',
  ultimo_error VARCHAR(500) NULL,
  sincronizado_en TIMESTAMP NULL DEFAULT NULL,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (equipo_id),
  INDEX idx_estado_hikvision_online (online, sincronizado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS eventos_hikvision (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  equipo_id VARCHAR(64) NOT NULL,
  tipo_evento VARCHAR(80) NOT NULL,
  severidad ENUM('INFO', 'PRECAUCION', 'CRITICO') NOT NULL DEFAULT 'INFO',
  codigo VARCHAR(100) NULL,
  descripcion VARCHAR(500) NULL,
  dedupe_key CHAR(64) NULL,
  detalle_json TEXT NULL,
  ocurrido_en DATETIME NULL,
  recibido_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_eventos_hikvision_dedupe (dedupe_key),
  INDEX idx_eventos_hikvision_equipo_fecha (equipo_id, recibido_en),
  INDEX idx_eventos_hikvision_severidad (severidad, recibido_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SHOW TABLES LIKE 'equipos_hikvision';
SHOW TABLES LIKE 'estado_hikvision';
SHOW TABLES LIKE 'eventos_hikvision';
