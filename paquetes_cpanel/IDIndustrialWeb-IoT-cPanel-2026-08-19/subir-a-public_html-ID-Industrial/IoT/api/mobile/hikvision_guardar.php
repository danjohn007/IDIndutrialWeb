<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/mobile_auth.php';
require_once dirname(__DIR__) . '/lib/hikvision.php';

requerirMetodo('POST');
$usuario = requerirTokenMovil(['ADMIN']);
$clienteId = (int) $usuario['cliente_id'];
idindHikvisionRequerirTablas($pdo);
$data = obtenerJson();

$accion = strtoupper(trim((string) ($data['accion'] ?? '')));
$id = trim((string) ($data['id'] ?? ''));
if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,63}$/', $id)) {
    responderJson(422, ['ok' => false, 'error' => 'ID Hikvision invalido']);
}
$ubicacion = trim((string) ($data['ubicacion'] ?? ''));
if (strlen($ubicacion) < 2 || strlen($ubicacion) > 160) {
    responderJson(422, ['ok' => false, 'error' => 'Ubicacion invalida']);
}
$estado = ucfirst(strtolower(trim((string) ($data['estado'] ?? 'Activo'))));
if (!in_array($estado, ['Activo', 'Mantenimiento', 'Inactivo'], true)) {
    responderJson(422, ['ok' => false, 'error' => 'Estado invalido']);
}

$stmt = $pdo->prepare('SELECT * FROM equipos_hikvision WHERE id = :id AND cliente_id = :cliente_id LIMIT 1');
$stmt->execute(['id' => $id, 'cliente_id' => $clienteId]);
$actual = $stmt->fetch() ?: null;
$config = idindHikvisionConfiguracion($data, $actual ?: []);

if ($accion === 'CREAR') {
    if ($actual) responderJson(409, ['ok' => false, 'error' => 'Ese equipo Hikvision ya existe']);
    $stmt = $pdo->prepare(
        'INSERT INTO equipos_hikvision (
           id, cliente_id, nombre, ubicacion, categoria, modelo, numero_serie,
           ip_local, puerto, protocolo, estado
         ) VALUES (
           :id, :cliente_id, :nombre, :ubicacion, :categoria, :modelo, :serial,
           :ip, :puerto, :protocolo, :estado
         )'
    );
} elseif ($accion === 'ACTUALIZAR') {
    if (!$actual) responderJson(404, ['ok' => false, 'error' => 'Equipo Hikvision no encontrado']);
    $stmt = $pdo->prepare(
        'UPDATE equipos_hikvision SET
           nombre = :nombre, ubicacion = :ubicacion, categoria = :categoria,
           modelo = :modelo, numero_serie = :serial, ip_local = :ip,
           puerto = :puerto, protocolo = :protocolo, estado = :estado
         WHERE id = :id AND cliente_id = :cliente_id'
    );
} else {
    responderJson(422, ['ok' => false, 'error' => 'Accion Hikvision no reconocida']);
}
$stmt->execute([
    'id' => $id, 'cliente_id' => $clienteId, 'nombre' => $config['nombre'],
    'ubicacion' => $ubicacion, 'categoria' => $config['categoria'],
    'modelo' => $config['modelo'], 'serial' => $config['numero_serie'],
    'ip' => $config['ip_local'], 'puerto' => $config['puerto'],
    'protocolo' => $config['protocolo'], 'estado' => $estado,
]);
$equipo = idindHikvisionCliente($pdo, $clienteId, $id);
responderJson($accion === 'CREAR' ? 201 : 200, ['ok' => true, 'data' => ['equipo' => $equipo[0] ?? null]]);
