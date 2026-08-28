# Conector ZKTeco

Este proceso se ejecuta en una PC o mini PC dentro de la misma red del terminal. Consulta equipos compatibles con ZKLib por TCP 4370 y envia a cPanel solamente salud, capacidad y marcajes. No lee ni almacena plantillas biometricas.

## Instalacion

1. Instala Node.js 20 LTS o posterior.
2. Ejecuta `npm install` en esta carpeta.
3. Copia `config.example.json` como `config.json`.
4. Usa en `bridgeToken` el mismo valor de `zkteco_bridge_token` en `api/config.local.php`.
5. Registra el equipo en la web con el mismo `id` del arreglo `devices`.
6. Ejecuta `npm start`.

La PC debe permanecer encendida y tener acceso a la IP del terminal. No abras el puerto 4370 en el router ni lo expongas a Internet.

## Limitaciones

- `PULL_4370` depende de que el modelo y firmware sean compatibles con ZKLib/Standalone SDK.
- Para firmware `PUSH_ADMS`, use ZKBio WDMS/BioTime o un receptor PUSH certificado. El conector TCP no atiende ese protocolo.
- El conector nunca borra marcajes del terminal. La primera ejecucion solo envia eventos de los ultimos minutos configurados.
