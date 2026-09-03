<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/auth.php';
requerirMetodo('POST');

if (
    trim((string) $setupToken) === 'CAMBIA_ESTE_TOKEN_DE_INSTALACION'
    || strlen(trim((string) $setupToken)) < 32
) {
    responderJson(503, ['ok' => false, 'error' => 'Token de instalacion no configurado']);
}

$data = obtenerJson();
$token = trim((string) ($data['setup_token'] ?? ''));
$tokenConfigurado = trim((string) $setupToken);
$clienteEmail = strtolower(trim((string) ($data['cliente_email'] ?? '')));
$nombre = trim((string) ($data['nombre'] ?? ''));
$email = strtolower(trim((string) ($data['email'] ?? '')));
$password = (string) ($data['password'] ?? '');
$textLength = static function (string $value): int {
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
};

if ($token === '' || !hash_equals($tokenConfigurado, $token)) {
    responderJson(403, [
        'ok' => false,
        'error' => 'Token de instalacion invalido',
        'codigo' => 'SETUP_TOKEN_MISMATCH',
        'detalle' => [
            'origen_configurado' => (string) ($setupTokenOrigen ?? 'desconocido'),
            'config_local_detectado' => is_file($rutaConfigLocal ?? ''),
            'longitud_configurada' => strlen($tokenConfigurado),
            'longitud_recibida' => strlen($token),
        ],
    ]);
}
if (!filter_var($clienteEmail, FILTER_VALIDATE_EMAIL)) {
    responderJson(422, ['ok' => false, 'error' => 'Correo del cliente invalido']);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 160) {
    responderJson(422, ['ok' => false, 'error' => 'Correo del administrador invalido']);
}
if ($textLength($nombre) < 2 || $textLength($nombre) > 100) {
    responderJson(422, ['ok' => false, 'error' => 'Nombre invalido']);
}
if (
    strlen($password) < 8
    || strlen($password) > 200
    || !preg_match('/[a-z]/', $password)
    || !preg_match('/[A-Z]/', $password)
    || !preg_match('/[0-9]/', $password)
) {
    responderJson(422, [
        'ok' => false,
        'error' => 'Usa al menos 8 caracteres, mayuscula, minuscula y numero',
    ]);
}

try {
    $pdo->beginTransaction();

    $totalUsuarios = (int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
    if ($totalUsuarios > 0) {
        $pdo->rollBack();
        responderJson(409, ['ok' => false, 'error' => 'El administrador inicial ya fue creado']);
    }

    $stmtCliente = $pdo->prepare(
        'SELECT id FROM clientes WHERE email = :email LIMIT 1'
    );
    $stmtCliente->execute(['email' => $clienteEmail]);
    $clienteId = $stmtCliente->fetchColumn();
    if (!$clienteId) {
        $pdo->rollBack();
        responderJson(404, ['ok' => false, 'error' => 'No existe un cliente con ese correo']);
    }

    $stmtUsuario = $pdo->prepare(
        "INSERT INTO usuarios (
            cliente_id, nombre, email, password_hash, rol, estado
         ) VALUES (
            :cliente_id, :nombre, :email, :password_hash, 'ADMIN', 'ACTIVO'
         )"
    );
    $stmtUsuario->execute([
        'cliente_id' => (int) $clienteId,
        'nombre' => $nombre,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);

    $usuario = [
        'id' => (int) $pdo->lastInsertId(),
        'cliente_id' => (int) $clienteId,
        'nombre' => $nombre,
        'email' => $email,
        'rol' => 'ADMIN',
    ];
    $pdo->commit();

    $sesion = crearSesionUsuario($usuario);
    responderJson(201, [
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
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('ID Industrial alta inicial: ' . $error->getMessage());
    responderJson(500, ['ok' => false, 'error' => 'No fue posible crear el administrador']);
}
