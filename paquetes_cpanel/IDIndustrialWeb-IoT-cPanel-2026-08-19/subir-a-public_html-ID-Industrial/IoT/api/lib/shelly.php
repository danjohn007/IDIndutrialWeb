<?php
declare(strict_types=1);

require_once __DIR__ . '/alexa_events.php';
require_once __DIR__ . '/shelly_notificaciones.php';

final class IdindShellyException extends RuntimeException
{
}

function idindShellyCredenciales(array $config): array
{
    $server = trim((string) (
        getenv('IDIND_SHELLY_CLOUD_SERVER')
        ?: ($config['shelly_cloud_server'] ?? '')
    ));
    $authKey = trim((string) (
        getenv('IDIND_SHELLY_CLOUD_AUTH_KEY')
        ?: ($config['shelly_cloud_auth_key'] ?? '')
    ));

    if ($server === '' || $authKey === '') {
        throw new IdindShellyException(
            'Shelly Cloud no esta configurado en crm/config.php (seccion iot) ni api/config.local.php'
        );
    }
    if (!preg_match('#^https://#i', $server)) {
        $server = 'https://' . $server;
    }
    $partes = parse_url($server);
    if (
        !is_array($partes)
        || strtolower((string) ($partes['scheme'] ?? '')) !== 'https'
        || empty($partes['host'])
    ) {
        throw new IdindShellyException('El Server URI de Shelly Cloud no es valido');
    }

    $base = 'https://' . $partes['host'];
    if (isset($partes['port'])) {
        $base .= ':' . (int) $partes['port'];
    }
    $ruta = rtrim((string) ($partes['path'] ?? ''), '/');
    if ($ruta !== '') {
        $base .= $ruta;
    }

    return ['server' => $base, 'auth_key' => $authKey];
}

function idindShellyConfigurado(array $config): bool
{
    try {
        idindShellyCredenciales($config);
        return true;
    } catch (Throwable $error) {
        return false;
    }
}

function idindShellyWebhookToken(array $config): string
{
    $token = trim((string) (
        getenv('IDIND_SHELLY_WEBHOOK_TOKEN')
        ?: ($config['shelly_webhook_token'] ?? '')
    ));
    if (strlen($token) < 32) {
        throw new IdindShellyException(
            'Configura shelly_webhook_token con al menos 32 caracteres'
        );
    }
    return $token;
}

function idindShellyJsonSeguro($valor): ?string
{
    if ($valor === null) {
        return null;
    }
    $json = json_encode(
        $valor,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    return $json === false ? null : $json;
}

function idindShellyPeticionCloud(
    PDO $pdo,
    array $config,
    int $clienteId,
    string $ruta,
    array $cuerpo
): array {
    if (!function_exists('curl_init')) {
        throw new IdindShellyException('La extension cURL no esta habilitada en PHP');
    }

    $credenciales = idindShellyCredenciales($config);
    $lockName = 'idind_shelly_' . max(0, $clienteId);
    $stmtLock = $pdo->prepare('SELECT GET_LOCK(:nombre, 10)');
    $stmtLock->execute(['nombre' => $lockName]);
    if ((int) $stmtLock->fetchColumn() !== 1) {
        throw new IdindShellyException('Shelly Cloud esta ocupado; intenta nuevamente');
    }

    try {
        $url = rtrim($credenciales['server'], '/')
            . '/' . ltrim($ruta, '/')
            . (strpos($ruta, '?') === false ? '?' : '&')
            . 'auth_key=' . rawurlencode($credenciales['auth_key']);
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode(
                $cuerpo,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'ID-Industrial/1.0 ShellyIntegration',
        ]);
        $respuesta = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $errorCurl = curl_error($curl);
        curl_close($curl);

        // Shelly Cloud limita las llamadas a una por segundo. Mantener el lock
        // durante esta pausa tambien serializa peticiones PHP concurrentes.
        usleep(1050000);

        if ($respuesta === false) {
            throw new IdindShellyException(
                $errorCurl !== '' ? 'Error de red Shelly: ' . $errorCurl : 'Shelly Cloud no respondio'
            );
        }
        $json = json_decode((string) $respuesta, true);
        if ($status < 200 || $status >= 300) {
            $detalle = is_array($json)
                ? (string) ($json['error'] ?? $json['message'] ?? '')
                : '';
            throw new IdindShellyException(
                trim('Shelly Cloud respondio HTTP ' . $status . ' ' . $detalle)
            );
        }
        if (!is_array($json)) {
            return ['http_status' => $status, 'respuesta' => (string) $respuesta];
        }
        return $json;
    } finally {
        try {
            $stmtUnlock = $pdo->prepare('SELECT RELEASE_LOCK(:nombre)');
            $stmtUnlock->execute(['nombre' => $lockName]);
        } catch (Throwable $error) {
            error_log('ID Industrial Shelly unlock: ' . $error->getMessage());
        }
    }
}

