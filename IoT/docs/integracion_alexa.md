# Integracion Amazon Alexa

## Arquitectura

Alexa no llama directamente al hosting cPanel. Amazon requiere una Smart Home
Skill con una funcion AWS Lambda. La Lambda incluida en
`integrations/alexa-lambda/` reenvia las directivas a cPanel mediante HTTPS y un
secreto compartido.

Flujo:

1. El usuario habilita la Skill ID Industrial en Alexa.
2. Alexa abre `api/alexa/authorize.php` y el usuario inicia sesion.
3. `api/alexa/token.php` entrega tokens OAuth de corta duracion y renovables.
4. Alexa solicita descubrimiento a Lambda.
5. Lambda reenvia la directiva a `api/alexa/smart_home.php`.
6. cPanel valida el token, filtra equipos permitidos y controla Shelly Cloud.
7. Las rutinas compatibles se publican como escenas activables por voz.

Solo se comparten canales Shelly que sean `AUTOMATIZACION`, esten activos,
tengan `Permitir rutinas`, usen modo `CLOUD` o `HIBRIDO` y no soliciten
confirmacion manual. Los equipos de `SEGURIDAD` nunca se publican a Alexa.

## 1. Base de datos

En phpMyAdmin seleccione la base de ID Industrial e importe:

`database/migracion_alexa_mysql57.sql`

Para sincronizar cambios fisicos y apagados automaticos con la app Alexa,
importe tambien:

`database/migracion_alexa_eventos_mysql57.sql`

## 2. Configuracion de cPanel

Agregue a `api/config.local.php`:

```php
'alexa_public_base_url' => 'https://idactivos.digital/ID-Industrial/api',
'alexa_oauth_client_id' => 'idindustrial-alexa',
'alexa_oauth_client_secret' => 'SECRETO_ALEATORIO_1_DE_48_O_MAS',
'alexa_lambda_shared_secret' => 'SECRETO_ALEATORIO_2_DE_48_O_MAS',
'alexa_event_client_id' => 'CLIENT_ID_DE_ALEXA_SKILL_MESSAGING',
'alexa_event_client_secret' => 'CLIENT_SECRET_DE_ALEXA_SKILL_MESSAGING',
'alexa_event_region' => 'NA',
'alexa_oauth_redirect_uris' => [
    'PEGA_AQUI_LA_REDIRECT_URL_QUE_MUESTRE_ALEXA',
],
```

Los dos secretos deben ser distintos. Para generar cada uno en PowerShell:

```powershell
[Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(32))
```

Suba las carpetas `api/alexa/` y `api/lib/`, ademas de los endpoints moviles
actualizados. No suba `integrations/alexa-lambda/` a cPanel.

## 3. Crear la Smart Home Skill

1. Entre a Alexa Developer Console y cree una Skill.
2. Seleccione experiencia `Smart Home`, modelo `Smart Home` y hosting
   `Provision your own`.
3. Copie el Skill ID y guardelo desde la pantalla Rutinas de la app.
4. Use espanol de Mexico como locale principal si el proyecto se utilizara en
   Mexico.

## 4. Crear Lambda

Para espanol de Mexico use AWS Lambda en `us-east-1`.

1. Cree una funcion con runtime Node.js 22 o posterior estable.
2. Si AWS crea `index.mjs`, pegue `integrations/alexa-lambda/index.mjs` y
   despliegue. Para una funcion CommonJS que use `index.js`, conserve la version
   `integrations/alexa-lambda/index.js`.
3. Configure handler `index.handler`, memoria minima 256 MB y timeout 8 segundos.
4. Agregue variables de entorno:

   - `IDIND_ALEXA_HANDLER_URL` =
     `https://idactivos.digital/ID-Industrial/api/alexa/smart_home.php`
   - `IDIND_ALEXA_BRIDGE_TOKEN` = el mismo valor de
     `alexa_lambda_shared_secret`.

5. Agregue trigger `Alexa Smart Home` y capture el Skill ID.
6. Copie el ARN de Lambda en el endpoint predeterminado y Norteamerica de la
   Smart Home Skill.

## 5. Account Linking

