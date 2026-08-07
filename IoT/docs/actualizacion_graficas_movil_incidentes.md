# Graficas en vivo y detalle de incidentes

Esta actualizacion no requiere una migracion SQL.

## Archivos que deben subirse a cPanel

Sube o reemplaza estos archivos dentro de `public_html/ID-Industrial/`:

- `api/mobile/graficas.php`
- `api/mobile/incidente.php`
- `api/incidente.php`
- `web/index.html`
- `web/js/dashboard.js`
- `web/css/dashboard.css`

Si el contenido de `web/` esta directamente en `public_html/ID-Industrial/`, sube
`index.html`, `js/dashboard.js` y `css/dashboard.css` sin crear otra carpeta `web`.

## Como funcionan las graficas moviles

1. Al abrir `En vivo`, la app consulta una sola vez los ultimos 30 minutos.
2. Mientras la pantalla esta visible, agrega la lectura actual cada 5 segundos.
3. Al pasar la app a segundo plano, las consultas se detienen.
4. El historial se vuelve a consultar al cambiar de dispositivo o tocar recargar.
5. La interfaz limita los puntos dibujados para mantener fluidez en el telefono.

Este flujo ofrece una sensacion de tiempo real sin solicitar todo el historial a
MySQL cada 5 segundos.

## Interpretacion de incidentes

El detalle ahora separa:

- `Causa registrada`: condicion que creo la alerta.
- `Lectura del evento`: valores capturados cuando se genero.
- `Estado actual`: ultima condicion conocida del dispositivo.
- `Lectura historica seleccionada`: otro punto antes o despues del evento.

Por ejemplo, una alerta de flama puede indicar `Flama detectada` en la lectura del
evento y `Sin deteccion` en el estado actual. Esto significa que la condicion ya
termino; no significa que la alerta original sea incorrecta.
