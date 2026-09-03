-- EJEMPLO. Ajusta antes de ejecutar.
-- Ejecuta en la base IoT / idactivos.

SET NAMES utf8mb4;

UPDATE actuadores_shelly
SET
  dispositivo_vinculado_id = 'ESP32_001',
  funcion = 'SIRENA',
  categoria = 'SEGURIDAD',
  modo_control = 'HIBRIDO',
  estado = 'Activo'
WHERE shelly_device_id = '441D64655F78'
  AND canal = 0;

SELECT
  id,
  cliente_id,
  nombre,
  ubicacion,
  dispositivo_vinculado_id,
  shelly_device_id,
  canal,
  funcion,
  categoria,
  modo_control,
  estado
FROM actuadores_shelly
WHERE shelly_device_id = '441D64655F78';
