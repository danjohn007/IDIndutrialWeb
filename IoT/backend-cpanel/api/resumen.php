<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
requerirMetodo('GET');
$usuario = requerirSesion();

$horas = filter_var($_GET['horas'] ?? 24, FILTER_VALIDATE_INT);
$horas = $horas === false ? 24 : max(1, min(720, $horas));

$gasUmbral = filter_var($_GET['gas_umbral'] ?? 1600, FILTER_VALIDATE_INT);
$gasUmbral = $gasUmbral === false ? 1600 : max(1, min(4095, $gasUmbral));

$params = ['cliente_id' => (int) $usuario['cliente_id']];
$filtroDispositivo = '';
$dispositivoId = trim((string) ($_GET['dispositivo_id'] ?? ''));
if ($dispositivoId !== '') {
    if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $dispositivoId)) {
        responderJson(422, ['ok' => false, 'error' => 'dispositivo_id invalido']);
    }
    $filtroDispositivo = ' AND h.dispositivo_id = :dispositivo_id';
    $params['dispositivo_id'] = $dispositivoId;
}

$where = "h.periodo_minuto >= UTC_TIMESTAMP() - INTERVAL {$horas} HOUR
    AND d.cliente_id = :cliente_id
    AND d.estado <> 'Inactivo'{$filtroDispositivo}";
$intervaloSegundos = $horas <= 6
    ? 300
    : ($horas <= 24 ? 1800 : ($horas <= 168 ? 14400 : 43200));
$periodoSql =
    "FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(h.periodo_minuto) / {$intervaloSegundos})"
    . " * {$intervaloSegundos})";

$stmtResumen = $pdo->prepare(
    "SELECT
        COUNT(*) AS lecturas,
        ROUND(AVG(h.temperatura), 2) AS temperatura_promedio,
        MAX(h.temperatura) AS temperatura_maxima,
        ROUND(AVG(h.humedad), 2) AS humedad_promedio,
        ROUND(AVG(h.gas_raw), 2) AS gas_promedio,
        MAX(h.gas_raw) AS gas_maximo,
        ROUND(AVG(h.wifi_rssi), 2) AS wifi_rssi_promedio,
        MIN(h.wifi_rssi) AS wifi_rssi_minimo,
        SUM(h.flama_detectada = 1) AS lecturas_con_flama,
        SUM(h.estacion_manual_activada = 1) AS lecturas_estacion_manual,
        SUM(h.gas_raw >= {$gasUmbral}) AS lecturas_con_gas,
        SUM(h.estado_general = 'ALERTA') AS lecturas_alerta,
        SUM(h.estado_general = 'ALARMA') AS lecturas_alarma,
        SUM(h.salud_dht IN ('REVISAR', 'FALLO')) AS revisiones_dht,
        SUM(h.salud_mq2 IN ('REVISAR', 'FALLO')) AS revisiones_mq2,
        SUM(h.salud_flama IN ('REVISAR', 'FALLO')) AS revisiones_flama
     FROM muestras_historicas h
     INNER JOIN dispositivos d ON d.id = h.dispositivo_id
     WHERE {$where}"
);
$stmtResumen->execute($params);

$stmtSerie = $pdo->prepare(
    "SELECT
        DATE_FORMAT({$periodoSql}, '%Y-%m-%d %H:%i:%s') AS periodo,
        ROUND(AVG(h.temperatura), 2) AS temperatura,
        ROUND(AVG(h.humedad), 2) AS humedad,
        ROUND(AVG(h.gas_raw), 2) AS gas_raw,
        MAX(h.gas_raw >= {$gasUmbral}) AS gas_detectado,
        MAX(h.flama_detectada) AS flama_detectada,
        MAX(h.estacion_manual_activada) AS estacion_manual_activada,
        ROUND(AVG(h.wifi_rssi), 2) AS wifi_rssi,
        SUM(h.estado_general = 'ALARMA') AS alarmas,
        SUM(h.estado_general = 'ALERTA') AS alertas,
        SUM(h.salud_dht IN ('REVISAR', 'FALLO')) AS revisiones_dht,
        SUM(h.salud_mq2 IN ('REVISAR', 'FALLO')) AS revisiones_mq2,
        SUM(h.salud_flama IN ('REVISAR', 'FALLO')) AS revisiones_flama
     FROM muestras_historicas h
     INNER JOIN dispositivos d ON d.id = h.dispositivo_id
     WHERE {$where}
     GROUP BY {$periodoSql}
     ORDER BY periodo"
);
$stmtSerie->execute($params);

$filtroActual = '';
$paramsActual = ['cliente_id' => (int) $usuario['cliente_id']];
if ($dispositivoId !== '') {
    $filtroActual = ' AND e.dispositivo_id = :dispositivo_id';
    $paramsActual['dispositivo_id'] = $dispositivoId;
}

$stmtActual = $pdo->prepare(
    "SELECT
        DATE_FORMAT(e.actualizado_en, '%Y-%m-%d %H:%i:%s') AS periodo,
        e.temperatura,
        e.humedad,
        e.gas_raw,
        e.gas_detectado,
        e.flama_detectada,
        e.estacion_manual_activada,
        e.wifi_rssi,
        (e.estado_general = 'ALARMA') AS alarmas,
        (e.estado_general = 'ALERTA') AS alertas,
        (e.salud_dht IN ('REVISAR', 'FALLO')) AS revisiones_dht,
        (e.salud_mq2 IN ('REVISAR', 'FALLO')) AS revisiones_mq2,
        (e.salud_flama IN ('REVISAR', 'FALLO')) AS revisiones_flama
     FROM estado_sensores e
     INNER JOIN dispositivos d ON d.id = e.dispositivo_id
     WHERE e.actualizado_en >= UTC_TIMESTAMP() - INTERVAL {$horas} HOUR
       AND d.cliente_id = :cliente_id
       AND d.estado <> 'Inactivo'
       {$filtroActual}
     ORDER BY e.actualizado_en DESC
     LIMIT 1"
);
$stmtActual->execute($paramsActual);

$serie = $stmtSerie->fetchAll();
$estadoActual = $stmtActual->fetch();
if ($estadoActual) {
    $serie[] = $estadoActual;
    usort(
        $serie,
        static fn(array $a, array $b): int =>
            strcmp((string) ($a['periodo'] ?? ''), (string) ($b['periodo'] ?? ''))
    );
}

responderJson(200, [
    'ok' => true,
    'data' => [
        'periodo_horas' => $horas,
        'intervalo_segundos' => $intervaloSegundos,
        'gas_umbral' => $gasUmbral,
        'modo_historial' => 'muestras_por_minuto',
        'resumen' => $stmtResumen->fetch(),
        'serie' => $serie,
    ],
]);
