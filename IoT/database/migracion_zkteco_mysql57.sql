-- Integracion ZKTeco para MySQL 5.7.
-- No almacena huellas, rostros, tarjetas completas ni contrasenas del terminal.

CREATE TABLE IF NOT EXISTS equipos_zkteco (
  id VARCHAR(64) NOT NULL,
  cliente_id INT UNSIGNED NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  ubicacion VARCHAR(160) NOT NULL,
  categoria ENUM('ASISTENCIA', 'CONTROL_ACCESO', 'HIBRIDO', 'OTRO') NOT NULL DEFAULT 'ASISTENCIA',
  modelo VARCHAR(100) NULL,
  numero_serie VARCHAR(100) NULL,
  ip_local VARCHAR(255) NULL,
  puerto SMALLINT UNSIGNED NOT NULL DEFAULT 4370,
  protocolo ENUM('PULL_4370', 'PUSH_ADMS', 'WDMS_API') NOT NULL DEFAULT 'PULL_4370',
  numero_maquina SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  estado ENUM('Activo', 'Mantenimiento', 'Inactivo') NOT NULL DEFAULT 'Activo',
  ultima_conexion TIMESTAMP NULL DEFAULT NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_zkteco_cliente_estado (cliente_id, estado),
  INDEX idx_zkteco_ultima_conexion (ultima_conexion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS estado_zkteco (
  equipo_id VARCHAR(64) NOT NULL,
  online TINYINT(1) NOT NULL DEFAULT 0,
  nombre_detectado VARCHAR(100) NULL,
  modelo_detectado VARCHAR(100) NULL,
  serial_detectado VARCHAR(100) NULL,
  firmware VARCHAR(100) NULL,
  plataforma VARCHAR(100) NULL,
  usuarios_total INT UNSIGNED NULL,
  registros_total INT UNSIGNED NULL,
  capacidad_usuarios INT UNSIGNED NULL,
  capacidad_registros INT UNSIGNED NULL,
  fuente VARCHAR(30) NOT NULL DEFAULT 'CONECTOR_LOCAL',
  ultimo_error VARCHAR(500) NULL,
  sincronizado_en TIMESTAMP NULL DEFAULT NULL,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (equipo_id),
  INDEX idx_estado_zkteco_online (online, sincronizado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS eventos_zkteco (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  equipo_id VARCHAR(64) NOT NULL,
  pin_usuario VARCHAR(64) NULL,
  tipo_evento VARCHAR(50) NOT NULL DEFAULT 'MARCAJE',
  modo_verificacion VARCHAR(50) NULL,
  estado_entrada VARCHAR(50) NULL,
  dedupe_key CHAR(64) NOT NULL,
  detalle_json TEXT NULL,
  ocurrido_en DATETIME NULL,
  recibido_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_eventos_zkteco_dedupe (dedupe_key),
  INDEX idx_eventos_zkteco_equipo_fecha (equipo_id, ocurrido_en),
  INDEX idx_eventos_zkteco_usuario_fecha (pin_usuario, ocurrido_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
