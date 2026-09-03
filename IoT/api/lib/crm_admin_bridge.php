<?php
declare(strict_types=1);

function idindCrmBridgeTableExists(PDO $pdo, string $table): bool
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        return false;
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table'
        );
        $stmt->execute(['table' => $table]);
        return (int) $stmt->fetchColumn() > 0;
    } catch (Throwable $error) {
        return false;
    }
}

function idindCrmBridgeIsAdminRole(string $role): bool
{
    return in_array(strtolower(trim($role)), ['admin', 'superadmin'], true);
}

function idindCrmBridgeShortText(string $value, int $max): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?: '');
    if ($value === '') {
        return '';
    }
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $max, 'UTF-8');
    }
    return substr($value, 0, $max);
}

function idindCrmBridgeAuthenticateAdmin(PDO $pdo, string $email, string $password): ?array
{
    if (!idindCrmBridgeTableExists($pdo, 'users')) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, name, email, password_hash, role
         FROM users
         WHERE LOWER(email) = :email
         LIMIT 1'
    );
    $stmt->execute(['email' => strtolower($email)]);
    $crmUser = $stmt->fetch();

    if (
        !$crmUser
        || !idindCrmBridgeIsAdminRole((string) ($crmUser['role'] ?? ''))
        || !password_verify($password, (string) ($crmUser['password_hash'] ?? ''))
    ) {
        return null;
    }

    return $crmUser;
}

function idindCrmBridgePrimaryClientId(PDO $pdo): int
{
    $clientId = (int) ($pdo->query('SELECT id FROM clientes ORDER BY id LIMIT 1')->fetchColumn() ?: 0);
    if ($clientId > 0) {
        return $clientId;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO clientes (nombre_empresa, email, password_hash)
         VALUES (:nombre, :email, :password_hash)'
    );
    $stmt->execute([
        'nombre' => 'ID Industrial',
        'email' => 'admin@idactivos.com',
        'password_hash' => password_hash(bin2hex(random_bytes(18)), PASSWORD_DEFAULT),
    ]);

    return (int) $pdo->lastInsertId();
}

function idindCrmBridgeSyncAdminUser(
    PDO $pdo,
    array $crmUser,
    ?array $existingIotUser,
    string $password
): array {
    $email = strtolower(trim((string) ($crmUser['email'] ?? '')));
    $name = idindCrmBridgeShortText((string) ($crmUser['name'] ?? ''), 100);
    if ($name === '') {
        $name = 'Administrador';
    }

    if (!$existingIotUser) {
        $stmt = $pdo->prepare(
            'SELECT id, cliente_id, nombre, email, rol
             FROM usuarios
             WHERE LOWER(email) = :email
             LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $existingIotUser = $stmt->fetch() ?: null;
    }

    $clientId = (int) ($existingIotUser['cliente_id'] ?? 0);
    if ($clientId < 1) {
        $clientId = idindCrmBridgePrimaryClientId($pdo);
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    if ($existingIotUser) {
        $stmt = $pdo->prepare(
            "UPDATE usuarios
             SET cliente_id = :cliente_id,
                 nombre = :nombre,
                 password_hash = :password_hash,
                 rol = 'ADMIN',
                 estado = 'ACTIVO',
                 intentos_fallidos = 0,
                 bloqueado_hasta = NULL
             WHERE id = :id"
        );
        $stmt->execute([
            'cliente_id' => $clientId,
            'nombre' => $name,
            'password_hash' => $passwordHash,
            'id' => (int) $existingIotUser['id'],
        ]);

        return [
            'id' => (int) $existingIotUser['id'],
            'cliente_id' => $clientId,
            'nombre' => $name,
            'email' => $email,
            'password_hash' => $passwordHash,
            'rol' => 'ADMIN',
            'estado' => 'ACTIVO',
            'intentos_fallidos' => 0,
            'bloqueado_hasta' => null,
        ];
    }

    $stmt = $pdo->prepare(
        "INSERT INTO usuarios
         (cliente_id, nombre, email, password_hash, rol, estado)
         VALUES
         (:cliente_id, :nombre, :email, :password_hash, 'ADMIN', 'ACTIVO')"
    );
    $stmt->execute([
        'cliente_id' => $clientId,
        'nombre' => $name,
        'email' => $email,
        'password_hash' => $passwordHash,
    ]);

    return [
        'id' => (int) $pdo->lastInsertId(),
        'cliente_id' => $clientId,
        'nombre' => $name,
        'email' => $email,
        'password_hash' => $passwordHash,
        'rol' => 'ADMIN',
        'estado' => 'ACTIVO',
        'intentos_fallidos' => 0,
        'bloqueado_hasta' => null,
    ];
}

function idindCrmBridgeLoginAsIotAdmin(
    PDO $pdo,
    string $email,
    string $password,
    ?array $existingIotUser = null
): ?array {
    $crmUser = idindCrmBridgeAuthenticateAdmin($pdo, $email, $password);
    if (!$crmUser) {
        return null;
    }

    return idindCrmBridgeSyncAdminUser($pdo, $crmUser, $existingIotUser, $password);
}
