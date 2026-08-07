-- ID Industrial: registra 50 dispositivos simulados sin lecturas.
-- Compatible con MySQL 5.7 y MariaDB.
--
-- Resultado esperado en el panel:
--   SIM_001 a SIM_050 aparecen como Activo / OFFLINE y sin lecturas.
--
-- IMPORTANTE:
--   Este archivo no genera trafico ni prueba la capacidad real de cPanel.
--   Solo registra los equipos para probar administracion, consultas y renderizado.

SET NAMES utf8mb4;

-- Usa el cliente del primer administrador activo. Si no existe, usa el primer
-- cliente registrado. En una instalacion con varios clientes, sustituye este
-- valor manualmente antes de ejecutar el INSERT.
SET @cliente_id := COALESCE(
  (
    SELECT u.cliente_id
    FROM usuarios u
    WHERE u.rol = 'ADMIN'
      AND u.estado = 'ACTIVO'
    ORDER BY u.id
    LIMIT 1
  ),
  (
    SELECT c.id
    FROM clientes c
    ORDER BY c.id
    LIMIT 1
  )
);

SELECT
  @cliente_id AS cliente_seleccionado,
  CASE
    WHEN @cliente_id IS NULL THEN 'ERROR: no existe un cliente'
    ELSE 'OK'
  END AS validacion;

INSERT INTO dispositivos (
  id,
  cliente_id,
  ubicacion,
  estado,
  ultima_conexion
)
SELECT
  CONCAT('SIM_', LPAD(numeros.numero, 3, '0')) AS id,
  @cliente_id AS cliente_id,
  CONCAT('Zona simulada ', LPAD(numeros.numero, 2, '0')) AS ubicacion,
  'Activo' AS estado,
  NULL AS ultima_conexion
FROM (
  SELECT unidades.n + decenas.n * 10 AS numero
  FROM (
    SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL
    SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL
    SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9
  ) unidades
  CROSS JOIN (
    SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL
    SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5
  ) decenas
) numeros
WHERE numeros.numero BETWEEN 1 AND 50
ORDER BY numeros.numero
ON DUPLICATE KEY UPDATE
  cliente_id = VALUES(cliente_id),
  ubicacion = VALUES(ubicacion),
  estado = 'Activo',
  ultima_conexion = NULL;

-- Verificacion final.
SELECT
  COUNT(*) AS dispositivos_simulados,
  SUM(estado = 'Activo') AS activos,
  SUM(ultima_conexion IS NULL) AS sin_conexion
FROM dispositivos
WHERE cliente_id = @cliente_id
  AND id BETWEEN 'SIM_001' AND 'SIM_050';

SELECT
  id,
  ubicacion,
  estado,
  ultima_conexion
FROM dispositivos
WHERE cliente_id = @cliente_id
  AND id BETWEEN 'SIM_001' AND 'SIM_050'
ORDER BY id;

-- DESPUES DE LA PRUEBA:
-- Para conservar los registros pero ocultarlos del panel en vivo, ejecuta:
--
-- UPDATE dispositivos
-- SET estado = 'Inactivo', ultima_conexion = NULL
-- WHERE cliente_id = @cliente_id
--   AND id BETWEEN 'SIM_001' AND 'SIM_050';
--
-- Si ya ejecutaste el simulador y quieres volver a mostrarlos completamente
-- sin lecturas, primero respalda la base. Luego puedes eliminar exclusivamente
-- el estado actual simulado. No se ejecuta automaticamente:
--
-- DELETE FROM estado_sensores
-- WHERE dispositivo_id BETWEEN 'SIM_001' AND 'SIM_050';
