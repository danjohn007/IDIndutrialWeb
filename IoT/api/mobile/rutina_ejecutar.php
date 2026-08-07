<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/mobile_auth.php';
require_once dirname(__DIR__) . '/lib/rutinas.php';

requerirMetodo('POST');
$usuario = requerirTokenMovil(['ADMIN', 'OPERADOR']);
$data = obtenerJson();
$rutinaId = (int) ($data['rutina_id'] ?? 0);
if ($rutinaId < 1 || empty($data['confirmado'])) {
    responderJson(422, ['ok' => false, 'error' => 'Confirma la ejecucion de la rutina']);
}

try {
    idindRutinasRequerirMigracion($pdo);
    $resultado = idindRutinaEjecutar(
        $pdo,
        $configLocal,
        (int) $usuario['cliente_id'],
        $rutinaId,
        'MANUAL',
        (int) $usuario['id']
    );
    responderJson(!empty($resultado['ejecutada']) ? 200 : 409, [
        'ok' => !empty($resultado['ejecutada']),
        'data' => $resultado,
        'error' => $resultado['error'] ?? null,
    ]);
} catch (IdindRutinaException $error) {
    responderJson(422, ['ok' => false, 'error' => $error->getMessage()]);
} catch (Throwable $error) {
    error_log('ID Industrial ejecutar rutina: ' . $error->getMessage());
    responderJson(500, ['ok' => false, 'error' => 'No fue posible ejecutar la rutina']);
}
