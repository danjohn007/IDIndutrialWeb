<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$allowedOrigin = getenv('IDIND_ALLOWED_ORIGIN') ?: '';
if (
    $allowedOrigin !== ''
    && isset($_SERVER['HTTP_ORIGIN'])
    && hash_equals($allowedOrigin, (string) $_SERVER['HTTP_ORIGIN'])
) {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Access-Control-Allow-Headers: Authorization, Content-Type, X-API-TOKEN, X-CSRF-TOKEN, X-Requested-With');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Vary: Origin');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$sharedConfig = [];
$sharedConfigPaths = array_unique([
    dirname(__DIR__, 2) . '/crm/config.php',
    dirname(__DIR__, 3) . '/crm/config.php',
    dirname(__DIR__, 2) . '/sistema/crm/config.php',
    dirname(__DIR__, 3) . '/sistema/crm/config.php',
]);
foreach ($sharedConfigPaths as $sharedConfigPath) {
    if (!is_file($sharedConfigPath)) {
        continue;
    }
    $sharedConfigLoaded = require $sharedConfigPath;
    if (is_array($sharedConfigLoaded)) {
        $sharedConfig = $sharedConfigLoaded;
    }
    break;
}
if ($sharedConfig === []) {
    error_log('ID Industrial: no se encontro crm/config.php compartido');
}

$configLocal = is_array($sharedConfig['iot'] ?? null)
    ? $sharedConfig['iot']
    : [];
$dbHost = getenv('IDIND_DB_HOST') ?: ($sharedConfig['host'] ?? 'localhost');
$dbName = getenv('IDIND_DB_NAME') ?: ($sharedConfig['database'] ?? 'TU_BASE_DE_DATOS');
$dbUser = getenv('IDIND_DB_USER') ?: ($sharedConfig['username'] ?? 'TU_USUARIO');
$dbPass = getenv('IDIND_DB_PASS') ?: ($sharedConfig['password'] ?? 'TU_PASSWORD');
$apiToken = getenv('IDIND_API_TOKEN')
    ?: ($configLocal['api_token'] ?? 'CAMBIA_ESTE_TOKEN_SECRETO');
$setupTokenLocal = trim((string) ($configLocal['setup_token'] ?? ''));
$setupTokenEntorno = trim((string) (getenv('IDIND_SETUP_TOKEN') ?: ''));
if ($setupTokenLocal !== '') {
    $setupToken = $setupTokenLocal;
    $setupTokenOrigen = 'crm/config.php: iot';
} elseif ($setupTokenEntorno !== '') {
    $setupToken = $setupTokenEntorno;
    $setupTokenOrigen = 'variable_de_entorno';
} else {
    $setupToken = 'CAMBIA_ESTE_TOKEN_DE_INSTALACION';
    $setupTokenOrigen = 'valor_por_defecto';
}

function responderJson(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

function requerirMetodo(string $metodo): void
{
    $actual = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($actual !== strtoupper($metodo)) {
        header('Allow: ' . strtoupper($metodo));
        responderJson(405, ['ok' => false, 'error' => 'Metodo no permitido']);
    }
}

function obtenerJson(): array
{
    $contenido = file_get_contents('php://input');
    if ($contenido === false || trim($contenido) === '' || strlen($contenido) > 16384) {
        responderJson(400, ['ok' => false, 'error' => 'Cuerpo JSON vacio o demasiado grande']);
    }

    $data = json_decode($contenido, true);
    if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
        responderJson(400, ['ok' => false, 'error' => 'JSON invalido']);
    }

    return $data;
}

function validarTokenDispositivo(): void
{
    global $apiToken;

    if ($apiToken === 'CAMBIA_ESTE_TOKEN_SECRETO' || strlen($apiToken) < 32) {
        responderJson(503, ['ok' => false, 'error' => 'Token de API no configurado']);
    }

    $tokenRecibido = (string) ($_SERVER['HTTP_X_API_TOKEN'] ?? '');
    if ($tokenRecibido === '' || !hash_equals($apiToken, $tokenRecibido)) {
        responderJson(401, ['ok' => false, 'error' => 'Token invalido']);
    }
}

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]
    );
    $pdo->exec("SET time_zone = '+00:00'");
} catch (Throwable $error) {
    error_log('ID Industrial DB: ' . $error->getMessage());
    responderJson(500, ['ok' => false, 'error' => 'No fue posible conectar con la base de datos']);
}
