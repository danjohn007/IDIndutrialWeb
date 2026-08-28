<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/mobile_auth.php';
requerirMetodo('GET');

$usuario = requerirTokenMovil();
$alertaId = filter_var($_GET['alerta_id'] ?? null, FILTER_VALIDATE_INT);
$minutos = filter_var($_GET['minutos'] ?? 15, FILTER_VALIDATE_INT);
$minutos = $minutos === false ? 15 : max(5, min(60, $minutos));

if ($alertaId === false || $alertaId < 1) {
    responderJson(422, ['ok' => false, 'error' => 'alerta_id invalido']);
}

$stmtAlerta = $pdo->prepare(
    "SELECT
        a.id,
        a.dispositivo_id,
        a.tipo_alerta,
        a.valor_sensor,
        a.severidad,
        a.atendida,
        a.fecha_hora,
        d.ubicacion,
        CASE
            WHEN a.atendida = 1 THEN 'RESUELTA'
            WHEN g.accion = 'RECONOCER' THEN 'RECONOCIDA'
            ELSE 'NUEVA'
        END AS estado_atencion,
        g.responsable,
        g.comentario,
        g.fecha_hora AS gestion_fecha,
        COALESCE(c.umbral_adc, 1600) AS gas_umbral
     FROM alertas a
     INNER JOIN dispositivos d ON d.id = a.dispositivo_id
     LEFT JOIN configuracion_mq2 c ON c.dispositivo_id = d.id
     LEFT JOIN alerta_gestiones g ON g.id = (
       SELECT g2.id
       FROM alerta_gestiones g2
       WHERE g2.alerta_id = a.id
       ORDER BY g2.fecha_hora DESC, g2.id DESC
       LIMIT 1
     )
     WHERE a.id = :alerta_id
       AND d.cliente_id = :cliente_id
     LIMIT 1"
);
$stmtAlerta->execute([
    'alerta_id' => $alertaId,
    'cliente_id' => (int) $usuario['cliente_id'],
]);
$alerta = $stmtAlerta->fetch();

if (!$alerta) {
    responderJson(404, ['ok' => false, 'error' => 'La alerta no existe']);
}

$fechaAlerta = new DateTimeImmutable(
    (string) $alerta['fecha_hora'],
    new DateTimeZone('UTC')
);
$desde = $fechaAlerta->sub(new DateInterval('PT' . $minutos . 'M'));
$hasta = $fechaAlerta->add(new DateInterval('PT' . $minutos . 'M'));

$stmtSerie = $pdo->prepare(
    "SELECT
        DATE_FORMAT(h.periodo_minuto, '%Y-%m-%d %H:%i:%s') AS periodo,
        h.temperatura,
        h.humedad,
        h.indice_calor,
        h.gas_raw,
        h.gas_porcentaje,
        h.gas_detectado,
        h.flama_detectada,
        h.estacion_manual_activada,
        h.estado_general,
        h.salud_dht,
        h.salud_mq2,
        h.salud_flama
     FROM muestras_historicas h
     WHERE h.dispositivo_id = :dispositivo_id
       AND h.periodo_minuto BETWEEN :desde AND :hasta
     ORDER BY h.periodo_minuto"
);
$stmtSerie->execute([
    'dispositivo_id' => (string) $alerta['dispositivo_id'],
    'desde' => $desde->format('Y-m-d H:i:s'),
    'hasta' => $hasta->format('Y-m-d H:i:s'),
]);
$serie = $stmtSerie->fetchAll();

$eventoDesde = $fechaAlerta->sub(new DateInterval('PT2M'));
$eventoHasta = $fechaAlerta->add(new DateInterval('PT2M'));
$gasUmbral = (int) ($alerta['gas_umbral'] ?? 1600);

$stmtEvento = $pdo->prepare(
    "SELECT
        DATE_FORMAT(l.fecha_hora, '%Y-%m-%d %H:%i:%s') AS periodo,
        l.temperatura,
        l.humedad,
        l.indice_calor,
        l.gas_raw,
        l.gas_porcentaje,
        CASE WHEN l.gas_raw >= :gas_umbral THEN 1 ELSE 0 END AS gas_detectado,
        l.flama_detectada,
        l.estacion_manual_activada,
        l.estado_general,
        l.salud_dht,
        l.salud_mq2,
        l.salud_flama
     FROM lecturas_sensores l
     WHERE l.dispositivo_id = :dispositivo_id
       AND l.fecha_hora BETWEEN :evento_desde AND :evento_hasta
     ORDER BY ABS(TIMESTAMPDIFF(SECOND, l.fecha_hora, :fecha_alerta)), l.id DESC
     LIMIT 1"
);
$stmtEvento->execute([
    'gas_umbral' => $gasUmbral,
    'dispositivo_id' => (string) $alerta['dispositivo_id'],
    'evento_desde' => $eventoDesde->format('Y-m-d H:i:s'),
    'evento_hasta' => $eventoHasta->format('Y-m-d H:i:s'),
    'fecha_alerta' => $fechaAlerta->format('Y-m-d H:i:s'),
]);
$lecturaEvento = $stmtEvento->fetch() ?: null;

