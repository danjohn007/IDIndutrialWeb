-- Estacion manual de incendio para MySQL 5.7.
-- Ejecuta este archivo una sola vez sobre la base IoT/idactivos.
-- Agrega el contacto seco de la estacion manual al estado actual, historial y resumen.

SET NAMES utf8mb4;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE estado_sensores ADD COLUMN estacion_manual_activada TINYINT(1) NOT NULL DEFAULT 0 AFTER flama_detectada',
    'SELECT "estado_sensores.estacion_manual_activada ya existe" AS mensaje'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'estado_sensores'
    AND COLUMN_NAME = 'estacion_manual_activada'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE lecturas_sensores ADD COLUMN estacion_manual_activada TINYINT(1) NOT NULL DEFAULT 0 AFTER flama_detectada',
    'SELECT "lecturas_sensores.estacion_manual_activada ya existe" AS mensaje'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'lecturas_sensores'
    AND COLUMN_NAME = 'estacion_manual_activada'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE muestras_historicas ADD COLUMN estacion_manual_activada TINYINT(1) NOT NULL DEFAULT 0 AFTER flama_detectada',
    'SELECT "muestras_historicas.estacion_manual_activada ya existe" AS mensaje'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'muestras_historicas'
    AND COLUMN_NAME = 'estacion_manual_activada'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE resumen_horario ADD COLUMN detecciones_estacion_manual INT UNSIGNED NOT NULL DEFAULT 0 AFTER detecciones_flama',
    'SELECT "resumen_horario.detecciones_estacion_manual ya existe" AS mensaje'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'resumen_horario'
    AND COLUMN_NAME = 'detecciones_estacion_manual'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

DROP TRIGGER IF EXISTS trg_muestra_historica_insert;
DROP TRIGGER IF EXISTS trg_muestra_historica_update;

DELIMITER $$

CREATE TRIGGER trg_muestra_historica_insert
AFTER INSERT ON estado_sensores
FOR EACH ROW
BEGIN
  INSERT IGNORE INTO muestras_historicas (
    dispositivo_id, periodo_minuto, temperatura, humedad, indice_calor,
    gas_raw, gas_porcentaje, gas_detectado, flama_detectada,
    estacion_manual_activada, estado_general,
    salud_dht, salud_mq2, salud_flama, wifi_rssi, contador_alarmas
  ) VALUES (
    NEW.dispositivo_id,
    DATE_SUB(NEW.actualizado_en, INTERVAL SECOND(NEW.actualizado_en) SECOND),
    NEW.temperatura, NEW.humedad, NEW.indice_calor,
    NEW.gas_raw, NEW.gas_porcentaje, NEW.gas_detectado, NEW.flama_detectada,
    NEW.estacion_manual_activada,
    NEW.estado_general, NEW.salud_dht, NEW.salud_mq2, NEW.salud_flama,
    NEW.wifi_rssi, NEW.contador_alarmas
  );

  IF NEW.estado_general = 'ALARMA' THEN
    INSERT INTO lecturas_sensores (
      dispositivo_id, temperatura, humedad, indice_calor,
      gas_raw, gas_porcentaje, flama_detectada, estacion_manual_activada,
      estado_general,
      salud_dht, salud_mq2, salud_flama, wifi_rssi,
      tiempo_encendido, contador_alarmas, fecha_hora
    ) VALUES (
      NEW.dispositivo_id, NEW.temperatura, NEW.humedad, NEW.indice_calor,
      NEW.gas_raw, NEW.gas_porcentaje, NEW.flama_detectada,
      NEW.estacion_manual_activada, NEW.estado_general,
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
    gas_raw, gas_porcentaje, gas_detectado, flama_detectada,
    estacion_manual_activada, estado_general,
    salud_dht, salud_mq2, salud_flama, wifi_rssi, contador_alarmas
  ) VALUES (
    NEW.dispositivo_id,
    DATE_SUB(NEW.actualizado_en, INTERVAL SECOND(NEW.actualizado_en) SECOND),
    NEW.temperatura, NEW.humedad, NEW.indice_calor,
    NEW.gas_raw, NEW.gas_porcentaje, NEW.gas_detectado, NEW.flama_detectada,
    NEW.estacion_manual_activada,
    NEW.estado_general, NEW.salud_dht, NEW.salud_mq2, NEW.salud_flama,
    NEW.wifi_rssi, NEW.contador_alarmas
  );

  IF NEW.estado_general = 'ALARMA'
     AND (
       OLD.estado_general <> 'ALARMA'
       OR NOT (NEW.gas_detectado <=> OLD.gas_detectado)
       OR NOT (NEW.flama_detectada <=> OLD.flama_detectada)
       OR NOT (NEW.estacion_manual_activada <=> OLD.estacion_manual_activada)
       OR NEW.contador_alarmas <> OLD.contador_alarmas
     )
  THEN
    INSERT INTO lecturas_sensores (
      dispositivo_id, temperatura, humedad, indice_calor,
      gas_raw, gas_porcentaje, flama_detectada, estacion_manual_activada,
      estado_general,
      salud_dht, salud_mq2, salud_flama, wifi_rssi,
      tiempo_encendido, contador_alarmas, fecha_hora
    ) VALUES (
      NEW.dispositivo_id, NEW.temperatura, NEW.humedad, NEW.indice_calor,
      NEW.gas_raw, NEW.gas_porcentaje, NEW.flama_detectada,
      NEW.estacion_manual_activada, NEW.estado_general,
      NEW.salud_dht, NEW.salud_mq2, NEW.salud_flama, NEW.wifi_rssi,
      NEW.tiempo_encendido, NEW.contador_alarmas, NEW.actualizado_en
    );
  END IF;
END$$

DELIMITER ;

SHOW COLUMNS FROM estado_sensores LIKE 'estacion_manual_activada';
SHOW COLUMNS FROM lecturas_sensores LIKE 'estacion_manual_activada';
SHOW COLUMNS FROM muestras_historicas LIKE 'estacion_manual_activada';
SHOW COLUMNS FROM resumen_horario LIKE 'detecciones_estacion_manual';
