# Actualizacion de tiempo real y almacenamiento por alarma

## Cambios integrados

- Una tarjeta independiente para DHT11, MQ-2 y KY-026.
- Graficas Canvas locales sin Chart.js ni CDN.
- Tercera grafica para RSSI, eventos y revisiones de sensores.
- Exportacion CSV del historial.
- Consulta web cada 2 segundos.
- Envio inmediato del ESP32 cuando comienza gas, flama o una alarma general.
- Una sola fila de estado actual por dispositivo.
- Historial nuevo solamente cuando comienza o cambia una alarma.

## Arquitectura de datos

`estado_sensores` siempre contiene la lectura mas reciente. La API actualiza esa
fila cada vez que recibe al ESP32, por lo que no crece indefinidamente.

La tabla no declara una clave foranea contra `dispositivos` para conservar
compatibilidad con bases existentes de MySQL 5.7 que pueden utilizar otra
collation. `guardar_lectura.php` comprueba que el dispositivo exista antes de
actualizar esta tabla.

Los triggers `trg_estado_alarm_insert` y `trg_estado_alarm_update` copian una
lectura a `lecturas_sensores` solamente cuando `estado_general = 'ALARMA'`.
Tambien registran un cambio de origen, por ejemplo cuando primero aparece gas y
despues flama durante la misma alarma.

`trg_historial_solo_alarmas` protege directamente `lecturas_sensores` y rechaza
filas `NORMAL` o `ALERTA`, incluso si por error se publica una API anterior.

Las lecturas historicas que ya existen no se borran.

## Correccion de crecimiento en MySQL 5.7

Si `lecturas_sensores` sigue recibiendo una fila cada 10 segundos, importa:

```text
database/correccion_crecimiento_mysql57.sql
```

Este archivo reactiva `ESP32_001`, recupera la lectura mas reciente en
`estado_sensores`, reinstala los tres triggers e impide nuevas filas normales.

Despues reemplaza `api/guardar_lectura.php` con la version actual. Para eliminar
las filas normales antiguas existe `database/limpieza_normales_opcional.sql`.
Ese segundo archivo es destructivo y solo debe ejecutarse despues de exportar
una copia de seguridad.

## Orden obligatorio en cPanel

1. En phpMyAdmin selecciona `idactivo_idindustrial`.
2. Abre **Importar** y ejecuta:

```text
database/migracion_estado_actual_triggers.sql
```

3. Confirma los triggers desde la pestana SQL:

```sql
SHOW TRIGGERS
WHERE `Table` IN ('estado_sensores', 'lecturas_sensores');
```

4. Sube a `public_html/ID-Industrial/api/`:

```text
backend-cpanel/api/guardar_lectura.php
backend-cpanel/api/ultima_lectura.php
backend-cpanel/api/web_get.php
backend-cpanel/api/resumen.php
backend-cpanel/api/historial.php
backend-cpanel/api/exportar_csv.php
```

5. Sube a `public_html/ID-Industrial/`:

```text
web/index.html
web/css/dashboard.css
web/js/dashboard.js
```

6. No reemplaces `api/config.local.php`.
7. Compila y carga `firmware/esp32_monitor/esp32_monitor.ino`.
8. Recarga el panel con `Ctrl + F5`.

## Comprobacion

El monitor serie debe mostrar `API HTTP: 201`. La respuesta incluye:

```json
{
  "estado_actualizado": true,
  "historial_guardado": false
}
```

En estado normal, `historial_guardado` debe ser `false`. Al comenzar una alarma
debe cambiar a `true`. El panel puede mostrar la lectura actual aunque no se
inserte una fila historica.

La actualizacion web es casi en tiempo real: el ESP32 envia la alarma de
inmediato y el navegador consulta cada 2 segundos. No depende de WebSockets,
Firebase ni procesos persistentes que suelen estar limitados en cPanel.
