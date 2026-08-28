# Conector Hikvision de ID Industrial

Debe ejecutarse en una computadora, mini PC o Raspberry Pi que permanezca encendida en la misma red local que los Hikvision. cPanel no puede consultar directamente direcciones privadas como `192.168.x.x`.

1. Importa `database/migracion_hikvision_mysql57.sql` en phpMyAdmin.
2. Agrega `hikvision_bridge_token` a `api/config.local.php` en cPanel.
3. Registra el Hikvision desde web o movil. El ID debe coincidir con `devices[].id`.
4. Copia `config.example.json` como `config.json` y completa IP, usuario y password locales.
5. Ejecuta `npm install` y despues `npm start -- config.json`.

El conector consulta `/ISAPI/System/deviceInfo` y `/ISAPI/System/status` con autenticacion Digest. Si `listenEvents` esta activo, mantiene abierta `/ISAPI/Event/notification/alertStream` para enviar eventos inmediatamente. La contrasena local nunca se envia a cPanel.

Para operacion continua en Windows, crea una tarea que ejecute el comando al iniciar el equipo. Reserva la IP del Hikvision en el router.
