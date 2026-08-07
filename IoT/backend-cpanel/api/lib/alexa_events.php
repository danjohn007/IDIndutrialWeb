<?php
declare(strict_types=1);

final class IdindAlexaEventException extends RuntimeException
{
}

function idindAlexaEndpointId(string $actuadorId): string
{
    return 'idindustrial_' . substr(hash('sha256', $actuadorId), 0, 32);
}

function idindAlexaEventConfig(array $config): array
{
    $region = strtoupper(trim((string) (
        getenv('IDIND_ALEXA_EVENT_REGION')
        ?: ($config['alexa_event_region'] ?? 'NA')
    )));
    $gateways = [
        'NA' => 'https://api.amazonalexa.com/v3/events',
        'EU' => 'https://api.eu.amazonalexa.com/v3/events',
        'FE' => 'https://api.fe.amazonalexa.com/v3/events',
    ];
    if (!isset($gateways[$region])) {
        $region = 'NA';
    }
    return [
        'client_id' => trim((string) (
            getenv('IDIND_ALEXA_EVENT_CLIENT_ID')
            ?: ($config['alexa_event_client_id'] ?? '')
        )),
        'client_secret' => trim((string) (
            getenv('IDIND_ALEXA_EVENT_CLIENT_SECRET')
            ?: ($config['alexa_event_client_secret'] ?? '')
        )),
        'region' => $region,
        'gateway' => $gateways[$region],
        'token_url' => 'https://api.amazon.com/auth/o2/token',
    ];
}

function idindAlexaEventConfigurado(array $config): bool
{
    $eventos = idindAlexaEventConfig($config);
    return strlen($eventos['client_id']) >= 10
        && strlen($eventos['client_secret']) >= 20;
}

function idindAlexaEventMigracionLista(PDO $pdo): bool
{
    try {
        $pdo->query('SELECT usuario_id FROM alexa_event_tokens LIMIT 0');
        return true;
    } catch (Throwable $error) {
        return false;
    }
}

function idindAlexaEventCifrar(string $valor, string $secret): string
{
    if (!function_exists('openssl_encrypt')) {
        throw new IdindAlexaEventException('La extension OpenSSL no esta habilitada en PHP');
    }
    $iv = random_bytes(12);
    $tag = '';
    $clave = hash('sha256', 'idindustrial-alexa-events|' . $secret, true);
    $cifrado = openssl_encrypt(
        $valor,
        'aes-256-gcm',
        $clave,
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
        'idindustrial-alexa-events',
        16
    );
    if (!is_string($cifrado) || strlen($tag) !== 16) {
        throw new IdindAlexaEventException('No fue posible proteger el token de Alexa');
    }
    return rtrim(strtr(base64_encode($iv . $tag . $cifrado), '+/', '-_'), '=');
}

function idindAlexaEventDescifrar(string $valor, string $secret): string
{
    if (!function_exists('openssl_decrypt')) {
        throw new IdindAlexaEventException('La extension OpenSSL no esta habilitada en PHP');
    }
    $normalizado = strtr($valor, '-_', '+/');
    $normalizado .= str_repeat('=', (4 - strlen($normalizado) % 4) % 4);
    $binario = base64_decode($normalizado, true);
    if (!is_string($binario) || strlen($binario) < 29) {
        throw new IdindAlexaEventException('El token protegido de Alexa no es valido');
    }
    $iv = substr($binario, 0, 12);
    $tag = substr($binario, 12, 16);
    $cifrado = substr($binario, 28);
    $clave = hash('sha256', 'idindustrial-alexa-events|' . $secret, true);
    $plano = openssl_decrypt(
        $cifrado,
        'aes-256-gcm',
        $clave,
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
        'idindustrial-alexa-events'
    );
    if (!is_string($plano) || $plano === '') {
        throw new IdindAlexaEventException('No fue posible leer el token protegido de Alexa');
    }
    return $plano;
}

function idindAlexaEventHttp(
    string $url,
    array $headers,
    string $cuerpo,
    int $timeout = 10
): array {
    if (!function_exists('curl_init')) {
        throw new IdindAlexaEventException('La extension cURL no esta habilitada en PHP');
    }
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $cuerpo,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'ID-Industrial/1.0 AlexaEvents',
    ]);
    $respuesta = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $errorCurl = curl_error($curl);
    curl_close($curl);
    if ($respuesta === false) {
        throw new IdindAlexaEventException(
            $errorCurl !== '' ? 'Error de red Alexa: ' . $errorCurl : 'Amazon no respondio'
        );
    }
    return ['status' => $status, 'body' => (string) $respuesta];
}