function idindShellyListaRespuesta(array $respuesta): array
{
    if (isset($respuesta['data']) && is_array($respuesta['data'])) {
        $respuesta = $respuesta['data'];
    }
    if (isset($respuesta['id']) && is_string($respuesta['id'])) {
        return [$respuesta];
    }
    $lista = [];
    foreach ($respuesta as $clave => $item) {
        if (!is_array($item)) {
            continue;
        }
        if (!isset($item['id']) && is_string($clave)) {
            $item['id'] = $clave;
        }
        if (isset($item['id'])) {
            $lista[] = $item;
        }
    }
    return $lista;
}

function idindShellyNumero($valor): ?float
{
    return is_numeric($valor) && is_finite((float) $valor) ? (float) $valor : null;
}

function idindShellyEstadoCanal(array $dispositivo, int $canal): array
{
    $online = in_array($dispositivo['online'] ?? null, [1, '1', true, 'true'], true);
    $status = is_array($dispositivo['status'] ?? null) ? $dispositivo['status'] : [];
    $switch = [];
    foreach (['switch:' . $canal, 'relay:' . $canal] as $clave) {
        if (is_array($status[$clave] ?? null)) {
            $switch = $status[$clave];
            break;
        }
    }
    if ($switch === [] && is_array($status['relays'][$canal] ?? null)) {
        $switch = $status['relays'][$canal];
    }
    $salida = $switch['output'] ?? $switch['ison'] ?? null;
    if ($salida !== null) {
        $salida = in_array($salida, [1, '1', true, 'true', 'on'], true);
    }
    $temperatura = null;
    if (is_array($switch['temperature'] ?? null)) {
        $temperatura = idindShellyNumero(
            $switch['temperature']['tC'] ?? $switch['temperature']['celsius'] ?? null
        );
    }
    return [
        'online' => $online,
        'salida_encendida' => $salida,
        'potencia_w' => idindShellyNumero($switch['apower'] ?? $switch['power'] ?? null),
        'voltaje_v' => idindShellyNumero($switch['voltage'] ?? null),
        'corriente_a' => idindShellyNumero($switch['current'] ?? null),
        'temperatura_c' => $temperatura,
        'errores' => is_array($switch['errors'] ?? null) ? $switch['errors'] : [],
        'raw' => $switch,
    ];
}

function idindShellyBaseLocal(array $actuador): string
{
    $host = trim((string) ($actuador['ip_local'] ?? ''));
    if ($host === '') {
        throw new IdindShellyException('Configura la IP local del Shelly para usar RPC local');
    }
    if (!preg_match('/^[A-Za-z0-9.:-]+$/', $host)) {
        throw new IdindShellyException('La IP local del Shelly no es valida');
    }
    return 'http://' . trim($host, '/') . '/rpc/';
}

function idindShellyPeticionLocal(array $actuador, string $metodo, array $parametros = []): array
{
    if (!function_exists('curl_init')) {
        throw new IdindShellyException('La extension cURL no esta habilitada en PHP');
    }
    if (!preg_match('/^[A-Za-z0-9.]+$/', $metodo)) {
        throw new IdindShellyException('Metodo RPC local no valido');
    }

    $url = idindShellyBaseLocal($actuador) . $metodo;
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode(
            $parametros,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 4,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_USERAGENT => 'ID-Industrial/1.0 ShellyLocalRpc',
    ]);
    $respuesta = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $errorCurl = curl_error($curl);
    curl_close($curl);

    if ($respuesta === false) {
        throw new IdindShellyException(
            $errorCurl !== '' ? 'Error RPC local Shelly: ' . $errorCurl : 'Shelly local no respondio'
        );
    }
    $json = json_decode((string) $respuesta, true);
    if ($status < 200 || $status >= 300) {
        $detalle = is_array($json)
            ? (string) ($json['error'] ?? $json['message'] ?? '')
            : '';
        throw new IdindShellyException(
            trim('Shelly local respondio HTTP ' . $status . ' ' . $detalle)
        );
    }
    if (!is_array($json)) {
        throw new IdindShellyException('Shelly local devolvio una respuesta no JSON');
    }
    return $json;
}

function idindShellyEstadoCanalLocal(array $status): array
{
    $temperatura = null;
    if (is_array($status['temperature'] ?? null)) {
        $temperatura = idindShellyNumero(
            $status['temperature']['tC'] ?? $status['temperature']['celsius'] ?? null
        );
    } else {
        $temperatura = idindShellyNumero($status['temperature'] ?? null);
    }
    $salida = $status['output'] ?? null;
    if ($salida !== null) {
        $salida = in_array($salida, [1, '1', true, 'true', 'on'], true);
    }
    return [
        'online' => true,
        'salida_encendida' => $salida,
        'potencia_w' => idindShellyNumero($status['apower'] ?? $status['power'] ?? null),
        'voltaje_v' => idindShellyNumero($status['voltage'] ?? null),
        'corriente_a' => idindShellyNumero($status['current'] ?? null),
        'temperatura_c' => $temperatura,
        'errores' => is_array($status['errors'] ?? null) ? $status['errors'] : [],
        'raw' => $status,
    ];
}

