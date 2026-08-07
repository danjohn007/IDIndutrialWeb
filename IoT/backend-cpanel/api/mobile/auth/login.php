<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/mobile_auth.php';
requerirMetodo('POST');

$data = obtenerJson();
$email = strtolower(trim((string) ($data['email'] ?? '')));
$password = (string) ($data['password'] ?? '');
$nombreDispositivo = trim((string) ($data['dispositivo'] ?? 'App ID Industrial'));

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
        ['ok' => false, 'error' => 'Correo o password incorrectos']
    );
}

if (password_needs_rehash((string) $usuario['password_hash'], PASSWORD_DEFAULT)) {
    $pdo->prepare(
        'UPDATE usuarios SET password_hash = :password_hash WHERE id = :id'
    )->execute([
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'id' => (int) $usuario['id'],
    ]);
}

$pdo->prepare(
    'UPDATE usuarios
     SET intentos_fallidos = 0, bloqueado_hasta = NULL, ultimo_acceso = UTC_TIMESTAMP()
     WHERE id = :id'
)->execute(['id' => (int) $usuario['id']]);

try {
    $token = crearTokenMovil($usuario, $nombreDispositivo);
} catch (PDOException $error) {
    error_log('ID Industrial mobile login: ' . $error->getMessage());
    responderJson(503, [
        'ok' => false,
        'error' => 'Acceso movil pendiente de instalar en la base de datos',
    ]);
}

responderJson(200, [
    'ok' => true,
    'data' => [
        'usuario' => usuarioMovilPublico($usuario),
        'sesion' => $token,
    ],
]);
