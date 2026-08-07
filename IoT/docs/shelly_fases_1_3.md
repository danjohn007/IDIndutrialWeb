# Shelly: fases 1 a 3

## Alcance implementado

- Clasificacion por `SEGURIDAD`, `AUTOMATIZACION` o `MONITOREO`.
- Ficha de carga: tipo, corriente maxima, potencia maxima y descripcion.
- Confirmacion manual para encender y apagado automatico ejecutado por Shelly.
- Permisos: ADMIN administra, ADMIN/OPERADOR controla y LECTURA consulta.
- Deteccion por Device ID mediante Shelly Cloud sin exponer la Cloud Key.
- Detalle movil con estado, mediciones y 50 eventos recientes.
- Alta, edicion, mantenimiento y baja logica desde la app.

## Orden de despliegue en cPanel

1. Importar una sola vez `database/migracion_shelly_seguridad_mysql57.sql` en la base del proyecto.
2. Subir el contenido actualizado de `api/` a la carpeta `api/` del servidor.
3. No subir ni reemplazar `api/config.local.php` si ya contiene las credenciales reales.
4. Confirmar que `shelly_cloud_auth_key` y `shelly_cloud_server` siguen definidos en `config.local.php`.
5. Reiniciar Expo y probar con una cuenta ADMIN.

## Prueba minima

1. Abrir `Dispositivos` y seleccionar `Equipos Shelly`.
2. Pulsar `+`, escribir el Device ID y usar `Detectar con Shelly Cloud`.
3. Clasificar el equipo y definir canal, funcion y limites.
4. Guardar, abrir el detalle y pulsar `Probar conexion`.
5. Encender y apagar una carga de prueba adecuada al Shelly.
6. Confirmar en `eventos_shelly` los eventos de configuracion y salida.

## Nota de seguridad

Los limites de corriente y potencia documentan la carga autorizada. La proteccion
electrica final tambien debe configurarse y dimensionarse en el Shelly, protecciones
termomagneticas, contactor y cableado. El apagado automatico si se envia como
`toggle_after`, por lo que no depende de que la app o el cron permanezcan abiertos.
