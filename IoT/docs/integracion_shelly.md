# Integracion Shelly Pro 4PM

## Arquitectura

- El ESP32 detecta el riesgo y envia la lectura a `api/guardar_lectura.php`.
- Al iniciar `ALARMA`, PHP ordena encender los Shelly asociados al ESP32.
- Shelly Cloud controla el canal configurado del Shelly Pro 4PM cuando el modo
  es `CLOUD` o `HIBRIDO` y existen credenciales Cloud.
- Si Cloud no esta configurado, los canales `LOCAL` y `HIBRIDO` intentan usar
  RPC local contra `http://IP_LOCAL/rpc/`. Esto solo funciona cuando el servidor
  PHP esta en la misma red que el Shelly; un cPanel publico normalmente no puede
  alcanzar direcciones `192.168.x.x`, `10.x.x.x` o `172.16-31.x.x`.
- Web y app consultan el estado almacenado en MySQL, sin llamar a Shelly cada 2 segundos.
- Los webhooks reflejan cambios manuales de inmediato. Como respaldo, el cron compara
  el estado de Cloud con MySQL y registra `CAMBIO_CLOUD_ENCENDIDO` o
  `CAMBIO_CLOUD_APAGADO` cuando detecta una conmutacion externa no recibida por webhook.
- Mientras el tablero web esta abierto, `api/shelly_sync_live.php` consulta Cloud como
  maximo una vez cada 5 segundos por cliente. Un bloqueo MySQL comparte esa consulta
  entre pestanas y permite capturar salidas temporizadas de 15 segundos sin exponer
  credenciales Shelly en JavaScript.
- Al silenciar desde web o app, se apagan los actuadores asociados y se conserva la revision fisica pendiente del ESP32.
- El cron actualiza estado y reintenta ordenes que fallaron temporalmente.

## 1. Base de datos

Seleccionar la base de ID Industrial en phpMyAdmin e importar, en este orden:

1. `database/migracion_shelly_mysql57.sql`
2. `database/migracion_shelly_operacion_mysql57.sql`

Ambas migraciones son compatibles con MySQL 5.7 y se pueden ejecutar nuevamente.

## 2. Credenciales de Shelly Cloud

En Shelly Smart Control abrir los ajustes de usuario y la seccion de autorizacion.
Copiar el `Server URI` y generar/copiar el `Authorization cloud key`.

Agregar en `crm/config.php`, dentro de la seccion `iot`. Si despliegas IoT
separado del CRM, usa `api/config.local.php`:

```php
'shelly_cloud_server' => 'https://SERVIDOR_ASIGNADO.shelly.cloud',
'shelly_cloud_auth_key' => 'CLAVE_CLOUD_PRIVADA',
'shelly_webhook_token' => 'TOKEN_ALEATORIO_DE_32_CARACTERES_O_MAS',
```

La clave Cloud nunca debe escribirse en JavaScript, Expo, HTML ni en el firmware.

## 3. Registro del actuador

En `Administrar dispositivos` registrar:

- ID interno: `SHELLY_001`
- Device ID Shelly: `441D64655F78`
- Modelo: `Shelly Pro 4PM`
- Generacion: `GEN2_PLUS`
- Canal: el canal fisico donde esta conectada la sirena, por ejemplo `0`
- Funcion: `SIRENA`
- Categoria si controla la alarma real: `SEGURIDAD`
- Modo: `HIBRIDO` si tambien configuraras Cloud; `LOCAL` solo si el backend PHP
  corre dentro de la misma red local
- ESP32 asociado: `ESP32_001`

Canales del Shelly Pro 4PM: `0` = O1, `1` = O2, `2` = O3, `3` = O4.

En cPanel publico, la IP local sirve para documentar el equipo y configurar
webhooks, pero el control remoto confiable requiere Shelly Cloud. En un backend
local dentro de la misma LAN, la IP local permite probar y controlar por RPC.

## 4. Cron de cPanel

Ejecutar cada minuto, sustituyendo el usuario y la ruta reales de cPanel:

```text
* * * * * /usr/local/bin/php -q /home/USUARIO/public_html/ID-Industrial/api/cron_shelly.php >/dev/null 2>&1
```

El cron sincroniza el estado, respeta el limite de una llamada por segundo y reintenta
ordenes con esperas progresivas. La activacion inicial de una alarma no espera al cron.

## 5. Webhook opcional

El sistema funciona sin webhook. Para reflejar un cambio manual con menor demora se
pueden crear dos acciones web en Shelly para el canal de la sirena:

```text
https://DOMINIO/ID-Industrial/api/shelly_webhook.php?token=TOKEN&device_id=441D64655F78&channel=0&output=1
https://DOMINIO/ID-Industrial/api/shelly_webhook.php?token=TOKEN&device_id=441D64655F78&channel=0&output=0
```

Usar la primera al encender la salida y la segunda al apagarla.

## 6. Prueba controlada

1. Dejar desconectada la sirena real o usar primero una carga de prueba segura.
2. Si no hay Cloud Key en `crm/config.php` o `api/config.local.php`, verificar
   que el PHP este en la misma red que el Shelly antes de probar modo `LOCAL`.
3. En `Administrar dispositivos`, pulsar `Probar` y confirmar estado `ONLINE`.
4. Confirmar que voltaje, potencia y salida correspondan al canal esperado.
5. Pulsar `Encender` y luego `Apagar`.
6. Conectar la sirena mediante un instalador electrico y repetir la prueba.
7. Provocar una alarma de sensor controlada y verificar encendido automatico.
8. Silenciar desde web y app; verificar que Shelly se apague y el ESP32 conserve la revision pendiente.

## Seguridad electrica

El Shelly Pro 4PM trabaja con tension de red. No se conecta a los GPIO ni al protoboard.
La sirena debe conectarse a la salida correcta de acuerdo con su voltaje, corriente y
diagrama del fabricante. La instalacion final debe realizarla personal calificado.
