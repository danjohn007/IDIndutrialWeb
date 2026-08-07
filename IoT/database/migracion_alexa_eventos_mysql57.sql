-- ID Industrial - sincronizacion proactiva Alexa ChangeReport
-- Compatible con MySQL 5.7. Ejecutar una vez en la base seleccionada.

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
