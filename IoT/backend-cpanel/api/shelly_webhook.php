<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/shelly.php';

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

$stmt = $pdo->prepare(
    'SELECT id FROM actuadores_shelly
     WHERE LOWER(shelly_device_id) = :device_id AND canal = :canal'
);
$stmt->execute(['device_id' => $deviceId, 'canal' => $canal]);
$actuadores = $stmt->fetchAll();
if ($actuadores === []) {
    responderJson(404, ['ok' => false, 'error' => 'Canal Shelly no registrado']);
}

foreach ($actuadores as $actuador) {
    $estado = [
        'online' => true,
        'salida_encendida' => $encendida,
        'potencia_w' => idindShellyNumero($entrada['apower'] ?? $entrada['power'] ?? null),
        'voltaje_v' => idindShellyNumero($entrada['voltage'] ?? null),
        'corriente_a' => idindShellyNumero($entrada['current'] ?? null),
        'temperatura_c' => idindShellyNumero($entrada['temperature'] ?? null),
        'errores' => [],
    ];
    idindShellyGuardarEstado(
        $pdo,
        $actuador,
        $estado,
        'WEBHOOK',
        null,
        $configLocal,
        'PHYSICAL_INTERACTION'
    );
    $pdo->prepare(
        "INSERT INTO eventos_shelly (
            actuador_id, evento, origen, salida_encendida, detalle_json
         ) VALUES (:actuador, :evento, 'WEBHOOK', :salida, :detalle)"
    )->execute([
        'actuador' => (string) $actuador['id'],
        'evento' => $encendida ? 'WEBHOOK_ENCENDIDO' : 'WEBHOOK_APAGADO',
        'salida' => $encendida ? 1 : 0,
        'detalle' => idindShellyJsonSeguro($entrada),
    ]);
}

responderJson(200, ['ok' => true, 'data' => ['actualizados' => count($actuadores)]]);
