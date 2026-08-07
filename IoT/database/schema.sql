-- Esquema importable en phpMyAdmin para MariaDB/MySQL.
-- Selecciona primero la base de datos creada en cPanel.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS clientes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre_empresa VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dispositivos (
  id VARCHAR(64) PRIMARY KEY,
  cliente_id INT UNSIGNED NOT NULL,
  ubicacion VARCHAR(160) NOT NULL,
  estado ENUM('Activo', 'Mantenimiento', 'Inactivo') NOT NULL DEFAULT 'Activo',
  ultima_conexion TIMESTAMP NULL DEFAULT NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_dispositivos_clientes
    FOREIGN KEY (cliente_id) REFERENCES clientes(id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  INDEX idx_dispositivos_cliente_estado (cliente_id, estado),
  INDEX idx_dispositivos_ultima_conexion (ultima_conexion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Los Shelly son actuadores independientes. Se mantienen fuera de dispositivos
-- para que no se contabilicen como ESP32 ni alteren las graficas de sensores.
CREATE TABLE IF NOT EXISTS actuadores_shelly (
  id VARCHAR(64) NOT NULL,
  cliente_id INT UNSIGNED NOT NULL,
  nombre VARCHAR(120) NULL,
  ubicacion VARCHAR(160) NOT NULL,
  dispositivo_vinculado_id VARCHAR(64) NULL,
  shelly_device_id VARCHAR(100) NOT NULL,
  modelo VARCHAR(80) NOT NULL,
  generacion ENUM('GEN1', 'GEN2_PLUS') NOT NULL DEFAULT 'GEN2_PLUS',
  ip_local VARCHAR(255) NULL,
  canal TINYINT UNSIGNED NOT NULL DEFAULT 0,
  funcion ENUM('SIRENA', 'BALIZA', 'VENTILACION', 'CONTACTOR', 'OTRO')
    NOT NULL DEFAULT 'SIRENA',
  categoria ENUM('SEGURIDAD', 'AUTOMATIZACION', 'MONITOREO')
    NOT NULL DEFAULT 'SEGURIDAD',
  tipo_carga ENUM('RESISTIVA', 'INDUCTIVA', 'ELECTRONICA', 'DESCONOCIDA')
    NOT NULL DEFAULT 'DESCONOCIDA',
  corriente_max_a DECIMAL(6,2) NULL,
  potencia_max_w DECIMAL(10,2) NULL,
  tiempo_max_encendido_s INT UNSIGNED NULL,
  apagado_automatico TINYINT(1) NOT NULL DEFAULT 0,
  permite_rutinas TINYINT(1) NOT NULL DEFAULT 0,
  requiere_confirmacion TINYINT(1) NOT NULL DEFAULT 1,
  descripcion VARCHAR(255) NULL,
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
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (actuador_id),
  INDEX idx_estado_shelly_online (online, sincronizado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comandos_shelly (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actuador_id VARCHAR(64) NOT NULL,
  alerta_id BIGINT UNSIGNED NULL,
  accion ENUM('ENCENDER', 'APAGAR') NOT NULL,
  origen ENUM('AUTOMATICO', 'WEB', 'APP', 'CRON', 'ALEXA') NOT NULL,
  solicitado_por INT UNSIGNED NULL,
  motivo VARCHAR(255) NULL,
  estado ENUM('PENDIENTE', 'PROCESANDO', 'APLICADO', 'REINTENTAR', 'FALLIDO')
    NOT NULL DEFAULT 'PENDIENTE',
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
  origen ENUM('CLOUD', 'WEBHOOK', 'SISTEMA', 'USUARIO', 'ALEXA') NOT NULL,
  salida_encendida TINYINT(1) NULL,
  detalle_json TEXT NULL,
  fecha_hora TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_eventos_shelly_actuador_fecha (actuador_id, fecha_hora, id),
  INDEX idx_eventos_shelly_comando (comando_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS integraciones_domoticas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cliente_id INT UNSIGNED NOT NULL,
  proveedor ENUM('ALEXA') NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  estado ENUM('PENDIENTE', 'CONFIGURADA', 'ERROR', 'INACTIVA')
    NOT NULL DEFAULT 'PENDIENTE',
  identificador_externo VARCHAR(190) NULL,
  detalle_json TEXT NULL,
  ultima_sincronizacion DATETIME NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_integracion_cliente_proveedor (cliente_id, proveedor),
  INDEX idx_integracion_cliente_estado (cliente_id, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alexa_oauth_codes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id INT UNSIGNED NOT NULL,
  client_id VARCHAR(190) NOT NULL,
  code_hash CHAR(64) NOT NULL,
  redirect_uri VARCHAR(500) NOT NULL,
  scope VARCHAR(255) NOT NULL,
  code_challenge VARCHAR(128) NULL,
  code_challenge_method ENUM('S256') NULL,
  expira_en DATETIME NOT NULL,
  usado_en DATETIME NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_alexa_oauth_code_hash (code_hash),
  INDEX idx_alexa_oauth_code_validacion (client_id, expira_en, usado_en),
  INDEX idx_alexa_oauth_code_usuario (usuario_id, creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alexa_oauth_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id INT UNSIGNED NOT NULL,
  client_id VARCHAR(190) NOT NULL,
  access_token_hash CHAR(64) NOT NULL,
  refresh_token_hash CHAR(64) NOT NULL,
  scope VARCHAR(255) NOT NULL,
  access_expira_en DATETIME NOT NULL,
  refresh_expira_en DATETIME NOT NULL,
  ultimo_uso DATETIME NULL,
  revocado_en DATETIME NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_alexa_access_token_hash (access_token_hash),
  UNIQUE KEY uq_alexa_refresh_token_hash (refresh_token_hash),
  INDEX idx_alexa_oauth_usuario (usuario_id, revocado_en, refresh_expira_en),
  INDEX idx_alexa_oauth_access (access_expira_en, revocado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alexa_event_tokens (
  usuario_id INT UNSIGNED NOT NULL,
  region ENUM('NA', 'EU', 'FE') NOT NULL DEFAULT 'NA',
  access_token_cifrado TEXT NOT NULL,
  refresh_token_cifrado TEXT NOT NULL,
  access_expira_en DATETIME NOT NULL,
  ultimo_envio DATETIME NULL,
  ultimo_http_status SMALLINT UNSIGNED NULL,
  ultimo_error VARCHAR(500) NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (usuario_id),
  INDEX idx_alexa_event_expiracion (access_expira_en),
  INDEX idx_alexa_event_region (region)
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
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,
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
  estado ENUM('EJECUTANDO', 'COMPLETADA', 'PARCIAL', 'FALLIDA', 'OMITIDA')
    NOT NULL DEFAULT 'EJECUTANDO',
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

CREATE TABLE IF NOT EXISTS usuarios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cliente_id INT UNSIGNED NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  email VARCHAR(160) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  rol ENUM('ADMIN', 'OPERADOR', 'LECTURA') NOT NULL DEFAULT 'LECTURA',
  estado ENUM('ACTIVO', 'BLOQUEADO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  intentos_fallidos TINYINT UNSIGNED NOT NULL DEFAULT 0,
  bloqueado_hasta DATETIME NULL,
  ultimo_acceso DATETIME NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_usuarios_email (email),
  INDEX idx_usuarios_cliente_estado (cliente_id, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tokens_moviles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  nombre_dispositivo VARCHAR(120) NOT NULL,
  expira_en DATETIME NOT NULL,
  ultimo_uso DATETIME NULL,
  revocado_en DATETIME NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tokens_moviles_hash (token_hash),
  INDEX idx_tokens_moviles_usuario (usuario_id, revocado_en, expira_en),
  INDEX idx_tokens_moviles_expira (expira_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS moviles_push (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id INT UNSIGNED NOT NULL,
  sesion_movil_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expo_push_token VARCHAR(255) NOT NULL,
  plataforma ENUM('ANDROID', 'IOS') NOT NULL,
  nombre_dispositivo VARCHAR(120) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  ultimo_registro DATETIME NOT NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_moviles_push_token_hash (token_hash),
  INDEX idx_moviles_push_usuario_activo (usuario_id, activo),
  INDEX idx_moviles_push_sesion (sesion_movil_id, activo),
  INDEX idx_moviles_push_actualizado (actualizado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notificaciones_push (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  alerta_id BIGINT UNSIGNED NOT NULL,
  push_token_id BIGINT UNSIGNED NOT NULL,
  cliente_id INT UNSIGNED NOT NULL,
  titulo VARCHAR(120) NOT NULL,
  cuerpo VARCHAR(255) NOT NULL,
  payload_json TEXT NULL,
  estado ENUM(
    'PENDIENTE', 'ENVIANDO', 'ENVIADA', 'REINTENTAR', 'DESCARTADA'
  ) NOT NULL DEFAULT 'PENDIENTE',
  intentos TINYINT UNSIGNED NOT NULL DEFAULT 0,
  disponible_en DATETIME NOT NULL,
  ticket_id VARCHAR(255) NULL,
  ultimo_error VARCHAR(500) NULL,
  enviado_en DATETIME NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_notificacion_alerta_token (alerta_id, push_token_id),
  INDEX idx_notificaciones_estado_disponible (estado, disponible_en, id),
  INDEX idx_notificaciones_cliente (cliente_id, creado_en),
  INDEX idx_notificaciones_ticket (ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lecturas_sensores (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dispositivo_id VARCHAR(64) NOT NULL,
  temperatura DECIMAL(6,2) NULL,
  humedad DECIMAL(6,2) NULL,
  indice_calor DECIMAL(6,2) NULL,
  gas_raw SMALLINT UNSIGNED NULL,
  gas_porcentaje DECIMAL(5,2) NULL,
  flama_detectada TINYINT(1) NOT NULL DEFAULT 0,
  estado_general ENUM('NORMAL', 'ALERTA', 'ALARMA') NOT NULL DEFAULT 'NORMAL',
  salud_dht ENUM(
    'INICIANDO', 'CALENTANDO', 'OK', 'REVISAR', 'FALLO', 'DESCONOCIDO'
  ) NOT NULL DEFAULT 'DESCONOCIDO',
  salud_mq2 ENUM(
    'INICIANDO', 'CALENTANDO', 'OK', 'REVISAR', 'FALLO', 'DESCONOCIDO'
  ) NOT NULL DEFAULT 'DESCONOCIDO',
  salud_flama ENUM(
    'INICIANDO', 'CALENTANDO', 'OK', 'REVISAR', 'FALLO', 'DESCONOCIDO'
  ) NOT NULL DEFAULT 'DESCONOCIDO',
  wifi_rssi SMALLINT NULL,
  tiempo_encendido INT UNSIGNED NULL,
  contador_alarmas INT UNSIGNED NOT NULL DEFAULT 0,
  fecha_hora TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_lecturas_dispositivos
    FOREIGN KEY (dispositivo_id) REFERENCES dispositivos(id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  INDEX idx_lecturas_dispositivo_fecha (dispositivo_id, fecha_hora, id),
  INDEX idx_lecturas_estado_fecha (estado_general, fecha_hora),
  INDEX idx_lecturas_fecha (fecha_hora)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS estado_sensores (
  dispositivo_id VARCHAR(64) PRIMARY KEY,
  temperatura DECIMAL(6,2) NULL,
  humedad DECIMAL(6,2) NULL,
  indice_calor DECIMAL(6,2) NULL,
  gas_raw SMALLINT UNSIGNED NULL,
  gas_porcentaje DECIMAL(5,2) NULL,
  gas_detectado TINYINT(1) NOT NULL DEFAULT 0,
  flama_detectada TINYINT(1) NOT NULL DEFAULT 0,
  estado_general ENUM('NORMAL', 'ALERTA', 'ALARMA') NOT NULL DEFAULT 'NORMAL',
  peligro_activo TINYINT(1) NOT NULL DEFAULT 0,
  alarma_enclavada TINYINT(1) NOT NULL DEFAULT 0,
  alarma_silenciada TINYINT(1) NOT NULL DEFAULT 0,
  revision_fisica_pendiente TINYINT(1) NOT NULL DEFAULT 0,
  buzzer_encendido TINYINT(1) NOT NULL DEFAULT 0,
  modo_operacion ENUM(
    'NORMAL', 'ALERTA', 'ALARMA_SONORA', 'ALARMA_SILENCIADA', 'REVISION_PENDIENTE'
  ) NOT NULL DEFAULT 'NORMAL',
  silenciada_por ENUM(
    'NINGUNO', 'APP_MOVIL', 'BOTON_FISICO'
  ) NOT NULL DEFAULT 'NINGUNO',
  salud_dht ENUM(
    'INICIANDO', 'CALENTANDO', 'OK', 'REVISAR', 'FALLO', 'DESCONOCIDO'
  ) NOT NULL DEFAULT 'DESCONOCIDO',
  salud_mq2 ENUM(
    'INICIANDO', 'CALENTANDO', 'OK', 'REVISAR', 'FALLO', 'DESCONOCIDO'
  ) NOT NULL DEFAULT 'DESCONOCIDO',
  salud_flama ENUM(
    'INICIANDO', 'CALENTANDO', 'OK', 'REVISAR', 'FALLO', 'DESCONOCIDO'
  ) NOT NULL DEFAULT 'DESCONOCIDO',
  wifi_rssi SMALLINT NULL,
  tiempo_encendido INT UNSIGNED NULL,
  contador_alarmas INT UNSIGNED NOT NULL DEFAULT 0,
  contador_silencios_en_linea INT UNSIGNED NOT NULL DEFAULT 0,
  contador_silencios_fisicos INT UNSIGNED NOT NULL DEFAULT 0,
  contador_resets_fisicos INT UNSIGNED NOT NULL DEFAULT 0,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_estado_actualizado (actualizado_en),
  INDEX idx_estado_general (estado_general)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS configuracion_mq2 (
  dispositivo_id VARCHAR(64) NOT NULL,
  umbral_adc SMALLINT UNSIGNED NOT NULL DEFAULT 1600,
  calentamiento_total_s INT UNSIGNED NOT NULL DEFAULT 120,
  ultima_lectura_adc SMALLINT UNSIGNED NULL,
  ultima_calibracion DATETIME NULL,
  adc_aire_limpio SMALLINT UNSIGNED NULL,
  calibrado_por INT UNSIGNED NULL,
  nota_calibracion VARCHAR(500) NULL,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (dispositivo_id),
  INDEX idx_mq2_ultima_calibracion (ultima_calibracion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mq2_calibraciones (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  dispositivo_id VARCHAR(64) NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  adc_aire_limpio SMALLINT UNSIGNED NOT NULL,
  umbral_reportado SMALLINT UNSIGNED NOT NULL,
  comentario VARCHAR(500) NULL,
  fecha_hora TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_mq2_calibracion_dispositivo_fecha (
    dispositivo_id, fecha_hora, id
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS muestras_historicas (
  dispositivo_id VARCHAR(64) NOT NULL,
  periodo_minuto DATETIME NOT NULL,
  temperatura DECIMAL(6,2) NULL,
  humedad DECIMAL(6,2) NULL,
  indice_calor DECIMAL(6,2) NULL,
  gas_raw SMALLINT UNSIGNED NULL,
  gas_porcentaje DECIMAL(5,2) NULL,
  gas_detectado TINYINT(1) NOT NULL DEFAULT 0,
  flama_detectada TINYINT(1) NOT NULL DEFAULT 0,
  estado_general ENUM('NORMAL', 'ALERTA', 'ALARMA') NOT NULL DEFAULT 'NORMAL',
  salud_dht ENUM(
    'INICIANDO', 'CALENTANDO', 'OK', 'REVISAR', 'FALLO', 'DESCONOCIDO'
  ) NOT NULL DEFAULT 'DESCONOCIDO',
  salud_mq2 ENUM(
    'INICIANDO', 'CALENTANDO', 'OK', 'REVISAR', 'FALLO', 'DESCONOCIDO'
  ) NOT NULL DEFAULT 'DESCONOCIDO',
  salud_flama ENUM(
    'INICIANDO', 'CALENTANDO', 'OK', 'REVISAR', 'FALLO', 'DESCONOCIDO'
  ) NOT NULL DEFAULT 'DESCONOCIDO',
  wifi_rssi SMALLINT NULL,
  contador_alarmas INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (dispositivo_id, periodo_minuto),
  INDEX idx_muestras_periodo (periodo_minuto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS resumen_horario (
  dispositivo_id VARCHAR(64) NOT NULL,
  periodo_hora DATETIME NOT NULL,
  muestras INT UNSIGNED NOT NULL,
  temperatura_promedio DECIMAL(7,2) NULL,
  temperatura_minima DECIMAL(6,2) NULL,
  temperatura_maxima DECIMAL(6,2) NULL,
  humedad_promedio DECIMAL(6,2) NULL,
  humedad_minima DECIMAL(6,2) NULL,
  humedad_maxima DECIMAL(6,2) NULL,
  gas_promedio DECIMAL(8,2) NULL,
  gas_minimo SMALLINT UNSIGNED NULL,
  gas_maximo SMALLINT UNSIGNED NULL,
  detecciones_gas INT UNSIGNED NOT NULL DEFAULT 0,
  detecciones_flama INT UNSIGNED NOT NULL DEFAULT 0,
  muestras_alarma INT UNSIGNED NOT NULL DEFAULT 0,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (dispositivo_id, periodo_hora),
  INDEX idx_resumen_hora (periodo_hora)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alertas (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dispositivo_id VARCHAR(64) NOT NULL,
  tipo_alerta VARCHAR(80) NOT NULL,
  valor_sensor DECIMAL(10,2) NULL,
  severidad ENUM('NORMAL', 'PRECAUCION', 'CRITICO') NOT NULL DEFAULT 'PRECAUCION',
  atendida TINYINT(1) NOT NULL DEFAULT 0,
  fecha_hora TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_alertas_dispositivos
    FOREIGN KEY (dispositivo_id) REFERENCES dispositivos(id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  INDEX idx_alertas_fecha (fecha_hora),
  INDEX idx_alertas_dispositivo_fecha (dispositivo_id, fecha_hora),
  INDEX idx_alertas_estado_fecha (atendida, severidad, fecha_hora)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alerta_gestiones (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  alerta_id BIGINT UNSIGNED NOT NULL,
  accion ENUM('RECONOCER', 'RESOLVER') NOT NULL,
  responsable VARCHAR(100) NOT NULL,
  comentario VARCHAR(500) NULL,
  fecha_hora TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_gestiones_alerta_fecha (alerta_id, fecha_hora, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

DROP TRIGGER IF EXISTS trg_historial_solo_alarmas;
DROP TRIGGER IF EXISTS trg_estado_alarm_insert;
DROP TRIGGER IF EXISTS trg_estado_alarm_update;
DROP TRIGGER IF EXISTS trg_muestra_historica_insert;
DROP TRIGGER IF EXISTS trg_muestra_historica_update;
DROP TRIGGER IF EXISTS trg_evento_alarma_insert;
DROP TRIGGER IF EXISTS trg_evento_alarma_update;

DELIMITER $$

CREATE TRIGGER trg_muestra_historica_insert
AFTER INSERT ON estado_sensores
FOR EACH ROW
BEGIN
  INSERT IGNORE INTO muestras_historicas (
    dispositivo_id, periodo_minuto, temperatura, humedad, indice_calor,
    gas_raw, gas_porcentaje, gas_detectado, flama_detectada, estado_general,
    salud_dht, salud_mq2, salud_flama, wifi_rssi, contador_alarmas
  ) VALUES (
    NEW.dispositivo_id,
    DATE_SUB(NEW.actualizado_en, INTERVAL SECOND(NEW.actualizado_en) SECOND),
    NEW.temperatura, NEW.humedad, NEW.indice_calor,
    NEW.gas_raw, NEW.gas_porcentaje, NEW.gas_detectado, NEW.flama_detectada,
    NEW.estado_general, NEW.salud_dht, NEW.salud_mq2, NEW.salud_flama,
    NEW.wifi_rssi, NEW.contador_alarmas
  );

  IF NEW.estado_general = 'ALARMA' THEN
    INSERT INTO lecturas_sensores (
      dispositivo_id, temperatura, humedad, indice_calor,
      gas_raw, gas_porcentaje, flama_detectada, estado_general,
      salud_dht, salud_mq2, salud_flama, wifi_rssi,
      tiempo_encendido, contador_alarmas, fecha_hora
    ) VALUES (
      NEW.dispositivo_id, NEW.temperatura, NEW.humedad, NEW.indice_calor,
      NEW.gas_raw, NEW.gas_porcentaje, NEW.flama_detectada, NEW.estado_general,
      NEW.salud_dht, NEW.salud_mq2, NEW.salud_flama, NEW.wifi_rssi,
      NEW.tiempo_encendido, NEW.contador_alarmas, NEW.actualizado_en
    );
  END IF;
END$$

CREATE TRIGGER trg_muestra_historica_update
AFTER UPDATE ON estado_sensores
FOR EACH ROW
BEGIN
  INSERT IGNORE INTO muestras_historicas (
    dispositivo_id, periodo_minuto, temperatura, humedad, indice_calor,
    gas_raw, gas_porcentaje, gas_detectado, flama_detectada, estado_general,
    salud_dht, salud_mq2, salud_flama, wifi_rssi, contador_alarmas
  ) VALUES (
    NEW.dispositivo_id,
    DATE_SUB(NEW.actualizado_en, INTERVAL SECOND(NEW.actualizado_en) SECOND),
    NEW.temperatura, NEW.humedad, NEW.indice_calor,
    NEW.gas_raw, NEW.gas_porcentaje, NEW.gas_detectado, NEW.flama_detectada,
    NEW.estado_general, NEW.salud_dht, NEW.salud_mq2, NEW.salud_flama,
    NEW.wifi_rssi, NEW.contador_alarmas
  );

  IF NEW.estado_general = 'ALARMA'
     AND (
       OLD.estado_general <> 'ALARMA'
       OR NOT (NEW.gas_detectado <=> OLD.gas_detectado)
       OR NOT (NEW.flama_detectada <=> OLD.flama_detectada)
       OR NEW.contador_alarmas <> OLD.contador_alarmas
     )
  THEN
    INSERT INTO lecturas_sensores (
      dispositivo_id, temperatura, humedad, indice_calor,
      gas_raw, gas_porcentaje, flama_detectada, estado_general,
      salud_dht, salud_mq2, salud_flama, wifi_rssi,
      tiempo_encendido, contador_alarmas, fecha_hora
    ) VALUES (
      NEW.dispositivo_id, NEW.temperatura, NEW.humedad, NEW.indice_calor,
      NEW.gas_raw, NEW.gas_porcentaje, NEW.flama_detectada, NEW.estado_general,
      NEW.salud_dht, NEW.salud_mq2, NEW.salud_flama, NEW.wifi_rssi,
      NEW.tiempo_encendido, NEW.contador_alarmas, NEW.actualizado_en
    );
  END IF;
END$$

DELIMITER ;
