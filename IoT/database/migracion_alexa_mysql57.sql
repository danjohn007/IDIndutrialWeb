-- ID Industrial - Amazon Alexa Smart Home
-- Compatible con MySQL 5.7. Ejecutar una vez en la base seleccionada.

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

ALTER TABLE comandos_shelly
  MODIFY origen ENUM('AUTOMATICO', 'WEB', 'APP', 'CRON', 'ALEXA') NOT NULL;

ALTER TABLE eventos_shelly
  MODIFY origen ENUM('CLOUD', 'WEBHOOK', 'SISTEMA', 'USUARIO', 'ALEXA') NOT NULL;

