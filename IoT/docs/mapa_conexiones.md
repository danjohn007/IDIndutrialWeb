# Mapa de conexiones

## ESP32-WROOM32

| Componente | Terminal | Conexión ESP32 | Nota |
|---|---|---|---|
| DHT11 | `S` | GPIO 17 | Pull-up de 4.7 kΩ a 10 kΩ a 3V3 si es sensor sin módulo |
| DHT11 | `+` | 3V3 | No usar 5 V en la señal |
| DHT11 | `-` | GND | Tierra común |
| KY-026 | `DO` | GPIO 23 | Salida activa en `LOW` |
| KY-026 | `VCC` | 3V3 | Mantiene `DO` compatible con ESP32 |
| KY-026 | `GND` | GND | Tierra común |
| MQ-2 | `VCC` | VIN/5V | El calentador requiere 5 V y corriente suficiente |
| MQ-2 | `GND` | GND | Tierra común |
| MQ-2 | `AO` | Divisor hacia GPIO 34 | Nunca conectar una salida de 5 V directamente |
| LED verde | Ánodo | GPIO 18 mediante 220 Ω | Cátodo a GND |
| LED amarillo | Ánodo | GPIO 19 mediante 220 Ω | Cátodo a GND |
| LED rojo | Ánodo | GPIO 21 mediante 220 Ω | Cátodo a GND |
| Buzzer activo | `+` | GPIO 22 | Usar transistor si consume más de lo permitido por el GPIO |
| Buzzer activo | `-` | GND | Tierra común |
| Botón de revisión | Una terminal | GPIO 25 | Entrada `INPUT_PULLUP`; mantener 2 segundos |
| Botón de revisión | Otra terminal | GND | No conectar a 3V3 ni a 5V |

## Divisor de tensión del MQ-2

Conecta:

```text
MQ-2 AO ---- 10 kΩ ----+---- GPIO 34
                       |
                      20 kΩ
                       |
                      GND
```

Con una salida máxima de 5 V, el nodo del GPIO recibe aproximadamente:

```text
5 V * 20 kΩ / (10 kΩ + 20 kΩ) = 3.33 V
```

Mide el voltaje real con multímetro antes de conectar GPIO 34. El GPIO 34 es
solo entrada y no tiene resistencias pull-up/pull-down internas.

## Alimentación

Todos los módulos deben compartir GND. El calentador del MQ-2 puede consumir más
corriente que los sensores digitales; una fuente 5 V estable es preferible. No
alimente el MQ-2 desde el pin 3V3 del ESP32.

El firmware asume un buzzer activo. Para un buzzer pasivo se debe generar una
frecuencia con PWM/LEDC y utilizar una etapa de manejo adecuada.
