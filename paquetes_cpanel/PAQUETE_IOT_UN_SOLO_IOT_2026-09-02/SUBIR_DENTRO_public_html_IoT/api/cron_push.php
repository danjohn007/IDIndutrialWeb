<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => false, 'error' => 'No encontrado']);
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/push_notificaciones.php';

function salidaCron(array $datos, int $codigo = 0): void
{
    echo json_encode(
        $datos,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) . PHP_EOL;
    exit($codigo);
}

try {
    $pdo->exec(
        "UPDATE notificaciones_push
         SET estado = 'REINTENTAR',
             disponible_en = UTC_TIMESTAMP(),
             ultimo_error = 'Envio interrumpido; se recupero la cola'
         WHERE estado = 'ENVIANDO'
           AND actualizado_en < UTC_TIMESTAMP() - INTERVAL 10 MINUTE"
    );

    $pendientes = idindPushTomarPendientes($pdo, 50);
    $resultado = idindPushEnviarFilas($pdo, $pendientes, $configLocal, 10, 25);
    salidaCron($resultado, ($resultado['ok'] ?? false) ? 0 : 1);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('ID Industrial cron push: ' . $error->getMessage());
    salidaCron(['ok' => false, 'error' => 'Fallo interno del cron push'], 1);
}
