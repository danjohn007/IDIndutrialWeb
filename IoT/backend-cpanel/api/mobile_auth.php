<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

const IDIND_MOBILE_TOKEN_DAYS = 30;

function tokenBearerMovil(): string
{
    $cabecera = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
    if ($cabecera === '' && function_exists('getallheaders')) {
        $cabeceras = getallheaders();
        if (is_array($cabeceras)) {
            $cabecera = trim((string) ($cabeceras['Authorization'] ?? $cabeceras['authorization'] ?? ''));
        }
    }

    if (!preg_match('/^Bearer\s+([A-Za-z0-9_-]{43,128})$/i', $cabecera, $coincidencias)) {
        responderJson(401, ['ok' => false, 'error' => 'Token movil requerido']);
    }

    return $coincidencias[1];
}

function hashTokenMovil(string $token): string
{
    return hash('sha256', $token);
}

function crearTokenMovil(array $usuario, string $nombreDispositivo): array
{
    global $pdo;

    $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $tokenHash = hashTokenMovil($token);
    $nombreDispositivo = trim($nombreDispositivo);
    if ($nombreDispositivo === '') {
        $nombreDispositivo = 'Dispositivo movil';
    }
    $nombreDispositivo = substr($nombreDispositivo, 0, 120);
    $expiraEn = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
        ->modify('+' . IDIND_MOBILE_TOKEN_DAYS . ' days')
        ->format('Y-m-d H:i:s');

    $pdo->prepare(
        'DELETE FROM tokens_moviles
         WHERE usuario_id = :usuario_id
           AND (revocado_en IS NOT NULL OR expira_en < UTC_TIMESTAMP())'
    )->execute(['usuario_id' => (int) $usuario['id']]);

    $stmt = $pdo->prepare(
        'INSERT INTO tokens_moviles (
            usuario_id, token_hash, nombre_dispositivo, expira_en
         ) VALUES (
            :usuario_id, :token_hash, :nombre_dispositivo, :expira_en
         )'
    );
    $stmt->execute([
        'usuario_id' => (int) $usuario['id'],
        'token_hash' => $tokenHash,
        'nombre_dispositivo' => $nombreDispositivo,
        'expira_en' => $expiraEn,
    ]);

    return [
        'token' => $token,
        'tipo' => 'Bearer',
        'expira_en' => $expiraEn,
    ];
}

function requerirTokenMovil(array $roles = []): array
{
    global $pdo;

    $tokenHash = hashTokenMovil(tokenBearerMovil());
    $stmt = $pdo->prepare(
        "SELECT
            t.id AS token_id,
            t.token_hash,
            t.expira_en,
            u.id,
            u.cliente_id,
            u.nombre,
            u.email,
            u.rol
         FROM tokens_moviles t
         INNER JOIN usuarios u ON u.id = t.usuario_id
         INNER JOIN clientes c ON c.id = u.cliente_id
         WHERE t.token_hash = :token_hash
           AND t.revocado_en IS NULL
           AND t.expira_en > UTC_TIMESTAMP()
           AND u.estado = 'ACTIVO'
         LIMIT 1"
    );
    $stmt->execute(['token_hash' => $tokenHash]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        responderJson(401, ['ok' => false, 'error' => 'Token movil invalido o vencido']);
    }
    if ($roles && !in_array((string) $usuario['rol'], $roles, true)) {
        responderJson(403, ['ok' => false, 'error' => 'Permisos insuficientes']);
    }

    $pdo->prepare(
        'UPDATE tokens_moviles
         SET ultimo_uso = UTC_TIMESTAMP()
         WHERE id = :id
           AND (ultimo_uso IS NULL OR ultimo_uso < UTC_TIMESTAMP() - INTERVAL 5 MINUTE)'
    )->execute(['id' => (int) $usuario['token_id']]);

    return [
        'id' => (int) $usuario['id'],
        'cliente_id' => (int) $usuario['cliente_id'],
        'nombre' => (string) $usuario['nombre'],
        'email' => (string) $usuario['email'],
        'rol' => (string) $usuario['rol'],
        'token_id' => (int) $usuario['token_id'],
        'token_hash' => (string) $usuario['token_hash'],
        'expira_en' => (string) $usuario['expira_en'],
    ];
}

function usuarioMovilPublico(array $usuario): array
{
    return [
        'id' => (int) $usuario['id'],
        'nombre' => (string) $usuario['nombre'],
        'email' => (string) $usuario['email'],
        'rol' => (string) $usuario['rol'],
    ];
}
