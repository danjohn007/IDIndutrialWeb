<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => false, 'error' => 'No encontrado']);
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/shelly.php';

try {
    if (!idindShellyConfigurado($configLocal)) {
        throw new IdindShellyException('Shelly Cloud no esta configurado');
    }
    $comandos = idindShellyProcesarPendientes($pdo, $configLocal, 10);
    $clientes = $pdo->query(
        "SELECT DISTINCT cliente_id FROM actuadores_shelly
         WHERE estado = 'Activo' AND modo_control IN ('CLOUD', 'HIBRIDO')"
    )->fetchAll();
    $sincronizaciones = [];
    foreach ($clientes as $cliente) {
        $clienteId = (int) $cliente['cliente_id'];
        $sincronizaciones[$clienteId] = idindShellySincronizar(
            $pdo,
            $configLocal,
            $clienteId
        );
    }
    echo json_encode([
        'ok' => true,
        'comandos' => $comandos,
        'sincronizaciones' => $sincronizaciones,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $error) {
    error_log('ID Industrial cron Shelly: ' . $error->getMessage());
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}

