-- Acceso de la app movil para MySQL 5.7.
-- Ejecuta este archivo una sola vez con idactivo_idindustrial seleccionada.

SET NAMES utf8mb4;

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

SHOW TABLES LIKE 'tokens_moviles';
