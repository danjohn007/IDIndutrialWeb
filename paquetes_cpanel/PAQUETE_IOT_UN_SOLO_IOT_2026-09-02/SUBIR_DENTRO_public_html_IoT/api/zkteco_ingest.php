<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/zkteco.php';

requerirMetodo('POST');
idindZktecoRequerirTablas($pdo);

$tokenConfigurado = trim((string) ($configLocal['zkteco_bridge_token'] ?? (getenv('IDIND_ZKTECO_BRIDGE_TOKEN') ?: '')));
$tokenRecibido = trim((string) ($_SERVER['HTTP_X_ZKTECO_BRIDGE_TOKEN'] ?? ''));
if (strlen($tokenConfigurado) < 32) {
    responderJson(503, ['ok' => false, 'error' => 'Token del conector ZKTeco no configurado']);
}
if ($tokenRecibido === '' || !hash_equals($tokenConfigurado, $tokenRecibido)) {
    responderJson(401, ['ok' => false, 'error' => 'Token del conector ZKTeco invalido']);
}

$data = obtenerJson();
$equipoId = trim((string) ($data['equipo_id'] ?? ''));
if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,63}$/', $equipoId)) {
    responderJson(422, ['ok' => false, 'error' => 'equipo_id invalido']);
}
$stmt = $pdo->prepare("SELECT id FROM equipos_zkteco WHERE id = :id AND estado <> 'Inactivo' LIMIT 1");
$stmt->execute(['id' => $equipoId]);
if (!$stmt->fetchColumn()) {
    responderJson(404, ['ok' => false, 'error' => 'Equipo ZKTeco no registrado o inactivo']);
}

$limpiar = static function ($valor, int $max): ?string {
    $texto = trim((string) ($valor ?? ''));
    return $texto === '' ? null : substr($texto, 0, $max);
};
$entero = static function ($valor): ?int {
    return is_numeric($valor) ? max(0, (int) $valor) : null;
};
$online = !empty($data['online']) ? 1 : 0;
$estado = is_array($data['estado'] ?? null) ? $data['estado'] : [];
$ultimoError = $limpiar($data['error'] ?? null, 500);
$fuente = strtoupper((string) ($data['fuente'] ?? 'CONECTOR_LOCAL'));
if (!in_array($fuente, ['CONECTOR_LOCAL', 'PUSH_ADMS', 'WDMS_API'], true)) $fuente = 'CONECTOR_LOCAL';

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare(
        "INSERT INTO estado_zkteco (
           equipo_id, online, nombre_detectado, modelo_detectado, serial_detectado,
           firmware, plataforma, usuarios_total, registros_total, capacidad_usuarios,
           capacidad_registros, fuente, ultimo_error, sincronizado_en
         ) VALUES (
           :id, :online, :nombre, :modelo, :serial, :firmware, :plataforma,
           :usuarios, :registros, :cap_usuarios, :cap_registros, :fuente, :error, UTC_TIMESTAMP()
         ) ON DUPLICATE KEY UPDATE
           online = VALUES(online), nombre_detectado = COALESCE(VALUES(nombre_detectado), nombre_detectado),
           modelo_detectado = COALESCE(VALUES(modelo_detectado), modelo_detectado),
           serial_detectado = COALESCE(VALUES(serial_detectado), serial_detectado),
           firmware = COALESCE(VALUES(firmware), firmware),
           plataforma = COALESCE(VALUES(plataforma), plataforma),
           usuarios_total = COALESCE(VALUES(usuarios_total), usuarios_total),
           registros_total = COALESCE(VALUES(registros_total), registros_total),
           capacidad_usuarios = COALESCE(VALUES(capacidad_usuarios), capacidad_usuarios),
           capacidad_registros = COALESCE(VALUES(capacidad_registros), capacidad_registros),
           fuente = VALUES(fuente), ultimo_error = VALUES(ultimo_error),
           sincronizado_en = VALUES(sincronizado_en)"
    );
    $stmt->execute([
        'id' => $equipoId, 'online' => $online,
        'nombre' => $limpiar($estado['nombre'] ?? null, 100),
        'modelo' => $limpiar($estado['modelo'] ?? null, 100),
        'serial' => $limpiar($estado['serial'] ?? null, 100),
        'firmware' => $limpiar($estado['firmware'] ?? null, 100),
        'plataforma' => $limpiar($estado['plataforma'] ?? null, 100),
        'usuarios' => $entero($estado['usuarios_total'] ?? null),
        'registros' => $entero($estado['registros_total'] ?? null),
        'cap_usuarios' => $entero($estado['capacidad_usuarios'] ?? null),
        'cap_registros' => $entero($estado['capacidad_registros'] ?? null),
        'fuente' => $fuente, 'error' => $ultimoError,
    ]);
    $pdo->prepare('UPDATE equipos_zkteco SET ultima_conexion = UTC_TIMESTAMP() WHERE id = :id')
        ->execute(['id' => $equipoId]);

    $eventos = is_array($data['eventos'] ?? null) ? array_slice($data['eventos'], 0, 25) : [];
    $insertados = 0;
    foreach ($eventos as $evento) {
        if (!is_array($evento)) continue;
        $ocurrido = $limpiar($evento['ocurrido_en'] ?? null, 25);
        $ocurridoSql = $ocurrido && preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}/', $ocurrido)
            ? substr(str_replace('T', ' ', $ocurrido), 0, 19) : null;
        $pin = $limpiar($evento['pin_usuario'] ?? null, 64);
        $tipo = $limpiar($evento['tipo_evento'] ?? 'MARCAJE', 50) ?: 'MARCAJE';
        $modo = $limpiar($evento['modo_verificacion'] ?? null, 50);
        $entrada = $limpiar($evento['estado_entrada'] ?? null, 50);
        $detalle = json_encode($evento['detalle'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $dedupe = $limpiar($evento['dedupe_key'] ?? null, 64)
            ?: hash('sha256', $equipoId . '|' . ($pin ?? '') . '|' . $tipo . '|' . ($ocurridoSql ?? '') . '|' . $detalle);
        if (!preg_match('/^[a-fA-F0-9]{64}$/', $dedupe)) $dedupe = hash('sha256', $dedupe);
        $stmtEvento = $pdo->prepare(
            'INSERT IGNORE INTO eventos_zkteco (
               equipo_id, pin_usuario, tipo_evento, modo_verificacion,
               estado_entrada, dedupe_key, detalle_json, ocurrido_en
             ) VALUES (:equipo, :pin, :tipo, :modo, :entrada, :dedupe, :detalle, :ocurrido)'
        );
        $stmtEvento->execute([
            'equipo' => $equipoId, 'pin' => $pin, 'tipo' => $tipo, 'modo' => $modo,
            'entrada' => $entrada, 'dedupe' => strtolower($dedupe), 'detalle' => $detalle,
            'ocurrido' => $ocurridoSql,
        ]);
        $insertados += $stmtEvento->rowCount();
    }
    $pdo->commit();
    responderJson(200, ['ok' => true, 'data' => ['equipo_id' => $equipoId, 'eventos_insertados' => $insertados]]);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('ID Industrial ZKTeco ingest: ' . $error->getMessage());
    responderJson(500, ['ok' => false, 'error' => 'No fue posible guardar el estado ZKTeco']);
}
