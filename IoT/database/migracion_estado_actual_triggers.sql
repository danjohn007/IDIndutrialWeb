-- Ejecuta este archivo una sola vez en phpMyAdmin sobre idactivo_idindustrial.
-- Conserva las lecturas existentes. A partir de la migracion, el estado actual
-- ocupa una fila por dispositivo y el historial recibe solo alarmas.

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

DROP TRIGGER IF EXISTS trg_estado_alarm_insert;
DROP TRIGGER IF EXISTS trg_estado_alarm_update;
DROP TRIGGER IF EXISTS trg_historial_solo_alarmas;

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
