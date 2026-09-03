<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$usuarioActual = requerirSesion(['ADMIN']);
$clienteId = (int) $usuarioActual['cliente_id'];

function usuarioNombreValido(string $nombre): string
{
    $nombre = trim($nombre);
    if (strlen($nombre) < 2 || strlen($nombre) > 100) {
        responderJson(422, ['ok' => false, 'error' => 'El nombre debe tener entre 2 y 100 caracteres']);
    }
    return $nombre;
}

function usuarioEmailValido(string $email): string
{
    $email = strtolower(trim($email));
    if (strlen($email) > 160 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        responderJson(422, ['ok' => false, 'error' => 'Correo electronico invalido']);
    }
    return $email;
}

function usuarioRolValido(string $rol): string
{
    $rol = strtoupper(trim($rol));
    if (!in_array($rol, ['ADMIN', 'OPERADOR', 'LECTURA'], true)) {
        responderJson(422, ['ok' => false, 'error' => 'Rol de usuario invalido']);
    }
    return $rol;
}

function usuarioEstadoValido(string $estado): string
{
    $estado = strtoupper(trim($estado));
    if (!in_array($estado, ['ACTIVO', 'BLOQUEADO', 'INACTIVO'], true)) {
        responderJson(422, ['ok' => false, 'error' => 'Estado de usuario invalido']);
    }
    return $estado;
}

function usuarioPasswordValido(string $password, bool $obligatoria = true): string
{
    if ($password === '' && !$obligatoria) {
        return '';
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
            'error' => 'La contraseña debe tener 8 caracteres, mayuscula, minuscula y numero',
        ]);
    }
    return $password;
}

function obtenerUsuarioCliente(PDO $pdo, int $id, int $clienteId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, cliente_id, nombre, email, rol, estado, intentos_fallidos,
                bloqueado_hasta, ultimo_acceso, creado_en, actualizado_en
         FROM usuarios
         WHERE id = :id AND cliente_id = :cliente_id
         LIMIT 1'
    );
    $stmt->execute(['id' => $id, 'cliente_id' => $clienteId]);
    $usuario = $stmt->fetch();
    return $usuario ?: null;
}

function asegurarAdministradorDisponible(PDO $pdo, int $clienteId, array $usuario, string $rol, string $estado): void
{
    if ((string) $usuario['rol'] !== 'ADMIN' || (string) $usuario['estado'] !== 'ACTIVO') {
        return;
    }
    if ($rol === 'ADMIN' && $estado === 'ACTIVO') {
        return;
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM usuarios
         WHERE cliente_id = :cliente_id
           AND rol = 'ADMIN'
           AND estado = 'ACTIVO'
           AND id <> :id"
    );
    $stmt->execute(['cliente_id' => $clienteId, 'id' => (int) $usuario['id']]);
    if ((int) $stmt->fetchColumn() < 1) {
        responderJson(422, ['ok' => false, 'error' => 'Debe existir al menos un administrador activo']);
    }
}

function usuarioPublico(array $usuario): array
{
    return [
        'id' => (int) $usuario['id'],
        'nombre' => (string) $usuario['nombre'],
        'email' => (string) $usuario['email'],
        'rol' => (string) $usuario['rol'],
        'estado' => (string) $usuario['estado'],
        'intentos_fallidos' => (int) $usuario['intentos_fallidos'],
        'bloqueado_hasta' => $usuario['bloqueado_hasta'],
        'ultimo_acceso' => $usuario['ultimo_acceso'],
        'creado_en' => $usuario['creado_en'],
        'actualizado_en' => $usuario['actualizado_en'],
    ];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $stmt = $pdo->prepare(
        "SELECT id, nombre, email, rol, estado, intentos_fallidos,
                bloqueado_hasta, ultimo_acceso, creado_en, actualizado_en
         FROM usuarios
         WHERE cliente_id = :cliente_id
         ORDER BY FIELD(estado, 'ACTIVO', 'BLOQUEADO', 'INACTIVO'), nombre ASC"
    );
    $stmt->execute(['cliente_id' => $clienteId]);
    responderJson(200, [
        'ok' => true,
        'data' => ['usuarios' => array_map('usuarioPublico', $stmt->fetchAll())],
    ]);
}

requerirMetodo('POST');
requerirCsrf($usuarioActual);
$data = obtenerJson();
$accion = strtolower(trim((string) ($data['accion'] ?? '')));

