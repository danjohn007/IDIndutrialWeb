<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/mobile_auth.php';
requerirMetodo('POST');

$usuario = requerirTokenMovil();
$data = obtenerJson();
$expoPushToken = trim((string) ($data['expo_push_token'] ?? ''));
$plataforma = strtoupper(trim((string) ($data['plataforma'] ?? '')));
$nombreDispositivo = trim((string) ($data['nombre_dispositivo'] ?? ''));

if (
    strlen($expoPushToken) > 255
    || !preg_match('/^Expo(?:nent)?PushToken\[[A-Za-z0-9_-]{20,220}\]$/', $expoPushToken)
) {
    responderJson(422, ['ok' => false, 'error' => 'Token push invalido']);
}
if (!in_array($plataforma, ['ANDROID', 'IOS'], true)) {
    responderJson(422, ['ok' => false, 'error' => 'Plataforma no permitida']);
}
if ($nombreDispositivo === '') {
    $nombreDispositivo = 'ID Industrial ' . ucfirst(strtolower($plataforma));
}
$nombreDispositivo = substr($nombreDispositivo, 0, 120);
$tokenHash = hash('sha256', $expoPushToken);

$stmt = $pdo->prepare(
    'INSERT INTO moviles_push (
        usuario_id, sesion_movil_id, token_hash, expo_push_token,
        plataforma, nombre_dispositivo, activo, ultimo_registro
     ) VALUES (
        :usuario_id, :sesion_movil_id, :token_hash, :expo_push_token,
        :plataforma, :nombre_dispositivo, 1, UTC_TIMESTAMP()
     )
     ON DUPLICATE KEY UPDATE
        usuario_id = VALUES(usuario_id),
        sesion_movil_id = VALUES(sesion_movil_id),
        expo_push_token = VALUES(expo_push_token),
        plataforma = VALUES(plataforma),
        nombre_dispositivo = VALUES(nombre_dispositivo),
        activo = 1,
        ultimo_registro = UTC_TIMESTAMP()'
);
$stmt->execute([
    'usuario_id' => (int) $usuario['id'],
    'sesion_movil_id' => (int) $usuario['token_id'],
    'token_hash' => $tokenHash,
    'expo_push_token' => $expoPushToken,
    'plataforma' => $plataforma,
    'nombre_dispositivo' => $nombreDispositivo,
]);

responderJson(200, [
    'ok' => true,
    'data' => [
        'activa' => true,
        'plataforma' => $plataforma,
        'nombre_dispositivo' => $nombreDispositivo,
    ],
]);
