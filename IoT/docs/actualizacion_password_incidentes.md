# Password y detalle de incidentes

## Cambios

- Password minimo de 8 caracteres.
- Se mantiene el requisito de mayuscula, minuscula y numero.
- Cambio de password desde el boton de llave del dashboard.
- La sesion se cierra despues del cambio para validar el nuevo password.
- Cada fila de alerta abre un detalle de incidente.
- El detalle muestra 15 minutos antes y 15 minutos despues de la alerta.
- Incluye temperatura, humedad, valor MQ-2, umbral y deteccion de flama.
- La linea roja vertical identifica el momento exacto de la alerta.

## Archivos para cPanel

Sube a `public_html/ID-Industrial/`:

```text
index.html
login.html
css/dashboard.css
js/dashboard.js
```

Sube a `public_html/ID-Industrial/api/`:

```text
incidente.php
auth/crear_admin_inicial.php
auth/cambiar_password.php
```

No reemplaces `api/config.local.php`.

## Cambiar el password

1. Inicia sesion.
2. Pulsa el boton de llave del encabezado.
3. Escribe el password actual.
4. Escribe y confirma el nuevo password.
5. Inicia sesion nuevamente.

El nuevo password debe contener al menos 8 caracteres, una mayuscula, una
minuscula y un numero.

## Detalle de incidente

Selecciona una fila en **Alertas recientes**. La API verifica que la alerta
pertenezca al `cliente_id` de la sesion y consulta `muestras_historicas` dentro
de la ventana del incidente.

Las alertas recientes pueden no tener todavia todos los minutos posteriores.
La informacion se completa conforme el ESP32 envia nuevas lecturas.
