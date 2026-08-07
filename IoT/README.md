# Proyecto de monitoreo industrial

Prototipo con ESP32-WROOM32 para monitorear temperatura, humedad, gas/humo y
flama. Incluye firmware, API PHP para cPanel/MariaDB, panel web y documentación.

> Este proyecto no sustituye un sistema contra incendios certificado. Antes de
> usarlo en campo se deben calibrar sensores, validar alimentación, probar fallos
> y cumplir la normativa aplicable.

## Estructura

```text
ID-Industrial/
|-- firmware/
|   `-- esp32_monitor/esp32_monitor.ino
|-- backend-cpanel/
|   `-- api/
|       |-- auth/
|       |   |-- login.php
|       |   |-- logout.php
|       |   |-- me.php
|       |   `-- crear_admin_inicial.php
|       |-- auth.php
|       |-- config.php
|       |-- config.local.example.php
|       |-- calibrar_mq2.php
|       |-- cron_retencion.php
|       |-- reporte_pdf.php
|       |-- salud_sistema.php
|       |-- exportar_csv.php
|       |-- guardar_lectura.php
|       |-- ultima_lectura.php
|       |-- historial.php
|       |-- resumen.php
|       `-- web_get.php
|-- web/
|   |-- css/dashboard.css
|   |-- css/login.css
|   |-- js/dashboard.js
|   |-- js/login.js
|   |-- js/alertas.js
|   |-- alertas.html
|   |-- salud.html
|   |-- reportes.html
|   |-- login.html
|   `-- index.html
|-- database/
|   |-- schema.sql
|   |-- migracion_estado_actual_triggers.sql
|   |-- migracion_diagnostico_mq2_mysql57.sql
|   |-- migracion_retencion_mysql57.sql
|   |-- correccion_crecimiento_mysql57.sql
|   |-- limpieza_normales_opcional.sql
|   `-- consultas.sql
`-- docs/
    |-- actualizacion_alertas_graficas.md
    |-- contexto_proyecto.md
    |-- despliegue_cpanel.md
    |-- mapa_conexiones.md
    `-- plan_desarrollo.md
```

## Preparación de cPanel

1. Crea la base y el usuario MySQL desde cPanel.
2. Para una instalacion nueva, importa `database/schema.sql`.
3. Si la base ya existe, ejecuta una sola vez
   `database/migracion_estado_actual_triggers.sql`.
4. Importa `database/migracion_usuarios_sesiones_mysql57.sql`.
5. Importa `database/migracion_diagnostico_mq2_mysql57.sql` y
   `database/migracion_retencion_mysql57.sql`.
6. Registra el primer cliente y dispositivo con los ejemplos editables de
   `database/consultas.sql`.
7. Crea `backend-cpanel/api/config.local.php` a partir de
   `config.local.example.php` y coloca las credenciales reales y un token de al
   menos 32 caracteres.
8. Agrega un `setup_token` distinto de al menos 32 caracteres.
9. Sube los archivos de `backend-cpanel/api/` a
   `public_html/ID-Industrial/api/`.
10. Sube el contenido de `web/` a `public_html/ID-Industrial/`.
11. Abre `login.html` y crea el administrador inicial.
12. Configura el Cron Job de `docs/actualizacion_mq2_retencion.md`.

El archivo `.htaccess` bloquea el acceso web directo a los archivos de
configuración. `config.local.php` está ignorado por Git.

## Configuración del ESP32

Edita al inicio de `firmware/esp32_monitor/esp32_monitor.ino`:

```cpp
const char* ssid = "TU_WIFI";
const char* password = "TU_PASSWORD";
const char* API_URL = "https://idactivos.digital/ID-Industrial/api/guardar_lectura.php";
const char* API_TOKEN = "EL_MISMO_TOKEN_DE_CONFIG_LOCAL";
const char* DISPOSITIVO_ID = "ESP32_001";
```

`DISPOSITIVO_ID` debe existir previamente en la tabla `dispositivos`. El
firmware lee cada 2 segundos y publica el estado cada 10 segundos. Cuando
comienza una alarma, realiza un envio urgente.

