# Alertas de conectividad

El sistema genera una alerta de tipo `Dispositivo sin conexion` cuando un ESP32
activo deja de reportar durante mas de dos minutos.

## Comportamiento

- Se crea una sola alerta abierta por dispositivo.
- La severidad es `PRECAUCION`; una falla de red no se presenta como incendio.
- Los usuarios moviles activos reciben una notificacion push.
- La alerta aparece en el tablero, el historial web y la app movil.
- Cuando llega una nueva lectura, la alerta se resuelve automaticamente.
- Los dispositivos recien registrados tienen cinco minutos de gracia.

## Cron de cPanel

Sube `api/cron_conectividad.php` y los archivos nuevos de `api/lib/`. Ejecuta el
detector cada minuto con una ruta adaptada a tu cuenta:

```text
* * * * * /usr/local/bin/php -q /home/TU_USUARIO/public_html/ID-Industrial/api/cron_conectividad.php >> /home/TU_USUARIO/cron_conectividad.log 2>&1
```

En la interfaz de Cron Jobs de cPanel normalmente solo se pega el comando a
partir de `/usr/local/bin/php` y se selecciona `Una vez por minuto`.

`cron_conectividad.php` solo funciona por CLI. Si se abre desde el navegador,
responde 404 de forma intencional.

## Prueba

1. Confirma que el ESP32 aparezca `ONLINE`.
2. Apaga o desconecta el ESP32.
3. Espera entre dos y tres minutos.
4. Revisa la alerta nueva en web y movil.
5. Enciende el ESP32 y espera su siguiente envio.
6. La alerta debe cambiar a `RESUELTA` con responsable `Sistema`.

El detector cubre los ESP32 de la tabla `dispositivos`. Los Shelly se mantienen
como actuadores independientes y su estado se administra en el modulo Shelly.
