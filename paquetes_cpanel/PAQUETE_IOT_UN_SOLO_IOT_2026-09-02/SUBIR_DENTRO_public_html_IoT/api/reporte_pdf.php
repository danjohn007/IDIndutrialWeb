<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/reporte_pdf.php';
requerirMetodo('GET');

$usuario = requerirSesion();
$clienteId = (int) $usuario['cliente_id'];

function fechaReporte(string $campo, DateTimeImmutable $default): DateTimeImmutable
{
    $valor = trim((string) ($_GET[$campo] ?? ''));
    if ($valor === '') {
        return $default;
    }
    try {
        return (new DateTimeImmutable($valor))->setTimezone(new DateTimeZone('UTC'));
    } catch (Throwable $error) {
        responderJson(422, ['ok' => false, 'error' => "Fecha invalida: {$campo}"]);
    }
}

$zonaUtc = new DateTimeZone('UTC');
$hasta = fechaReporte('hasta', new DateTimeImmutable('now', $zonaUtc));
$desde = fechaReporte('desde', $hasta->modify('-7 days'));
if ($desde > $hasta || $desde < $hasta->modify('-90 days')) {
    responderJson(422, [
        'ok' => false,
        'error' => 'El periodo debe ser de hasta 90 dias y tener fechas validas',
    ]);
}

$dispositivoId = trim((string) ($_GET['dispositivo_id'] ?? ''));
if ($dispositivoId !== '' && !preg_match('/^[A-Za-z0-9_-]{1,64}$/', $dispositivoId)) {
    responderJson(422, ['ok' => false, 'error' => 'dispositivo_id invalido']);
}

$empresaStmt = $pdo->prepare(
    'SELECT nombre_empresa FROM clientes WHERE id = :cliente_id LIMIT 1'
);
$empresaStmt->execute(['cliente_id' => $clienteId]);
$empresa = (string) ($empresaStmt->fetchColumn() ?: 'ID Industrial');

$condicionesAlertas = [
    'd.cliente_id = :cliente_id',
    'a.fecha_hora >= :desde',
    'a.fecha_hora <= :hasta',
];
$paramsAlertas = [
    'cliente_id' => $clienteId,
    'desde' => $desde->format('Y-m-d H:i:s'),
    'hasta' => $hasta->format('Y-m-d H:i:s'),
];
if ($dispositivoId !== '') {
    $condicionesAlertas[] = 'a.dispositivo_id = :dispositivo_id';
    $paramsAlertas['dispositivo_id'] = $dispositivoId;
}
$whereAlertas = 'WHERE ' . implode(' AND ', $condicionesAlertas);

$stmtResumen = $pdo->prepare(
    "SELECT
        COUNT(*) AS alertas_periodo,
        SUM(a.severidad = 'CRITICO') AS alertas_criticas,
        SUM(a.severidad = 'PRECAUCION') AS alertas_precaucion,
        SUM(a.atendida = 0) AS alertas_pendientes
     FROM alertas a
     INNER JOIN dispositivos d ON d.id = a.dispositivo_id
     {$whereAlertas}"
);
$stmtResumen->execute($paramsAlertas);
$resumenAlertas = $stmtResumen->fetch() ?: [];

$condicionesDispositivos = ['d.cliente_id = :cliente_id', "d.estado <> 'Inactivo'"];
$paramsDispositivos = ['cliente_id' => $clienteId];
if ($dispositivoId !== '') {
    $condicionesDispositivos[] = 'd.id = :dispositivo_id';
    $paramsDispositivos['dispositivo_id'] = $dispositivoId;
}
$whereDispositivos = 'WHERE ' . implode(' AND ', $condicionesDispositivos);

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
        e.salud_dht,
        e.salud_mq2,
        e.salud_flama,
        e.actualizado_en AS ultima_lectura,
        c.ultima_calibracion,
        CASE
          WHEN e.salud_mq2 = 'CALENTANDO' OR e.gas_raw IS NULL THEN 0
          WHEN e.gas_raw IN (0, 4095) THEN 1
          WHEN COALESCE(diag.muestras, 0) >= 5
           AND diag.gas_maximo - diag.gas_minimo <= 2 THEN 1
          ELSE 0
        END AS mq2_lectura_atascada
     FROM dispositivos d
     LEFT JOIN estado_sensores e ON e.dispositivo_id = d.id
     LEFT JOIN configuracion_mq2 c ON c.dispositivo_id = d.id
     LEFT JOIN (
       SELECT dispositivo_id, COUNT(*) AS muestras, MIN(gas_raw) AS gas_minimo, MAX(gas_raw) AS gas_maximo
       FROM muestras_historicas
       WHERE periodo_minuto >= UTC_TIMESTAMP() - INTERVAL 10 MINUTE
         AND gas_raw IS NOT NULL
       GROUP BY dispositivo_id
     ) diag ON diag.dispositivo_id = d.id
     {$whereDispositivos}
     ORDER BY d.ubicacion, d.id"
);
$stmtDispositivos->execute($paramsDispositivos);
$dispositivos = $stmtDispositivos->fetchAll();

