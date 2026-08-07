<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
requerirMetodo('GET');
$usuario = requerirSesion();

$dispositivoId = trim((string) ($_GET['dispositivo_id'] ?? ''));
$params = ['cliente_id' => (int) $usuario['cliente_id']];
$filtro = '';

if ($dispositivoId !== '') {
    if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $dispositivoId)) {
        responderJson(422, ['ok' => false, 'error' => 'dispositivo_id invalido']);
    }
    $filtro = ' AND e.dispositivo_id = :dispositivo_id';
    $params['dispositivo_id'] = $dispositivoId;
}

$stmt = $pdo->prepare(
    "SELECT
        e.*,
        e.actualizado_en AS fecha_hora,
        d.ubicacion,
        d.estado AS estado_dispositivo
     FROM estado_sensores e
     INNER JOIN dispositivos d ON d.id = e.dispositivo_id
     WHERE d.cliente_id = :cliente_id
       {$filtro}
     ORDER BY e.actualizado_en DESC
     LIMIT 1"
);
$stmt->execute($params);

responderJson(200, ['ok' => true, 'data' => $stmt->fetch() ?: null]);
