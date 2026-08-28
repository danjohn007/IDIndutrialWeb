# Hotfix Estacion Manual IoT

## Orden recomendado

1. En phpMyAdmin abre la base IoT/ID Activos, donde estan `estado_sensores`, `lecturas_sensores`, `muestras_historicas` y `resumen_horario`.
2. Ejecuta `migraciones-nuevas/07_estacion_manual.sql`.
3. Sube el contenido de `subir-a-public_html-ID-Industrial/` a `public_html/`, reemplazando archivos.
4. En Arduino IDE abre `firmware/esp32_monitor/esp32_monitor.ino` y cargalo al ESP32.
5. Prueba la estacion manual: al bajar la palanca debe aparecer `ALARMA` y una alerta `Estacion manual`.
6. Regresa la estacion manual con llave y mantén presionado el boton fisico de revision por 2 segundos para restablecer.

## Cableado minimo

```text
ESP32 GPIO32 ---- resistencia 2.2k a 4.7k ---- contacto de estacion manual ---- ESP32 GND
```

No conectes 12V, 24V, salida de sirena, NAC o SLC directo al ESP32. Si hay voltaje externo, usa relevador intermedio u optoacoplador.
