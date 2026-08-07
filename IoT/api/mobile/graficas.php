<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/mobile_auth.php';
requerirMetodo('GET');

$usuario = requerirTokenMovil();
$clienteId = (int) $usuario['cliente_id'];
$dispositivoId = trim((string) ($_GET['dispositivo_id'] ?? ''));
$minutos = filter_var($_GET['minutos'] ?? 30, FILTER_VALIDATE_INT);
$minutos = $minutos === false ? 30 : max(15, min(120, $minutos));

if ($dispositivoId === '') {
    responderJson(422, ['ok' => false, 'error' => 'Selecciona un dispositivo']);
}

$stmtDispositivo = $pdo->prepare(
    "SELECT
        d.id,
        d.ubicacion,
        CASE
            WHEN d.ultima_conexion IS NULL
              OR d.ultima_conexion < UTC_TIMESTAMP() - INTERVAL 2 MINUTE
            THEN 'OFFLINE'
            ELSE 'ONLINE'
        END AS conexion,
        COALESCE(c.umbral_adc, 1600) AS mq2_umbral_adc
     FROM dispositivos d
     LEFT JOIN configuracion_mq2 c ON c.dispositivo_id = d.id
     WHERE d.id = :dispositivo_id
       AND d.cliente_id = :cliente_id
       AND d.estado <> 'Inactivo'
     LIMIT 1"
);
$stmtDispositivo->execute([
    'dispositivo_id' => $dispositivoId,
    'cliente_id' => $clienteId,
]);
$dispositivo = $stmtDispositivo->fetch();

if (!$dispositivo) {
    responderJson(404, ['ok' => false, 'error' => 'El dispositivo no existe']);
}

$desde = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
    ->sub(new DateInterval('PT' . $minutos . 'M'));

$stmtSerie = $pdo->prepare(
    "SELECT
        DATE_FORMAT(h.periodo_minuto, '%Y-%m-%d %H:%i:%s') AS periodo,
        h.temperatura,
        h.humedad,
        h.gas_raw,
        h.gas_porcentaje,
        h.gas_detectado,
        h.flama_detectada,
        h.estado_general
     FROM muestras_historicas h
     WHERE h.dispositivo_id = :dispositivo_id
       AND h.periodo_minuto >= :desde
     ORDER BY h.periodo_minuto
     LIMIT 180"
);
$stmtSerie->execute([
    'dispositivo_id' => $dispositivoId,
    'desde' => $desde->format('Y-m-d H:i:s'),
]);
$serie = $stmtSerie->fetchAll();

$stmtActual = $pdo->prepare(
    "SELECT
        DATE_FORMAT(e.actualizado_en, '%Y-%m-%d %H:%i:%s') AS periodo,
        e.temperatura,
        e.humedad,
        e.gas_raw,
        e.gas_porcentaje,
        e.gas_detectado,
        e.flama_detectada,
        e.estado_general
     FROM estado_sensores e
     WHERE e.dispositivo_id = :dispositivo_id
     LIMIT 1"
);
$stmtActual->execute(['dispositivo_id' => $dispositivoId]);
$actual = $stmtActual->fetch();

$seriePorPeriodo = [];
foreach ($serie as $muestra) {
    $seriePorPeriodo[(string) $muestra['periodo']] = $muestra;
}
if ($actual) {
    $seriePorPeriodo[(string) $actual['periodo']] = $actual;
}
ksort($seriePorPeriodo);

responderJson(200, [
    'ok' => true,
    'data' => [
        'generado_en' => gmdate('Y-m-d H:i:s'),
        'intervalo_actualizacion_s' => 5,
        'ventana_minutos' => $minutos,
        'dispositivo' => $dispositivo,
        'muestras' => array_values($seriePorPeriodo),
    ],
]);
