-- Ejecuta este archivo una sola vez en phpMyAdmin sobre la base del proyecto.
-- Compatible con MySQL 5.7. No selecciona ni crea una base de datos.

SET NAMES utf8mb4;

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

SELECT
  'Migracion MQ-2 instalada' AS resultado,
  COUNT(*) AS configuraciones_existentes
FROM configuracion_mq2;