function idindShellyLeerEstadoLocal(array $actuador): array
{
    $status = idindShellyPeticionLocal(
        $actuador,
        'Switch.GetStatus',
        ['id' => (int) $actuador['canal']]
    );
    return idindShellyEstadoCanalLocal($status);
}

function idindShellySincronizarLocal(
    PDO $pdo,
    int $clienteId,
    ?string $actuadorId = null
): array {
    $sql =
        'SELECT id, cliente_id, ubicacion, dispositivo_vinculado_id,
                shelly_device_id, modelo, generacion, ip_local, canal,
                funcion, modo_control, estado
         FROM actuadores_shelly
         WHERE cliente_id = :cliente_id AND estado <> \'Inactivo\'
           AND modo_control IN (\'LOCAL\', \'HIBRIDO\')';
    $parametros = ['cliente_id' => $clienteId];
    if ($actuadorId !== null) {
        $sql .= ' AND id = :id';
        $parametros['id'] = $actuadorId;
    }
    $sql .= ' ORDER BY ubicacion, shelly_device_id, canal, id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametros);

    $consultados = 0;
    $online = 0;
    $offline = 0;
    $errores = [];
    foreach ($stmt->fetchAll() as $actuador) {
        $consultados++;
        try {
            $estado = idindShellyLeerEstadoLocal($actuador);
            idindShellyGuardarEstado($pdo, $actuador, $estado, 'LOCAL');
            !empty($estado['online']) ? $online++ : $offline++;
        } catch (Throwable $error) {
            $offline++;
            idindShellyGuardarError($pdo, $actuador, $error->getMessage(), 'LOCAL');
            $errores[] = $actuador['id'] . ': ' . $error->getMessage();
        }
    }
    if ($consultados === 0 && $actuadorId !== null) {
        throw new IdindShellyException('Actuador Shelly local no encontrado');
    }
    return compact('consultados', 'online', 'offline', 'errores');
}

function idindShellyGuardarEstado(
    PDO $pdo,
    array $actuador,
    array $estado,
    string $fuente = 'CLOUD',
    ?string $error = null,
    ?array $config = null,
    ?string $causaAlexa = null
): array {
    $online = !empty($estado['online']) ? 1 : 0;
    $salida = $estado['salida_encendida'] ?? null;
    $salidaDb = $salida === null ? null : ($salida ? 1 : 0);
    $salidaAnterior = null;
    $teniaSalidaAnterior = false;
    if ($config !== null && $salidaDb !== null) {
        $stmtAnterior = $pdo->prepare(
            'SELECT salida_encendida FROM estado_shelly WHERE actuador_id = :id LIMIT 1'
        );
        $stmtAnterior->execute(['id' => (string) $actuador['id']]);
        $valorAnterior = $stmtAnterior->fetchColumn();
        if ($valorAnterior !== false && $valorAnterior !== null) {
            $salidaAnterior = (int) $valorAnterior;
            $teniaSalidaAnterior = true;
        }
    }
    $stmt = $pdo->prepare(
        'INSERT INTO estado_shelly (
            actuador_id, online, salida_encendida, potencia_w, voltaje_v,
            corriente_a, temperatura_c, errores_json, fuente, ultimo_error,
            sincronizado_en, apagado_programado_en
         ) VALUES (
            :actuador_id, :online, :salida, :potencia, :voltaje,
            :corriente, :temperatura, :errores, :fuente, :ultimo_error,
            UTC_TIMESTAMP(), NULL
         )
         ON DUPLICATE KEY UPDATE
            online = VALUES(online), salida_encendida = VALUES(salida_encendida),
            potencia_w = COALESCE(VALUES(potencia_w), potencia_w),
            voltaje_v = COALESCE(VALUES(voltaje_v), voltaje_v),
            corriente_a = COALESCE(VALUES(corriente_a), corriente_a),
            temperatura_c = COALESCE(VALUES(temperatura_c), temperatura_c),
            errores_json = VALUES(errores_json), fuente = VALUES(fuente),
            ultimo_error = VALUES(ultimo_error), sincronizado_en = UTC_TIMESTAMP(),
            apagado_programado_en = CASE
                WHEN VALUES(salida_encendida) = 0 THEN NULL
                ELSE apagado_programado_en
            END'
    );
    $stmt->execute([
        'actuador_id' => (string) $actuador['id'],
        'online' => $online,
        'salida' => $salidaDb,
        'potencia' => $estado['potencia_w'] ?? null,
        'voltaje' => $estado['voltaje_v'] ?? null,
        'corriente' => $estado['corriente_a'] ?? null,
        'temperatura' => $estado['temperatura_c'] ?? null,
        'errores' => idindShellyJsonSeguro($estado['errores'] ?? []),
        'fuente' => $fuente,
        'ultimo_error' => $error === null ? null : substr($error, 0, 500),
    ]);

    $stmtActuador = $pdo->prepare(
        'UPDATE actuadores_shelly
         SET estado_salida = COALESCE(:salida, estado_salida),
             ultima_conexion = CASE WHEN :online = 1 THEN UTC_TIMESTAMP() ELSE ultima_conexion END
         WHERE id = :id'
    );
    $stmtActuador->execute([
        'salida' => $salidaDb,
        'online' => $online,
        'id' => (string) $actuador['id'],
    ]);

    $alexa = ['enviados' => 0, 'errores' => []];
    $cambioSalida = $salidaDb !== null
        && $teniaSalidaAnterior
        && $salidaAnterior !== $salidaDb;
    if (
        $config !== null
        && $causaAlexa !== null
        && $cambioSalida
    ) {
        $alexa = idindAlexaNotificarCambioShelly(
            $pdo,
            $config,
            (string) $actuador['id'],
            $salidaDb === 1,
            $online === 1,
            $causaAlexa
        );
    }
    return [
        'cambio_salida' => $cambioSalida,
        'salida_anterior' => $teniaSalidaAnterior ? $salidaAnterior : null,
        'salida_actual' => $salidaDb,
        'alexa' => $alexa,
    ];
}

