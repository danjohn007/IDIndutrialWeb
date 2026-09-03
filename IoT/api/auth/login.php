<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/lib/crm_admin_bridge.php';
requerirMetodo('POST');

$data = obtenerJson();
$email = strtolower(trim((string) ($data['email'] ?? '')));
$password = (string) ($data['password'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 160) {
    responderJson(422, ['ok' => false, 'error' => 'Credenciales invalidas']);
}
if ($password === '' || strlen($password) > 200) {
    responderJson(422, ['ok' => false, 'error' => 'Credenciales invalidas']);
}

$stmt = $pdo->prepare(
    "SELECT
        u.id,
        u.cliente_id,
        u.nombre,
        u.email,
        u.password_hash,
        u.rol,
        u.estado,
        u.intentos_fallidos,
        u.bloqueado_hasta
     FROM usuarios u
     INNER JOIN clientes c ON c.id = u.cliente_id
     WHERE u.email = :email
     LIMIT 1"
);
$stmt->execute(['email' => $email]);
$usuario = $stmt->fetch();

$dummyHash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
$hash = $usuario ? (string) $usuario['password_hash'] : $dummyHash;
$passwordCorrecto = password_verify($password, $hash);
$bloqueado = $usuario
    && $usuario['bloqueado_hasta']
    && strtotime((string) $usuario['bloqueado_hasta'] . ' UTC') > time();
$activo = $usuario && (string) $usuario['estado'] === 'ACTIVO';

if (!$usuario || !$passwordCorrecto || !$activo || $bloqueado) {
    $usuarioCrmAdmin = idindCrmBridgeLoginAsIotAdmin(
        $pdo,
        $email,
        $password,
        $usuario ?: null
    );
    if ($usuarioCrmAdmin) {
        $usuario = $usuarioCrmAdmin;
        $passwordCorrecto = true;
        $activo = true;
        $bloqueado = false;
    }
}

if (!$usuario || !$passwordCorrecto || !$activo || $bloqueado) {
    if ($usuario && !$bloqueado) {
        $intentos = min(255, (int) $usuario['intentos_fallidos'] + 1);
        $stmtFallo = $pdo->prepare(
            "UPDATE usuarios
             SET
                intentos_fallidos = :intentos,
                bloqueado_hasta = CASE
                    WHEN :intentos_bloqueo >= 5
                    THEN UTC_TIMESTAMP() + INTERVAL 15 MINUTE
                    ELSE NULL
                END
             WHERE id = :id"
        );
        $stmtFallo->execute([
            'intentos' => $intentos,
            'intentos_bloqueo' => $intentos,
            'id' => (int) $usuario['id'],
        ]);
    }

    usleep(250000);
    responderJson(
        $bloqueado ? 429 : 401,
        ['ok' => false, 'error' => 'Correo o contrasena incorrectos']
    );
}

if (password_needs_rehash((string) $usuario['password_hash'], PASSWORD_DEFAULT)) {
    $nuevoHash = password_hash($password, PASSWORD_DEFAULT);
    $stmtRehash = $pdo->prepare(
        'UPDATE usuarios SET password_hash = :password_hash WHERE id = :id'
    );
    $stmtRehash->execute([
        'password_hash' => $nuevoHash,
        'id' => (int) $usuario['id'],
    ]);
}

$stmtAcceso = $pdo->prepare(
    'UPDATE usuarios
     SET intentos_fallidos = 0, bloqueado_hasta = NULL, ultimo_acceso = UTC_TIMESTAMP()
     WHERE id = :id'
);
$stmtAcceso->execute(['id' => (int) $usuario['id']]);

$sesion = crearSesionUsuario($usuario);
responderJson(200, [
    'ok' => true,
    'data' => [
        'usuario' => [
            'id' => $sesion['id'],
            'nombre' => $sesion['nombre'],
            'email' => $sesion['email'],
            'rol' => $sesion['rol'],
        ],
        'csrf_token' => $sesion['csrf_token'],
    ],
]);
