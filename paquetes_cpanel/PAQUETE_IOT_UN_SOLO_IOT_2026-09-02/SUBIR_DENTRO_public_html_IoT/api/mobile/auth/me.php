<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/mobile_auth.php';
requerirMetodo('GET');

$usuario = requerirTokenMovil();
responderJson(200, [
    'ok' => true,
    'data' => [
        'usuario' => usuarioMovilPublico($usuario),
        'sesion' => [
            'tipo' => 'Bearer',
            'expira_en' => $usuario['expira_en'],
        ],
    ],
]);
