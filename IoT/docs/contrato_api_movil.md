# Contrato API movil

Base de produccion:

```text
https://idactivos.digital/ID-Industrial/api/mobile/
```

Todas las respuestas usan JSON y UTC. Los endpoints protegidos reciben:

```http
Authorization: Bearer TOKEN_DE_SESION
```

El token dura 30 dias, se almacena como hash en MySQL y puede revocarse al
cerrar sesion. La app conserva el valor original en el almacenamiento seguro
del telefono.

## Endpoints iniciales

### POST `auth/login.php`

```json
{
  "email": "admin@idindustrial.com",
  "password": "password",
  "dispositivo": "Android de Jonathan"
}
```

Devuelve el usuario, rol, token Bearer y fecha de expiracion.

### GET `auth/me.php`

Valida el token y devuelve el usuario activo. Responde `401` si el token fue
revocado, vencio o el usuario dejo de estar activo.

### POST `auth/logout.php`

Revoca solamente la sesion del telefono actual.

### GET `resumen.php`

Devuelve un paquete compacto para la portada:

- Estado general y KPI.
- Hasta 20 dispositivos activos con su ultima lectura.
- Las cinco alertas mas recientes.
- Una revision para detectar cambios sin descargar historiales.

### GET `alertas.php`

Devuelve el historial paginado de alertas. Acepta los filtros opcionales
`dispositivo_id`, `sensor` (`GAS`, `FLAMA`, `TEMPERATURA`, `DHT`),
`severidad` (`NORMAL`, `PRECAUCION`, `CRITICO`), `estado`
(`NUEVA`, `RECONOCIDA`, `RESUELTA`), `pagina` y `por_pagina`.

La app solicita 20 eventos por pagina y permite cargar paginas adicionales.
No genera reportes ni exportaciones desde el telefono.

### GET `dispositivos.php`

Devuelve la salud de todos los ESP32 activos del cliente autenticado:

- conexion `ONLINE` u `OFFLINE`;
- lectura actual de DHT11, MQ-2 y KY-026;
- umbral, calentamiento y ultima calibracion del MQ-2;
- ultima lectura y ultima alerta del dispositivo.

### GET `incidente.php`

Recibe `alerta_id` y opcionalmente `minutos` (5 a 60). Devuelve la alerta,
su ultima gestion y las muestras historicas anteriores y posteriores. La app
usa una ventana de 15 minutos en cada direccion.

### POST `atender_alerta.php`

Disponible solamente para `ADMIN` y `OPERADOR`. Recibe JSON con `alerta_id`,
`accion` (`RECONOCER` o `RESOLVER`) y `comentario` opcional. El responsable
se toma del token autenticado y no puede ser enviado por el telefono.

### POST `push/registrar.php`

Registra el Expo Push Token del telefono autenticado. Recibe
`expo_push_token`, `plataforma` (`ANDROID` o `IOS`) y
`nombre_dispositivo`. El token queda asociado tanto al usuario como a su
sesion movil actual.

### GET `push/estado.php`

Devuelve cuantos telefonos activos tiene el usuario para notificaciones y sus
metadatos. Nunca devuelve el Expo Push Token.

### POST `push/desactivar.php`

Desactiva las notificaciones del telefono cuyo `expo_push_token` se envia en
el cuerpo. `auth/logout.php` tambien desactiva todos los tokens vinculados a
la sesion que se esta cerrando.

## Respuestas de error

```json
{
  "ok": false,
  "error": "Descripcion segura para el usuario"
}
```

Codigos principales: `401` autenticacion, `403` permisos, `422` validacion,
`429` bloqueo temporal y `500/503` configuracion o servidor.

## Instalacion en cPanel

1. Ejecuta `database/migracion_tokens_moviles_mysql57.sql` en phpMyAdmin con la
   base correcta seleccionada.
2. Ejecuta `database/migracion_notificaciones_push_mysql57.sql`.
3. Sube `mobile_auth.php`, `guardar_lectura.php`, `cron_push.php` y la carpeta
   `mobile/` dentro de `api/`.
4. Configura el Cron Job descrito en `docs/notificaciones_push_cpanel.md`.
5. Conserva el `config.local.php` existente. El token de acceso de Expo es
   opcional y solo se usa cuando la seguridad reforzada del proyecto Expo esta
   habilitada.
