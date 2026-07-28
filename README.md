# ID Industrial Web

Sitio PHP listo para cPanel de ID Industrial. Incluye navegación por secciones, estilos propios, animaciones de scroll/hover, formulario de contacto, SEO base, datos estructurados, `robots.txt`, `sitemap.xml` y `.htaccess`.

## Estructura

- `index.php`: contenido principal y datos de servicios.
- `includes/`: `head.php`, `navbar.php` y `footer.php`.
- `assets/css/styles.css`: diseño responsive y animaciones.
- `assets/js/main.js`: menú móvil, header sticky, reveals y contadores.
- `assets/img/`: imágenes del sitio.
- `.htaccess`: HTTPS, no indexado de directorios, compresión, cache y headers.

## Prueba local

```powershell
C:\xampp\php\php.exe -S 127.0.0.1:8099 -t .
```

Abrir:

```text
http://127.0.0.1:8099/
```

## Subida a cPanel

Subir todos los archivos y carpetas del repositorio al directorio público del dominio, normalmente `public_html`.

Antes de publicar, confirmar estos datos en `index.php`:

- `$contactEmail`
- `$phone`
- `$whatsapp`
- `$siteUrl`

Después de publicar, visitar:

- `https://idindustrial.com.mx/`
- `https://idindustrial.com.mx/robots.txt`
- `https://idindustrial.com.mx/sitemap.xml`
