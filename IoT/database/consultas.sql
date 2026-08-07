-- Consultas administrativas para phpMyAdmin y referencia del backend.
-- Sustituye los valores de ejemplo antes de ejecutar los INSERT.

-- 1. Registrar el primer cliente sin duplicarlo al repetir este bloque.
-- "pendiente_login" es temporal mientras no exista inicio de sesión.
INSERT INTO clientes (nombre_empresa, email, password_hash)
VALUES ('ID Industrial', 'admin@idactivos.com', 'pendiente_login')
ON DUPLICATE KEY UPDATE nombre_empresa = 'ID Industrial';

-- 2. Registrar el ESP32. Debe coincidir con DISPOSITIVO_ID del firmware.
INSERT INTO dispositivos (id, cliente_id, ubicacion, estado)
VALUES (
  'ESP32_001',
  (SELECT id FROM clientes WHERE email = 'admin@idactivos.com' LIMIT 1),
  'Area de pruebas',
  'Activo'
)
ON DUPLICATE KEY UPDATE
  cliente_id = (SELECT id FROM clientes WHERE email = 'admin@idactivos.com' LIMIT 1),
  ubicacion = 'Area de pruebas',
  estado = 'Activo';

-- Estado actual de un dispositivo. Solo ocupa una fila por ESP32.
SELECT
  e.*,
  d.ubicacion
FROM estado_sensores e
INNER JOIN dispositivos d ON d.id = e.dispositivo_id
WHERE e.dispositivo_id = 'ESP32_001';

-- Historial reciente de alarmas para graficas.
SELECT
  fecha_hora,
  temperatura,
  humedad,
  indice_calor,
  gas_raw,
  gas_porcentaje,
  flama_detectada,
  estado_general,
  wifi_rssi
FROM lecturas_sensores
WHERE dispositivo_id = 'ESP32_001'
  AND fecha_hora >= UTC_TIMESTAMP() - INTERVAL 24 HOUR
ORDER BY fecha_hora;

-- Confirmar que estan instalados los dos triggers de alarmas.
SHOW TRIGGERS WHERE `Table` = 'estado_sensores';

-- Promedios y máximos de las últimas 24 horas.
SELECT
  COUNT(*) AS lecturas,
  ROUND(AVG(temperatura), 2) AS temperatura_promedio,
  MAX(temperatura) AS temperatura_maxima,
  ROUND(AVG(humedad), 2) AS humedad_promedio,
  ROUND(AVG(gas_raw), 2) AS gas_promedio,
  MAX(gas_raw) AS gas_maximo,
  SUM(flama_detectada = 1) AS lecturas_con_flama,
  SUM(estado_general = 'ALARMA') AS lecturas_alarma
FROM lecturas_sensores
WHERE dispositivo_id = 'ESP32_001'
  AND fecha_hora >= UTC_TIMESTAMP() - INTERVAL 24 HOUR;

-- Alertas pendientes.
SELECT
  a.id,
  a.fecha_hora,
  a.dispositivo_id,
  d.ubicacion,
  a.tipo_alerta,
  a.valor_sensor,
  a.severidad
FROM alertas a
INNER JOIN dispositivos d ON d.id = a.dispositivo_id
WHERE a.atendida = 0
ORDER BY a.fecha_hora DESC;

-- Conteo de alertas por tipo en las últimas 24 horas.
SELECT
  tipo_alerta,
  severidad,
  COUNT(*) AS total
FROM alertas
WHERE fecha_hora >= UTC_TIMESTAMP() - INTERVAL 24 HOUR
GROUP BY tipo_alerta, severidad
ORDER BY total DESC;

-- Máximos por hora para explicar tendencias y gráficas.
SELECT
  DATE_FORMAT(fecha_hora, '%Y-%m-%d %H:00:00') AS hora_utc,
  ROUND(AVG(temperatura), 2) AS temperatura_promedio,
  MAX(temperatura) AS temperatura_maxima,
  ROUND(AVG(humedad), 2) AS humedad_promedio,
  ROUND(AVG(gas_raw), 2) AS gas_promedio,
  MAX(gas_raw) AS gas_maximo,
  SUM(gas_raw >= 1600) AS lecturas_con_gas,
  SUM(flama_detectada = 1) AS lecturas_con_flama,
  SUM(estado_general = 'ALARMA') AS lecturas_alarma,
  ROUND(AVG(wifi_rssi), 2) AS wifi_promedio
FROM lecturas_sensores
WHERE dispositivo_id = 'ESP32_001'
  AND fecha_hora >= UTC_TIMESTAMP() - INTERVAL 24 HOUR
GROUP BY hora_utc
ORDER BY hora_utc;

-- Marcar una alerta como atendida.
-- Ejecuta manualmente despues de sustituir el identificador:
-- UPDATE alertas
-- SET atendida = 1
-- WHERE id = 1;

-- Desactivar un dispositivo sin borrar su historial.
-- No ejecutes este ejemplo durante la instalacion:
-- UPDATE dispositivos
-- SET estado = 'Inactivo'
-- WHERE id = 'ESP32_001';
