<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/shelly.php';

requerirMetodo('POST');
$usuario = requerirSesion();
requerirCsrf($usuario);

$clienteId = (int) $usuario['cliente_id'];
$intervaloMinimo = 5;
$lockName = 'idind_shelly_live_' . $clienteId;
$stmtLock = $pdo->prepare('SELECT GET_LOCK(:nombre, 1)');
$stmtLock->execute(['nombre' => $lockName]);

if ((int) $stmtLock->fetchColumn() !== 1) {
    responderJson(200, [
        'ok' => true,
        'data' => ['sincronizado' => false, 'motivo' => 'SINCRONIZACION_EN_CURSO'],
    ]);
}

try {
    $shellyCloudDisponible = idindShellyConfigurado($configLocal);
    $modosSincronizables = $shellyCloudDisponible
        ? "('CLOUD', 'HIBRIDO')"
        : "('LOCAL', 'HIBRIDO')";

    $stmtEstado = $pdo->prepare(
        "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN es.sincronizado_en IS NULL THEN 1 ELSE 0 END) AS sin_datos,
            TIMESTAMPDIFF(SECOND, MIN(es.sincronizado_en), UTC_TIMESTAMP()) AS edad_segundos
         FROM actuadores_shelly a
         LEFT JOIN estado_shelly es ON es.actuador_id = a.id
         WHERE a.cliente_id = :cliente_id
           AND a.estado = 'Activo'
           AND a.modo_control IN {$modosSincronizables}"
    );
    $stmtEstado->execute(['cliente_id' => $clienteId]);
    $resumen = $stmtEstado->fetch() ?: [];
    $total = (int) ($resumen['total'] ?? 0);
    $sinDatos = (int) ($resumen['sin_datos'] ?? 0);
    $edad = $resumen['edad_segundos'] === null
        ? null
        : max(0, (int) $resumen['edad_segundos']);

    if ($total === 0) {
        responderJson(200, [
            'ok' => true,
            'data' => ['sincronizado' => false, 'motivo' => 'SIN_ACTUADORES'],
        ]);
    }
    if ($sinDatos === 0 && $edad !== null && $edad < $intervaloMinimo) {
        responderJson(200, [
            'ok' => true,
            'data' => [
                'sincronizado' => false,
                'motivo' => 'ESTADO_RECIENTE',
                'edad_segundos' => $edad,
            ],
        ]);
    }

    $resultado = $shellyCloudDisponible
        ? idindShellySincronizar($pdo, $configLocal, $clienteId)
        : idindShellySincronizarLocal($pdo, $clienteId);
    responderJson(200, [
        'ok' => true,
        'data' => ['sincronizado' => true, 'resultado' => $resultado],
    ]);
} catch (Throwable $error) {
    error_log('ID Industrial Shelly en vivo: ' . $error->getMessage());
    responderJson(502, ['ok' => false, 'error' => $error->getMessage()]);
} finally {
    try {
        $stmtUnlock = $pdo->prepare('SELECT RELEASE_LOCK(:nombre)');
        $stmtUnlock->execute(['nombre' => $lockName]);
    } catch (Throwable $errorUnlock) {
        error_log('ID Industrial lock Shelly: ' . $errorUnlock->getMessage());
    }
}
