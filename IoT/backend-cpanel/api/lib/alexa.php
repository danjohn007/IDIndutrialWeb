<?php
declare(strict_types=1);

require_once __DIR__ . '/shelly_admin.php';
require_once __DIR__ . '/rutinas.php';

final class IdindAlexaException extends RuntimeException
{
    private $alexaType;

    public function __construct(string $message, string $alexaType = 'INTERNAL_ERROR')
    {
        parent::__construct($message);
        $this->alexaType = $alexaType;
    }

    public function alexaType(): string
    {
        return $this->alexaType;
    }
}

function idindAlexaBase64Url(string $bytes): string
{
    return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
}

function idindAlexaTokenAleatorio(int $bytes = 48): string
{
    return idindAlexaBase64Url(random_bytes($bytes));
}

function idindAlexaConfig(array $config): array
{
    $redirects = $config['alexa_oauth_redirect_uris'] ?? [];
    if (!is_array($redirects)) {
        $redirects = [];
    }
    $redirects = array_values(array_filter(array_map('trim', $redirects), static function ($uri) {
        return is_string($uri) && strpos($uri, 'https://') === 0;
    }));
    return [
        'public_base_url' => rtrim(trim((string) ($config['alexa_public_base_url'] ?? '')), '/'),
        'client_id' => trim((string) ($config['alexa_oauth_client_id'] ?? '')),
        'client_secret' => trim((string) ($config['alexa_oauth_client_secret'] ?? '')),
        'lambda_secret' => trim((string) ($config['alexa_lambda_shared_secret'] ?? '')),
        'redirect_uris' => $redirects,
    ];
}

function idindAlexaConfigEstado(array $config): array
{
    $alexa = idindAlexaConfig($config);
    $oauth = $alexa['public_base_url'] !== ''
        && strpos($alexa['public_base_url'], 'https://') === 0
        && strlen($alexa['client_id']) >= 8
        && strlen($alexa['client_secret']) >= 32
        && $alexa['redirect_uris'] !== [];
    $lambda = strlen($alexa['lambda_secret']) >= 32;
    return [
        'oauth_listo' => $oauth,
        'lambda_lista' => $lambda,
        'lista' => $oauth && $lambda,
        'authorize_url' => $alexa['public_base_url'] === '' ? null : $alexa['public_base_url'] . '/alexa/authorize.php',
        'token_url' => $alexa['public_base_url'] === '' ? null : $alexa['public_base_url'] . '/alexa/token.php',
        'handler_url' => $alexa['public_base_url'] === '' ? null : $alexa['public_base_url'] . '/alexa/smart_home.php',
    ];
}

function idindAlexaRequerirConfig(array $config): array
{
    $estado = idindAlexaConfigEstado($config);
    if (!$estado['lista']) {
        throw new IdindAlexaException('La integracion Alexa no esta configurada en config.local.php');
    }
    return idindAlexaConfig($config);
}

function idindAlexaRequerirMigracion(PDO $pdo): void
{
    try {
        $pdo->query('SELECT id FROM alexa_oauth_codes LIMIT 0');
        $pdo->query('SELECT id FROM alexa_oauth_tokens LIMIT 0');
    } catch (Throwable $error) {
        throw new IdindAlexaException('Falta importar database/migracion_alexa_mysql57.sql');
    }
}

function idindAlexaRedirectPermitido(array $config, string $redirectUri): bool
{
    foreach ($config['redirect_uris'] as $permitido) {
        if (hash_equals((string) $permitido, $redirectUri)) {
            return true;
        }
    }
    return false;
}

function idindAlexaAutenticarToken(PDO $pdo, string $token): array
{
    if (strlen($token) < 43 || strlen($token) > 256) {
        throw new IdindAlexaException('Token de cuenta invalido', 'INVALID_AUTHORIZATION_CREDENTIAL');
    }
    $stmt = $pdo->prepare(
        "SELECT t.id AS oauth_token_id, t.usuario_id, u.cliente_id, u.nombre,
                u.email, u.rol
         FROM alexa_oauth_tokens t
         INNER JOIN usuarios u ON u.id = t.usuario_id
         WHERE t.access_token_hash = :token_hash
           AND t.revocado_en IS NULL
           AND t.access_expira_en > UTC_TIMESTAMP()
           AND u.estado = 'ACTIVO'
           AND u.rol IN ('ADMIN', 'OPERADOR')
         LIMIT 1"
    );
    $stmt->execute(['token_hash' => hash('sha256', $token)]);
    $usuario = $stmt->fetch();
    if (!$usuario) {
        throw new IdindAlexaException('Token de cuenta vencido o revocado', 'EXPIRED_AUTHORIZATION_CREDENTIAL');
    }
    $pdo->prepare(
        'UPDATE alexa_oauth_tokens SET ultimo_uso = UTC_TIMESTAMP()
         WHERE id = :id AND (ultimo_uso IS NULL OR ultimo_uso < UTC_TIMESTAMP() - INTERVAL 5 MINUTE)'
    )->execute(['id' => (int) $usuario['oauth_token_id']]);
    return $usuario;
}

