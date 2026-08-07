-- Soporte de registro para actuadores Shelly en MySQL 5.7.
-- En phpMyAdmin selecciona primero la base de datos de ID Industrial.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS actuadores_shelly (
  id VARCHAR(64) NOT NULL,
  cliente_id INT UNSIGNED NOT NULL,
  ubicacion VARCHAR(160) NOT NULL,
  dispositivo_vinculado_id VARCHAR(64) NULL,
  shelly_device_id VARCHAR(100) NOT NULL,
  modelo VARCHAR(80) NOT NULL,
  generacion ENUM('GEN1', 'GEN2_PLUS') NOT NULL DEFAULT 'GEN2_PLUS',
  ip_local VARCHAR(255) NULL,
  canal TINYINT UNSIGNED NOT NULL DEFAULT 0,
  funcion ENUM('SIRENA', 'BALIZA', 'VENTILACION', 'CONTACTOR', 'OTRO')
    NOT NULL DEFAULT 'SIRENA',
  modo_control ENUM('LOCAL', 'CLOUD', 'HIBRIDO') NOT NULL DEFAULT 'HIBRIDO',
  estado ENUM('Activo', 'Mantenimiento', 'Inactivo') NOT NULL DEFAULT 'Activo',
  estado_salida TINYINT(1) NULL,
  ultima_conexion TIMESTAMP NULL DEFAULT NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_shelly_cliente_dispositivo_canal (
    cliente_id, shelly_device_id, canal
  ),
  INDEX idx_shelly_cliente_estado (cliente_id, estado),
  INDEX idx_shelly_vinculado (dispositivo_vinculado_id),
  INDEX idx_shelly_ultima_conexion (ultima_conexion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