$stmtActual = $pdo->prepare(
    "SELECT
        DATE_FORMAT(e.actualizado_en, '%Y-%m-%d %H:%i:%s') AS periodo,
        e.temperatura,
        e.humedad,
        e.indice_calor,
        e.gas_raw,
        e.gas_porcentaje,
        e.gas_detectado,
        e.flama_detectada,
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
        CASE
            WHEN d.ultima_conexion IS NULL
              OR d.ultima_conexion < UTC_TIMESTAMP() - INTERVAL 2 MINUTE
            THEN 'OFFLINE'
            ELSE 'ONLINE'
        END AS conexion
     FROM estado_sensores e
     INNER JOIN dispositivos d ON d.id = e.dispositivo_id
     WHERE e.dispositivo_id = :dispositivo_id
     LIMIT 1"
);
$stmtActual->execute([
    'dispositivo_id' => (string) $alerta['dispositivo_id'],
]);
$actual = $stmtActual->fetch();

if (!$lecturaEvento) {
    $distanciaMenor = PHP_INT_MAX;
    foreach ($serie as $muestra) {
        $fechaMuestra = new DateTimeImmutable(
            (string) $muestra['periodo'],
            new DateTimeZone('UTC')
        );
        $distancia = abs($fechaMuestra->getTimestamp() - $fechaAlerta->getTimestamp());
        if ($distancia < $distanciaMenor) {
            $distanciaMenor = $distancia;
            $lecturaEvento = $muestra;
        }
    }
}

if (!$lecturaEvento) {
    $lecturaEvento = [
        'periodo' => $fechaAlerta->format('Y-m-d H:i:s'),
        'temperatura' => null,
        'humedad' => null,
        'indice_calor' => null,
        'gas_raw' => null,
        'gas_porcentaje' => null,
        'gas_detectado' => 0,
        'flama_detectada' => 0,
        'estacion_manual_activada' => 0,
        'estado_general' => $alerta['severidad'] === 'CRITICO' ? 'ALARMA' : 'ALERTA',
        'salud_dht' => null,
        'salud_mq2' => null,
        'salud_flama' => null,
    ];
}

$tipoAlerta = strtolower((string) $alerta['tipo_alerta']);
if (strpos($tipoAlerta, 'flama') !== false) {
    $lecturaEvento['flama_detectada'] = 1;
}
if (strpos($tipoAlerta, 'estacion manual') !== false || strpos($tipoAlerta, 'pulsador') !== false) {
    $lecturaEvento['estacion_manual_activada'] = 1;
}
if (strpos($tipoAlerta, 'humo') !== false || strpos($tipoAlerta, 'gas') !== false) {
    $lecturaEvento['gas_detectado'] = 1;
    if ($alerta['valor_sensor'] !== null) {
        $lecturaEvento['gas_raw'] = $alerta['valor_sensor'];
    }
}
if (strpos($tipoAlerta, 'temperatura') !== false && $alerta['valor_sensor'] !== null) {
    $lecturaEvento['temperatura'] = $alerta['valor_sensor'];
}

$lecturaEvento['periodo'] = $fechaAlerta->format('Y-m-d H:i:s');
$seriePorPeriodo = [];

foreach ($serie as $muestra) {
    $seriePorPeriodo[(string) $muestra['periodo']] = $muestra;
}
$seriePorPeriodo[(string) $lecturaEvento['periodo']] = $lecturaEvento;
ksort($seriePorPeriodo);

unset($alerta['gas_umbral']);

responderJson(200, [
    'ok' => true,
    'data' => [
        'alerta' => $alerta,
        'ventana' => [
            'minutos_antes' => $minutos,
            'minutos_despues' => $minutos,
            'desde' => $desde->format('Y-m-d H:i:s'),
            'hasta' => $hasta->format('Y-m-d H:i:s'),
        ],
        'gas_umbral' => $gasUmbral,
        'lectura_evento' => $lecturaEvento,
        'estado_actual' => $actual ?: null,
        'serie' => array_values($seriePorPeriodo),
    ],
]);
