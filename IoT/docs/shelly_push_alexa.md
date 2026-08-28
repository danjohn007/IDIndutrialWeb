# Shelly: webhooks, avisos externos y sincronizacion con Alexa

## Que resuelve

- Recibe en segundos los cambios realizados desde Shelly Smart Control, el boton fisico o un temporizador interno.
- Actualiza el estado de ID Industrial sin esperar a que el usuario pulse `Probar`.
- Envia push por cambios externos y evita tratar como externo un comando reciente de ID Industrial.
- Informa a Alexa mediante `ChangeReport` y conserva `ReportState` como consulta de respaldo.
- Registra cada entrega de webhook, aunque no haya cambiado el valor almacenado.

## Archivos que deben subirse

- `api/shelly_webhook.php`
- `api/shelly_webhooks_admin.php`
- `api/lib/shelly_webhooks.php`
- `api/cron_retencion.php`
- `dispositivos.html`
- `js/dispositivos.js`
- `css/usuarios.css`

Si el servidor usa la carpeta fuente `backend-cpanel/api`, sube su contenido como la carpeta publica `api`; no subas ambas rutas al mismo destino.

## Base de datos MySQL 5.7

1. Haz un respaldo y selecciona en phpMyAdmin la base de ID Industrial.
2. Ejecuta las migraciones operativas que todavia no tenga el servidor.
3. Ejecuta `database/migracion_shelly_webhooks_mysql57.sql`.
4. Confirma que exista `entregas_webhook_shelly`.

La tabla de auditoria no lleva claves foraneas para evitar problemas de compatibilidad con instalaciones cPanel existentes.

## Configuracion en cPanel

`api/config.local.php` debe contener:

```php
'shelly_webhook_token' => 'TOKEN_ALEATORIO_DE_32_CARACTERES_O_MAS',
'alexa_public_base_url' => 'https://idactivos.digital/ID-Industrial/api',
```

El token nunca se captura en la web ni debe publicarse. El panel administrativo lo usa solamente para generar las URLs protegidas.

Conserva `cron_shelly.php` cada minuto como respaldo. No hace falta otro cron para recibir webhooks. `cron_retencion.php` tambien elimina auditorias vencidas usando `retention_shelly_event_days`.

## Instalacion guiada en Shelly Smart Control

1. En ID Industrial entra como administrador a `Administrar dispositivos`.
2. En el Shelly correcto pulsa `Webhooks`.
3. Pulsa `Probar receptor`. Debe confirmar token, Device ID y canal sin cambiar la salida fisica.
4. Copia la `URL de encendido`.
5. En Shelly Smart Control abre el dispositivo, entra a `Acciones` o `Webhooks` y crea una accion siempre activa.
6. Selecciona la condicion `Encender`, intervalo de repeticion `0` y pega la URL.
7. Repite el proceso con la condicion `Apagar` y la URL de apagado.
8. Verifica que ambas acciones queden habilitadas.

No reutilices `shelly_cloud_auth_key` como parametro `token`. Las URLs deben
copiarse completas desde el dialogo `Webhooks`; el receptor valida exclusivamente
`shelly_webhook_token`. Si detecta la clave Cloud en una accion antigua, la entrega
aparecera como `ERROR` sin guardar la clave recibida.

El panel tambien genera dos cargas `Webhook.Create` para instalacion RPC local. Deben enviarse mediante POST a `http://IP_DEL_SHELLY/rpc` desde una computadora conectada a la misma LAN. El hosting cPanel no puede acceder a una IP privada como `192.168.x.x`.

## Prueba completa

1. Enciende el canal desde Shelly Smart Control.
2. En menos de unos segundos abre `Webhooks` en ID Industrial y pulsa `Actualizar`.
3. `Encendido` debe mostrar `PROCESADA` y la tabla debe registrar la entrega.
4. Apaga el canal o espera su temporizador de 15/20 segundos.
5. Confirma una entrega `APAGADO` y que web, movil y Alexa terminen en `OFF`.
6. Enciende desde ID Industrial. La entrega puede aparecer como confirmacion, pero no debe generar una alerta externa duplicada.

Consulta SQL de diagnostico:

```sql
SELECT id, actuador_id, evento, metodo, estado, cambio_estado,
       cambio_externo, alexa_enviados, ultimo_error, recibido_en
FROM entregas_webhook_shelly
ORDER BY id DESC
LIMIT 20;
```

Si solo aparecen eventos `CLOUD` en `eventos_shelly` y no hay entregas en la tabla anterior, Shelly no esta llamando la URL. Revisa que la accion este habilitada, que corresponda al canal correcto y que el certificado HTTPS del dominio sea valido.

## Requisitos de Alexa

El canal debe estar en categoria `AUTOMATIZACION`, permitir rutinas, no exigir confirmacion manual y usar modo `CLOUD` o `HIBRIDO`. La cuenta debe estar vinculada y `alexa_event_tokens` debe contener un token vigente.

El apagado automatico se ejecuta dentro del Shelly mediante `toggle_after`. El webhook informa el apagado a ID Industrial y Alexa. Si falla, `cron_shelly.php` corrige el estado en su siguiente ejecucion, por lo que puede existir una demora aproximada de hasta un minuto.
