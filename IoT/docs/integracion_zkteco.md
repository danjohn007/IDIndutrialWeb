# Integracion ZKTeco con ID Industrial

## Alcance

La integracion registra el terminal en la administracion web, supervisa si responde y copia marcajes operativos a MySQL. No descarga plantillas de huella o rostro, no guarda passwords y no borra informacion del equipo.

## Elegir el modo correcto

Revise la etiqueta trasera y `Menu > Informacion del sistema` para obtener modelo, serie y firmware.

- Use `PULL_4370` si el terminal muestra `COMM > Ethernet`, trabaja con el Standalone SDK y acepta conexiones TCP/UDP por el puerto 4370.
- Use `PUSH_ADMS` si existe `COMM > Cloud Server` o `ADMS`. En ese modo el terminal envia datos a ZKBio WDMS/BioTime o a un receptor PUSH compatible.
- Use `WDMS_API` cuando ya exista un servidor ZKBio WDMS y se disponga de su API.

El conector incluido en este repositorio implementa `PULL_4370`. Los otros dos modos quedan registrados para una integracion posterior con el middleware oficial.

## Instalacion en cPanel

1. En phpMyAdmin seleccione la base de ID Industrial.
2. Importe `database/migracion_zkteco_mysql57.sql`.
3. Suba a `api/` los archivos `lib/zkteco.php`, `zkteco_ingest.php`, `dispositivos_admin.php`, `config.php` y su version actual de la web.
4. Genere un token aleatorio:

```powershell
$bytes = New-Object byte[] 32
$rng = [Security.Cryptography.RandomNumberGenerator]::Create()
$rng.GetBytes($bytes)
-join ($bytes | ForEach-Object { $_.ToString('x2') })
```

5. Agregue el resultado en `crm/config.php`, seccion `iot`. Si despliega IoT
   separado del CRM, use `api/config.local.php`:

```php
'zkteco_bridge_token' => 'TOKEN_GENERADO_DE_64_CARACTERES',
```

## Preparar un terminal TCP 4370

1. Conecte el terminal por Ethernet a la misma red que la PC del conector.
2. En `Menu > COMM > Ethernet`, configure IP, mascara y puerta de enlace.
3. Reserve esa IP en el router para que no cambie.
4. Confirme que el puerto de comunicacion sea `4370`.
5. No abra el puerto 4370 en el router. Solo debe ser accesible desde la LAN.
6. Registre el equipo en `Administrar dispositivos > Nuevo dispositivo > ZKTeco`.

El ID elegido, por ejemplo `ZK_001`, debe coincidir exactamente con el ID del archivo del conector.

## Instalar el conector local

En una PC que permanezca encendida dentro de la red:

```powershell
cd integrations\zkteco-bridge
npm install
Copy-Item config.example.json config.json
notepad config.json
npm start -- config.json
```

Configure `apiBaseUrl`, el token y la IP del terminal. En menos de tres minutos debe aparecer `ONLINE` en la web. Para operacion continua cree una tarea de Windows que inicie `node index.mjs config.json` al arrancar la PC.

## Equipos PUSH/ADMS

En firmware PUSH, ZKTeco indica que la direccion y puerto del servidor se definen en `COMM > Cloud Server/ADMS`. La opcion mas estable es instalar ZKBio WDMS o BioTime en un servidor accesible para el terminal y consumir su API. No apunte el terminal directamente a `zkteco_ingest.php`: ese endpoint recibe JSON del conector de ID Industrial, no implementa el protocolo ADMS propietario.

Fuentes del fabricante: [integracion PUSH SDK](https://www.zkteco.com/en/IntegrationfmA), [ZKBio WDMS](https://www.zkteco.com/en/ZKBio_WDMS/ZKBio_WDMS) y [especificaciones F18](https://www.zkteco.com/en/FSeries/F18).
