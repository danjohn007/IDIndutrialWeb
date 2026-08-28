<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/shelly.php';
require_once __DIR__ . '/lib/shelly_webhooks.php';

$metodo = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($metodo, ['GET', 'POST'], true)) {
    responderJson(405, ['ok' => false, 'error' => 'Metodo no permitido']);
}

try {
    $esperado = idindShellyWebhookToken($configLocal);
} catch (Throwable $error) {
    responderJson(503, ['ok' => false, 'error' => 'Webhook Shelly no configurado']);
}
$recibido = trim((string) (
    $_SERVER['HTTP_X_SHELLY_WEBHOOK_TOKEN']
    ?? $_GET['token']
    ?? ''
));
if ($recibido === '' || !hash_equals($esperado, $recibido)) {
    // Error frecuente: usar la clave privada de Shelly Cloud en la URL del
    // webhook. Registrar solamente ese caso conocido permite diagnosticarlo
    // sin aceptar la peticion ni conservar ningun secreto.
    $cloudAuthKey = trim((string) ($configLocal['shelly_cloud_auth_key'] ?? ''));
    $usaClaveCloud = $recibido !== ''
        && $cloudAuthKey !== ''
        && hash_equals($cloudAuthKey, $recibido);
    if ($usaClaveCloud && idindWebhookAuditoriaDisponible($pdo)) {
        $deviceIdRechazado = strtolower(trim((string) ($_GET['device_id'] ?? $_GET['id'] ?? '')));
        $canalRechazado = filter_var(
            $_GET['channel'] ?? $_GET['canal'] ?? null,
            FILTER_VALIDATE_INT
        );
        $salidaRechazada = strtolower(trim((string) ($_GET['output'] ?? $_GET['on'] ?? '')));
        if (
            $deviceIdRechazado !== ''
            && strlen($deviceIdRechazado) <= 100
            && $canalRechazado !== false
            && $canalRechazado >= 0
            && $canalRechazado <= 31
            && in_array($salidaRechazada, ['0', '1', 'false', 'true', 'off', 'on'], true)
        ) {
            $encendidaRechazada = in_array($salidaRechazada, ['1', 'true', 'on'], true);
            $stmtRechazados = $pdo->prepare(
                'SELECT id FROM actuadores_shelly
                 WHERE LOWER(shelly_device_id) = :device_id AND canal = :canal'
            );
            $stmtRechazados->execute([
                'device_id' => $deviceIdRechazado,
                'canal' => $canalRechazado,
            ]);
            foreach ($stmtRechazados->fetchAll() as $actuadorRechazado) {
                $entregaRechazada = idindWebhookCrearEntrega(
                    $pdo,
                    (string) $actuadorRechazado['id'],
                    $encendidaRechazada,
                    $metodo,
                    [
                        'device_id' => $deviceIdRechazado,
                        'channel' => $canalRechazado,
                        'output' => $encendidaRechazada ? 1 : 0,
                    ]
                );
                idindWebhookCerrarEntrega(
                    $pdo,
                    $entregaRechazada,
                    'ERROR',
                    false,
                    false,
                    0,
                    [],
                    'La accion usa shelly_cloud_auth_key. Reemplazala con la URL generada por ID Industrial.'
                );
            }
        }
    }
    responderJson(401, ['ok' => false, 'error' => 'Token webhook invalido']);
}

$entrada = $_GET;
if ($metodo === 'POST') {
    $contenido = file_get_contents('php://input');
    $json = json_decode((string) $contenido, true);
    if (is_array($json)) {
        $entrada = array_merge($entrada, $json);
    }
}
$deviceId = strtolower(trim((string) ($entrada['device_id'] ?? $entrada['id'] ?? '')));
$canal = filter_var($entrada['channel'] ?? $entrada['canal'] ?? null, FILTER_VALIDATE_INT);
$salidaTexto = strtolower(trim((string) ($entrada['output'] ?? $entrada['on'] ?? '')));
if ($deviceId === '' || strlen($deviceId) > 100 || $canal === false || $canal < 0 || $canal > 31) {
    responderJson(422, ['ok' => false, 'error' => 'Identificacion Shelly invalida']);
}
if (!in_array($salidaTexto, ['0', '1', 'false', 'true', 'off', 'on'], true)) {
    responderJson(422, ['ok' => false, 'error' => 'Estado de salida invalido']);
}
$encendida = in_array($salidaTexto, ['1', 'true', 'on'], true);

// Nunca conservar secretos recibidos por query string dentro de la auditoria.
$detalleSeguro = $entrada;
unset($detalleSeguro['token']);

