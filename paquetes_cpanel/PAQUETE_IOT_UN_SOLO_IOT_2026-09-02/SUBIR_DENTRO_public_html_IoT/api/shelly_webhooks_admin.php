<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/shelly.php';
require_once __DIR__ . '/lib/shelly_webhooks.php';

requerirMetodo('GET');
$usuario = requerirSesion(['ADMIN']);
$actuadorId = trim((string) ($_GET['actuador_id'] ?? ''));
if ($actuadorId === '' || strlen($actuadorId) > 64) {
    responderJson(422, ['ok' => false, 'error' => 'Actuador Shelly no valido']);
}

$stmt = $pdo->prepare(
    "SELECT a.id, a.nombre, a.ubicacion, a.shelly_device_id, a.modelo,
            a.generacion, a.ip_local, a.canal, a.estado,
            es.fuente, es.salida_encendida, es.sincronizado_en
     FROM actuadores_shelly a
     LEFT JOIN estado_shelly es ON es.actuador_id = a.id
     WHERE a.id = :id AND a.cliente_id = :cliente_id
     LIMIT 1"
);
$stmt->execute([
    'id' => $actuadorId,
    'cliente_id' => (int) $usuario['cliente_id'],
]);
$actuador = $stmt->fetch();
if (!$actuador) {
    responderJson(404, ['ok' => false, 'error' => 'Actuador Shelly no encontrado']);
}
if ((string) $actuador['generacion'] !== 'GEN2_PLUS') {
    responderJson(422, [
        'ok' => false,
        'error' => 'La instalacion guiada de webhooks requiere un Shelly Gen2 o posterior',
    ]);
}

try {
    $token = idindShellyWebhookToken($configLocal);
} catch (Throwable $error) {
    responderJson(503, ['ok' => false, 'error' => $error->getMessage()]);
}

$baseApi = trim((string) ($configLocal['alexa_public_base_url'] ?? ''));
if ($baseApi === '') {
    $proxyProto = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || $proxyProto === 'https';
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $ruta = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/api')));
    if (!$https || $host === '') {
        responderJson(503, [
            'ok' => false,
            'error' => 'Configura alexa_public_base_url con la URL HTTPS publica de la API',
        ]);
    }
    $baseApi = 'https://' . $host . rtrim($ruta, '/');
}
$baseApi = rtrim($baseApi, '/');
if (!preg_match('#^https://#i', $baseApi)) {
    responderJson(503, ['ok' => false, 'error' => 'La URL publica de la API debe usar HTTPS']);
}

$parametrosBase = [
    'token' => $token,
    'device_id' => strtolower((string) $actuador['shelly_device_id']),
    'channel' => (int) $actuador['canal'],
];
$urlEncendido = $baseApi . '/shelly_webhook.php?' . http_build_query(
    array_merge($parametrosBase, ['output' => 1]),
    '',
    '&',
    PHP_QUERY_RFC3986
);
$urlApagado = $baseApi . '/shelly_webhook.php?' . http_build_query(
    array_merge($parametrosBase, ['output' => 0]),
    '',
    '&',
    PHP_QUERY_RFC3986
);
$urlPrueba = $baseApi . '/shelly_webhook.php?' . http_build_query(
    array_merge($parametrosBase, ['output' => 0, 'probe' => 1]),
    '',
    '&',
    PHP_QUERY_RFC3986
);
if (strlen($urlEncendido) > 300 || strlen($urlApagado) > 300 || strlen($urlPrueba) > 300) {
    responderJson(503, [
        'ok' => false,
        'error' => 'La URL generada supera el limite de 300 caracteres de Shelly',
    ]);
}
$canal = (int) $actuador['canal'];
$nombreBase = 'ID Industrial ' . $actuadorId . ' canal ' . $canal;
$rpc = [
    'encendido' => [
        'id' => 1,
        'method' => 'Webhook.Create',
        'params' => [
            'cid' => $canal,
            'enable' => true,
            'event' => 'switch.on',
            'name' => substr($nombreBase . ' ON', 0, 64),
            'urls' => [$urlEncendido],
            'repeat_period' => 0,
        ],
    ],
    'apagado' => [
        'id' => 2,
        'method' => 'Webhook.Create',
        'params' => [
            'cid' => $canal,
            'enable' => true,
            'event' => 'switch.off',
            'name' => substr($nombreBase . ' OFF', 0, 64),
            'urls' => [$urlApagado],
            'repeat_period' => 0,
        ],
    ],
];

$auditoriaDisponible = idindWebhookAuditoriaDisponible($pdo);
$entregas = [];
$ultimas = ['ENCENDIDO' => null, 'APAGADO' => null];
if ($auditoriaDisponible) {
    $stmtEntregas = $pdo->prepare(
        'SELECT id, evento, salida_encendida, metodo, estado, cambio_estado,
                cambio_externo, alexa_enviados, alexa_errores_json,
                ultimo_error, recibido_en, procesado_en
         FROM entregas_webhook_shelly
         WHERE actuador_id = :actuador_id
         ORDER BY id DESC
         LIMIT 10'
    );
    $stmtEntregas->execute(['actuador_id' => $actuadorId]);
    $entregas = $stmtEntregas->fetchAll();
    foreach ($entregas as &$entrega) {
        foreach (['salida_encendida', 'cambio_estado', 'cambio_externo', 'alexa_enviados'] as $campo) {
            $entrega[$campo] = (int) $entrega[$campo];
        }
        $erroresAlexa = json_decode((string) ($entrega['alexa_errores_json'] ?? ''), true);
        $entrega['alexa_errores'] = is_array($erroresAlexa) ? $erroresAlexa : [];
        unset($entrega['alexa_errores_json']);
        $evento = (string) $entrega['evento'];
        if (array_key_exists($evento, $ultimas) && $ultimas[$evento] === null) {
            $ultimas[$evento] = $entrega;
        }
    }
    unset($entrega);
}

responderJson(200, [
    'ok' => true,
    'data' => [
        'actuador' => [
            'id' => (string) $actuador['id'],
            'nombre' => $actuador['nombre'],
            'ubicacion' => (string) $actuador['ubicacion'],
            'shelly_device_id' => (string) $actuador['shelly_device_id'],
            'modelo' => (string) $actuador['modelo'],
            'ip_local' => $actuador['ip_local'],
            'canal' => $canal,
            'fuente_estado' => $actuador['fuente'],
            'salida_encendida' => $actuador['salida_encendida'] === null
                ? null
                : (int) $actuador['salida_encendida'],
            'sincronizado_en' => $actuador['sincronizado_en'],
        ],
        'urls' => [
            'encendido' => $urlEncendido,
            'apagado' => $urlApagado,
            'prueba' => $urlPrueba,
        ],
        'rpc' => $rpc,
        'rpc_endpoint_local' => empty($actuador['ip_local'])
            ? null
            : 'http://' . trim((string) $actuador['ip_local'], '/') . '/rpc',
        'auditoria_disponible' => $auditoriaDisponible,
        'ultimas_entregas' => [
            'encendido' => $ultimas['ENCENDIDO'],
            'apagado' => $ultimas['APAGADO'],
        ],
        'entregas' => $entregas,
    ],
]);
