-- OPCIONAL Y DESTRUCTIVO.
-- Haz una copia de seguridad antes de ejecutar este archivo.
-- Elimina el historial NORMAL/ALERTA guardado antes de la correccion.

SELECT estado_general, COUNT(*) AS filas
FROM lecturas_sensores
GROUP BY estado_general;

DELETE FROM lecturas_sensores
WHERE estado_general <> 'ALARMA';

OPTIMIZE TABLE lecturas_sensores;

SELECT estado_general, COUNT(*) AS filas
FROM lecturas_sensores
GROUP BY estado_general;
