<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/mobile_auth.php';
requerirMetodo('GET');

$usuario = requerirTokenMovil();
$clienteId = (int) $usuario['cliente_id'];

$stmtResumen = $pdo->prepare(
    "SELECT
        COUNT(DISTINCT d.id) AS dispositivos_total,
        COUNT(DISTINCT CASE
            WHEN d.estado = 'Activo'
             AND d.ultima_conexion >= UTC_TIMESTAMP() - INTERVAL 2 MINUTE
            THEN d.id
        END) AS dispositivos_online,
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
     WHERE d.cliente_id = :cliente_id"
);
$stmtResumen->execute(['cliente_id' => $clienteId]);
$resumen = $stmtResumen->fetch() ?: [];

$stmtDispositivos = $pdo->prepare(
    "SELECT
        d.id,
        d.ubicacion,
        CASE
            WHEN d.ultima_conexion IS NULL
              OR d.ultima_conexion < UTC_TIMESTAMP() - INTERVAL 2 MINUTE
            THEN 'OFFLINE'
            ELSE 'ONLINE'
        END AS conexion,
        e.estado_general,
        e.temperatura,
        e.humedad,
        e.indice_calor,
        e.gas_raw,
        e.gas_porcentaje,
        e.gas_detectado,
        e.flama_detectada,
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
        e.actualizado_en AS ultima_lectura,
        COALESCE(c.umbral_adc, 1600) AS mq2_umbral_adc,
        GREATEST(
          COALESCE(c.calentamiento_total_s, 120)
          - COALESCE(e.tiempo_encendido, 0),
          0
        ) AS mq2_calentamiento_restante_s
     FROM dispositivos d
     LEFT JOIN estado_sensores e ON e.dispositivo_id = d.id
     LEFT JOIN configuracion_mq2 c ON c.dispositivo_id = d.id
     WHERE d.cliente_id = :cliente_id
       AND d.estado <> 'Inactivo'
     ORDER BY
       FIELD(COALESCE(e.estado_general, 'NORMAL'), 'ALARMA', 'ALERTA', 'NORMAL'),
       d.ubicacion, d.id
     LIMIT 20"
);
$stmtDispositivos->execute(['cliente_id' => $clienteId]);
$dispositivos = $stmtDispositivos->fetchAll();

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
        END AS estado_atencion
     FROM alertas a
     INNER JOIN dispositivos d ON d.id = a.dispositivo_id
     LEFT JOIN alerta_gestiones g ON g.id = (
       SELECT g2.id
       FROM alerta_gestiones g2
       WHERE g2.alerta_id = a.id
       ORDER BY g2.fecha_hora DESC, g2.id DESC
       LIMIT 1
     )
     WHERE d.cliente_id = :cliente_id
     ORDER BY a.fecha_hora DESC, a.id DESC
     LIMIT 5"
);
$stmtAlertas->execute(['cliente_id' => $clienteId]);
$alertas = $stmtAlertas->fetchAll();

$estadoGeneral = 'NORMAL';
$dispositivosOnline = 0;
foreach ($dispositivos as $dispositivo) {
    if (($dispositivo['conexion'] ?? 'OFFLINE') === 'OFFLINE') {
        continue;
    }
    $dispositivosOnline++;
    if (($dispositivo['estado_general'] ?? '') === 'ALARMA') {
        $estadoGeneral = 'ALARMA';
        break;
    }
    if (($dispositivo['estado_general'] ?? '') === 'ALERTA') {
        $estadoGeneral = 'ALERTA';
    }
}
if ($estadoGeneral === 'NORMAL' && count($dispositivos) > 0 && $dispositivosOnline === 0) {
    $estadoGeneral = 'OFFLINE';
}

$resumen['estado_general'] = $estadoGeneral;
$resumen['dispositivos_offline'] = max(
    0,
    (int) ($resumen['dispositivos_total'] ?? 0)
      - (int) ($resumen['dispositivos_online'] ?? 0)
);

$revision = hash('sha256', json_encode([
    $estadoGeneral,
    $resumen,
    array_column($dispositivos, 'ultima_lectura'),
    array_column($alertas, 'id'),
]));
header('ETag: "' . $revision . '"');

responderJson(200, [
    'ok' => true,
    'data' => [
        'generado_en' => gmdate('Y-m-d H:i:s'),
        'revision' => $revision,
        'resumen' => $resumen,
        'dispositivos' => $dispositivos,
        'alertas' => $alertas,
    ],
]);