$stmtAlertas = $pdo->prepare(
    "SELECT
        a.fecha_hora,
        a.dispositivo_id,
        d.ubicacion,
        a.tipo_alerta,
        a.valor_sensor,
        a.severidad,
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
     {$whereAlertas}
     ORDER BY a.fecha_hora DESC, a.id DESC
     LIMIT 30"
);
$stmtAlertas->execute($paramsAlertas);
$alertas = $stmtAlertas->fetchAll();

$online = count(array_filter(
    $dispositivos,
    static fn (array $item): bool => ($item['conexion'] ?? '') === 'ONLINE'
));
$revisiones = 0;
$revisionesDht = 0;
$revisionesMq2 = 0;
$revisionesFlama = 0;
foreach ($dispositivos as $dispositivo) {
    foreach (['salud_dht' => 'dht', 'salud_mq2' => 'mq2', 'salud_flama' => 'flama'] as $campo => $sensor) {
        if (in_array($dispositivo[$campo] ?? '', ['REVISAR', 'FALLO'], true)) {
            $revisiones++;
            if ($sensor === 'dht') {
                $revisionesDht++;
            } elseif ($sensor === 'mq2') {
                $revisionesMq2++;
            } else {
                $revisionesFlama++;
            }
        }
    }
    if ((int) ($dispositivo['mq2_lectura_atascada'] ?? 0) === 1) {
        $revisiones++;
        $revisionesMq2++;
    }
}

$logoCandidatos = [
    dirname(__DIR__, 2) . '/web/assets/logo-id-industrial.png',
    dirname(__DIR__) . '/assets/logo-id-industrial.png',
];
$rutaLogo = null;
foreach ($logoCandidatos as $candidato) {
    if (is_file($candidato)) {
        $rutaLogo = $candidato;
        break;
    }
}

$pdf = new ReportePdf();
$pdf->nuevaPagina();
$pdf->rect(0, 680, 595.28, 161.89, '0.07 0.07 0.07');
$pdf->rect(0, 672, 595.28, 8, '1 0.69 0');
if ($rutaLogo !== null) {
    $pdf->imagenPng($rutaLogo, 42, 766, 155, 53, [18, 22, 27]);
} else {
    $pdf->texto(42, 790, 13, 'ID INDUSTRIAL', '1 0.69 0', true);
}
$pdf->texto(42, 730, 26, 'REPORTE OPERATIVO', '1 1 1', true);
$pdf->texto(42, 706, 10, reportePdfTextoCorto($empresa, 54), '0.78 0.78 0.78');
$pdf->texto(42, 688, 8, 'Periodo UTC: ' . $desde->format('d/m/Y H:i') . ' a ' . $hasta->format('d/m/Y H:i'), '0.70 0.70 0.70');
$pdf->texto(362, 790, 8, 'GENERADO EN UTC', '1 0.69 0', true);
$pdf->texto(362, 772, 9, gmdate('d/m/Y H:i') . ' UTC', '1 1 1', true);
$pdf->texto(362, 752, 8, 'Usuario: ' . reportePdfTextoCorto((string) $usuario['nombre'], 27), '0.70 0.70 0.70');

$kpis = [
    ['Alertas', (string) ((int) ($resumenAlertas['alertas_periodo'] ?? 0)), '0.08 0.08 0.08'],
    ['Criticas', (string) ((int) ($resumenAlertas['alertas_criticas'] ?? 0)), '1 0.69 0'],
    ['Pendientes', (string) ((int) ($resumenAlertas['alertas_pendientes'] ?? 0)), '1 0.69 0'],
    ['Online', "{$online}/" . count($dispositivos), '0.08 0.08 0.08'],
    ['Revisiones', (string) $revisiones, $revisiones > 0 ? '1 0.69 0' : '0.08 0.08 0.08'],
];
$x = 42;
foreach ($kpis as [$etiqueta, $valor, $color]) {
    $pdf->rect($x, 602, 98, 46, '0.92 0.92 0.92');
    $pdf->texto($x + 10, 632, 8, $etiqueta, '0.28 0.28 0.28', true);
    $pdf->texto($x + 10, 611, 19, $valor, $color, true);
    $x += 103;
}