`estado_sensores` conserva una sola fila por dispositivo para el panel en
tiempo real. Los triggers guardan en `lecturas_sensores` unicamente el inicio o
cambio de una alarma. Un tercer trigger rechaza cualquier insercion directa con
estado `NORMAL` o `ALERTA`.

## Endpoints

| Método | Ruta | Uso |
|---|---|---|
| `POST` | `/api/guardar_lectura.php` | Guarda lectura; requiere `X-API-TOKEN` |
| `POST` | `/api/auth/login.php` | Inicia una sesión de usuario |
| `POST` | `/api/auth/logout.php` | Cierra la sesión; requiere CSRF |
| `POST` | `/api/auth/cambiar_password.php` | Cambia el password del usuario actual |
| `POST` | `/api/calibrar_mq2.php` | Registra referencia limpia del MQ-2 |
| `GET/POST` | `/api/dispositivos_admin.php` | Administra dispositivos; requiere rol `ADMIN` y CSRF para cambios |
| `GET` | `/api/salud_sistema.php` | Diagnóstico por dispositivo |
| `GET` | `/api/reporte_pdf.php` | Descarga reporte operativo en PDF |
| `GET` | `/api/auth/me.php` | Devuelve usuario, rol y token CSRF |
| `GET` | `/api/incidente.php` | Muestras alrededor de una alerta |
| `GET` | `/api/alertas.php` | Historial filtrado y paginado de alertas |
| `GET` | `/api/exportar_alertas_csv.php` | Exporta alertas con los filtros aplicados |
| `GET` | `/api/ultima_lectura.php` | Estado actual global o por dispositivo |
| `GET` | `/api/historial.php` | Historial filtrado de alarmas |
| `GET` | `/api/resumen.php` | Alarmas y estado actual para graficas |
| `GET` | `/api/web_get.php` | Datos combinados del dashboard |
| `GET` | `/api/exportar_csv.php` | Descarga lecturas para Excel/Sheets |

`cron_retencion.php` no es un endpoint web: se ejecuta únicamente desde el Cron
Job de cPanel.

Parámetros disponibles:

- `ultima_lectura.php?dispositivo_id=ESP32_001`
- `historial.php?dispositivo_id=ESP32_001&limite=100&desde=2026-07-01T00:00:00Z&hasta=2026-07-31T23:59:59Z`
- `resumen.php?dispositivo_id=ESP32_001&horas=24`
- `exportar_csv.php?dispositivo_id=ESP32_001&limite=1000`

Los tiempos se guardan y consultan en UTC. La aplicación cliente debe
presentarlos en la zona horaria del usuario.

Los endpoints de consulta requieren una sesión PHP y filtran los dispositivos
con el `cliente_id` de esa sesión. El ESP32 permanece separado y utiliza
`X-API-TOKEN`.

## Validación rápida

```bash
curl -X POST "https://idactivos.digital/ID-Industrial/api/guardar_lectura.php" \
  -H "Content-Type: application/json" \
  -H "X-API-TOKEN: TU_TOKEN" \
  -d "{\"dispositivo_id\":\"ESP32_001\",\"temperatura\":24.5,\"humedad\":50,\"indice_calor\":24.5,\"gas_raw\":1800,\"gas_umbral\":1600,\"gas_detectado\":1,\"flama_detectada\":0,\"estado_general\":\"ALARMA\",\"tipo_alerta\":\"Gas/Humo\",\"salud_dht\":\"OK\",\"salud_mq2\":\"OK\",\"salud_flama\":\"OK\",\"wifi_rssi\":-55,\"tiempo_encendido\":180,\"contador_alarmas\":1}"
```

Una respuesta correcta devuelve HTTP `201`, confirma el estado actualizado e
indica con `historial_guardado` si los triggers almacenaron una alarma.

Si la respuesta es HTTP `500` con el mensaje `No fue posible conectar con la
base de datos`, la API si esta llegando, pero `backend-cpanel/api/config.local.php`
no coincide con la base, usuario o password de MySQL en cPanel, o el usuario no
tiene permisos sobre la base.
