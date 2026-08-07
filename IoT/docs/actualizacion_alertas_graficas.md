# Actualización de alertas y gráficas

Esta actualización no modifica tablas ni requiere volver a importar
`database/schema.sql`.

## Archivos para reemplazar en cPanel

Sube estos archivos a `public_html/ID-Industrial/api/`:

```text
backend-cpanel/api/guardar_lectura.php
backend-cpanel/api/web_get.php
backend-cpanel/api/resumen.php
backend-cpanel/api/exportar_csv.php
```

Sube estos archivos a `public_html/ID-Industrial/`:

```text
web/index.html
web/css/dashboard.css
web/js/dashboard.js
```

No reemplaces `config.local.php`.

## Firmware

Vuelve a compilar y cargar:

```text
firmware/esp32_monitor/esp32_monitor.ino
```

El firmware ahora:

- Activa alarma y buzzer por gas cuando `gas_raw >= gas_umbral`, incluso si el
  MQ-2 sigue marcado como `CALENTANDO`.
- Envia `gas_detectado`, `gas_umbral`, `temperatura_alerta` y
  `temperatura_alarma`.
- Conserva el estado de salud del MQ-2 para diagnóstico, pero no lo usa como
  candado de seguridad.

## Comportamiento esperado

- Sin detección: ambos recuadros permanecen en estado normal.
- Solo MQ-2: aparece una alerta `Humo/Gas` con el valor ADC.
- Solo KY-026: aparece una alerta `Flama`.
- Ambos: aparecen dos filas de alerta con la misma hora aproximada.
- Temperatura alta: aparece `Temperatura alta` o `Temperatura peligrosa`.
- Fallo de lectura DHT11: aparece `Fallo DHT11`.
- Una detección sostenida no crea una fila nueva cada 10 segundos.
- Después de volver a normal, una detección nueva vuelve a crear la alerta.

La métrica superior distingue entre un dispositivo ESP32 conectado y sus dos
sensores de incendio operativos. DHT11 continúa mostrándose como sensor
ambiental.

## Gráficas

El dashboard consulta `api/resumen.php` y permite periodos de 6 horas, 24 horas,
7 días y 30 días. La primera gráfica muestra temperatura/humedad; la segunda
muestra el valor MQ-2, su umbral y la detección de flama; la tercera muestra
WiFi RSSI y eventos por periodo.

Las gráficas usan Chart.js desde jsDelivr. El navegador que abre el panel debe
tener acceso a Internet para descargar esa biblioteca.

El botón `Descargar CSV` exporta las lecturas recientes para analizarlas en
Excel, Google Sheets o cualquier herramienta de gráficas.
