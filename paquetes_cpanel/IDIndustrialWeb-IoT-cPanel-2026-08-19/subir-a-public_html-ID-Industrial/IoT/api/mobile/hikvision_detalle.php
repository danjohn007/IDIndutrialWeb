<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/mobile_auth.php';
require_once dirname(__DIR__) . '/lib/hikvision.php';

requerirMetodo('GET');
$usuario = requerirTokenMovil();
$clienteId = (int) $usuario['cliente_id'];
idindHikvisionRequerirTablas($pdo);
$id = trim((string) ($_GET['equipo_id'] ?? ''));
$equipos = idindHikvisionCliente($pdo, $clienteId, $id);
if ($equipos === []) responderJson(404, ['ok' => false, 'error' => 'Equipo Hikvision no encontrado']);

$stmt = $pdo->prepare(
    'SELECT id, tipo_evento, severidad, codigo, descripcion, detalle_json, ocurrido_en, recibido_en
     FROM eventos_hikvision WHERE equipo_id = :id ORDER BY recibido_en DESC, id DESC LIMIT 20'
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
