<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/mobile_auth.php';
require_once dirname(__DIR__) . '/lib/shelly_admin.php';

requerirMetodo('GET');
$usuario = requerirTokenMovil();
$actuadorId = trim((string) ($_GET['actuador_id'] ?? ''));

try {
    idindShellyAdminRequerirMigracion($pdo);
    $actuadorId = idindShellyAdminId($actuadorId);
    $clienteId = (int) $usuario['cliente_id'];
    $actuador = idindShellyAdminObtener($pdo, $clienteId, $actuadorId);
    if (!$actuador) {
        responderJson(404, ['ok' => false, 'error' => 'Dispositivo Shelly no encontrado']);
    }

    $stmt = $pdo->prepare(
        'SELECT id, evento, origen, salida_encendida, detalle_json, fecha_hora
         FROM eventos_shelly
         WHERE actuador_id = :actuador_id
         ORDER BY fecha_hora DESC, id DESC LIMIT 10'
    );
    $stmt->execute(['actuador_id' => $actuadorId]);
    $eventos = $stmt->fetchAll();
    foreach ($eventos as &$evento) {
        $detalle = json_decode((string) ($evento['detalle_json'] ?? ''), true);
        $evento['detalle'] = is_array($detalle) ? $detalle : [];
        $evento['salida_encendida'] = $evento['salida_encendida'] === null
            ? null
            : (int) $evento['salida_encendida'];
        unset($evento['detalle_json']);
    }
    unset($evento);

    $stmt = $pdo->prepare(
        "SELECT id, ubicacion, estado
         FROM dispositivos
         WHERE cliente_id = :cliente_id AND estado <> 'Inactivo'
         ORDER BY ubicacion, id"
    );
    $stmt->execute(['cliente_id' => $clienteId]);

    responderJson(200, [
        'ok' => true,
        'data' => [
            'actuador' => $actuador,
            'eventos' => $eventos,
            'dispositivos_esp32' => $stmt->fetchAll(),
            'permisos' => [
                'administrar' => $usuario['rol'] === 'ADMIN',
                'controlar' => in_array($usuario['rol'], ['ADMIN', 'OPERADOR'], true),
            ],
        ],
    ]);
} catch (IdindShellyAdminException $error) {
    responderJson(422, ['ok' => false, 'error' => $error->getMessage()]);
} catch (Throwable $error) {
    error_log('ID Industrial detalle Shelly movil: ' . $error->getMessage());
    responderJson(500, ['ok' => false, 'error' => 'No fue posible cargar el dispositivo Shelly']);
}
