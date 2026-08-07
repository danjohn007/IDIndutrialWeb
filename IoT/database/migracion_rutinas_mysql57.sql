-- ID Industrial - Fases 4 a 7: automatizacion y rutinas
-- Compatible con MySQL 5.7. Ejecutar UNA sola vez.

CREATE TABLE IF NOT EXISTS integraciones_domoticas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cliente_id INT UNSIGNED NOT NULL,
  proveedor ENUM('ALEXA') NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  estado ENUM('PENDIENTE', 'CONFIGURADA', 'ERROR', 'INACTIVA') NOT NULL DEFAULT 'PENDIENTE',
  identificador_externo VARCHAR(190) NULL,
  detalle_json TEXT NULL,
  ultima_sincronizacion DATETIME NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_integracion_cliente_proveedor (cliente_id, proveedor),
  INDEX idx_integracion_cliente_estado (cliente_id, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rutinas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cliente_id INT UNSIGNED NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  descripcion VARCHAR(255) NULL,
  tipo_disparador ENUM('MANUAL', 'HORARIO') NOT NULL DEFAULT 'MANUAL',
  hora_local TIME NULL,
  dias_semana VARCHAR(20) NULL,
  zona_horaria VARCHAR(64) NOT NULL DEFAULT 'America/Mexico_City',
  activa TINYINT(1) NOT NULL DEFAULT 1,
  creado_por INT UNSIGNED NULL,
  ultima_ejecucion DATETIME NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_rutina_cliente_activa (cliente_id, activa, tipo_disparador),
  INDEX idx_rutina_programacion (tipo_disparador, activa, hora_local)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rutina_acciones (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  rutina_id BIGINT UNSIGNED NOT NULL,
  orden TINYINT UNSIGNED NOT NULL,
  actuador_id VARCHAR(64) NOT NULL,
  accion ENUM('ENCENDER', 'APAGAR') NOT NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rutina_accion_orden (rutina_id, orden),
  INDEX idx_rutina_accion_actuador (actuador_id, rutina_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rutina_ejecuciones (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  rutina_id BIGINT UNSIGNED NOT NULL,
  cliente_id INT UNSIGNED NOT NULL,
  origen ENUM('MANUAL', 'CRON') NOT NULL,
  solicitado_por INT UNSIGNED NULL,
  clave_programacion VARCHAR(40) NULL,
  estado ENUM('EJECUTANDO', 'COMPLETADA', 'PARCIAL', 'FALLIDA', 'OMITIDA') NOT NULL DEFAULT 'EJECUTANDO',
  acciones_total TINYINT UNSIGNED NOT NULL DEFAULT 0,
  acciones_exitosas TINYINT UNSIGNED NOT NULL DEFAULT 0,
  detalle_json TEXT NULL,
  iniciada_en DATETIME NOT NULL,
  finalizada_en DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rutina_programacion (rutina_id, clave_programacion),
  INDEX idx_rutina_ejecucion_cliente_fecha (cliente_id, iniciada_en, id),
  INDEX idx_rutina_ejecucion_rutina_fecha (rutina_id, iniciada_en, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