function idindAlexaTokenDirectiva(array $directiva): string
{
    $token = $directiva['endpoint']['scope']['token']
        ?? $directiva['payload']['scope']['token']
        ?? $directiva['payload']['grantee']['token']
        ?? '';
    return trim((string) $token);
}

function idindAlexaActuadores(PDO $pdo, int $clienteId): array
{
    $stmt = $pdo->prepare(
        "SELECT a.id, a.nombre, a.ubicacion, a.modelo, a.shelly_device_id,
                a.canal, a.funcion, a.tipo_carga, a.descripcion,
                es.online, es.salida_encendida, es.sincronizado_en,
                CASE
                  WHEN es.sincronizado_en IS NULL THEN 0
                  WHEN es.sincronizado_en < UTC_TIMESTAMP() - INTERVAL 3 MINUTE THEN 0
                  WHEN es.online = 1 THEN 1 ELSE 0
                END AS conexion_online
         FROM actuadores_shelly a
         LEFT JOIN estado_shelly es ON es.actuador_id = a.id
         WHERE a.cliente_id = :cliente_id
           AND a.estado = 'Activo'
           AND a.categoria = 'AUTOMATIZACION'
           AND a.permite_rutinas = 1
           AND a.requiere_confirmacion = 0
           AND a.modo_control IN ('CLOUD', 'HIBRIDO')
         ORDER BY a.ubicacion, a.nombre, a.id"
    );
    $stmt->execute(['cliente_id' => $clienteId]);
    return $stmt->fetchAll();
}

function idindAlexaActuador(PDO $pdo, int $clienteId, string $endpointId): array
{
    foreach (idindAlexaActuadores($pdo, $clienteId) as $actuador) {
        if (hash_equals(idindAlexaEndpointId((string) $actuador['id']), $endpointId)) {
            return $actuador;
        }
    }
    throw new IdindAlexaException('El dispositivo no existe o no esta habilitado para Alexa', 'NO_SUCH_ENDPOINT');
}

function idindAlexaRutinaEndpointId(int $rutinaId): string
{
    return 'idindustrial_scene_' . substr(hash('sha256', (string) $rutinaId), 0, 32);
}

function idindAlexaRutinas(PDO $pdo, int $clienteId): array
{
    $stmt = $pdo->prepare(
        "SELECT r.id, r.nombre, r.descripcion
         FROM rutinas r
         WHERE r.cliente_id = :cliente_id AND r.activa = 1
           AND EXISTS (SELECT 1 FROM rutina_acciones ra WHERE ra.rutina_id = r.id)
           AND NOT EXISTS (
             SELECT 1 FROM rutina_acciones ra
             LEFT JOIN actuadores_shelly a ON a.id = ra.actuador_id
             WHERE ra.rutina_id = r.id
               AND (
                 a.id IS NULL OR a.cliente_id <> r.cliente_id OR a.estado <> 'Activo'
                 OR a.categoria <> 'AUTOMATIZACION' OR a.permite_rutinas <> 1
                 OR a.requiere_confirmacion <> 0 OR a.modo_control NOT IN ('CLOUD', 'HIBRIDO')
               )
           )
         ORDER BY r.nombre, r.id LIMIT 12"
    );
    $stmt->execute(['cliente_id' => $clienteId]);
    return $stmt->fetchAll();
}

function idindAlexaRutina(PDO $pdo, int $clienteId, string $endpointId): array
{
    foreach (idindAlexaRutinas($pdo, $clienteId) as $rutina) {
        if (hash_equals(idindAlexaRutinaEndpointId((int) $rutina['id']), $endpointId)) {
            return $rutina;
        }
    }
    throw new IdindAlexaException('La rutina no existe o contiene equipos no disponibles', 'NO_SUCH_ENDPOINT');
}

function idindAlexaFecha(): string
{
    return gmdate('Y-m-d\TH:i:s\Z');
}

function idindAlexaPropiedades(array $actuador): array
{
    $fecha = idindAlexaFecha();
    return [
        [
            'namespace' => 'Alexa.PowerController',
            'name' => 'powerState',
            'value' => !empty($actuador['salida_encendida']) ? 'ON' : 'OFF',
            'timeOfSample' => $fecha,
            'uncertaintyInMilliseconds' => 5000,
        ],
        [
            'namespace' => 'Alexa.EndpointHealth',
            'name' => 'connectivity',
            'value' => ['value' => !empty($actuador['conexion_online']) ? 'OK' : 'UNREACHABLE'],
            'timeOfSample' => $fecha,
            'uncertaintyInMilliseconds' => 5000,
        ],
    ];
}

function idindAlexaCabecera(string $namespace, string $name, array $entrada, bool $correlacion = false): array
{
    $cabecera = [
        'namespace' => $namespace,
        'name' => $name,
        'messageId' => idindAlexaTokenAleatorio(18),
        'payloadVersion' => '3',
    ];
    if ($correlacion && isset($entrada['correlationToken'])) {
        $cabecera['correlationToken'] = (string) $entrada['correlationToken'];
    }
    return $cabecera;
}

