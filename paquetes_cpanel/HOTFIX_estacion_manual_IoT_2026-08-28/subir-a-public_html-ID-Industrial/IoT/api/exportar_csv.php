<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
requerirMetodo('GET');
$usuario = requerirSesion();

$limite = filter_var($_GET['limite'] ?? 1000, FILTER_VALIDATE_INT);
$limite = $limite === false ? 1000 : max(1, min(5000, $limite));

$gasUmbral = filter_var($_GET['gas_umbral'] ?? 1600, FILTER_VALIDATE_INT);
$gasUmbral = $gasUmbral === false ? 1600 : max(1, min(4095, $gasUmbral));

$condiciones = ['d.cliente_id = :cliente_id'];
$params = ['cliente_id' => (int) $usuario['cliente_id']];
$dispositivoId = trim((string) ($_GET['dispositivo_id'] ?? ''));

if ($dispositivoId !== '') {
    if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $dispositivoId)) {
        responderJson(422, ['ok' => false, 'error' => 'dispositivo_id invalido']);
    }
    $condiciones[] = 'l.dispositivo_id = :dispositivo_id';
    $params['dispositivo_id'] = $dispositivoId;
}

foreach (['desde' => '>=', 'hasta' => '<='] as $campo => $operador) {
    if (!isset($_GET[$campo]) || trim((string) $_GET[$campo]) === '') {
        continue;
    }

    try {
        $fecha = new DateTimeImmutable((string) $_GET[$campo]);
        $fecha = $fecha->setTimezone(new DateTimeZone('UTC'));
    } catch (Throwable $error) {
        responderJson(422, ['ok' => false, 'error' => "Fecha invalida: {$campo}"]);
    }

    $condiciones[] = "l.fecha_hora {$operador} :{$campo}";
    $params[$campo] = $fecha->format('Y-m-d H:i:s');
}

$where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';
$stmt = $pdo->prepare(
    "SELECT
        l.fecha_hora,
        l.dispositivo_id,
        d.ubicacion,
        l.temperatura,
        l.humedad,
        l.indice_calor,
        l.gas_raw,
        l.gas_porcentaje,
        CASE WHEN l.gas_raw >= {$gasUmbral} THEN 1 ELSE 0 END AS gas_detectado,
        l.flama_detectada,
        l.estacion_manual_activada,
        l.estado_general,
        l.salud_dht,
        l.salud_mq2,
        l.salud_flama,
        l.wifi_rssi,
        l.tiempo_encendido,
        l.contador_alarmas
     FROM lecturas_sensores l
     INNER JOIN dispositivos d ON d.id = l.dispositivo_id
     {$where}
     ORDER BY l.fecha_hora DESC, l.id DESC
     LIMIT :limite"
);

foreach ($params as $nombre => $valor) {
    $tipo = $nombre === 'cliente_id' ? PDO::PARAM_INT : PDO::PARAM_STR;
    $stmt->bindValue(':' . $nombre, $valor, $tipo);
}
$stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
$stmt->execute();

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="alarmas_id_industrial.csv"');
header('Cache-Control: no-store, no-cache, must-revalidate');

$salida = fopen('php://output', 'w');
if ($salida === false) {
    exit;
}

echo "\xEF\xBB\xBF";
fputcsv($salida, [
    'fecha_hora_utc',
    'dispositivo_id',
    'ubicacion',
    'temperatura_c',
    'humedad_porcentaje',
    'indice_calor_c',
    'gas_raw_adc',
    'gas_porcentaje',
    'gas_detectado',
    'flama_detectada',
    'estacion_manual_activada',
    'estado_general',
    'salud_dht',
    'salud_mq2',
    'salud_flama',
    'wifi_rssi_dbm',
    'tiempo_encendido_s',
    'contador_alarmas',
]);

while ($fila = $stmt->fetch()) {
    fputcsv($salida, $fila);
}

fclose($salida);
