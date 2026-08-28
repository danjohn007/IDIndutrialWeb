<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/hikvision.php';

requerirMetodo('POST');
idindHikvisionRequerirTablas($pdo);

$tokenConfigurado = trim((string) ($configLocal['hikvision_bridge_token'] ?? (getenv('IDIND_HIKVISION_BRIDGE_TOKEN') ?: '')));
$tokenRecibido = trim((string) ($_SERVER['HTTP_X_HIKVISION_BRIDGE_TOKEN'] ?? ''));
if (strlen($tokenConfigurado) < 32) {
    responderJson(503, ['ok' => false, 'error' => 'Token del conector Hikvision no configurado']);
}
if ($tokenRecibido === '' || !hash_equals($tokenConfigurado, $tokenRecibido)) {
    responderJson(401, ['ok' => false, 'error' => 'Token del conector Hikvision invalido']);
}

$data = obtenerJson();
$equipoId = trim((string) ($data['equipo_id'] ?? ''));
if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,63}$/', $equipoId)) {
    responderJson(422, ['ok' => false, 'error' => 'equipo_id invalido']);
}
$stmt = $pdo->prepare("SELECT id FROM equipos_hikvision WHERE id = :id AND estado <> 'Inactivo' LIMIT 1");
$stmt->execute(['id' => $equipoId]);
if (!$stmt->fetchColumn()) {
    responderJson(404, ['ok' => false, 'error' => 'Equipo Hikvision no registrado o inactivo']);
}

$limpiar = static function ($valor, int $max): ?string {
    $texto = trim((string) ($valor ?? ''));
    return $texto === '' ? null : substr($texto, 0, $max);
};
$online = !empty($data['online']) ? 1 : 0;
$estado = is_array($data['estado'] ?? null) ? $data['estado'] : [];
$ultimoError = $limpiar($data['error'] ?? null, 500);
$uptime = isset($estado['uptime_s']) && is_numeric($estado['uptime_s']) ? max(0, (int) $estado['uptime_s']) : null;

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare(
        "INSERT INTO estado_hikvision (
           equipo_id, online, nombre_detectado, modelo_detectado, serial_detectado,
           firmware, mac, uptime_s, fuente, ultimo_error, sincronizado_en
         ) VALUES (
           :id, :online, :nombre, :modelo, :serial, :firmware, :mac, :uptime,
           'CONECTOR_LOCAL', :error, UTC_TIMESTAMP()
         ) ON DUPLICATE KEY UPDATE
           online = VALUES(online), nombre_detectado = COALESCE(VALUES(nombre_detectado), nombre_detectado),
           modelo_detectado = COALESCE(VALUES(modelo_detectado), modelo_detectado),
           serial_detectado = COALESCE(VALUES(serial_detectado), serial_detectado),
           firmware = COALESCE(VALUES(firmware), firmware), mac = COALESCE(VALUES(mac), mac),
           uptime_s = COALESCE(VALUES(uptime_s), uptime_s),
           fuente = VALUES(fuente), ultimo_error = VALUES(ultimo_error),
           sincronizado_en = VALUES(sincronizado_en)"
    );
    $stmt->execute([
        'id' => $equipoId, 'online' => $online,
        'nombre' => $limpiar($estado['nombre'] ?? null, 100),
        'modelo' => $limpiar($estado['modelo'] ?? null, 100),
        'serial' => $limpiar($estado['serial'] ?? null, 100),
        'firmware' => $limpiar($estado['firmware'] ?? null, 100),
        'mac' => $limpiar($estado['mac'] ?? null, 32),
        'uptime' => $uptime, 'error' => $ultimoError,
    ]);
    $pdo->prepare('UPDATE equipos_hikvision SET ultima_conexion = UTC_TIMESTAMP() WHERE id = :id')
        ->execute(['id' => $equipoId]);

    $eventos = is_array($data['eventos'] ?? null) ? array_slice($data['eventos'], 0, 50) : [];
    $insertados = 0;
    foreach ($eventos as $evento) {
        if (!is_array($evento)) continue;
        $tipo = $limpiar($evento['tipo'] ?? null, 80);
        if (!$tipo) continue;
        $severidad = strtoupper((string) ($evento['severidad'] ?? 'INFO'));
        if (!in_array($severidad, ['INFO', 'PRECAUCION', 'CRITICO'], true)) $severidad = 'INFO';
        $detalle = json_encode($evento['detalle'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $ocurrido = $limpiar($evento['ocurrido_en'] ?? null, 25);
        $dedupe = hash('sha256', $equipoId . '|' . $tipo . '|' . ($evento['codigo'] ?? '') . '|' . ($ocurrido ?? '') . '|' . $detalle);
        $stmtEvento = $pdo->prepare(
            'INSERT IGNORE INTO eventos_hikvision (
               equipo_id, tipo_evento, severidad, codigo, descripcion, dedupe_key, detalle_json, ocurrido_en
             ) VALUES (:equipo, :tipo, :severidad, :codigo, :descripcion, :dedupe, :detalle, :ocurrido)'
        );
        $stmtEvento->execute([
            'equipo' => $equipoId, 'tipo' => $tipo, 'severidad' => $severidad,
            'codigo' => $limpiar($evento['codigo'] ?? null, 100),
            'descripcion' => $limpiar($evento['descripcion'] ?? null, 500),
            'dedupe' => $dedupe, 'detalle' => $detalle,
            'ocurrido' => $ocurrido && preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}/', $ocurrido)
                ? substr(str_replace('T', ' ', $ocurrido), 0, 19) : null,
        ]);
        $insertados += $stmtEvento->rowCount();
    }
    $pdo->commit();
    responderJson(200, ['ok' => true, 'data' => ['equipo_id' => $equipoId, 'eventos_insertados' => $insertados]]);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('ID Industrial Hikvision ingest: ' . $error->getMessage());
    responderJson(500, ['ok' => false, 'error' => 'No fue posible guardar el estado Hikvision']);
}
