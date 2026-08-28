# Tu Caso: CRM + ID Activos

Archivos revisados:

- CRM: `idindust_crm_idindustrial.sql`
- IoT / ID Activos: `idactivo_idindustrialiot (1).sql`

El ZIP directo ya subido a cPanel solo puso codigo. Todavia falta preparar base de datos y configuracion.

## Base que queda activa

Usa como base final:

`idindust_crm_idindustrial`

La base de ID Activos queda como origen/respaldo. No ejecutes las migraciones nuevas ahi, salvo que quieras seguir usando el sistema viejo por separado.

## Orden en phpMyAdmin

1. Haz backup de las dos bases.
2. Abre phpMyAdmin.
3. Selecciona la base `idindust_crm_idindustrial`.
4. Importa `idactivo_idindustrialiot (1).sql` dentro de esa base.
   - Este dump no trae `CREATE DATABASE` ni `USE`, asi que respeta la base seleccionada.
   - Si phpMyAdmin pregunta charset, usa `utf8mb4`.
5. Sin cambiar de base, ejecuta las migraciones nuevas en este orden:
   - `01_crm_portal_sso_clientes_usuarios.sql`
   - `02_eventos_push_shelly_y_cotizaciones.sql`
   - `03_shelly_push_alexa_estado.sql`
   - `04_shelly_webhooks_auditoria.sql`
   - `05_hikvision_tablas.sql`
   - `06_zkteco_tablas.sql`

## Que hace cada base

### `idindust_crm_idindustrial`

Aqui debe quedar todo:

- tablas CRM: `clients`, `users`, `opportunities`, `quotes`, `client_portal_users`
- tablas IoT: `clientes`, `usuarios`, `dispositivos`, `actuadores_shelly`, `estado_sensores`, `estado_shelly`
- tablas nuevas: Hikvision, ZKTeco, webhooks, push, SSO

El archivo `crm/config.php` debe apuntar a esta base.

### Base vieja ID Activos

Usala solo como respaldo/origen despues de migrar. Si los ESP32 o Shelly siguen mandando datos al dominio viejo, los datos se van a seguir guardando alla y no en el CRM nuevo.

## Configuracion

Edita el archivo real:

`crm/config.php`

Conserva estos datos apuntando al CRM:

```php
'host' => 'localhost',
'database' => 'idindust_crm_idindustrial',
'username' => 'USUARIO_MYSQL_DEL_CRM',
'password' => 'PASSWORD_MYSQL_DEL_CRM',
```

Agrega dentro del mismo arreglo el bloque `iot` antes del `];` final. Copia ahi solo tokens/llaves del `config.local.php` viejo:

```php
'iot' => [
  'api_token' => 'API_TOKEN_DEL_CONFIG_LOCAL_VIEJO',
  'setup_token' => 'SETUP_TOKEN_DEL_CONFIG_LOCAL_VIEJO',
  'crm_sso_iot_email' => 'admin@idindustrial.com',

  'shelly_cloud_server' => 'https://TU_SERVER_SHELLY_CLOUD',
  'shelly_cloud_auth_key' => 'TU_AUTH_KEY_SHELLY_CLOUD',
  'shelly_webhook_token' => 'TOKEN_LARGO_WEBHOOK',

  'hikvision_bridge_token' => 'TOKEN_LARGO_INVENTADO',
  'zkteco_bridge_token' => 'TOKEN_LARGO_INVENTADO',

  'alexa_public_base_url' => 'https://idindustrial.com.mx/iot/api',
  'alexa_oauth_client_id' => 'idindustrial-alexa',
  'alexa_oauth_client_secret' => 'SECRETO_LARGO_INVENTADO',
  'alexa_lambda_shared_secret' => 'OTRO_SECRETO_LARGO_INVENTADO',
  'alexa_event_client_id' => '',
  'alexa_event_client_secret' => '',
  'alexa_event_region' => 'NA',

  'retention_raw_days' => 90,
  'retention_hourly_months' => 24,
  'retention_shelly_event_days' => 365,
  'retention_push_days' => 90,
  'retention_hours_per_run' => 48,
  'retention_max_runtime_seconds' => 45,
],
```

No pegues `db_host`, `db_name`, `db_user`, `db_pass` viejos dentro de `iot`.

## URLs de dispositivos

Si el ESP32 todavia apunta al dominio viejo de ID Activos, actualiza firmware para que mande a la nueva ruta publica:

- `https://idindustrial.com.mx/iot/api/guardar_lectura.php`
- `https://idindustrial.com.mx/iot/api/comando_dispositivo.php`

Si el sitio quedo dentro de una subcarpeta, agrega esa subcarpeta en la URL.

## Clientes del portal

El admin puede ver/administrar IoT.

Para que un cliente del portal vea dispositivos, esos dispositivos deben pertenecer al cliente IoT ligado al CRM por `clientes.crm_client_id`. Si un cliente entra y no ve nada, probablemente todavia no tiene dispositivos asignados.
