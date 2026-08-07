<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/auth.php';
requerirMetodo('POST');

$usuario = requerirSesion();
requerirCsrf($usuario);
$data = obtenerJson();
$passwordActual = (string) ($data['password_actual'] ?? '');
$passwordNueva = (string) ($data['password_nueva'] ?? '');

if ($passwordActual === '' || strlen($passwordActual) > 200) {
    responderJson(422, ['ok' => false, 'error' => 'Password actual invalido']);
}
if (
    strlen($passwordNueva) < 8
    || strlen($passwordNueva) > 200
    || !preg_match('/[a-z]/', $passwordNueva)
    || !preg_match('/[A-Z]/', $passwordNueva)
    || !preg_match('/[0-9]/', $passwordNueva)
) {
    responderJson(422, [
        'ok' => false,
        'error' => 'Usa al menos 8 caracteres, mayuscula, minuscula y numero',
    ]);
}
if (hash_equals($passwordActual, $passwordNueva)) {
    responderJson(422, ['ok' => false, 'error' => 'El nuevo password debe ser diferente']);
}

$stmtUsuario = $pdo->prepare(
    "SELECT password_hash
     FROM usuarios
     WHERE id = :id
       AND cliente_id = :cliente_id
       AND estado = 'ACTIVO'
     LIMIT 1"
);
$stmtUsuario->execute([
    'id' => (int) $usuario['id'],
    'cliente_id' => (int) $usuario['cliente_id'],
]);
$hashActual = $stmtUsuario->fetchColumn();

if (!$hashActual || !password_verify($passwordActual, (string) $hashActual)) {
    usleep(250000);
    responderJson(401, ['ok' => false, 'error' => 'El password actual no coincide']);
}

$stmtActualizar = $pdo->prepare(
    'UPDATE usuarios
     SET
        password_hash = :password_hash,
        intentos_fallidos = 0,
        bloqueado_hasta = NULL
     WHERE id = :id
       AND cliente_id = :cliente_id'
);
$stmtActualizar->execute([
    'password_hash' => password_hash($passwordNueva, PASSWORD_DEFAULT),
    'id' => (int) $usuario['id'],
    'cliente_id' => (int) $usuario['cliente_id'],
]);

limpiarSesion();
responderJson(200, [
    'ok' => true,
    'data' => ['requiere_login' => true],
]);