En Build, Account Linking configure Authorization Code Grant:

- Authorization URI:
  `https://idactivos.digital/ID-Industrial/api/alexa/authorize.php`
- Access Token URI:
  `https://idactivos.digital/ID-Industrial/api/alexa/token.php`
- Client ID: el valor de `alexa_oauth_client_id`.
- Client Secret: el valor de `alexa_oauth_client_secret`.
- Authentication Scheme: HTTP Basic o credenciales en el cuerpo.
- Scope: `smart_home`.

Alexa mostrara una o varias Redirect URL. Copie exactamente las que utilice a
`alexa_oauth_redirect_uris` en `config.local.php`.

## 6. Sincronizacion proactiva

1. En Alexa Developer Console abra `Permissions`.
2. Active `Send Alexa Events`.
3. En `Alexa Skill Messaging` pulse `Show` y copie Client ID y Client Secret a
   `alexa_event_client_id` y `alexa_event_client_secret`.
4. Importe `database/migracion_alexa_eventos_mysql57.sql`.
5. Suba `api/lib/alexa_events.php`, `api/lib/shelly.php`,
   `api/alexa/smart_home.php` y `api/shelly_webhook.php`.
6. Deshabilite y vuelva a habilitar la Skill para que Amazon envie una nueva
   directiva `AcceptGrant`.
7. Solicite nuevamente el descubrimiento de dispositivos.

Los tokens de Event Gateway se guardan cifrados con AES-256-GCM. El webhook
envia el cambio inmediatamente y `cron_shelly.php` funciona como respaldo.

## 7. Habilitar un Shelly

En ID Industrial edite un canal que controle una carga no critica:

1. Categoria: `Automatizacion`.
2. Modo: `Cloud` o `Hibrido`.
3. Estado: `Activo`.
4. Confirmacion de encendido: desactivada.
5. Permitir rutinas: activado.

No cambie la sirena a Automatizacion. En un Shelly multicanal registre otro
canal como un actuador independiente.

No publique en Alexa escenas que enciendan cerraduras, puertas de garaje,
camaras, sistemas de seguridad ni aparatos de coccion. Amazon restringe esos
tipos de equipo dentro de escenas.

## 8. Prueba

1. Active pruebas de desarrollo en Alexa Developer Console.
2. Habilite la Skill desde la app Alexa y vincule la cuenta ID Industrial.
3. Solicite detectar dispositivos.
4. Pruebe: `Alexa, enciende luz de oficina`.
5. Para una escena pruebe: `Alexa, enciende cierre de oficina`.
6. Consulte `eventos_shelly`: el origen debe ser `ALEXA`.

La integracion implementa Discovery, PowerController, ReportState,
SceneController y ChangeReport proactivo. Para comprobar la sincronizacion,
encienda el canal y espere su apagado automatico; Alexa debe reflejar `Off`
despues de que Shelly invoque el webhook.

## 9. Consistencia del estado

El estado se mantiene por tres caminos complementarios:

1. Las acciones `Encendido` y `Apagado` de Shelly invocan
   `api/shelly_webhook.php`. El endpoint actualiza MySQL y envia el
   `ChangeReport` a Alexa inmediatamente.
2. `cron_shelly.php` consulta Shelly Cloud como respaldo cuando la web y la app
   estan cerradas. El webhook sigue siendo indispensable para cargas que se
   apagan antes del siguiente minuto del cron.
3. Cuando Alexa envia `ReportState`, ID Industrial consulta primero Shelly
   Cloud y despues responde. Por ello una pregunta de estado no depende de un
   valor antiguo almacenado en MySQL.

La pantalla `Equipos Shelly` de la app movil sincroniza mientras permanece
visible, cada 10 segundos. El servidor limita las consultas a una cada 8
segundos por cliente para cuidar cPanel y el limite de Shelly Cloud.

Al actualizar esta integracion no es necesario borrar el dispositivo de Alexa:
deshabilite y habilite la Skill, vincule la cuenta y ejecute `Detectar
dispositivos`. Borre el dispositivo anterior solamente si Alexa conserva un
duplicado o no renueva sus capacidades despues del nuevo descubrimiento.