function idindAlexaDescubrimiento(PDO $pdo, int $clienteId, array $cabeceraEntrada): array
{
    $endpoints = [];
    foreach (idindAlexaActuadores($pdo, $clienteId) as $actuador) {
        $nombre = trim((string) ($actuador['nombre'] ?? ''));
        if ($nombre === '') {
            $nombre = 'Equipo ' . (string) $actuador['id'];
        }
        $categoria = (string) $actuador['tipo_carga'] === 'ELECTRONICA' ? 'SMARTPLUG' : 'SWITCH';
        $endpoints[] = [
            'endpointId' => idindAlexaEndpointId((string) $actuador['id']),
            'manufacturerName' => 'ID Industrial',
            'description' => trim((string) ($actuador['descripcion'] ?? '')) ?: 'Canal Shelly administrado por ID Industrial',
            'friendlyName' => $nombre,
            'additionalAttributes' => [
                'manufacturer' => 'Shelly',
                'model' => (string) $actuador['modelo'],
                'serialNumber' => (string) $actuador['shelly_device_id'] . '-' . (string) $actuador['canal'],
                'customIdentifier' => (string) $actuador['id'],
            ],
            'displayCategories' => [$categoria],
            'cookie' => ['actuatorId' => (string) $actuador['id']],
            'capabilities' => [
                [
                    'type' => 'AlexaInterface',
                    'interface' => 'Alexa.PowerController',
                    'version' => '3',
                    'properties' => [
                        'supported' => [['name' => 'powerState']],
                        'proactivelyReported' => true,
                        'retrievable' => true,
                    ],
                ],
                [
                    'type' => 'AlexaInterface',
                    'interface' => 'Alexa.EndpointHealth',
                    'version' => '3.1',
                    'properties' => [
                        'supported' => [['name' => 'connectivity']],
                        'proactivelyReported' => false,
                        'retrievable' => true,
                    ],
                ],
                ['type' => 'AlexaInterface', 'interface' => 'Alexa', 'version' => '3'],
            ],
        ];
    }
    foreach (idindAlexaRutinas($pdo, $clienteId) as $rutina) {
        $endpoints[] = [
            'endpointId' => idindAlexaRutinaEndpointId((int) $rutina['id']),
            'manufacturerName' => 'ID Industrial',
            'description' => 'Escena de automatizacion conectada por ID Industrial',
            'friendlyName' => (string) $rutina['nombre'],
            'additionalAttributes' => [
                'manufacturer' => 'ID Industrial',
                'model' => 'Rutina',
                'customIdentifier' => 'routine-' . (string) $rutina['id'],
            ],
            'displayCategories' => ['ACTIVITY_TRIGGER'],
            'cookie' => ['routineId' => (string) $rutina['id']],
            'capabilities' => [
                [
                    'type' => 'AlexaInterface',
                    'interface' => 'Alexa.SceneController',
                    'version' => '3',
                    'supportsDeactivation' => false,
                ],
                ['type' => 'AlexaInterface', 'interface' => 'Alexa', 'version' => '3'],
            ],
        ];
    }
    return [
        'event' => [
            'header' => idindAlexaCabecera('Alexa.Discovery', 'Discover.Response', $cabeceraEntrada),
            'payload' => ['endpoints' => $endpoints],
        ],
    ];
}

function idindAlexaRespuestaEscena(array $directiva): array
{
    $cabeceraEntrada = $directiva['header'] ?? [];
    return [
        'context' => (object) [],
        'event' => [
            'header' => idindAlexaCabecera('Alexa.SceneController', 'ActivationStarted', $cabeceraEntrada, true),
            'endpoint' => $directiva['endpoint'] ?? (object) [],
            'payload' => [
                'cause' => ['type' => 'VOICE_INTERACTION'],
                'timestamp' => idindAlexaFecha(),
            ],
        ],
    ];
}

function idindAlexaRespuestaEstado(array $actuador, array $directiva, string $name = 'StateReport'): array
{
    $cabeceraEntrada = $directiva['header'] ?? [];
    return [
        'context' => ['properties' => idindAlexaPropiedades($actuador)],
        'event' => [
            'header' => idindAlexaCabecera('Alexa', $name, $cabeceraEntrada, true),
            'endpoint' => ['endpointId' => (string) ($directiva['endpoint']['endpointId'] ?? '')],
            'payload' => (object) [],
        ],
    ];
}

function idindAlexaRespuestaError(array $directiva, string $tipo, string $mensaje): array
{
    $cabeceraEntrada = $directiva['header'] ?? [];
    $respuesta = [
        'event' => [
            'header' => idindAlexaCabecera('Alexa', 'ErrorResponse', $cabeceraEntrada, true),
            'payload' => ['type' => $tipo, 'message' => $mensaje],
        ],
    ];
    if (!empty($directiva['endpoint']['endpointId'])) {
        $respuesta['event']['endpoint'] = ['endpointId' => (string) $directiva['endpoint']['endpointId']];
    }
    return $respuesta;
}
