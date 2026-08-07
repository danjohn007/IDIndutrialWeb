-- Correccion incremental para MySQL 5.7.
-- Detiene lecturas normales repetidas, reactiva ESP32_001 y conserva una fila
-- de estado actual por dispositivo.

SET NAMES utf8mb4;

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
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_estado_actualizado (actualizado_en),
  INDEX idx_estado_general (estado_general)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TRIGGER IF EXISTS trg_historial_solo_alarmas;
DROP TRIGGER IF EXISTS trg_estado_alarm_insert;
DROP TRIGGER IF EXISTS trg_estado_alarm_update;

UPDATE dispositivos
SET estado = 'Activo'
WHERE id = 'ESP32_001';

-- Recupera la ultima lectura previa como estado actual.
INSERT INTO estado_sensores (
  dispositivo_id, temperatura, humedad, indice_calor,
  gas_raw, gas_porcentaje, gas_detectado, flama_detectada,
  estado_general, salud_dht, salud_mq2, salud_flama,
  wifi_rssi, tiempo_encendido, contador_alarmas, actualizado_en
)
SELECT
  l.dispositivo_id, l.temperatura, l.humedad, l.indice_calor,
  l.gas_raw, l.gas_porcentaje,
  IF(l.salud_mq2 = 'OK' AND l.gas_raw >= 1600, 1, 0),
  l.flama_detectada, l.estado_general,
  l.salud_dht, l.salud_mq2, l.salud_flama,
  l.wifi_rssi, l.tiempo_encendido, l.contador_alarmas, l.fecha_hora
FROM lecturas_sensores l
INNER JOIN (
  SELECT dispositivo_id, MAX(id) AS id
  FROM lecturas_sensores
  GROUP BY dispositivo_id
) ultima
  ON ultima.dispositivo_id = l.dispositivo_id
 AND ultima.id = l.id
ON DUPLICATE KEY UPDATE
  temperatura = VALUES(temperatura),
  humedad = VALUES(humedad),
  indice_calor = VALUES(indice_calor),
  gas_raw = VALUES(gas_raw),
  gas_porcentaje = VALUES(gas_porcentaje),
  gas_detectado = VALUES(gas_detectado),
  flama_detectada = VALUES(flama_detectada),
  estado_general = VALUES(estado_general),
  salud_dht = VALUES(salud_dht),
  salud_mq2 = VALUES(salud_mq2),
  salud_flama = VALUES(salud_flama),
  wifi_rssi = VALUES(wifi_rssi),
  tiempo_encendido = VALUES(tiempo_encendido),
  contador_alarmas = VALUES(contador_alarmas),
  actualizado_en = VALUES(actualizado_en);

DELIMITER $$

CREATE TRIGGER trg_historial_solo_alarmas
BEFORE INSERT ON lecturas_sensores
FOR EACH ROW
BEGIN
  IF NEW.estado_general <> 'ALARMA' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'lecturas_sensores solo acepta alarmas';
  END IF;
END$$

CREATE TRIGGER trg_estado_alarm_insert
AFTER INSERT ON estado_sensores
FOR EACH ROW
BEGIN
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

CREATE TRIGGER trg_estado_alarm_update
AFTER UPDATE ON estado_sensores
FOR EACH ROW
BEGIN
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

SELECT id, estado, ultima_conexion
FROM dispositivos
WHERE id = 'ESP32_001';

SELECT COUNT(*) AS filas_estado_actual
FROM estado_sensores;

SHOW TRIGGERS WHERE `Table` IN ('estado_sensores', 'lecturas_sensores');