function idindAlexaEventSolicitarTokens(array $eventos, array $campos): array
{
    $respuesta = idindAlexaEventHttp(
        $eventos['token_url'],
        ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded;charset=UTF-8'],
        http_build_query(array_merge($campos, [
            'client_id' => $eventos['client_id'],
            'client_secret' => $eventos['client_secret'],
        ]), '', '&', PHP_QUERY_RFC3986)
    );
    $json = json_decode($respuesta['body'], true);
    if (
        $respuesta['status'] < 200
        || $respuesta['status'] >= 300
        || !is_array($json)
        || empty($json['access_token'])
    ) {
        $detalle = is_array($json)
            ? (string) ($json['error_description'] ?? $json['error'] ?? '')
            : '';
        throw new IdindAlexaEventException(
            trim('Login with Amazon respondio HTTP ' . $respuesta['status'] . ' ' . $detalle)
        );
    }
    return $json;
}

function idindAlexaEventGuardarGrant(
    PDO $pdo,
    array $config,
    int $usuarioId,
    string $codigo
): void {
    if (!idindAlexaEventConfigurado($config)) {
        throw new IdindAlexaEventException(
            'Configura alexa_event_client_id y alexa_event_client_secret en config.local.php'
        );
    }
    if (!idindAlexaEventMigracionLista($pdo)) {
        throw new IdindAlexaEventException(
            'Falta importar database/migracion_alexa_eventos_mysql57.sql'
        );
    }
    if ($codigo === '') {
        throw new IdindAlexaEventException('Alexa no envio el codigo de Event Gateway');
    }
    $eventos = idindAlexaEventConfig($config);
    $tokens = idindAlexaEventSolicitarTokens($eventos, [
        'grant_type' => 'authorization_code',
        'code' => $codigo,
    ]);
    $refresh = trim((string) ($tokens['refresh_token'] ?? ''));
    if ($refresh === '') {
        throw new IdindAlexaEventException('Amazon no devolvio refresh token para eventos');
    }
    $expira = max(60, (int) ($tokens['expires_in'] ?? 3600));
    $expiraEn = gmdate('Y-m-d H:i:s', time() + $expira);
    $stmt = $pdo->prepare(
        'INSERT INTO alexa_event_tokens (
            usuario_id, region, access_token_cifrado, refresh_token_cifrado,
            access_expira_en, ultimo_error
         ) VALUES (
            :usuario_id, :region, :access_token, :refresh_token,
            :access_expira_en, NULL
         ) ON DUPLICATE KEY UPDATE
            region = VALUES(region), access_token_cifrado = VALUES(access_token_cifrado),
            refresh_token_cifrado = VALUES(refresh_token_cifrado),
            access_expira_en = VALUES(access_expira_en), ultimo_error = NULL,
            actualizado_en = CURRENT_TIMESTAMP'
    );
    $stmt->execute([
        'usuario_id' => $usuarioId,
        'region' => $eventos['region'],
        'access_token' => idindAlexaEventCifrar((string) $tokens['access_token'], $eventos['client_secret']),
        'refresh_token' => idindAlexaEventCifrar($refresh, $eventos['client_secret']),
        'access_expira_en' => $expiraEn,
    ]);
}

function idindAlexaEventAccessToken(
    PDO $pdo,
    array $config,
    array $registro,
    bool $forzar = false
): string {
    $eventos = idindAlexaEventConfig($config);
    $expira = strtotime((string) $registro['access_expira_en'] . ' UTC');
    if (!$forzar && $expira !== false && $expira > time() + 120) {
        return idindAlexaEventDescifrar(
            (string) $registro['access_token_cifrado'],
            $eventos['client_secret']
        );
    }
    $refresh = idindAlexaEventDescifrar(
        (string) $registro['refresh_token_cifrado'],
        $eventos['client_secret']
    );
    $tokens = idindAlexaEventSolicitarTokens($eventos, [
        'grant_type' => 'refresh_token',
        'refresh_token' => $refresh,
    ]);
    $nuevoRefresh = trim((string) ($tokens['refresh_token'] ?? ''));
    if ($nuevoRefresh === '') {
        $nuevoRefresh = $refresh;
    }
    $expiraSegundos = max(60, (int) ($tokens['expires_in'] ?? 3600));
    $accessExpiraEn = gmdate('Y-m-d H:i:s', time() + $expiraSegundos);
    $pdo->prepare(
        'UPDATE alexa_event_tokens
         SET access_token_cifrado = :access_token,
             refresh_token_cifrado = :refresh_token,
             access_expira_en = :access_expira_en,
             ultimo_error = NULL
         WHERE usuario_id = :usuario_id'
    )->execute([
        'access_token' => idindAlexaEventCifrar((string) $tokens['access_token'], $eventos['client_secret']),
        'refresh_token' => idindAlexaEventCifrar($nuevoRefresh, $eventos['client_secret']),
        'access_expira_en' => $accessExpiraEn,
        'usuario_id' => (int) $registro['usuario_id'],
    ]);
    return (string) $tokens['access_token'];
}

