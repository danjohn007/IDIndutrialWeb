<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/mobile_auth.php';
require_once dirname(__DIR__) . '/lib/rutinas.php';

requerirMetodo('POST');
$usuario = requerirTokenMovil(['ADMIN']);
$data = obtenerJson();
$rutinaId = (int) ($data['rutina_id'] ?? 0);
$activa = !empty($data['activa']) ? 1 : 0;

try {
    idindRutinasRequerirMigracion($pdo);
    $clienteId = (int) $usuario['cliente_id'];
    if (!idindRutinaObtener($pdo, $clienteId, $rutinaId)) {
        responderJson(404, ['ok' => false, 'error' => 'Rutina no encontrada']);
    }
    $pdo->prepare(
        'UPDATE rutinas SET activa = :activa WHERE id = :id AND cliente_id = :cliente_id'
    )->execute(['activa' => $activa, 'id' => $rutinaId, 'cliente_id' => $clienteId]);
    responderJson(200, ['ok' => true, 'data' => ['rutina_id' => $rutinaId, 'activa' => (bool) $activa]]);
} catch (Throwable $error) {
    error_log('ID Industrial estado rutina: ' . $error->getMessage());
    responderJson(500, ['ok' => false, 'error' => 'No fue posible cambiar la rutina']);
}
