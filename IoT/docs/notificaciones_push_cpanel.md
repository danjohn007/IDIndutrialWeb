# Notificaciones push en cPanel

## Flujo

1. El ESP32 crea una alerta en `guardar_lectura.php`.
2. Si la severidad es `CRITICO`, PHP crea una fila por telefono activo en
   `notificaciones_push`.
3. El ESP32 recibe respuesta sin esperar al proveedor de notificaciones.
4. `cron_push.php` toma hasta 50 pendientes y las envia a Expo Push Service.
5. Los fallos temporales se reintentan con espera progresiva, hasta cinco
   intentos. Un token marcado como `DeviceNotRegistered` se desactiva.

Las alertas de precaucion permanecen disponibles en la app, pero no producen
push para evitar ruido y consumo innecesario.

## Instalacion

1. Selecciona `idactivo_idindustrial` en phpMyAdmin.
2. Ejecuta `database/migracion_notificaciones_push_mysql57.sql`.
3. Sube estos archivos conservando sus rutas:

```text
api/guardar_lectura.php
api/cron_push.php
api/mobile/auth/logout.php
api/mobile/push/registrar.php
api/mobile/push/estado.php
api/mobile/push/desactivar.php
```

4. En cPanel abre **Cron Jobs** y crea una tarea cada minuto:

```text
/usr/local/bin/php -q /home/USUARIO_CPANEL/public_html/ID-Industrial/api/cron_push.php
```

Sustituye `USUARIO_CPANEL` por el usuario real. Algunos servidores muestran la
ruta exacta de PHP y del directorio principal en esa misma pantalla.

5. Verifica con soporte o en **Select PHP Version** que `curl` y `openssl`
   esten habilitados y que se permitan conexiones HTTPS salientes.

## Configuracion de Expo

La app necesita un proyecto EAS y un development build para recibir push
remoto. Coloca el Project ID generado por EAS en:

```env
EXPO_PUBLIC_EAS_PROJECT_ID=TU_PROJECT_ID
```

Expo Go puede seguir usandose para revisar pantallas y datos, pero no para
probar la recepcion remota en Android. Las credenciales FCM/APNs se configuran
al preparar el build, no en PHP.

Si el proyecto Expo activa seguridad reforzada para el envio, agrega de forma
opcional en `api/config.local.php`:

```php
'expo_access_token' => 'TOKEN_DE_ACCESO_DE_EXPO',
```

No es el token del ESP32 ni el token de instalacion.

## Latencia y carga

Con un Cron cada minuto, la latencia esperada parte de cero a sesenta segundos
mas el tiempo de FCM/APNs. Cada ejecucion procesa como maximo 50 mensajes y
termina; no deja procesos PHP abiertos. Este modelo es apropiado para un cPanel
compartido y evita consultas continuas desde la app en segundo plano.
