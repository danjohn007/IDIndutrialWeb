-- Migracion MySQL/MariaDB: reclasificar prospectos que aun no tienen proyecto iniciado.
-- Usar si existen clientes convertidos por el boton anterior antes de iniciar proyecto.
-- Ejecutar en phpMyAdmin sobre la base del CRM.

UPDATE clients c
SET
  c.lifecycle_stage = 'Prospecto',
  c.segment = CASE WHEN c.segment = 'Industrial' THEN 'Prospecto' ELSE c.segment END,
  c.converted_at = NULL
WHERE c.is_public = 0
  AND NOT EXISTS (
    SELECT 1
    FROM opportunities o
    WHERE o.client_id = c.id
      AND o.status IN ('Proyecto iniciado', 'Proyecto entregado')
  );