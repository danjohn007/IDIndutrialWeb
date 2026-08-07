-- Notificaciones push de la app para MySQL 5.7.
-- Ejecuta este archivo una sola vez con idactivo_idindustrial seleccionada.

SET NAMES utf8mb4;

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

SHOW TABLES LIKE 'moviles_push';
SHOW TABLES LIKE 'notificaciones_push';
