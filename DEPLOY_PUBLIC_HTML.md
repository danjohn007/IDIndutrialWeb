# Publicacion en public_html

1. Sube el contenido de este directorio directamente dentro de `public_html`; no crees una carpeta `sistema`.
2. Copia `crm/config.sample.php` como `crm/config.php` y configura base de datos, SMTP y `app_url` con `https://idindustrial.com.mx/crm`.
3. Verifica permisos de escritura para `crm/data/sessions` y `crm/data/request-evidence` (normalmente `750`, `755` o `770`, segun el hosting).
4. En una instalacion nueva importa `crm/database.sql`. En una instalacion existente, conserva la base y deja que el CRM ejecute sus migraciones automaticas.
5. Confirma que Apache tenga `mod_rewrite` habilitado y permita reglas `.htaccess`.
6. Prueba `/`, `/crm/`, `/crm/portal/` y la apertura autenticada de `/crm/evidencias/{id}`.

La regla de compatibilidad incluida redirige de forma permanente las URLs anteriores bajo `/sistema` hacia la raiz publica.
