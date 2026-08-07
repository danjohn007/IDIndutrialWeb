# Salud del sistema y reportes PDF

## Funciones agregadas

- `salud.html` concentra el diagnostico de todos los dispositivos.
- Distingue conexion offline, salud de DHT11/MQ-2/KY-026, calibraciones MQ-2
  pendientes y lecturas ADC posiblemente atascadas.
- `reportes.html` permite elegir dispositivo y un periodo de hasta 90 dias.
- El PDF incluye el logotipo de ID Industrial, un resumen ejecutivo mas compacto,
  graficas de alertas por severidad y hallazgos de salud por sensor, salud por
  dispositivo y hasta 30 alertas recientes del periodo.
- El documento usa una paleta sobria de negro, grises y ambar para que pueda
  imprimirse o consultarse sin introducir colores ajenos al panel.
- El CSV permanece disponible para analisis en Excel o Google Sheets.

## Archivos para cPanel

Sube a la raiz web, junto a `index.html`:

```text
salud.html
reportes.html
css/salud.css
css/reportes.css
js/salud.js
js/reportes.js
index.html
css/dashboard.css
```

Sube a `api/`:

```text
salud_sistema.php
reporte_pdf.php
lib/reporte_pdf.php
```

Tambien conserva `assets/logo-id-industrial.png` en la raiz web. No requiere
migracion de base de datos y no reemplaza `api/config.local.php`.

## Criterios de salud

- `OFFLINE`: la ultima conexion del dispositivo tiene mas de dos minutos.
- `Calibracion MQ-2 requerida`: no existe una calibracion o tiene mas de 90
  dias.
- `Lectura atascada`: el MQ-2 permanece en 0 o 4095, o sus ultimas cinco
  muestras dentro de diez minutos varian dos ADC o menos.

El reporte PDF usa esos mismos criterios y siempre aplica el `cliente_id` de la
sesion autenticada.
