<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/mobile_auth.php';
requerirMetodo('GET');

$usuario = requerirTokenMovil();
$stmt = $pdo->prepare(
    'SELECT
        id, plataforma, nombre_dispositivo, ultimo_registro
     FROM moviles_push
     WHERE usuario_id = :usuario_id
       AND activo = 1
     ORDER BY ultimo_registro DESC, id DESC'
);
$stmt->execute(['usuario_id' => (int) $usuario['id']]);
$registros = $stmt->fetchAll();

responderJson(200, [
    'ok' => true,
    'data' => [
        'habilitadas' => count($registros),
        'registros' => array_map(
            static function (array $registro): array {
                return [
                    'id' => (int) $registro['id'],
                    'plataforma' => (string) $registro['plataforma'],
                    'nombre_dispositivo' => (string) $registro['nombre_dispositivo'],
                    'ultimo_registro' => (string) $registro['ultimo_registro'],
                ];
            },
            $registros
        ),
    ],
]);