$alertasCriticas = (int) ($resumenAlertas['alertas_criticas'] ?? 0);
$alertasPrecaucion = (int) ($resumenAlertas['alertas_precaucion'] ?? 0);
$alertasPendientes = (int) ($resumenAlertas['alertas_pendientes'] ?? 0);
$maxAlertasGrafica = max(1, $alertasCriticas, $alertasPrecaucion, $alertasPendientes);
$pdf->rect(42, 414, 246, 166, '0.94 0.94 0.94');
$pdf->texto(56, 557, 11, 'Alertas del periodo', '0.08 0.08 0.08', true);
$pdf->texto(56, 541, 7, 'Distribucion por severidad y atencion', '0.35 0.35 0.35');
foreach ([['Criticas', $alertasCriticas], ['Precaucion', $alertasPrecaucion], ['Pendientes', $alertasPendientes]] as $indice => [$etiqueta, $valor]) {
    $yBarra = 512 - ($indice * 29);
    $pdf->texto(56, $yBarra + 5, 8, $etiqueta, '0.18 0.18 0.18', true);
    $pdf->rect(126, $yBarra, 132, 13, '0.78 0.78 0.78');
    $anchoBarra = 132 * ($valor / $maxAlertasGrafica);
    if ($anchoBarra > 0) {
        $pdf->rect(126, $yBarra, $anchoBarra, 13, '1 0.69 0');
    }
    $pdf->texto(266, $yBarra + 3, 8, (string) $valor, '0.08 0.08 0.08', true);
}

$maxSaludGrafica = max(1, $revisionesDht, $revisionesMq2, $revisionesFlama, count($dispositivos) - $online);
$pdf->rect(307, 414, 246, 166, '0.94 0.94 0.94');
$pdf->texto(321, 557, 11, 'Salud de sensores', '0.08 0.08 0.08', true);
$pdf->texto(321, 541, 7, 'Hallazgos que requieren seguimiento', '0.35 0.35 0.35');
foreach ([['DHT11', $revisionesDht], ['MQ-2', $revisionesMq2], ['KY-026', $revisionesFlama], ['Offline', count($dispositivos) - $online]] as $indice => [$etiqueta, $valor]) {
    $yBarra = 512 - ($indice * 24);
    $pdf->texto(321, $yBarra + 4, 8, $etiqueta, '0.18 0.18 0.18', true);
    $pdf->rect(380, $yBarra, 128, 11, '0.78 0.78 0.78');
    $anchoBarra = 128 * ($valor / $maxSaludGrafica);
    if ($anchoBarra > 0) {
        $pdf->rect(380, $yBarra, $anchoBarra, 11, '1 0.69 0');
    }
    $pdf->texto(516, $yBarra + 2, 8, (string) $valor, '0.08 0.08 0.08', true);
}

$pdf->texto(42, 382, 13, 'Estado por dispositivo', '0.08 0.08 0.08', true);
$pdf->linea(42, 374, 553, 374, '0.68 0.68 0.68');
$pdf->rect(42, 349, 511, 18, '0.12 0.12 0.12');
$encabezados = [[48, 'Dispositivo'], [140, 'Ubicacion'], [255, 'Conexion'], [327, 'DHT11'], [380, 'MQ-2'], [433, 'KY-026'], [490, 'Calibracion']];
foreach ($encabezados as [$posicion, $texto]) {
    $pdf->texto($posicion, 355, 7, $texto, '1 1 1', true);
}
$y = 330;
foreach (array_slice($dispositivos, 0, 5) as $indice => $dispositivo) {
    if ($indice % 2 === 0) {
        $pdf->rect(42, $y - 5, 511, 19, '0.95 0.95 0.95');
    }
    $pdf->texto(48, $y + 2, 8, reportePdfTextoCorto((string) $dispositivo['id'], 14), '0.08 0.08 0.08', true);
    $pdf->texto(140, $y + 2, 8, reportePdfTextoCorto((string) $dispositivo['ubicacion'], 20), '0.18 0.18 0.18');
    foreach ([[255, 'conexion'], [327, 'salud_dht'], [380, 'salud_mq2'], [433, 'salud_flama']] as [$posicion, $campo]) {
        $valor = (string) ($dispositivo[$campo] ?? 'SIN DATOS');
        $pdf->texto($posicion, $y + 2, 8, $valor, reportePdfColor($valor), true);
    }
    $calibracion = $dispositivo['ultima_calibracion']
        ? gmdate('d/m/Y', strtotime((string) $dispositivo['ultima_calibracion']))
        : 'PENDIENTE';
    $pdf->texto(490, $y + 2, 8, $calibracion, $calibracion === 'PENDIENTE' ? '1 0.69 0' : '0.18 0.18 0.18', true);
    $y -= 22;
}
$pdf->texto(42, 194, 8, 'Criterio: offline > 2 min; calibracion MQ-2 requerida cada 90 dias; lectura atascada = posible revision.', '0.37 0.37 0.37');
$pdf->texto(42, 56, 8, 'ID Industrial - Monitoreo contra incendios - Documento informativo, no sustituye un sistema certificado.', '0.37 0.37 0.37');

