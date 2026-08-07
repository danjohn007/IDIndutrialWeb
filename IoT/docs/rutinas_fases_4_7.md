# Rutinas y automatizacion - fases 4 a 7

Esta entrega incorpora rutinas Shelly manuales y programadas en la app movil. El
motor usa PHP, MySQL 5.7 y un cron de cPanel; no necesita un servidor WebSocket ni
un proceso residente.

## Alcance implementado

- Crear y editar rutinas desde la app con rol `ADMIN`.
- Activar o pausar rutinas.
- Ejecutarlas manualmente con rol `ADMIN` u `OPERADOR` y confirmacion previa.
- Programarlas por hora, dias de la semana y zona horaria.
- Incluir de una a cinco acciones Shelly, sin repetir el mismo canal.
- Auditar cada ejecucion y el resultado individual de sus acciones.
- Evitar ejecuciones duplicadas con bloqueo MySQL y una clave unica por minuto.
- Preparar el registro de Amazon Alexa sin guardar credenciales en la app.
- Consultar rutinas, acciones, horarios y resultados desde el tablero web.

Los equipos Shelly clasificados como `SEGURIDAD` no pueden participar en rutinas.
Para habilitar un canal debe estar activo, usar control `CLOUD` o `HIBRIDO`, tener
categoria `AUTOMATIZACION` y la opcion `Permitir rutinas` activada.

La vista web es deliberadamente de solo lectura. Crear, editar, pausar o ejecutar
rutinas sigue requiriendo la app movil y los permisos correspondientes.

## 1. Base de datos

En phpMyAdmin seleccione la base real de ID Industrial y ejecute una sola vez:

`database/migracion_rutinas_mysql57.sql`

Si todavia no se importo la seguridad de Shelly, ejecute antes:

`database/migracion_shelly_seguridad_mysql57.sql`

La migracion crea `rutinas`, `rutina_acciones`, `rutina_ejecuciones` e
`integraciones_domoticas`. Es compatible con MySQL 5.7 y no consulta
`information_schema`.

## 2. Archivos de la API

Suba el contenido actualizado de `api/` a:

`public_html/ID-Industrial/api/`

No reemplace su `config.local.php` por el archivo de ejemplo. Los endpoints nuevos
estan en `api/mobile/`, el motor en `api/lib/rutinas.php` y la tarea programada en
`api/cron_rutinas.php`.

Para habilitar la consulta en el tablero, suba tambien las versiones actualizadas
de `index.html`, `css/dashboard.css` y `js/dashboard.js`.

## 3. Cron de cPanel

Las rutinas manuales funcionan sin cron. Para ejecutar las programadas, cree una
tarea cada minuto. Ajuste el usuario y, si cPanel muestra otra ruta de PHP, use la
que indique su panel:

```cron
* * * * * /usr/local/bin/php -q /home/USUARIO/public_html/ID-Industrial/api/cron_rutinas.php >/dev/null 2>&1
```

`cron_rutinas.php` solo funciona por linea de comandos. Si alguien intenta abrirlo
desde el navegador obtiene `404`; esto es intencional.

## 4. Prueba funcional

1. En la app abra `Equipos` y edite un Shelly.
2. Seleccione categoria `Automatizacion`, control `Cloud` o `Hibrido` y active
   `Permitir rutinas`.
3. Abra `Rutinas`, cree una rutina manual con una accion y guardela.
4. Ejecute la rutina y confirme que el estado fisico y Shelly Cloud cambien.
5. Revise la ejecucion reciente: debe indicar `COMPLETADA`, `PARCIAL` o `FALLIDA`.
6. Cree una rutina por horario para dos o tres minutos despues y compruebe que el
   cron la ejecute una sola vez.

## 5. Permisos

- `ADMIN`: crea, edita, activa, pausa y ejecuta rutinas; prepara integraciones.
- `OPERADOR`: consulta y ejecuta rutinas activas.
- `VISOR`: solo consulta rutinas y resultados.

## 6. Amazon Alexa

El proyecto incluye el servidor OAuth, Discovery, PowerController, ReportState,
SceneController y una Lambda puente. La integracion permanece `PENDIENTE` hasta
que el usuario habilita la Smart Home Skill y completa Account Linking; entonces
cambia a `CONFIGURADA`. Consulte `docs/integracion_alexa.md` para desplegarla en
cPanel, Alexa Developer Console y AWS Lambda.

## 7. Prueba y build movil

Despues de importar SQL y subir la API puede probar localmente:

```powershell
cd mobile
npx expo start -c
```

Para una compilacion interna de Android:

```powershell
npx eas-cli@latest build --platform android --profile preview
```

Para un nuevo build de iOS/TestFlight:

```powershell
npx eas-cli@latest build --platform ios --profile production
npx eas-cli@latest submit --platform ios --latest
```

Los perfiles de `eas.json` ya incluyen `EXPO_PUBLIC_API_BASE_URL` para que el build
se conecte a la API alojada en cPanel.
