-- ID Industrial - Fase 1 de administracion y seguridad Shelly
-- Compatible con MySQL 5.7. Ejecutar UNA sola vez sobre la base del proyecto.
-- No usa information_schema porque algunos usuarios de cPanel no tienen acceso.

ALTER TABLE actuadores_shelly
  ADD COLUMN nombre VARCHAR(120) NULL AFTER cliente_id,
  ADD COLUMN categoria ENUM('SEGURIDAD', 'AUTOMATIZACION', 'MONITOREO')
    NOT NULL DEFAULT 'SEGURIDAD' AFTER funcion,
  ADD COLUMN tipo_carga ENUM('RESISTIVA', 'INDUCTIVA', 'ELECTRONICA', 'DESCONOCIDA')
    NOT NULL DEFAULT 'DESCONOCIDA' AFTER categoria,
  ADD COLUMN corriente_max_a DECIMAL(6,2) NULL AFTER tipo_carga,
  ADD COLUMN potencia_max_w DECIMAL(10,2) NULL AFTER corriente_max_a,
  ADD COLUMN tiempo_max_encendido_s INT UNSIGNED NULL AFTER potencia_max_w,
  ADD COLUMN apagado_automatico TINYINT(1) NOT NULL DEFAULT 0 AFTER tiempo_max_encendido_s,
  ADD COLUMN permite_rutinas TINYINT(1) NOT NULL DEFAULT 0 AFTER apagado_automatico,
  ADD COLUMN requiere_confirmacion TINYINT(1) NOT NULL DEFAULT 1 AFTER permite_rutinas,
  ADD COLUMN descripcion VARCHAR(255) NULL AFTER requiere_confirmacion;

UPDATE actuadores_shelly
SET nombre = id
WHERE nombre IS NULL OR TRIM(nombre) = '';

-- Los actuadores ya instalados se consideran de seguridad. Las rutinas quedan
-- deshabilitadas hasta que un administrador clasifique cada carga expresamente.
UPDATE actuadores_shelly
SET categoria = 'SEGURIDAD',
    permite_rutinas = 0,
    requiere_confirmacion = 1;
