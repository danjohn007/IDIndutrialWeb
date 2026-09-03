<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/auth.php';
requerirMetodo('GET');

$usuario = requerirSesion();
responderJson(200, [
    'ok' => true,
    'data' => [
        'usuario' => [
            'id' => $usuario['id'],
            'nombre' => $usuario['nombre'],
            'email' => $usuario['email'],
            'rol' => $usuario['rol'],
        ],
        'csrf_token' => $usuario['csrf_token'],
    ],
]);
