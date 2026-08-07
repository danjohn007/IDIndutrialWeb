# Despliegue en cPanel

## Datos confirmados

```text
Dominio: idactivos.digital
MySQL host: localhost
Base: idactivo_idindustrial
Usuario MySQL: idactivo_idindustrial_admin
Ruta web: public_html/ID-Industrial/
URL web: https://idactivos.digital/ID-Industrial/
```

## 1. Privilegios MySQL

En cPanel abre **MySQL Databases**. En **Add User To Database**, asigna
`idactivo_idindustrial_admin` a `idactivo_idindustrial` y marca
**ALL PRIVILEGES**.

## 2. Crear tablas

En una instalacion nueva carga `database/schema.sql`.

Si la base ya existe, conserva sus tablas e importa:

```text
database/migracion_usuarios_sesiones_mysql57.sql
```

Después abre **SQL** y ejecuta el bloque inicial de `database/consultas.sql` que
registra el cliente y `ESP32_001`. El bloque se puede repetir sin duplicar
registros.

## 3. Subir archivos

En File Manager crea:

```text
public_html/ID-Industrial/
|-- index.html
|-- login.html
|-- css/
|   |-- dashboard.css
|   `-- login.css
|-- js/
|   |-- dashboard.js
|   `-- login.js
`-- api/
    |-- .htaccess
    |-- auth.php
    |-- auth/
    |   |-- login.php
    |   |-- logout.php
    |   |-- me.php
    |   `-- crear_admin_inicial.php
    |-- atender_alerta.php
    |-- config.php
    |-- exportar_csv.php
    |-- guardar_lectura.php
    |-- ultima_lectura.php
    |-- historial.php
    |-- resumen.php
    `-- web_get.php
```

Activa **Show Hidden Files** para comprobar que `.htaccess` fue subido.
La API no necesita un archivo privado dentro de `api/`; carga `crm/config.php`.

## 4. Configuración privada compartida

Copia `crm/config.example.php` como `public_html/IoT/crm/config.php` y reemplaza los marcadores. Este es el único archivo privado: CRM toma de él MySQL y SMTP; la API toma MySQL y la sección `iot`.

Usa permisos `0600` o `0640`. `crm/config.php` está excluido de Git y su acceso web está bloqueado por `.htaccess`. El archivo anterior `IoT/api/config.local.php` ya no se utiliza.
## 5. Crear administrador inicial

Abre:

```text
https://idactivos.digital/ID-Industrial/login.html
```

Selecciona **Crear administrador inicial**. Usa el correo que ya existe en
`clientes`, un password de al menos 12 caracteres y el mismo `setup_token` de la seccion `iot` en `crm/config.php`. Esta operacion se desactiva al existir el primer usuario.

## 6. Firmware

En `firmware/esp32_monitor/esp32_monitor.ino` configura WiFi y verifica:

```cpp
const char* API_URL =
  "https://idactivos.digital/ID-Industrial/api/guardar_lectura.php";
const char* API_TOKEN = "EL_MISMO_TOKEN_DE_CONFIG_LOCAL";
const char* DISPOSITIVO_ID = "ESP32_001";
```

## 7. Pruebas

Sin sesion, los endpoints de consulta deben responder HTTP `401`. Inicia sesion
desde `login.html` y despues abre:

```text
https://idactivos.digital/ID-Industrial/api/ultima_lectura.php
```

Debe responder:

```json
{"ok":true,"data":null}
```

Si `ESP32_001` ya está registrado, este endpoint debe responder JSON:

```text
https://idactivos.digital/ID-Industrial/api/web_get.php
```

La URL final del panel es:

```text
https://idactivos.digital/ID-Industrial/
```

La URL directa de `crm/config.php` debe devolver `403 Forbidden`. Una visita
normal a `guardar_lectura.php` debe devolver `405 Metodo no permitido`, porque
ese endpoint solo acepta `POST`.