function idindShellyEsConfirmacionComandoReciente(
    PDO $pdo,
    string $actuadorId,
    bool $encendida
): bool {
    $stmt = $pdo->prepare(
        "SELECT id FROM comandos_shelly
         WHERE actuador_id = :actuador_id
           AND accion = :accion
           AND creado_en >= UTC_TIMESTAMP() - INTERVAL 15 SECOND
           AND estado IN ('PENDIENTE', 'PROCESANDO', 'APLICADO', 'REINTENTAR')
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([
        'actuador_id' => $actuadorId,
        'accion' => $encendida ? 'ENCENDER' : 'APAGAR',
    ]);
    return $stmt->fetchColumn() !== false;
}

function idindShellyGuardarError(
    PDO $pdo,
    array $actuador,
    string $mensaje,
    string $fuente = 'CLOUD'
): void
{
    $fuente = in_array($fuente, ['CLOUD', 'WEBHOOK', 'LOCAL'], true) ? $fuente : 'CLOUD';
    $stmt = $pdo->prepare(
        'INSERT INTO estado_shelly (
            actuador_id, online, fuente, ultimo_error, sincronizado_en
         ) VALUES (:id, 0, :fuente, :error, UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE
            fuente = VALUES(fuente),
            ultimo_error = VALUES(ultimo_error), sincronizado_en = UTC_TIMESTAMP()'
    );
    $stmt->execute([
        'id' => (string) $actuador['id'],
        'fuente' => $fuente,
        'error' => substr($mensaje, 0, 500),
    ]);
}

function idindShellyActuadoresCliente(PDO $pdo, int $clienteId, ?string $actuadorId = null): array
{
    $sql =
        'SELECT id, cliente_id, ubicacion, dispositivo_vinculado_id,
                shelly_device_id, modelo, generacion, ip_local, canal,
                funcion, modo_control, estado
         FROM actuadores_shelly
         WHERE cliente_id = :cliente_id AND estado <> \'Inactivo\'
           AND modo_control IN (\'CLOUD\', \'HIBRIDO\')';
    $parametros = ['cliente_id' => $clienteId];
    if ($actuadorId !== null) {
        $sql .= ' AND id = :id';
        $parametros['id'] = $actuadorId;
    }
    $sql .= ' ORDER BY shelly_device_id, canal, id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametros);
    return $stmt->fetchAll();
}

function idindShellySincronizar(
    PDO $pdo,
    array $config,
    int $clienteId,
    ?string $actuadorId = null
): array {
    $actuadores = idindShellyActuadoresCliente($pdo, $clienteId, $actuadorId);
    if ($actuadores === []) {
        if ($actuadorId !== null) {
            throw new IdindShellyException('Actuador Shelly no encontrado');
        }
        return ['consultados' => 0, 'online' => 0, 'offline' => 0, 'errores' => []];
    }

    $idsActuadores = array_values(array_unique(array_map(
        static function (array $actuador): string {
            return (string) $actuador['id'];
        },
        $actuadores
    )));
    $estadosPrevios = [];
    $marcadores = implode(',', array_fill(0, count($idsActuadores), '?'));
    $stmtPrevios = $pdo->prepare(
        "SELECT actuador_id, salida_encendida
         FROM estado_shelly
         WHERE actuador_id IN ({$marcadores})"
    );
    $stmtPrevios->execute($idsActuadores);
    foreach ($stmtPrevios->fetchAll() as $estadoPrevio) {
        $estadosPrevios[(string) $estadoPrevio['actuador_id']] =
            $estadoPrevio['salida_encendida'] === null
                ? null
                : (int) $estadoPrevio['salida_encendida'];
    }

    $porDeviceId = [];
    foreach ($actuadores as $actuador) {
        $porDeviceId[(string) $actuador['shelly_device_id']][] = $actuador;
    }
    $consultados = 0;
    $online = 0;
    $offline = 0;
    $errores = [];

    foreach (array_chunk(array_keys($porDeviceId), 10) as $ids) {
        try {
            $respuesta = idindShellyPeticionCloud(
                $pdo,
                $config,
                $clienteId,
                '/v2/devices/api/get',
                ['ids' => array_values($ids), 'select' => ['status', 'settings']]
            );
            $items = [];
            foreach (idindShellyListaRespuesta($respuesta) as $item) {
                $items[strtolower((string) $item['id'])] = $item;
            }
            foreach ($ids as $deviceId) {
                $item = $items[strtolower($deviceId)] ?? null;
                foreach ($porDeviceId[$deviceId] as $actuador) {
                    $consultados++;
                    if (!$item) {
                        $mensaje = 'Shelly Cloud no devolvio el Device ID solicitado';
                        idindShellyGuardarError($pdo, $actuador, $mensaje);
                        $errores[] = $actuador['id'] . ': ' . $mensaje;
                        continue;
                    }
                    $estado = idindShellyEstadoCanal($item, (int) $actuador['canal']);
                    $idActuador = (string) $actuador['id'];
                    $salidaAnterior = $estadosPrevios[$idActuador] ?? null;
                    $salidaActual = $estado['salida_encendida'] ?? null;
                    idindShellyGuardarEstado(
                        $pdo,
                        $actuador,
                        $estado,
                        'CLOUD',
                        null,
                        $config,
                        'PERIODIC_POLL'
                    );
                    if (
                        !empty($estado['online'])
                        && $salidaAnterior !== null
                        && $salidaActual !== null
                        && (bool) $salidaAnterior !== (bool) $salidaActual
                    ) {
                        $pdo->prepare(
                            "INSERT INTO eventos_shelly (
                                actuador_id, evento, origen, salida_encendida, detalle_json
                             ) VALUES (:actuador, :evento, 'CLOUD', :salida, :detalle)"
                        )->execute([
                            'actuador' => $idActuador,
                            'evento' => $salidaActual
                                ? 'CAMBIO_CLOUD_ENCENDIDO'
                                : 'CAMBIO_CLOUD_APAGADO',
                            'salida' => $salidaActual ? 1 : 0,
                            'detalle' => idindShellyJsonSeguro([
                                'motivo' => 'Cambio externo detectado durante la sincronizacion',
                                'canal' => (int) $actuador['canal'],
                            ]),
                        ]);
                        $eventoId = (int) $pdo->lastInsertId();
                        idindShellyEncolarNotificacionEvento($pdo, $eventoId, $config);
                    }
                    $estadosPrevios[$idActuador] = $salidaActual === null
                        ? null
                        : ($salidaActual ? 1 : 0);
                    !empty($estado['online']) ? $online++ : $offline++;
                }
            }
        } catch (Throwable $error) {
            foreach ($ids as $deviceId) {
                foreach ($porDeviceId[$deviceId] as $actuador) {
                    $consultados++;
                    idindShellyGuardarError($pdo, $actuador, $error->getMessage());
                    $errores[] = $actuador['id'] . ': ' . $error->getMessage();
                }
            }
        }
    }
    return compact('consultados', 'online', 'offline', 'errores');
}

function idindShellyCrearComando(
    PDO $pdo,
    string $actuadorId,
    string $accion,
    string $origen,
    ?int $usuarioId = null,
    ?int $alertaId = null,
    ?string $motivo = null
): int {
    $accion = strtoupper($accion);
    $origen = strtoupper($origen);
    if (!in_array($accion, ['ENCENDER', 'APAGAR'], true)) {
        throw new IdindShellyException('Accion Shelly no valida');
    }
    if (!in_array($origen, ['AUTOMATICO', 'WEB', 'APP', 'CRON', 'ALEXA'], true)) {
        throw new IdindShellyException('Origen Shelly no valido');
    }
    $pdo->prepare(
        "UPDATE comandos_shelly
         SET estado = 'FALLIDO',
             ultimo_error = 'Sustituido por una orden posterior',
             procesado_en = UTC_TIMESTAMP()
         WHERE actuador_id = :actuador_id
           AND accion <> :accion
           AND estado IN ('PENDIENTE', 'REINTENTAR')"
    )->execute(['actuador_id' => $actuadorId, 'accion' => $accion]);
    $stmtExistente = $pdo->prepare(
        "SELECT id FROM comandos_shelly
         WHERE actuador_id = :actuador_id AND accion = :accion
           AND estado IN ('PENDIENTE', 'PROCESANDO', 'REINTENTAR')
         ORDER BY id DESC LIMIT 1"
    );
    $stmtExistente->execute(['actuador_id' => $actuadorId, 'accion' => $accion]);
    $existente = $stmtExistente->fetchColumn();
    if ($existente) {
        return (int) $existente;
    }
    $stmt = $pdo->prepare(
        'INSERT INTO comandos_shelly (
            actuador_id, alerta_id, accion, origen, solicitado_por,
            motivo, estado, disponible_en
         ) VALUES (
            :actuador_id, :alerta_id, :accion, :origen, :usuario_id,
            :motivo, \'PENDIENTE\', UTC_TIMESTAMP()
         )'
    );
    $stmt->execute([
        'actuador_id' => $actuadorId,
        'alerta_id' => $alertaId,
        'accion' => $accion,
        'origen' => $origen,
        'usuario_id' => $usuarioId,
        'motivo' => $motivo === null ? null : substr($motivo, 0, 255),
    ]);
    return (int) $pdo->lastInsertId();
}