$pdf->nuevaPagina();
$pdf->rect(0, 760, 595.28, 81.89, '0.07 0.07 0.07');
$pdf->rect(0, 752, 595.28, 8, '1 0.69 0');
$pdf->texto(42, 805, 12, 'ID INDUSTRIAL', '1 0.69 0', true);
$pdf->texto(42, 778, 19, 'ALERTAS DEL PERIODO', '1 1 1', true);
$pdf->texto(42, 731, 9, 'Se incluyen hasta 30 alertas, ordenadas de la mas reciente a la mas antigua.', '0.37 0.37 0.37');
$pdf->rect(42, 704, 511, 18, '0.12 0.12 0.12');
foreach ([[48, 'Fecha'], [120, 'Dispositivo'], [210, 'Origen'], [350, 'Valor'], [414, 'Estado'], [484, 'Atencion']] as [$posicion, $texto]) {
    $pdf->texto($posicion, 710, 7, $texto, '1 1 1', true);
}
$y = 683;
foreach ($alertas as $indice => $alerta) {
    if ($y < 76) {
        break;
    }
    if ($indice % 2 === 0) {
        $pdf->rect(42, $y - 5, 511, 19, '0.95 0.95 0.95');
    }
    $fecha = gmdate('d/m/Y H:i', strtotime((string) $alerta['fecha_hora']));
    $tipo = (string) ($alerta['tipo_alerta'] ?? 'Alerta');
    $valor = $alerta['valor_sensor'] === null ? '--' : (string) $alerta['valor_sensor'];
    if (stripos($tipo, 'Flama') !== false) {
        $valor = 'Detectada';
    } elseif (stripos($tipo, 'Gas') !== false || stripos($tipo, 'Humo') !== false) {
        $valor .= ' ADC';
    } elseif (stripos($tipo, 'Temperatura') !== false) {
        $valor .= ' C';
    }
    $pdf->texto(48, $y + 2, 7.5, $fecha, '0.18 0.18 0.18');
    $pdf->texto(120, $y + 2, 7.5, reportePdfTextoCorto((string) $alerta['dispositivo_id'], 14), '0.08 0.08 0.08', true);
    $pdf->texto(210, $y + 2, 7.5, reportePdfTextoCorto($tipo, 24), '0.18 0.18 0.18');
    $pdf->texto(350, $y + 2, 7.5, reportePdfTextoCorto($valor, 10), '0.18 0.18 0.18');
    $pdf->texto(414, $y + 2, 7.5, (string) $alerta['severidad'], reportePdfColor((string) $alerta['severidad']), true);
    $pdf->texto(484, $y + 2, 7.5, reportePdfTextoCorto((string) $alerta['estado_atencion'], 12), '0.18 0.18 0.18', true);
    $y -= 22;
}
if ($alertas === []) {
    $pdf->texto(42, 675, 10, 'No se registraron alertas en el periodo seleccionado.', '0.37 0.37 0.37');
}
$pdf->texto(42, 56, 8, 'ID Industrial - Reporte generado automaticamente en UTC.', '0.37 0.37 0.37');

$nombre = 'reporte_id_industrial_' . gmdate('Ymd_His') . '.pdf';
$contenidoPdf = $pdf->salida();
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $nombre . '"');
header('Content-Length: ' . strlen($contenidoPdf));
header('Cache-Control: no-store, no-cache, must-revalidate');
echo $contenidoPdf;
