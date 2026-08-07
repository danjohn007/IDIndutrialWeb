# Aplicacion movil con backend PHP en cPanel

## Viabilidad

La app es viable sin cambiar el servidor actual. La primera version conectada
ya incluye autenticacion, monitoreo, historial, detalle de incidentes y
actualizacion automatica. Las notificaciones criticas usan una cola MySQL y un
Cron Job de PHP, sin procesos residentes.

La opcion recomendada es:

1. Convertir primero el panel en una PWA instalable.
2. Crear despues una app React Native/Expo que consuma la misma API.
3. Mantener PHP y MySQL como fuente unica de datos.

## Arquitectura compatible con cPanel

- Panel en primer plano: consulta de estado cada 5 segundos.
- Graficas: actualizacion cada 30 a 60 segundos.
- App en segundo plano: notificacion push, no consultas permanentes.
- Historial local: cache SQLite en el telefono.
- Escrituras: reconocer y resolver alertas mediante HTTPS.
- Procesos diferidos: Cron Jobs cortos de PHP.

No se deben depender de WebSockets, procesos PHP permanentes, Redis ni workers
residentes. Un cPanel compartido normalmente termina procesos largos y limita
CPU, conexiones MySQL y frecuencia de Cron.

## Inicio de sesion para app nativa

Las sesiones con cookie funcionan para la web y una PWA del mismo dominio. Una
app nativa necesita endpoints de acceso con tokens de corta duracion y refresh
tokens revocables. Los tokens deben guardarse en Keychain/Keystore, no en texto
plano ni en AsyncStorage.

Antes de iniciar la app nativa conviene crear:

- tabla `app_refresh_tokens`;
- endpoint de login movil;
- renovacion y cierre de sesion;
- token individual por dispositivo ESP32;
- registro de equipos moviles y sus tokens push.

## Notificaciones criticas

Expo Push Service entrega las notificaciones a FCM/APNs aunque la app este
cerrada. PHP registra una notificacion pendiente al crear una alerta critica y
un Cron Job la envia en lotes de hasta 50, con reintentos espaciados.

Esto requiere que cPanel permita conexiones HTTPS salientes y tenga `curl` y
OpenSSL habilitados. La entrega push no es instantanea garantizada: depende del
intervalo del Cron, el proveedor, la cobertura y las restricciones de bateria.

## Alcance sugerido

La primera version debe incluir login, estado general, tarjetas por sensor,
cinco alertas recientes, detalle del incidente, historial filtrado,
reconocer/resolver y push critico. La calibracion del MQ-2 debe permanecer en
web para perfiles ADMIN/OPERADOR hasta contar con auditoria adicional.