function idindShellyProcesarComando(PDO $pdo, array $config, int $comandoId): array
{
    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        "SELECT c.*, a.cliente_id, a.shelly_device_id, a.canal,
                a.categoria, a.apagado_automatico, a.tiempo_max_encendido_s,
                a.ip_local, a.modo_control,
                TIMESTAMPDIFF(SECOND, c.actualizado_en, UTC_TIMESTAMP()) AS segundos_procesando,
                a.estado AS estado_actuador, a.id AS actuador_id_real
         FROM comandos_shelly c
         INNER JOIN actuadores_shelly a ON a.id = c.actuador_id
         WHERE c.id = :id LIMIT 1 FOR UPDATE"
    );
    $stmt->execute(['id' => $comandoId]);
    $comando = $stmt->fetch();
    if (!$comando) {
        $pdo->rollBack();
        throw new IdindShellyException('Comando Shelly no encontrado');
    }
    if ($comando['estado'] === 'APLICADO') {
        $pdo->commit();
        return ['comando_id' => $comandoId, 'estado' => 'APLICADO', 'aplicado' => true];
    }
    if (
        $comando['estado'] === 'PROCESANDO'
        && (int) ($comando['segundos_procesando'] ?? 0) < 300
    ) {
        $pdo->commit();
        return [
            'comando_id' => $comandoId,
            'estado' => 'PROCESANDO',
            'aplicado' => false,
            'error' => 'La orden ya se esta procesando',
        ];
    }
    if ($comando['estado_actuador'] !== 'Activo') {
        $pdo->prepare(
            "UPDATE comandos_shelly SET estado = 'FALLIDO',
             ultimo_error = 'Actuador inactivo', procesado_en = UTC_TIMESTAMP()
             WHERE id = :id"
        )->execute(['id' => $comandoId]);
        $pdo->commit();
        return ['comando_id' => $comandoId, 'estado' => 'FALLIDO', 'aplicado' => false, 'error' => 'Actuador inactivo'];
    }
    if (($comando['categoria'] ?? 'SEGURIDAD') === 'MONITOREO') {
        $pdo->prepare(
            "UPDATE comandos_shelly SET estado = 'FALLIDO',
             ultimo_error = 'Equipo configurado solo para monitoreo',
             procesado_en = UTC_TIMESTAMP() WHERE id = :id"
        )->execute(['id' => $comandoId]);
        $pdo->commit();
        return [
            'comando_id' => $comandoId,
            'estado' => 'FALLIDO',
            'aplicado' => false,
            'error' => 'Este equipo esta configurado solo para monitoreo',
        ];
    }
    $intentos = (int) $comando['intentos'] + 1;
    $pdo->prepare(
        "UPDATE comandos_shelly SET estado = 'PROCESANDO', intentos = intentos + 1,
         ultimo_error = NULL WHERE id = :id"
    )->execute(['id' => $comandoId]);
    $pdo->commit();

    $encender = $comando['accion'] === 'ENCENDER';
    $fuenteEstado = 'CLOUD';
    try {
        $modoControl = (string) ($comando['modo_control'] ?? 'CLOUD');
        $usarCloud = $modoControl !== 'LOCAL' && idindShellyConfigurado($config);
        $usarLocal = !$usarCloud && in_array($modoControl, ['LOCAL', 'HIBRIDO'], true);
        if ($usarLocal) {
            $fuenteEstado = 'LOCAL';
            $parametrosLocal = [
                'id' => (int) $comando['canal'],
                'on' => $encender,
            ];
            if (
                $encender
                && !empty($comando['apagado_automatico'])
                && (int) ($comando['tiempo_max_encendido_s'] ?? 0) > 0
            ) {
                $parametrosLocal['toggle_after'] = (int) $comando['tiempo_max_encendido_s'];
            }
            $respuesta = idindShellyPeticionLocal($comando, 'Switch.Set', $parametrosLocal);
            $verificacion = idindShellyPeticionLocal(
                $comando,
                'Switch.GetStatus',
                ['id' => (int) $comando['canal']]
            );
            $estado = idindShellyEstadoCanalLocal($verificacion);
        } else {
            $parametrosComando = [
                'id' => (string) $comando['shelly_device_id'],
                'channel' => (int) $comando['canal'],
                'on' => $encender,
            ];
            if (
                $encender
                && !empty($comando['apagado_automatico'])
                && (int) ($comando['tiempo_max_encendido_s'] ?? 0) > 0
            ) {
                $parametrosComando['toggle_after'] = (int) $comando['tiempo_max_encendido_s'];
            }
            $respuesta = idindShellyPeticionCloud(
                $pdo,
                $config,
                (int) $comando['cliente_id'],
                '/v2/devices/api/set/switch',
                $parametrosComando
            );
            $verificacion = idindShellyPeticionCloud(
                $pdo,
                $config,
                (int) $comando['cliente_id'],
                '/v2/devices/api/get',
                [
                    'ids' => [(string) $comando['shelly_device_id']],
                    'select' => ['status', 'settings'],
                ]
            );
            $dispositivoVerificado = null;
            foreach (idindShellyListaRespuesta($verificacion) as $item) {
                if (
                    strtolower((string) ($item['id'] ?? ''))
                    === strtolower((string) $comando['shelly_device_id'])
                ) {
                    $dispositivoVerificado = $item;
                    break;
                }
            }
            if ($dispositivoVerificado === null) {
                throw new IdindShellyException(
                    'Shelly Cloud acepto la orden, pero no devolvio el dispositivo al verificar'
                );
            }
            $estado = idindShellyEstadoCanal(
                $dispositivoVerificado,
                (int) $comando['canal']
            );
        }
        if (empty($estado['online'])) {
            throw new IdindShellyException(
                'Shelly reporta el dispositivo offline despues de la orden'
            );
        }
        if ($estado['salida_encendida'] === null) {
            throw new IdindShellyException(
                'Shelly no devolvio switch:' . (int) $comando['canal']
                . '; revisa el canal y el Device ID'
            );
        }
        if ((bool) $estado['salida_encendida'] !== $encender) {
            throw new IdindShellyException(
                'Shelly acepto la orden, pero el canal '
                . (int) $comando['canal'] . ' no cambio fisicamente'
            );
        }
        idindShellyGuardarEstado(
            $pdo,
            ['id' => (string) $comando['actuador_id']],
            $estado,
            $fuenteEstado,
            null,
            $config,
            $comando['origen'] === 'ALEXA'
                ? 'VOICE_INTERACTION'
                : (in_array($comando['origen'], ['AUTOMATICO', 'CRON'], true)
                    ? 'PERIODIC_POLL'
                    : 'APP_INTERACTION')
        );
        $apagadoProgramado = null;
        if (
            $encender
            && !empty($comando['apagado_automatico'])
            && (int) ($comando['tiempo_max_encendido_s'] ?? 0) > 0
        ) {
            $apagadoProgramado = gmdate(
                'Y-m-d H:i:s',
                time() + (int) $comando['tiempo_max_encendido_s']
            );
        }
        $pdo->prepare(
            'UPDATE estado_shelly
             SET apagado_programado_en = :apagado_programado_en
             WHERE actuador_id = :actuador_id'
        )->execute([
            'apagado_programado_en' => $apagadoProgramado,
            'actuador_id' => (string) $comando['actuador_id'],
        ]);
        $pdo->prepare(
            "UPDATE comandos_shelly SET estado = 'APLICADO', respuesta_json = :respuesta,
             ultimo_error = NULL, procesado_en = UTC_TIMESTAMP() WHERE id = :id"
        )->execute([
            'respuesta' => idindShellyJsonSeguro([
                'comando' => $respuesta,
                'verificacion' => $verificacion,
            ]),
            'id' => $comandoId,
        ]);
        $pdo->prepare(
            "INSERT INTO eventos_shelly (
                actuador_id, comando_id, evento, origen, salida_encendida, detalle_json
             ) VALUES (:actuador, :comando, :evento, :origen, :salida, :detalle)"
        )->execute([
            'actuador' => (string) $comando['actuador_id'],
            'comando' => $comandoId,
            'evento' => $encender ? 'SALIDA_ENCENDIDA' : 'SALIDA_APAGADA',
            'origen' => $comando['origen'] === 'ALEXA'
                ? 'ALEXA'
                : (in_array($comando['origen'], ['AUTOMATICO', 'CRON'], true)
                    ? 'SISTEMA'
                    : 'USUARIO'),
            'salida' => $encender ? 1 : 0,
            'detalle' => idindShellyJsonSeguro(['motivo' => $comando['motivo']]),
        ]);
        return ['comando_id' => $comandoId, 'estado' => 'APLICADO', 'aplicado' => true, 'salida_encendida' => $encender];
    } catch (Throwable $error) {
        try {
            idindShellyGuardarError(
                $pdo,
                ['id' => (string) $comando['actuador_id']],
                $error->getMessage(),
                $fuenteEstado
            );
        } catch (Throwable $errorEstado) {
            error_log('ID Industrial estado Shelly: ' . $errorEstado->getMessage());
        }
        $estadoCola = $intentos >= 5 ? 'FALLIDO' : 'REINTENTAR';
        $espera = min(300, 15 * (2 ** max(0, $intentos - 1)));
        $disponible = gmdate('Y-m-d H:i:s', time() + $espera);
        $pdo->prepare(
            'UPDATE comandos_shelly
             SET estado = :estado, ultimo_error = :error,
                 disponible_en = :disponible,
                 procesado_en = CASE WHEN :fallido = 1 THEN UTC_TIMESTAMP() ELSE procesado_en END
             WHERE id = :id'
        )->execute([
            'estado' => $estadoCola,
            'error' => substr($error->getMessage(), 0, 500),
            'disponible' => $disponible,
            'fallido' => $estadoCola === 'FALLIDO' ? 1 : 0,
            'id' => $comandoId,
        ]);
        return [
            'comando_id' => $comandoId,
            'estado' => $estadoCola,
            'aplicado' => false,
            'error' => $error->getMessage(),
            'reintento_en' => $estadoCola === 'REINTENTAR' ? $disponible : null,
        ];
    }
}

