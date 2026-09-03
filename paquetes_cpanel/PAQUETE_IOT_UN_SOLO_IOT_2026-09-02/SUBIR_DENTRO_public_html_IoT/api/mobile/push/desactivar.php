<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/mobile_auth.php';
requerirMetodo('POST');

$usuario = requerirTokenMovil();
$data = obtenerJson();
$expoPushToken = trim((string) ($data['expo_push_token'] ?? ''));

if (
    strlen($expoPushToken) > 255
    || !preg_match('/^Expo(?:nent)?PushToken\[[A-Za-z0-9_-]{20,220}\]$/', $expoPushToken)
) {
    responderJson(422, ['ok' => false, 'error' => 'Token push invalido']);
}

$stmt = $pdo->prepare(
    'UPDATE moviles_push
     SET activo = 0
     WHERE usuario_id = :usuario_id
       AND token_hash = :token_hash'
);
$stmt->execute([
    'usuario_id' => (int) $usuario['id'],
    'token_hash' => hash('sha256', $expoPushToken),
]);

responderJson(200, [
    'ok' => true,
    'data' => [
        'desactivada' => true,
    ],
]);
