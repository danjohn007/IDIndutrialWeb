-- Ejecuta en phpMyAdmin sobre la base IoT / idactivos.
-- Sirve para confirmar si el ESP32 y la sirena Shelly estan listos.

SET NAMES utf8mb4;

SHOW TABLES LIKE 'dispositivos';
SHOW TABLES LIKE 'estado_sensores';
SHOW TABLES LIKE 'actuadores_shelly';
SHOW TABLES LIKE 'estado_shelly';

SHOW COLUMNS FROM estado_sensores LIKE 'estacion_manual_activada';
SHOW COLUMNS FROM lecturas_sensores LIKE 'estacion_manual_activada';

SELECT
  id,
  cliente_id,
  ubicacion,
  estado,
  ultima_conexion
FROM dispositivos
WHERE id = 'ESP32_001';

SELECT
  dispositivo_id,
  estacion_manual_activada,
  estado_general,
  alarma_enclavada,
  alarma_silenciada,
  peligro_activo,
  modo_operacion,
  actualizado_en
FROM estado_sensores
WHERE dispositivo_id = 'ESP32_001';

SELECT
  id,
  dispositivo_id,
  tipo_alerta,
  valor_sensor,
  severidad,
  atendida,
  fecha_hora
FROM alertas
WHERE dispositivo_id = 'ESP32_001'
ORDER BY fecha_hora DESC, id DESC
LIMIT 10;

SELECT
  a.id,
  a.cliente_id,
  a.nombre,
  a.ubicacion,
  a.dispositivo_vinculado_id,
  a.shelly_device_id,
  a.modelo,
  a.canal,
  a.funcion,
  a.categoria,
  a.modo_control,
  a.estado,
  es.online,
  es.salida_encendida,
  es.ultimo_error,
  es.sincronizado_en
FROM actuadores_shelly a
LEFT JOIN estado_shelly es ON es.actuador_id = a.id
WHERE a.dispositivo_vinculado_id = 'ESP32_001'
   OR a.shelly_device_id = '441D64655F78'
   OR a.id LIKE '%SHELLY%'
ORDER BY a.actualizado_en DESC, a.id;
