<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => false, 'error' => 'No encontrado']);
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/rutinas.php';

try {
    $resultados = idindRutinasProgramadasEjecutar($pdo, $configLocal, 5);
    echo json_encode([
        'ok' => true,
        'evaluado_en' => gmdate('Y-m-d H:i:s'),
        'ejecuciones' => $resultados,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $error) {
    error_log('ID Industrial cron rutinas: ' . $error->getMessage());
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
