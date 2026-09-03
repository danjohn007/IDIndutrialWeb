<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/auth.php';
requerirMetodo('POST');

$usuario = requerirSesion();
requerirCsrf($usuario);
limpiarSesion();

responderJson(200, ['ok' => true]);