function idindShellyComandarVinculados(
    PDO $pdo,
    array $config,
    string $esp32Id,
    string $accion,
    string $origen,
    ?int $usuarioId = null,
    ?int $alertaId = null,
    ?string $motivo = null
): array {
    $stmt = $pdo->prepare(
        "SELECT id FROM actuadores_shelly
         WHERE dispositivo_vinculado_id = :esp32_id
           AND estado = 'Activo'
           AND modo_control IN ('LOCAL', 'CLOUD', 'HIBRIDO')
           AND funcion IN ('SIRENA', 'BALIZA')
         ORDER BY FIELD(funcion, 'SIRENA', 'BALIZA'), canal, id"
    );
    $stmt->execute(['esp32_id' => $esp32Id]);
    $resultados = [];
    foreach ($stmt->fetchAll() as $actuador) {
        $comandoId = idindShellyCrearComando(
            $pdo,
            (string) $actuador['id'],
            $accion,
            $origen,
            $usuarioId,
            $alertaId,
            $motivo
        );
        $resultados[] = idindShellyProcesarComando($pdo, $config, $comandoId);
    }
    return $resultados;
}

function idindShellyProcesarPendientes(PDO $pdo, array $config, int $limite = 10): array
{
    $pdo->exec(
        "UPDATE comandos_shelly SET estado = 'REINTENTAR', disponible_en = UTC_TIMESTAMP(),
         ultimo_error = 'Comando interrumpido; recuperado por cron'
         WHERE estado = 'PROCESANDO'
           AND actualizado_en < UTC_TIMESTAMP() - INTERVAL 5 MINUTE"
    );
    $limite = max(1, min(25, $limite));
    $stmt = $pdo->query(
        "SELECT id FROM comandos_shelly
         WHERE estado IN ('PENDIENTE', 'REINTENTAR')
           AND disponible_en <= UTC_TIMESTAMP()
         ORDER BY id ASC LIMIT {$limite}"
    );
    $resultados = [];
    foreach ($stmt->fetchAll() as $fila) {
        $resultados[] = idindShellyProcesarComando($pdo, $config, (int) $fila['id']);
    }
    return $resultados;
}

