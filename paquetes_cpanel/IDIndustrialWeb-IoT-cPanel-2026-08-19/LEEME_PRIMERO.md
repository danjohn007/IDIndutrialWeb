# Paquete cPanel - ID Industrial + IoT

Fecha: 2026-08-19

## Que zip usar

- `SUBIR_DIRECTO_public_html-ID-Industrial-2026-08-19.zip`: subelo a la carpeta publica donde vive el sitio y extraelo ahi. Este es el zip para cPanel.
- `IDIndustrialWeb-IoT-cPanel-2026-08-19_COMPLETO.zip`: respaldo local con instrucciones, migraciones nuevas y referencia de configuracion. No lo dejes publicado en el servidor.

## Antes de subir

1. Haz backup de archivos actuales en cPanel.
2. Haz backup de la base MySQL desde phpMyAdmin.
3. Confirma donde vive tu instalacion: por ejemplo `public_html/ID-Industrial/` o directamente `public_html/`.

## Subida a cPanel

1. En cPanel > File Manager abre la carpeta donde vive el sitio.
2. Sube `SUBIR_DIRECTO_public_html-ID-Industrial-2026-08-19.zip`.
3. Extrae el zip en esa misma carpeta.
4. Permite sobrescribir archivos.
5. Borra el zip del servidor despues de extraer.

El zip directo agrega/sobrescribe estas rutas:

- `.htaccess`
- `router.php`
- `crm/cliente.php`
- `crm/index.php`
- `crm/iot-sso.php`
- `crm/lib/database.php`
- `crm/config.example.php`
- `IoT/api/`
- `IoT/web/`

No incluye `crm/config.php` real ni claves privadas.

## Migraciones

Las migraciones nuevas estan en `migraciones-nuevas/`. Importalas desde phpMyAdmin seleccionando la base completa, no una tabla individual.

En la integracion actual el CRM y IoT quedan unificados. Para tu instalacion, las migraciones nuevas se ejecutan en la base del CRM: `idindust_crm_idindustrial`, despues de importar ahi las tablas/datos IoT que venian de ID Activos.

## Configuracion

No copies completo el `config.local.php` viejo. En tu caso, conserva en `crm/config.php` la base del CRM:

- `host`
- `database`
- `username`
- `password`

Del `config.local.php` viejo de ID Activos copia solamente claves/tokens al bloque `iot`:

- `api_token` -> `iot.api_token`
- `setup_token` -> `iot.setup_token`
- `shelly_cloud_server` -> `iot.shelly_cloud_server`
- `shelly_cloud_auth_key` -> `iot.shelly_cloud_auth_key`
- `shelly_webhook_token` -> `iot.shelly_webhook_token`

Las claves nuevas van tambien dentro de `iot`:

- `hikvision_bridge_token`
- `zkteco_bridge_token`
- `retention_shelly_event_days`
- `retention_push_days`