if ($accion === 'crear') {
    $nombre = usuarioNombreValido((string) ($data['nombre'] ?? ''));
    $email = usuarioEmailValido((string) ($data['email'] ?? ''));
    $password = usuarioPasswordValido((string) ($data['password'] ?? ''));
    $rol = usuarioRolValido((string) ($data['rol'] ?? 'LECTURA'));
    $estado = usuarioEstadoValido((string) ($data['estado'] ?? 'ACTIVO'));

    $stmtExiste = $pdo->prepare('SELECT id FROM usuarios WHERE email = :email LIMIT 1');
    $stmtExiste->execute(['email' => $email]);
    if ($stmtExiste->fetchColumn()) {
        responderJson(409, ['ok' => false, 'error' => 'Ese correo ya esta registrado']);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO usuarios (cliente_id, nombre, email, password_hash, rol, estado)
         VALUES (:cliente_id, :nombre, :email, :password_hash, :rol, :estado)'
    );
    $stmt->execute([
        'cliente_id' => $clienteId,
        'nombre' => $nombre,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'rol' => $rol,
        'estado' => $estado,
    ]);

    $creado = obtenerUsuarioCliente($pdo, (int) $pdo->lastInsertId(), $clienteId);
    responderJson(201, ['ok' => true, 'data' => ['usuario' => usuarioPublico($creado ?? [])]]);
}

$id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id < 1) {
    responderJson(422, ['ok' => false, 'error' => 'Usuario invalido']);
}

$objetivo = obtenerUsuarioCliente($pdo, (int) $id, $clienteId);
if (!$objetivo) {
    responderJson(404, ['ok' => false, 'error' => 'Usuario no encontrado']);
}

if ($accion === 'actualizar') {
    $nombre = usuarioNombreValido((string) ($data['nombre'] ?? $objetivo['nombre']));
    $email = usuarioEmailValido((string) ($data['email'] ?? $objetivo['email']));
    $rol = usuarioRolValido((string) ($data['rol'] ?? $objetivo['rol']));
    $estado = usuarioEstadoValido((string) ($data['estado'] ?? $objetivo['estado']));
    $password = usuarioPasswordValido((string) ($data['password'] ?? ''), false);

    if ((int) $id === (int) $usuarioActual['id'] && ($rol !== 'ADMIN' || $estado !== 'ACTIVO')) {
        responderJson(422, ['ok' => false, 'error' => 'No puedes quitarte tus propios permisos administrativos']);
    }
    asegurarAdministradorDisponible($pdo, $clienteId, $objetivo, $rol, $estado);

    $stmtExiste = $pdo->prepare(
        'SELECT id FROM usuarios WHERE email = :email AND id <> :id LIMIT 1'
    );
    $stmtExiste->execute(['email' => $email, 'id' => (int) $id]);
    if ($stmtExiste->fetchColumn()) {
        responderJson(409, ['ok' => false, 'error' => 'Ese correo ya esta registrado']);
    }

    $sql = 'UPDATE usuarios
            SET nombre = :nombre, email = :email, rol = :rol, estado = :estado';
    $params = [
        'nombre' => $nombre,
        'email' => $email,
        'rol' => $rol,
        'estado' => $estado,
        'id' => (int) $id,
        'cliente_id' => $clienteId,
    ];
    if ($password !== '') {
        $sql .= ', password_hash = :password_hash, intentos_fallidos = 0, bloqueado_hasta = NULL';
        $params['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
    }
    $sql .= ' WHERE id = :id AND cliente_id = :cliente_id';
    $pdo->prepare($sql)->execute($params);

    $actualizado = obtenerUsuarioCliente($pdo, (int) $id, $clienteId);
    responderJson(200, ['ok' => true, 'data' => ['usuario' => usuarioPublico($actualizado ?? [])]]);
}

if ($accion === 'cambiar_estado') {
    $estado = usuarioEstadoValido((string) ($data['estado'] ?? ''));
    if ((int) $id === (int) $usuarioActual['id'] && $estado !== 'ACTIVO') {
        responderJson(422, ['ok' => false, 'error' => 'No puedes desactivar tu propia cuenta']);
    }
    asegurarAdministradorDisponible($pdo, $clienteId, $objetivo, (string) $objetivo['rol'], $estado);

    $stmt = $pdo->prepare(
        "UPDATE usuarios
         SET estado = :estado,
             intentos_fallidos = IF(:estado_activo_1 = 'ACTIVO', 0, intentos_fallidos),
             bloqueado_hasta = IF(:estado_activo_2 = 'ACTIVO', NULL, bloqueado_hasta)
         WHERE id = :id AND cliente_id = :cliente_id"
    );
    $stmt->execute([
        'estado' => $estado,
        'estado_activo_1' => $estado,
        'estado_activo_2' => $estado,
        'id' => (int) $id,
        'cliente_id' => $clienteId,
    ]);
    responderJson(200, ['ok' => true, 'data' => ['usuario' => usuarioPublico(obtenerUsuarioCliente($pdo, (int) $id, $clienteId) ?? [])]]);
}

responderJson(422, ['ok' => false, 'error' => 'Accion de usuario no reconocida']);
