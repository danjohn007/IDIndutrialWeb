<?php
declare(strict_types=1);

if (!isset($pdo) || !($pdo instanceof PDO)) {
    require_once __DIR__ . '/config.php';
}

const IDIND_SESSION_TIMEOUT = 28800;
const IDIND_SESSION_ROTATION = 1800;
const IDIND_SESSION_USER_CHECK = 300;

function iniciarSesionSegura(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $https = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
    );

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', $https ? '1' : '0');
    ini_set('session.cookie_samesite', 'Lax');
    session_name('IDINDSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function limpiarSesion(): void
{
    iniciarSesionSegura();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => 'Lax',
        ]);
    }

    session_destroy();
}

function crearSesionUsuario(array $usuario): array
{
    iniciarSesionSegura();
    session_regenerate_id(true);

    $ahora = time();
    $_SESSION['usuario_id'] = (int) $usuario['id'];
    $_SESSION['cliente_id'] = (int) $usuario['cliente_id'];
    $_SESSION['nombre'] = (string) $usuario['nombre'];
    $_SESSION['email'] = (string) $usuario['email'];
    $_SESSION['rol'] = (string) $usuario['rol'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['ultimo_acceso_ts'] = $ahora;
    $_SESSION['rotada_en_ts'] = $ahora;
    $_SESSION['usuario_validado_ts'] = $ahora;

    return usuarioSesionActual();
}

function usuarioSesionActual(): array
{
    return [
        'id' => (int) ($_SESSION['usuario_id'] ?? 0),
        'cliente_id' => (int) ($_SESSION['cliente_id'] ?? 0),
        'nombre' => (string) ($_SESSION['nombre'] ?? ''),
        'email' => (string) ($_SESSION['email'] ?? ''),
        'rol' => (string) ($_SESSION['rol'] ?? ''),
        'csrf_token' => (string) ($_SESSION['csrf_token'] ?? ''),
    ];
}

function requerirSesion(array $roles = []): array
{
    global $pdo;

    iniciarSesionSegura();

    $usuario = usuarioSesionActual();
    $ultimoAcceso = (int) ($_SESSION['ultimo_acceso_ts'] ?? 0);
    if (
        $usuario['id'] < 1
        || $usuario['cliente_id'] < 1
        || $ultimoAcceso < 1
        || time() - $ultimoAcceso > IDIND_SESSION_TIMEOUT
    ) {
        limpiarSesion();
        responderJson(401, ['ok' => false, 'error' => 'Sesion requerida']);
    }

    if (time() - (int) ($_SESSION['usuario_validado_ts'] ?? 0) > IDIND_SESSION_USER_CHECK) {
        $stmt = $pdo->prepare(
            "SELECT nombre, email, rol
             FROM usuarios
             WHERE id = :id
               AND cliente_id = :cliente_id
               AND estado = 'ACTIVO'
             LIMIT 1"
        );
        $stmt->execute([
            'id' => $usuario['id'],
            'cliente_id' => $usuario['cliente_id'],
        ]);
        $usuarioVigente = $stmt->fetch();
        if (!$usuarioVigente) {
            limpiarSesion();
            responderJson(401, ['ok' => false, 'error' => 'Sesion no vigente']);
        }

        $_SESSION['nombre'] = (string) $usuarioVigente['nombre'];
        $_SESSION['email'] = (string) $usuarioVigente['email'];
        $_SESSION['rol'] = (string) $usuarioVigente['rol'];
        $_SESSION['usuario_validado_ts'] = time();
        $usuario = usuarioSesionActual();
    }

    if ($roles && !in_array($usuario['rol'], $roles, true)) {
        session_write_close();
        responderJson(403, ['ok' => false, 'error' => 'Permisos insuficientes']);
    }

    $_SESSION['ultimo_acceso_ts'] = time();
    if (time() - (int) ($_SESSION['rotada_en_ts'] ?? 0) > IDIND_SESSION_ROTATION) {
        session_regenerate_id(true);
        $_SESSION['rotada_en_ts'] = time();
    }

    $usuario = usuarioSesionActual();
    session_write_close();
    return $usuario;
}

function requerirCsrf(array $usuario): void
{
    $recibido = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $esperado = (string) ($usuario['csrf_token'] ?? '');
    if ($esperado === '' || $recibido === '' || !hash_equals($esperado, $recibido)) {
        responderJson(403, ['ok' => false, 'error' => 'Token CSRF invalido']);
    }
}
