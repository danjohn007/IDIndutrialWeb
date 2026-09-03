# Estacion manual de incendio con ESP32

## Objetivo

Leer una estacion manual tipo pull station como entrada digital del ESP32 y guardar el evento en el monitoreo IoT como `Estacion manual`.

La entrada queda como contacto seco activo en LOW:

- Normal: contacto abierto, GPIO25/D25 en HIGH por `INPUT_PULLUP`.
- Activada: la palanca cierra el contacto hacia GND, GPIO25/D25 lee LOW.

## Cableado recomendado

Usa un contacto seco aislado de la estacion manual:

```text
ESP32 GPIO25/D25 ---- resistencia 2.2k a 4.7k ---- terminal NO/COM de estacion manual ---- ESP32 GND
```

El firmware ya usa `pinMode(PIN_ESTACION_MANUAL, INPUT_PULLUP)`, por eso no hace falta llevar 3.3V a la estacion. La resistencia en serie protege el GPIO si por error el pin queda como salida o hay un cableado equivocado de baja energia.

Para cable largo o ambiente ruidoso, agrega cerca del ESP32:

- Pull-up externo de 10k entre GPIO25/D25 y 3.3V.
- Capacitor de 100 nF entre GPIO25/D25 y GND.

## Muy importante

No conectes directo al ESP32 una linea energizada del panel, sirena, NAC, SLC, 12V o 24V. Un GPIO del ESP32 solo soporta 3.3V.

Si la estacion manual ya pertenece a un panel de incendio o trae voltaje externo, usa aislamiento:

- Relevador intermedio con contacto seco hacia el ESP32.
- Optoacoplador/modulo de entrada aislada.

El sistema IoT debe tomarse como monitoreo y automatizacion complementaria. No reemplaza un panel certificado de alarma contra incendio.

## Comportamiento en firmware

- Si se activa la estacion manual, el ESP32 enclava `ALARMA`.
- El buzzer local suena mientras no este silenciado.
- El backend guarda `estacion_manual_activada = 1`.
- Se crea alerta critica `Estacion manual`.
- Si el dispositivo tiene Shelly vinculado, el backend puede encender la sirena como evento automatico.
- Para restablecer, primero regresa la estacion manual a normal con su llave y despues mantén presionado el boton fisico de revision por 2 segundos.

## Pin agregado

```cpp
constexpr uint8_t PIN_ESTACION_MANUAL = 25;
```

Puedes cambiarlo por otro GPIO libre si el gabinete lo requiere. Evita GPIO34 para esta entrada si quieres pull-up interno, porque GPIO34 no tiene pull-up/pull-down interno.
