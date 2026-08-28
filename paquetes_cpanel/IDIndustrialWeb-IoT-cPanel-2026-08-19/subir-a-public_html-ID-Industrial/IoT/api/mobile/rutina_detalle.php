<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/mobile_auth.php';
require_once dirname(__DIR__) . '/lib/rutinas.php';

requerirMetodo('GET');
$usuario = requerirTokenMovil();
$rutinaId = filter_var($_GET['rutina_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($rutinaId === false || $rutinaId === null) {
    responderJson(422, ['ok' => false, 'error' => 'Rutina no valida']);
}

try {
    idindRutinasRequerirMigracion($pdo);
    $clienteId = (int) $usuario['cliente_id'];
    $rutina = idindRutinaObtener($pdo, $clienteId, (int) $rutinaId);
    if (!$rutina) {
        responderJson(404, ['ok' => false, 'error' => 'Rutina no encontrada']);
    }
    responderJson(200, [
        'ok' => true,
        'data' => [
            'rutina' => $rutina,
            'actuadores' => idindRutinaActuadoresDisponibles($pdo, $clienteId),
            'ejecuciones' => idindRutinaEjecuciones($pdo, $clienteId, (int) $rutinaId, 30),
            'permisos' => [
                'administrar' => $usuario['rol'] === 'ADMIN',
                'ejecutar' => in_array($usuario['rol'], ['ADMIN', 'OPERADOR'], true),
            ],
        ],
    ]);
} catch (Throwable $error) {
    error_log('ID Industrial detalle rutina: ' . $error->getMessage());
    responderJson(500, ['ok' => false, 'error' => 'No fue posible cargar la rutina']);
}
