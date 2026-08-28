# Integracion Hikvision con ID Industrial

## Arquitectura

Hikvision expone ISAPI por HTTP(S) dentro de la red local. ID Industrial usa un conector local para consultar el equipo con autenticacion Digest y enviar a cPanel solamente estado, identidad y eventos. No se abren puertos del Hikvision en el router y su password no se almacena en MySQL.

## Configuracion en cPanel

1. Importa `database/migracion_hikvision_mysql57.sql` en la base de ID Industrial.
2. Sube el contenido actualizado de `api/`, incluyendo `lib/hikvision.php`, `hikvision_ingest.php` y los endpoints de `api/mobile/`.
3. Genera un token con PowerShell:

```powershell
$bytes = New-Object byte[] 32
$rng = [Security.Cryptography.RandomNumberGenerator]::Create()
$rng.GetBytes($bytes)
-join ($bytes | ForEach-Object { $_.ToString('x2') })
```

4. Agrega el resultado en `crm/config.php`, seccion `iot`. Si despliegas IoT
   separado del CRM, usa `api/config.local.php`:

```php
'hikvision_bridge_token' => 'TOKEN_GENERADO_DE_64_CARACTERES',
```

## Preparacion del Hikvision

1. Asigna o reserva una IP fija en el router.
2. Confirma que ISAPI este habilitado. La ubicacion del ajuste depende del modelo y firmware.
3. Crea un usuario local dedicado a integracion con permisos de lectura y eventos. Evita usar la cuenta administradora si el modelo permite permisos limitados.
4. Desde una computadora de la misma red comprueba:

```text
http://IP_DEL_HIKVISION/ISAPI/System/deviceInfo
```

Debe pedir credenciales y devolver XML. No publiques esta IP ni hagas port forwarding.

## Registro y conector

1. En web: `Administrar dispositivos > Nuevo dispositivo > Hikvision`.
2. En movil: `Equipos > Hikvision > +`.
3. Usa un ID como `HIK_001`.
4. En `integrations/hikvision-bridge`, copia `config.example.json` como `config.json`.
5. El valor `devices[].id` debe ser exactamente `HIK_001` y `bridgeToken` debe coincidir con cPanel.
6. Instala y ejecuta:

```powershell
cd integrations\hikvision-bridge
npm install
npm start -- config.json
```

En menos de un minuto el equipo debe aparecer `ONLINE`. Para dejarlo permanente, configura el comando en el Programador de tareas de Windows o como servicio en Linux.

Antes de iniciar el conector puedes comprobar el receptor desde PowerShell, sustituyendo el token y usando un ID ya registrado:

```powershell
$headers = @{ 'X-HIKVISION-BRIDGE-TOKEN' = 'TU_TOKEN' }
$body = @{ equipo_id = 'HIK_001'; online = $true; estado = @{ modelo = 'Prueba ISAPI' } } | ConvertTo-Json
Invoke-RestMethod -Method Post -Uri 'https://idactivos.digital/ID-Industrial/api/hikvision_ingest.php' -Headers $headers -ContentType 'application/json' -Body $body
```

## Alcance de seguridad

La primera version permite inventario, salud y eventos ISAPI. No abre puertas, reinicia grabadores ni cambia configuraciones. Esas funciones deben habilitarse despues de validar el modelo exacto, permisos, respuesta de emergencia y auditoria requerida.

## Lista de cierre

La integracion Hikvision queda terminada cuando se cumplen estos puntos:

1. `migracion_hikvision_mysql57.sql` fue importada sin errores.
2. `hikvision_ingest.php` responde `401` al abrirlo sin token; esto confirma que existe y esta protegido.
3. El equipo real responde XML en `/ISAPI/System/deviceInfo` desde la PC local.
4. El usuario ISAPI dedicado tiene lectura de estado y eventos.
5. `config.json` contiene el ID registrado, IP, usuario, password y el token correcto.
6. `npm start -- config.json` muestra `ONLINE` y luego `escuchando eventos ISAPI`.
7. La administracion web muestra una fecha reciente y no `DESACTUALIZADO`.
8. El conector se ejecuta al iniciar Windows o Linux.

No se requiere cron de cPanel para Hikvision: el proceso local mantiene la comunicacion. Si el unico equipo fisico disponible es el terminal ZKTeco mostrado anteriormente, esta lista debe reservarse hasta disponer de un Hikvision real; ZKTeco no habla ISAPI.
