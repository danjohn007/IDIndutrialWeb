<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/shelly.php';

requerirMetodo('GET');
$usuario = requerirSesion();
$actuadores = idindShellyEstadoCliente($pdo, (int) $usuario['cliente_id']);

responderJson(200, [
    'ok' => true,
    'data' => [
        'configurado' => idindShellyConfigurado($configLocal),
        'generado_en' => gmdate('Y-m-d H:i:s'),
        'actuadores' => $actuadores,
    ],
]);