$stmt = $pdo->prepare(
    'SELECT id FROM actuadores_shelly
     WHERE LOWER(shelly_device_id) = :device_id AND canal = :canal'
);
$stmt->execute(['device_id' => $deviceId, 'canal' => $canal]);
$actuadores = $stmt->fetchAll();
if ($actuadores === []) {
    responderJson(404, ['ok' => false, 'error' => 'Canal Shelly no registrado']);
}

$esPrueba = in_array(
    strtolower(trim((string) ($entrada['probe'] ?? ''))),
    ['1', 'true', 'yes'],
    true
);
if ($esPrueba) {
    responderJson(200, [
        'ok' => true,
        'data' => [
            'receptor' => 'LISTO',
            'device_id' => $deviceId,
            'channel' => $canal,
            'actuadores' => array_values(array_map(
                static function (array $actuador): string {
                    return (string) $actuador['id'];
                },
                $actuadores
            )),
        ],
    ]);
}

$eventos = 0;
$notificaciones = 0;
$alexaEnviados = 0;
$alexaErrores = [];
$entregas = [];
$erroresProceso = [];
$auditoriaDisponible = idindWebhookAuditoriaDisponible($pdo);
foreach ($actuadores as $actuador) {
    $actuadorId = (string) $actuador['id'];
    $entregaId = idindWebhookCrearEntrega(
        $pdo,
        $actuadorId,
        $encendida,
        $metodo,
        $detalleSeguro
    );
    if ($entregaId !== null) {
        $entregas[] = $entregaId;
    }
    try {
    $estado = [
        'online' => true,
        'salida_encendida' => $encendida,
        'potencia_w' => idindShellyNumero($entrada['apower'] ?? $entrada['power'] ?? null),
        'voltaje_v' => idindShellyNumero($entrada['voltage'] ?? null),
        'corriente_a' => idindShellyNumero($entrada['current'] ?? null),
        'temperatura_c' => idindShellyNumero($entrada['temperature'] ?? null),
        'errores' => [],
    ];
    $resultadoEstado = idindShellyGuardarEstado(
        $pdo,
        $actuador,
        $estado,
        'WEBHOOK',
        null,
        $configLocal,
        'PHYSICAL_INTERACTION'
    );
    $alexaEnviados += (int) ($resultadoEstado['alexa']['enviados'] ?? 0);
    $alexaErrores = array_merge(
        $alexaErrores,
        is_array($resultadoEstado['alexa']['errores'] ?? null)
            ? $resultadoEstado['alexa']['errores']
            : []
    );
    $esCambioExterno = !empty($resultadoEstado['cambio_salida'])
        && !idindShellyEsConfirmacionComandoReciente(
            $pdo,
            $actuadorId,
            $encendida
        );
    idindWebhookCerrarEntrega(
        $pdo,
        $entregaId,
        'PROCESADA',
        !empty($resultadoEstado['cambio_salida']),
        $esCambioExterno,
        (int) ($resultadoEstado['alexa']['enviados'] ?? 0),
        is_array($resultadoEstado['alexa']['errores'] ?? null)
            ? $resultadoEstado['alexa']['errores']
            : []
    );
    if (!$esCambioExterno) {
        continue;
    }
    $pdo->prepare(
        "INSERT INTO eventos_shelly (
            actuador_id, evento, origen, salida_encendida, detalle_json
         ) VALUES (:actuador, :evento, 'WEBHOOK', :salida, :detalle)"
    )->execute([
        'actuador' => $actuadorId,
        'evento' => $encendida ? 'WEBHOOK_ENCENDIDO' : 'WEBHOOK_APAGADO',
        'salida' => $encendida ? 1 : 0,
        'detalle' => idindShellyJsonSeguro($detalleSeguro),
    ]);
    $eventoId = (int) $pdo->lastInsertId();
    $eventos++;
    $resultadoPush = idindShellyEncolarNotificacionEvento($pdo, $eventoId, $configLocal);
    $notificaciones += (int) ($resultadoPush['encoladas'] ?? 0);
    } catch (Throwable $error) {
        $mensaje = substr($error->getMessage(), 0, 500);
        $erroresProceso[] = $actuadorId . ': ' . $mensaje;
        idindWebhookCerrarEntrega(
            $pdo,
            $entregaId,
            'ERROR',
            false,
            false,
            0,
            [],
            $mensaje
        );
        error_log('ID Industrial webhook Shelly ' . $actuadorId . ': ' . $mensaje);
    }
}

responderJson($erroresProceso === [] ? 200 : 500, [
    'ok' => $erroresProceso === [],
    'data' => [
        'actualizados' => count($actuadores),
        'entregas_registradas' => $entregas,
        'auditoria_disponible' => $auditoriaDisponible,
        'cambios_externos' => $eventos,
        'notificaciones' => $notificaciones,
        'alexa_enviados' => $alexaEnviados,
        'alexa_errores' => $alexaErrores,
        'errores_proceso' => $erroresProceso,
    ],
]);