function idindShellyEstadoCliente(PDO $pdo, int $clienteId): array
{
    $stmt = $pdo->prepare(
        "SELECT
            a.id, a.nombre, a.ubicacion, a.dispositivo_vinculado_id,
            a.shelly_device_id, a.modelo, a.generacion, a.ip_local,
            a.canal, a.funcion, a.categoria, a.tipo_carga,
            a.corriente_max_a, a.potencia_max_w, a.tiempo_max_encendido_s,
            a.apagado_automatico, a.permite_rutinas, a.requiere_confirmacion,
            a.notificar_cambios_externos,
            a.descripcion, a.modo_control, a.estado,
            a.ultima_conexion,
            es.online, es.salida_encendida, es.potencia_w, es.voltaje_v,
            es.corriente_a, es.temperatura_c, es.errores_json,
            es.fuente, es.ultimo_error, es.sincronizado_en,
            es.apagado_programado_en,
            CASE
              WHEN es.sincronizado_en IS NULL THEN 'SIN_DATOS'
              WHEN es.sincronizado_en < UTC_TIMESTAMP() - INTERVAL 3 MINUTE
                THEN 'DESACTUALIZADO'
              WHEN es.online = 1 THEN 'ONLINE'
              ELSE 'OFFLINE'
            END AS conexion
         FROM actuadores_shelly a
         LEFT JOIN estado_shelly es ON es.actuador_id = a.id
         WHERE a.cliente_id = :cliente_id AND a.estado <> 'Inactivo'
         ORDER BY a.ubicacion, a.shelly_device_id, a.canal, a.id"
    );
    $stmt->execute(['cliente_id' => $clienteId]);
    $filas = $stmt->fetchAll();
    foreach ($filas as &$fila) {
        $errores = json_decode((string) ($fila['errores_json'] ?? ''), true);
        $fila['errores'] = is_array($errores) ? $errores : [];
        unset($fila['errores_json']);
        $fila['online'] = (int) ($fila['online'] ?? 0);
        $fila['salida_encendida'] = $fila['salida_encendida'] === null
            ? null
            : (int) $fila['salida_encendida'];
        foreach (['apagado_automatico', 'permite_rutinas', 'requiere_confirmacion', 'notificar_cambios_externos'] as $campo) {
            $fila[$campo] = (int) ($fila[$campo] ?? 0);
        }
    }
    unset($fila);
    return $filas;
}
