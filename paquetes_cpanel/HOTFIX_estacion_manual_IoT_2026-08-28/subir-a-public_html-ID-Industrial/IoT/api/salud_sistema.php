<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
requerirMetodo('GET');
$usuario = requerirSesion();
$clienteId = (int) $usuario['cliente_id'];

$stmt = $pdo->prepare(
    "SELECT
        d.id,
        d.ubicacion,
        d.estado AS estado_dispositivo,
        d.ultima_conexion,
        CASE
          WHEN d.ultima_conexion IS NULL
            OR d.ultima_conexion < UTC_TIMESTAMP() - INTERVAL 2 MINUTE
          THEN 'OFFLINE'
          ELSE 'ONLINE'
        END AS conexion,
        e.actualizado_en AS ultima_lectura,
        e.salud_dht,
        e.salud_mq2,
        e.salud_flama,
        e.gas_raw,
        e.gas_detectado,
        e.flama_detectada,
        e.estacion_manual_activada,
        COALESCE(c.umbral_adc, 1600) AS mq2_umbral_adc,
        c.ultima_calibracion,
        c.adc_aire_limpio,
        COALESCE(diag.muestras, 0) AS mq2_muestras_diagnostico,
        CASE
          WHEN e.salud_mq2 = 'CALENTANDO' OR e.gas_raw IS NULL THEN 0
          WHEN e.gas_raw >= 4080 THEN 1
          WHEN COALESCE(diag.muestras, 0) >= 5
           AND diag.gas_minimo >= 4080
           AND diag.gas_maximo - diag.gas_minimo <= 2 THEN 1
          ELSE 0
        END AS mq2_lectura_atascada,
        CASE
          WHEN c.ultima_calibracion IS NULL
            OR c.ultima_calibracion < UTC_TIMESTAMP() - INTERVAL 90 DAY
          THEN 1
          ELSE 0
        END AS mq2_calibracion_requerida
     FROM dispositivos d
     LEFT JOIN estado_sensores e ON e.dispositivo_id = d.id
     LEFT JOIN configuracion_mq2 c ON c.dispositivo_id = d.id
     LEFT JOIN (
       SELECT
          dispositivo_id,
          COUNT(*) AS muestras,
          MIN(gas_raw) AS gas_minimo,
          MAX(gas_raw) AS gas_maximo
       FROM muestras_historicas
       WHERE periodo_minuto >= UTC_TIMESTAMP() - INTERVAL 10 MINUTE
         AND gas_raw IS NOT NULL
       GROUP BY dispositivo_id
     ) diag ON diag.dispositivo_id = d.id
     WHERE d.cliente_id = :cliente_id
       AND d.estado <> 'Inactivo'
     ORDER BY
       FIELD(
         CASE
           WHEN d.ultima_conexion IS NULL
             OR d.ultima_conexion < UTC_TIMESTAMP() - INTERVAL 2 MINUTE
           THEN 'OFFLINE'
           ELSE 'ONLINE'
         END,
         'OFFLINE', 'ONLINE'
       ),
       d.ubicacion, d.id"
);
$stmt->execute(['cliente_id' => $clienteId]);
$dispositivos = $stmt->fetchAll();

$resumen = [
    'dispositivos_total' => count($dispositivos),
    'dispositivos_online' => 0,
    'dispositivos_offline' => 0,
    'sensores_revisar' => 0,
    'mq2_calibracion_requerida' => 0,
    'mq2_lectura_atascada' => 0,
];

foreach ($dispositivos as $dispositivo) {
    if (($dispositivo['conexion'] ?? 'OFFLINE') === 'ONLINE') {
        $resumen['dispositivos_online']++;
    } else {
        $resumen['dispositivos_offline']++;
    }

    foreach (['salud_dht', 'salud_mq2', 'salud_flama'] as $campo) {
        if (in_array($dispositivo[$campo] ?? '', ['REVISAR', 'FALLO'], true)) {
            $resumen['sensores_revisar']++;
        }
    }
    if ((int) ($dispositivo['mq2_calibracion_requerida'] ?? 0) === 1) {
        $resumen['mq2_calibracion_requerida']++;
    }
    if ((int) ($dispositivo['mq2_lectura_atascada'] ?? 0) === 1) {
        $resumen['mq2_lectura_atascada']++;
    }
}

responderJson(200, [
    'ok' => true,
    'data' => [
        'generado_en' => gmdate('Y-m-d H:i:s'),
        'umbral_calibracion_dias' => 90,
        'resumen' => $resumen,
        'dispositivos' => $dispositivos,
    ],
]);
