# Diagnostico MQ-2 y conservacion de datos

## Orden de instalacion

1. En phpMyAdmin selecciona la base del proyecto.
2. Importa `database/migracion_diagnostico_mq2_mysql57.sql`.
3. Importa `database/migracion_retencion_mysql57.sql`.
4. Sube los archivos web y API actualizados.
5. Carga el firmware actualizado en la ESP32.
6. Configura el Cron Job descrito abajo.

No ejecutes las migraciones desde la pantalla general de phpMyAdmin. La base
del proyecto debe aparecer seleccionada en la parte superior.

## Diagnostico del MQ-2

El firmware reporta:

- lectura analogica actual entre 0 y 4095 ADC;
- umbral realmente compilado en `GAS_UMBRAL`;
- segundos encendido;
- duracion del calentamiento;
- salud calculada por la ESP32.

El panel muestra el tiempo restante, umbral, ultima calibracion y valor base en
aire limpio. Tambien advierte si existen al menos cinco muestras en diez
minutos y el rango entre la menor y mayor es de dos puntos ADC o menos. Una
lectura exacta de 0 o 4095 despues del calentamiento tambien se marca como
posible atasco.

`Registrar calibracion` no cambia `GAS_UMBRAL` remotamente. Registra la lectura
actual como referencia de mantenimiento. Debe realizarse con el sensor caliente,
sin una alarma de gas activa y en aire limpio. Para cambiar el umbral efectivo,
edita el firmware, vuelve a compilar y carga la ESP32.

## Politica de conservacion

Valores predeterminados de `config.local.php`:

```php
'retention_raw_days' => 90,
'retention_hourly_months' => 24,
'retention_shelly_event_days' => 365,
'retention_push_days' => 90,
'retention_hours_per_run' => 48,
'retention_max_runtime_seconds' => 45,
```

Las muestras por minuto se conservan 90 dias. Las mas antiguas se agrupan por
hora en `resumen_horario` y se eliminan dentro de la misma transaccion. Los
resumenes horarios se conservan 24 meses. La auditoria Shelly se conserva 365
dias y las notificaciones push ya enviadas o descartadas, 90 dias. Las tablas `alertas`,
`alerta_gestiones` y `lecturas_sensores` no se borran con este proceso.

## Cron Job en cPanel

Configura una ejecucion diaria. Sustituye `USUARIO_CPANEL` y verifica en cPanel
la ruta de PHP:

```bash
/usr/local/bin/php -q /home/USUARIO_CPANEL/public_html/ID-Industrial/api/cron_retencion.php
```

El archivo solo acepta ejecucion por CLI; abrirlo en el navegador devuelve
HTTP 403. Cada corrida procesa como maximo 48 horas antiguas o 45 segundos.
Esto limita el uso de CPU en alojamiento compartido y permite ponerse al dia
gradualmente si existe un historial grande.
