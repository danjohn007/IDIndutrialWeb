# Historial completo de alertas

## Cambios

- El dashboard conserva solamente las cinco alertas mas recientes.
- La pagina `alertas.html` muestra el historial completo.
- Filtros por dispositivo, sensor, severidad, estado y fechas.
- Paginacion de 25, 50 o 100 registros.
- Exportacion CSV con los filtros activos.
- Acceso al detalle de incidente desde cada resultado.
- Todas las consultas se limitan al `cliente_id` de la sesion.

## Archivos para cPanel

Sube a `public_html/ID-Industrial/`:

```text
index.html
alertas.html
js/dashboard.js
js/alertas.js
css/alertas.css
```

Sube a `public_html/ID-Industrial/api/`:

```text
alertas.php
exportar_alertas_csv.php
lib/alertas_filtros.php
lib/.htaccess
```

No requiere migracion de base de datos y no reemplaza `config.local.php`.

## Rendimiento

La API usa un maximo de 100 filas por pagina y la exportacion se limita a 5000
alertas. Los valores de sensor, severidad y estado se validan contra listas
permitidas antes de construir la consulta.