function idindAlexaEventEnviarCambio(
    PDO $pdo,
    array $config,
    array $registro,
    string $actuadorId,
    bool $encendida,
    bool $online,
    string $causa
): void {
    $causas = ['APP_INTERACTION', 'PERIODIC_POLL', 'PHYSICAL_INTERACTION', 'VOICE_INTERACTION'];
    if (!in_array($causa, $causas, true)) {
        $causa = 'PERIODIC_POLL';
    }
    $eventos = idindAlexaEventConfig($config);
    $crearCuerpo = static function (string $token) use ($actuadorId, $encendida, $online, $causa): string {
        $fecha = gmdate('Y-m-d\TH:i:s.v\Z');
        return (string) json_encode([
            'context' => [
                'properties' => [[
                    'namespace' => 'Alexa.EndpointHealth',
                    'name' => 'connectivity',
                    'value' => ['value' => $online ? 'OK' : 'UNREACHABLE'],
                    'timeOfSample' => $fecha,
                    'uncertaintyInMilliseconds' => 1000,
                ]],
            ],
            'event' => [
                'header' => [
                    'namespace' => 'Alexa',
                    'name' => 'ChangeReport',
                    'messageId' => bin2hex(random_bytes(16)),
                    'payloadVersion' => '3',
                ],
                'endpoint' => [
                    'scope' => ['type' => 'BearerToken', 'token' => $token],
                    'endpointId' => idindAlexaEndpointId($actuadorId),
                ],
                'payload' => [
                    'change' => [
                        'cause' => ['type' => $causa],
                        'properties' => [[
                            'namespace' => 'Alexa.PowerController',
                            'name' => 'powerState',
                            'value' => $encendida ? 'ON' : 'OFF',
                            'timeOfSample' => $fecha,
                            'uncertaintyInMilliseconds' => 1000,
                        ]],
                    ],
                ],
            ],
        ], JSON_UNESCAPED_SLASHES);
    };
    $token = idindAlexaEventAccessToken($pdo, $config, $registro);
    $enviar = static function (string $token) use ($eventos, $crearCuerpo): array {
        return idindAlexaEventHttp(
            $eventos['gateway'],
            [
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
            ],
            $crearCuerpo($token)
        );
    };
    $respuesta = $enviar($token);
    if ($respuesta['status'] === 401) {
        $token = idindAlexaEventAccessToken($pdo, $config, $registro, true);
        $respuesta = $enviar($token);
    }
    if ($respuesta['status'] !== 202) {
        throw new IdindAlexaEventException(
            'Alexa Event Gateway respondio HTTP ' . $respuesta['status']
        );
    }
    $pdo->prepare(
        'UPDATE alexa_event_tokens
         SET ultimo_envio = UTC_TIMESTAMP(), ultimo_http_status = 202, ultimo_error = NULL
         WHERE usuario_id = :usuario_id'
    )->execute(['usuario_id' => (int) $registro['usuario_id']]);
}

function idindAlexaNotificarCambioShelly(
    PDO $pdo,
    array $config,
    string $actuadorId,
    bool $encendida,
    bool $online = true,
    string $causa = 'PERIODIC_POLL'
): array {
    if (!idindAlexaEventConfigurado($config) || !idindAlexaEventMigracionLista($pdo)) {
        return ['enviados' => 0, 'errores' => []];
    }
    $stmt = $pdo->prepare(
        "SELECT aet.*, u.cliente_id
         FROM alexa_event_tokens aet
         INNER JOIN usuarios u ON u.id = aet.usuario_id
         INNER JOIN actuadores_shelly a ON a.cliente_id = u.cliente_id
         WHERE a.id = :actuador_id
           AND a.estado = 'Activo' AND a.categoria = 'AUTOMATIZACION'
           AND a.permite_rutinas = 1 AND a.requiere_confirmacion = 0
           AND a.modo_control IN ('CLOUD', 'HIBRIDO')
           AND u.estado = 'ACTIVO' AND u.rol IN ('ADMIN', 'OPERADOR')"
    );
    $stmt->execute(['actuador_id' => $actuadorId]);
    $enviados = 0;
    $errores = [];
    foreach ($stmt->fetchAll() as $registro) {
        try {
            idindAlexaEventEnviarCambio(
                $pdo,
                $config,
                $registro,
                $actuadorId,
                $encendida,
                $online,
                $causa
            );
            $enviados++;
        } catch (Throwable $error) {
            $mensaje = substr($error->getMessage(), 0, 500);
            $errores[] = $mensaje;
            $pdo->prepare(
                'UPDATE alexa_event_tokens
                 SET ultimo_error = :error, ultimo_http_status = NULL
                 WHERE usuario_id = :usuario_id'
            )->execute([
                'error' => $mensaje,
                'usuario_id' => (int) $registro['usuario_id'],
            ]);
            error_log('ID Industrial Alexa ChangeReport: ' . $mensaje);
        }
    }
    return ['enviados' => $enviados, 'errores' => $errores];
}
