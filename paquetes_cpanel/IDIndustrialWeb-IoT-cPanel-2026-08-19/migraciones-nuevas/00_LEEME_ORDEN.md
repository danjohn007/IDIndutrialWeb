# Migraciones Nuevas

En phpMyAdmin selecciona primero la base de datos completa, no una tabla individual. Luego entra a la pestana SQL o Importar y ejecuta estos archivos en orden.

Para una instalacion unificada, usa la base donde viven las tablas IoT. Para tu caso con dos bases, ejecuta cada migracion en la base que tiene las tablas indicadas abajo.

Importante para `07_estacion_manual.sql`: va en la base IoT/ID Activos, donde existen `estado_sensores`, `lecturas_sensores`, `muestras_historicas` y `resumen_horario`.

## Orden recomendado

1. `01_crm_portal_sso_clientes_usuarios.sql`
   - Toca: `clientes`, `usuarios`.
   - Agrega: `clientes.crm_client_id`, `usuarios.crm_portal_user_id`.
   - Sirve para que el portal cliente entre a IoT y vea solo sus dispositivos.

2. `02_eventos_push_shelly_y_cotizaciones.sql`
   - Toca: `notificaciones_push`, `actuadores_shelly`.
   - Agrega soporte para notificaciones `SHELLY` y conserva `COTIZACION`.

3. `03_shelly_push_alexa_estado.sql`
   - Toca: `estado_shelly`, `actuadores_shelly`.
   - Agrega `apagado_programado_en` y `notificar_cambios_externos`.

4. `04_shelly_webhooks_auditoria.sql`
   - Crea: `entregas_webhook_shelly`.
   - Sirve para diagnosticar webhooks Shelly.

5. `05_hikvision_tablas.sql`
   - Crea: `equipos_hikvision`, `estado_hikvision`, `eventos_hikvision`.

6. `06_zkteco_tablas.sql`
   - Crea: `equipos_zkteco`, `estado_zkteco`, `eventos_zkteco`.

7. `07_estacion_manual.sql`
   - Toca: `estado_sensores`, `lecturas_sensores`, `muestras_historicas`, `resumen_horario`.
   - Agrega: `estacion_manual_activada` y `detecciones_estacion_manual`.
   - Ejecutalo en la base IoT/ID Activos, no en la base CRM si estan separadas.

## Prerrequisitos

Estos archivos asumen que ya existen las tablas base IoT como `clientes`, `usuarios`, `dispositivos`, `actuadores_shelly`, `estado_shelly`, `notificaciones_push` y `moviles_push`.

Si una migracion falla porque falta una tabla base, primero importa las migraciones viejas correspondientes desde `IoT/database/` del repo, o importa `IoT/database/schema.sql` solo en una base nueva.
