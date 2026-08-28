<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
requerirMetodo('GET');
$usuario = requerirSesion();

$limite = filter_var($_GET['limite'] ?? 100, FILTER_VALIDATE_INT);
$limite = $limite === false ? 100 : max(1, min(500, $limite));

$condiciones = ['d.cliente_id = :cliente_id'];
$params = ['cliente_id' => (int) $usuario['cliente_id']];
$dispositivoId = trim((string) ($_GET['dispositivo_id'] ?? ''));

if ($dispositivoId !== '') {
    if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $dispositivoId)) {
        responderJson(422, ['ok' => false, 'error' => 'dispositivo_id invalido']);
    }
    $condiciones[] = 'l.dispositivo_id = :dispositivo_id';
    $params['dispositivo_id'] = $dispositivoId;
}

foreach (['desde' => '>=', 'hasta' => '<='] as $campo => $operador) {
    if (!isset($_GET[$campo]) || trim((string) $_GET[$campo]) === '') {
        continue;
    }

    try {
        $fecha = new DateTimeImmutable((string) $_GET[$campo]);
        $fecha = $fecha->setTimezone(new DateTimeZone('UTC'));
    } catch (Throwable $error) {
        responderJson(422, ['ok' => false, 'error' => "Fecha invalida: {$campo}"]);
    }

    $condiciones[] = "l.fecha_hora {$operador} :{$campo}";
    $params[$campo] = $fecha->format('Y-m-d H:i:s');
}

$where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';
$stmt = $pdo->prepare(
    "SELECT
        l.*,
        d.ubicacion
     FROM lecturas_sensores l
     INNER JOIN dispositivos d ON d.id = l.dispositivo_id
     {$where}
     ORDER BY l.fecha_hora DESC, l.id DESC
     LIMIT :limite"
);

foreach ($params as $nombre => $valor) {
    $tipo = $nombre === 'cliente_id' ? PDO::PARAM_INT : PDO::PARAM_STR;
    $stmt->bindValue(':' . $nombre, $valor, $tipo);
}
$stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
$stmt->execute();

responderJson(200, [
    'ok' => true,
    'data' => $stmt->fetchAll(),
    'meta' => ['limite' => $limite],
]);
