<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => false, 'error' => 'Ejecucion permitida solo desde Cron/CLI']);
    exit;
}

require_once __DIR__ . '/config.php';

function configEntera(array $config, string $clave, int $default, int $min, int $max): int
{
    $valor = filter_var($config[$clave] ?? $default, FILTER_VALIDATE_INT);
    if ($valor === false) {
        return $default;
    }
    return max($min, min($max, (int) $valor));
}

$retencionDias = configEntera($configLocal, 'retention_raw_days', 90, 30, 365);
$retencionResumenMeses = configEntera(
    $configLocal,
    'retention_hourly_months',
    24,
    3,
    120
);
$retencionEventosShellyDias = configEntera(
    $configLocal,
    'retention_shelly_event_days',
    365,
    30,
    3650
);
$retencionPushDias = configEntera(
    $configLocal,
    'retention_push_days',
    90,
    30,
    730
);
$maxHorasPorEjecucion = configEntera(
    $configLocal,
    'retention_hours_per_run',
    48,
    1,
    168
);
$limiteSegundos = configEntera(
    $configLocal,
    'retention_max_runtime_seconds',
    45,
    10,
    240
);

$inicio = microtime(true);
$horasProcesadas = 0;
$muestrasEliminadas = 0;

$stmtAntigua = $pdo->prepare(
    "SELECT MIN(periodo_minuto)
     FROM muestras_historicas
     WHERE periodo_minuto < UTC_TIMESTAMP() - INTERVAL {$retencionDias} DAY"
);
$stmtResumen = $pdo->prepare(
    'INSERT INTO resumen_horario (
        dispositivo_id, periodo_hora, muestras,
        temperatura_promedio, temperatura_minima, temperatura_maxima,
        humedad_promedio, humedad_minima, humedad_maxima,
        gas_promedio, gas_minimo, gas_maximo,
        detecciones_gas, detecciones_flama, detecciones_estacion_manual,
        muestras_alarma
     )
     SELECT
        dispositivo_id,
        :periodo_hora,
        COUNT(*),
        ROUND(AVG(temperatura), 2), MIN(temperatura), MAX(temperatura),
        ROUND(AVG(humedad), 2), MIN(humedad), MAX(humedad),
        ROUND(AVG(gas_raw), 2), MIN(gas_raw), MAX(gas_raw),
        SUM(gas_detectado = 1),
        SUM(flama_detectada = 1),
        SUM(estacion_manual_activada = 1),
        SUM(estado_general = \'ALARMA\')
     FROM muestras_historicas
     WHERE periodo_minuto >= :desde
       AND periodo_minuto < :hasta
     GROUP BY dispositivo_id
     ON DUPLICATE KEY UPDATE
        muestras = VALUES(muestras),
        temperatura_promedio = VALUES(temperatura_promedio),
        temperatura_minima = VALUES(temperatura_minima),
        temperatura_maxima = VALUES(temperatura_maxima),
        humedad_promedio = VALUES(humedad_promedio),
        humedad_minima = VALUES(humedad_minima),
        humedad_maxima = VALUES(humedad_maxima),
        gas_promedio = VALUES(gas_promedio),
        gas_minimo = VALUES(gas_minimo),
        gas_maximo = VALUES(gas_maximo),
        detecciones_gas = VALUES(detecciones_gas),
        detecciones_flama = VALUES(detecciones_flama),
        detecciones_estacion_manual = VALUES(detecciones_estacion_manual),
        muestras_alarma = VALUES(muestras_alarma),
        actualizado_en = UTC_TIMESTAMP()'
);
$stmtEliminar = $pdo->prepare(
    'DELETE FROM muestras_historicas
     WHERE periodo_minuto >= :desde
       AND periodo_minuto < :hasta'
);

while (
    $horasProcesadas < $maxHorasPorEjecucion
    && microtime(true) - $inicio < $limiteSegundos
) {
    $stmtAntigua->execute();
    $fechaAntigua = $stmtAntigua->fetchColumn();
    if (!$fechaAntigua) {
        break;
    }

    $desde = new DateTimeImmutable((string) $fechaAntigua, new DateTimeZone('UTC'));
    $desde = $desde->setTime((int) $desde->format('H'), 0, 0);
    $hasta = $desde->modify('+1 hour');
    $parametros = [
        'periodo_hora' => $desde->format('Y-m-d H:i:s'),
        'desde' => $desde->format('Y-m-d H:i:s'),
        'hasta' => $hasta->format('Y-m-d H:i:s'),
    ];

    try {
        $pdo->beginTransaction();
        $stmtResumen->execute($parametros);
        $stmtEliminar->execute([
            'desde' => $parametros['desde'],
            'hasta' => $parametros['hasta'],
        ]);
        $muestrasEliminadas += $stmtEliminar->rowCount();
        $pdo->commit();
        $horasProcesadas++;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('ID Industrial retencion: ' . $error->getMessage());
        fwrite(STDERR, "Error al procesar {$parametros['desde']}\n");
        exit(1);
    }
}

$resumenesEliminados = $pdo->exec(
    "DELETE FROM resumen_horario
     WHERE periodo_hora < UTC_TIMESTAMP() - INTERVAL {$retencionResumenMeses} MONTH"
);

$pushEliminadas = 0;
$eventosShellyEliminados = 0;
$entregasWebhookEliminadas = 0;
try {
    $pushEliminadas = (int) $pdo->exec(
        "DELETE FROM notificaciones_push
         WHERE estado IN ('ENVIADA', 'DESCARTADA')
           AND creado_en < UTC_TIMESTAMP() - INTERVAL {$retencionPushDias} DAY"
    );
    $eventosShellyEliminados = (int) $pdo->exec(
        "DELETE FROM eventos_shelly
         WHERE fecha_hora < UTC_TIMESTAMP() - INTERVAL {$retencionEventosShellyDias} DAY"
    );
    try {
        $entregasWebhookEliminadas = (int) $pdo->exec(
            "DELETE FROM entregas_webhook_shelly
             WHERE recibido_en < UTC_TIMESTAMP() - INTERVAL {$retencionEventosShellyDias} DAY"
        );
    } catch (Throwable $errorAuditoriaWebhook) {
        // La migracion de webhooks puede instalarse despues del cron.
        error_log('ID Industrial retencion webhooks: ' . $errorAuditoriaWebhook->getMessage());
    }
} catch (Throwable $errorLimpiezaOperativa) {
    error_log('ID Industrial retencion operativa: ' . $errorLimpiezaOperativa->getMessage());
}

echo json_encode([
    'ok' => true,
    'retencion_muestras_dias' => $retencionDias,
    'retencion_resumen_meses' => $retencionResumenMeses,
    'retencion_eventos_shelly_dias' => $retencionEventosShellyDias,
    'retencion_push_dias' => $retencionPushDias,
    'horas_procesadas' => $horasProcesadas,
    'muestras_resumidas_y_eliminadas' => $muestrasEliminadas,
    'resumenes_vencidos_eliminados' => (int) $resumenesEliminados,
    'eventos_shelly_eliminados' => $eventosShellyEliminados,
    'entregas_webhook_eliminadas' => $entregasWebhookEliminadas,
    'notificaciones_push_eliminadas' => $pushEliminadas,
    'duracion_segundos' => round(microtime(true) - $inicio, 3),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
