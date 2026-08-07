-- Ejecuta este archivo una sola vez en phpMyAdmin sobre la base del proyecto.
-- Compatible con MySQL 5.7. Las muestras antiguas se resumen antes de borrarse.

SET NAMES utf8mb4;

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

SELECT
  'Politica de retencion instalada' AS resultado,
  COUNT(*) AS resumenes_existentes
FROM resumen_horario;
