# Control de alarma y boton fisico

## Objetivo

La alarma queda enclavada cuando el ESP32 confirma gas/humo, flama o temperatura
peligrosa. Que la lectura vuelva a un nivel seguro no borra la alarma: debe
completarse una revision fisica.

## Conexion del boton

Usa un pulsador normalmente abierto.

| Terminal | Conexion |
|---|---|
| Una terminal | GPIO25 / D25 |
| Otra terminal | GND |

El firmware usa `INPUT_PULLUP`. No se necesita resistencia externa y el boton
no debe conectarse a 3V3 ni a 5V.

En pulsadores de cuatro patas, las dos patas del mismo lado suelen estar unidas.
Usa una pata de cada lado opuesto y comprueba continuidad antes de energizar.

## Comportamiento

1. Un peligro nuevo enclava la alarma, enciende el LED rojo y activa el buzzer
   intermitente.
2. ADMIN u OPERADOR pueden solicitar silencio desde el detalle de una alerta en
   la app o desde la tarjeta del dispositivo en la web.
3. El ESP32 consulta la orden cada 2 segundos mientras la alarma esta enclavada.
4. Mantener presionado el boton fisico durante 2 segundos tambien silencia el
   buzzer si el peligro continua.
5. Si el peligro ya desaparecio, mantener presionado el boton fisico durante 2
   segundos restablece la alarma aunque el buzzer no se haya silenciado primero
   desde la app.
6. Una alarma silenciada desde la app mantiene el LED rojo parpadeando y muestra revision
   fisica pendiente.
7. Si existe peligro, el boton puede silenciar, pero nunca declara el sistema
   normal.
8. Una condicion critica nueva vuelve a activar el buzzer.

## MQ-2

En este montaje, `0 ADC` en aire limpio es normal. El firmware no lo considera
una falla. La advertencia `REVISAR` se conserva para saturacion sostenida cerca
de `4095 ADC`.

El calentamiento inicial sigue mostrandose durante 120 segundos. La deteccion de
gas se habilita despues del calentamiento y utiliza:

- Umbral de activacion: 1600 ADC.
- Umbral de liberacion: 1500 ADC.
- Dos lecturas consecutivas para confirmar o limpiar.

## Despliegue

1. Ejecutar `database/migracion_control_alarma_mysql57.sql` una sola vez en la
   base seleccionada en phpMyAdmin.
2. Subir los archivos modificados de `api/` al directorio `api/` del servidor.
   Esto incluye `silenciar_alarma.php` para la web y
   `mobile/silenciar_alarma.php` para la app.
3. Subir `js/dashboard.js` y `css/dashboard.css`.
4. Generar una nueva compilacion de la app movil.
5. Cargar `firmware/esp32_monitor/esp32_monitor.ino` en el ESP32.
6. Conectar el boton con el ESP32 apagado y energizar despues de revisar GND.

## Pruebas recomendadas

- Peligro activo: el buzzer debe sonar y el LED rojo quedar fijo.
- Silencio desde app: el buzzer debe parar y el LED rojo parpadear.
- Silencio con boton sin Internet: debe producir el mismo estado.
- Boton con peligro activo: no debe restablecer el estado normal.
- Boton con lecturas seguras: debe completar la revision y volver a normal.
- Nueva deteccion despues de silenciar: debe rearmar el buzzer.
- ESP32 offline: la app no debe dejar una orden de silencio atrasada.
