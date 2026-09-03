<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/mobile_auth.php';
require_once dirname(__DIR__) . '/lib/zkteco.php';

requerirMetodo('GET');
$usuario = requerirTokenMovil();
$clienteId = (int) $usuario['cliente_id'];
idindZktecoRequerirTablas($pdo);

$id = trim((string) ($_GET['equipo_id'] ?? ''));
$equipos = idindZktecoCliente($pdo, $clienteId, $id);
if ($equipos === []) {
    responderJson(404, ['ok' => false, 'error' => 'Equipo ZKTeco no encontrado']);
}

$stmt = $pdo->prepare(
    'SELECT id, pin_usuario, tipo_evento, modo_verificacion, estado_entrada,
            detalle_json, ocurrido_en, recibido_en
     FROM eventos_zkteco
     WHERE equipo_id = :id
     ORDER BY COALESCE(ocurrido_en, recibido_en) DESC, id DESC
     LIMIT 20'
);
$stmt->execute(['id' => $id]);
$eventos = $stmt->fetchAll();
foreach ($eventos as &$evento) {
    $evento['detalle'] = json_decode((string) ($evento['detalle_json'] ?? ''), true) ?: [];
    unset($evento['detalle_json']);
}
unset($evento);

responderJson(200, ['ok' => true, 'data' => [
    'equipo' => $equipos[0],
    'eventos' => $eventos,
    'permisos' => ['administrar' => $usuario['rol'] === 'ADMIN'],
]]);
