# Contexto del proyecto

## Objetivo

Construir un prototipo de monitoreo industrial que detecte condiciones de
precaución o alarma, informe localmente mediante LEDs y buzzer, conserve
historial en MariaDB y entregue datos a una web o app móvil.

## Flujo de datos

```text
DHT11 / MQ-2 / KY-026
          |
          v
       ESP32
       |   |
       |   `-- Panel local: http://IP_DEL_ESP32/
       |
       `-- HTTPS POST cada 10 s
                 |
                 v
          API PHP en cPanel
                 |
                 v
              MariaDB
                 |
          +------+------+
          |             |
       Panel web     App móvil
```

## Estados operativos

| Estado | Condición actual del firmware | Salidas |
|---|---|---|
| `NORMAL` | Sin flama, gas bajo el umbral y temperatura menor a 30 °C | LED verde |
| `ALERTA` | Temperatura desde 30 °C o fallo persistente del DHT11 | LED amarillo |
| `ALARMA` | Flama, gas desde 1600 o temperatura desde 35 °C | LED rojo y buzzer |

El MQ-2 se ignora para decisiones de alarma durante sus primeros 120 segundos de
calentamiento. Los umbrales son iniciales y deben calibrarse con mediciones
controladas en el lugar de instalación.

## Salud de sensores

- `DHT11`: una lectura válida produce `OK`; fallos consecutivos pasan por
  `REVISAR` y llegan a `FALLO` después de cinco intentos.
- `MQ-2`: inicia como `CALENTANDO`; después reporta `REVISAR` si el ADC permanece
  cerca de 0 o de saturación durante diez lecturas.
- `KY-026`: el pin digital solo informa detección. No permite distinguir de
  forma fiable entre operación normal y cable de señal desconectado, por lo que
  su salud queda en `OK` en este prototipo.

## Persistencia

Cada lectura conserva variables ambientales, salud de sensores, RSSI WiFi,
tiempo encendido y contador de alarmas. MQ-2 y KY-026 se evalúan por separado:
cuando ambos se activan en una misma lectura se guardan dos alertas, una
`Humo/Gas` y otra `Flama`. Cada origen se registra únicamente al pasar de no
detectado a detectado, evitando duplicados en cada envío.

## Seguridad

- El ESP32 autentica escrituras con `X-API-TOKEN`.
- El token se compara con `hash_equals()` y nunca se guarda en la base de datos.
- Las consultas usan PDO con sentencias preparadas.
- Las credenciales de cPanel viven en `config.local.php`, excluido de Git.
- La conexión del firmware usa HTTPS, pero actualmente emplea `setInsecure()`.
  Para producción debe instalarse la CA del certificado del hosting mediante
  `setCACert()`.
- Los endpoints de consulta requieren autenticación con sesión antes de exponer
  información de clientes en una instalación real.
