<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/shelly.php';
require_once __DIR__ . '/lib/rutinas.php';
requerirMetodo('GET');
$usuario = requerirSesion();
$clienteId = (int) $usuario['cliente_id'];

$stmtResumen = $pdo->prepare(
    "SELECT
        COUNT(DISTINCT d.id) AS dispositivos_total,
        COUNT(DISTINCT CASE
            WHEN d.estado = 'Activo'
             AND d.ultima_conexion >= UTC_TIMESTAMP() - INTERVAL 2 MINUTE
            THEN d.id
        END) AS dispositivos_activos,
        COUNT(DISTINCT CASE
            WHEN a.fecha_hora >= DATE_FORMAT(UTC_DATE(), '%Y-%m-01')
            THEN a.id
        END) AS alertas_mes,
        COUNT(DISTINCT CASE
            WHEN a.severidad = 'CRITICO' AND a.atendida = 0
            THEN a.id
        END) AS criticas_abiertas
     FROM dispositivos d
     LEFT JOIN alertas a ON a.dispositivo_id = d.id
     WHERE d.cliente_id = :cliente_id
       AND d.estado <> 'Inactivo'"
);
$stmtResumen->execute(['cliente_id' => $clienteId]);

$stmtDispositivos = $pdo->prepare(
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
        END AS estado_conexion,
        e.temperatura,
        e.humedad,
        e.indice_calor,
        e.gas_raw AS humo,
        e.gas_porcentaje,
        e.gas_detectado,
        COALESCE(c.umbral_adc, 1600) AS gas_umbral,
        COALESCE(c.calentamiento_total_s, 120) AS mq2_calentamiento_total_s,
        GREATEST(
          COALESCE(c.calentamiento_total_s, 120)
          - COALESCE(e.tiempo_encendido, 0),
          0
        ) AS mq2_calentamiento_restante_s,
        c.ultima_calibracion AS mq2_ultima_calibracion,
        c.adc_aire_limpio AS mq2_adc_aire_limpio,
        COALESCE(diag.muestras, 0) AS mq2_muestras_diagnostico,
        diag.gas_minimo AS mq2_gas_minimo,
        diag.gas_maximo AS mq2_gas_maximo,
        CASE
          WHEN e.salud_mq2 = 'CALENTANDO' OR e.gas_raw IS NULL THEN 0
          WHEN e.gas_raw >= 4080 THEN 1
          WHEN COALESCE(diag.muestras, 0) >= 5
           AND diag.gas_minimo >= 4080
           AND diag.gas_maximo - diag.gas_minimo <= 2 THEN 1
          ELSE 0
        END AS mq2_lectura_atascada,
        e.flama_detectada AS flama,
        e.estacion_manual_activada,
        e.estado_general,
        e.peligro_activo,
        e.alarma_enclavada,
        e.alarma_silenciada,
        e.revision_fisica_pendiente,
        e.buzzer_encendido,
        e.modo_operacion,
        e.silenciada_por,
        e.salud_dht,
        e.salud_mq2,
        e.salud_flama,
        e.wifi_rssi,
        e.actualizado_en AS ultima_lectura
     FROM dispositivos d
     LEFT JOIN estado_sensores e
       ON e.dispositivo_id = d.id
     LEFT JOIN configuracion_mq2 c
       ON c.dispositivo_id = d.id
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
     ) diag
       ON diag.dispositivo_id = d.id
     WHERE d.estado <> 'Inactivo'
       AND d.cliente_id = :cliente_id
     ORDER BY
        FIELD(COALESCE(e.estado_general, 'NORMAL'), 'ALARMA', 'ALERTA', 'NORMAL'),
        d.ubicacion"
);
$stmtDispositivos->execute(['cliente_id' => $clienteId]);

$stmtAlertas = $pdo->prepare(
    "SELECT
        a.id,
        a.dispositivo_id,
        d.ubicacion,
        a.tipo_alerta,
        a.valor_sensor,
        a.severidad,
        a.atendida,
        a.fecha_hora,
        CASE
            WHEN a.atendida = 1 THEN 'RESUELTA'
            WHEN g.accion = 'RECONOCER' THEN 'RECONOCIDA'
            ELSE 'NUEVA'
        END AS estado_atencion,
        g.responsable,
        g.comentario,
        g.fecha_hora AS gestion_fecha
     FROM alertas a
     INNER JOIN dispositivos d ON d.id = a.dispositivo_id
     LEFT JOIN alerta_gestiones g
       ON g.id = (
          SELECT g2.id
          FROM alerta_gestiones g2
          WHERE g2.alerta_id = a.id
          ORDER BY g2.fecha_hora DESC, g2.id DESC
          LIMIT 1
       )
     WHERE d.cliente_id = :cliente_id
       AND d.estado <> 'Inactivo'
     ORDER BY a.fecha_hora DESC, a.id DESC
     LIMIT 5"
);
$stmtAlertas->execute(['cliente_id' => $clienteId]);

$resumen = $stmtResumen->fetch();
$dispositivos = $stmtDispositivos->fetchAll();
$sensoresIncendioActivos = 0;

foreach ($dispositivos as $dispositivo) {
    if (($dispositivo['estado_conexion'] ?? 'OFFLINE') !== 'ONLINE') {
        continue;
    }

    if (
        ($dispositivo['salud_mq2'] ?? '') === 'OK'
        && (int) ($dispositivo['mq2_lectura_atascada'] ?? 0) === 0
    ) {
        $sensoresIncendioActivos++;
    }
    if (($dispositivo['salud_flama'] ?? '') === 'OK') {
        $sensoresIncendioActivos++;
    }
    if (array_key_exists('estacion_manual_activada', $dispositivo)) {
        $sensoresIncendioActivos++;
    }
}

$resumen['sensores_incendio_activos'] = $sensoresIncendioActivos;
$resumen['sensores_incendio_total'] = count($dispositivos) * 3;

$actuadoresShelly = [];
try {
    $actuadoresShelly = idindShellyEstadoCliente($pdo, $clienteId);
} catch (Throwable $errorShelly) {
    error_log('ID Industrial estado Shelly web: ' . $errorShelly->getMessage());
}

$rutinas = [];
$rutinasDisponibles = false;
try {
    $rutinasDisponibles = idindRutinasDisponibles($pdo);
    if ($rutinasDisponibles) {
        $rutinas = idindRutinaListar($pdo, $clienteId);
    }
} catch (Throwable $errorRutinas) {
    error_log('ID Industrial rutinas web: ' . $errorRutinas->getMessage());
}

responderJson(200, [
    'ok' => true,
    'success' => true,
    'data' => [
        'resumen' => $resumen,
        'dispositivos' => $dispositivos,
        'alertas' => $stmtAlertas->fetchAll(),
        'actuadores_shelly' => $actuadoresShelly,
        'rutinas' => $rutinas,
        'rutinas_disponibles' => $rutinasDisponibles,
    ],
]);
