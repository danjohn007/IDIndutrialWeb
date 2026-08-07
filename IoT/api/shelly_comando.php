<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/shelly.php';

requerirMetodo('POST');
$usuario = requerirSesion(['ADMIN', 'OPERADOR']);
requerirCsrf($usuario);
$data = obtenerJson();
$actuadorId = trim((string) ($data['actuador_id'] ?? ''));
$accion = strtoupper(trim((string) ($data['accion'] ?? '')));
if ($actuadorId === '' || strlen($actuadorId) > 64) {
    responderJson(422, ['ok' => false, 'error' => 'Actuador Shelly no valido']);
}
if (!in_array($accion, ['ENCENDER', 'APAGAR'], true)) {
    responderJson(422, ['ok' => false, 'error' => 'Accion Shelly no valida']);
}

$stmt = $pdo->prepare(
    "SELECT id, estado, modo_control FROM actuadores_shelly
     WHERE id = :id AND cliente_id = :cliente_id LIMIT 1"
);
$stmt->execute(['id' => $actuadorId, 'cliente_id' => (int) $usuario['cliente_id']]);
$actuador = $stmt->fetch();
if (!$actuador) {
    responderJson(404, ['ok' => false, 'error' => 'Actuador Shelly no encontrado']);
}
if ($actuador['estado'] !== 'Activo') {
    responderJson(409, ['ok' => false, 'error' => 'El actuador Shelly no esta activo']);
}
if ($actuador['modo_control'] === 'LOCAL') {
    responderJson(409, ['ok' => false, 'error' => 'El actuador esta configurado solo para control local']);
}

try {
    $comandoId = idindShellyCrearComando(
        $pdo,
        $actuadorId,
        $accion,
        'WEB',
        (int) $usuario['id'],
        null,
        'Control manual desde el panel web'
    );
    $resultado = idindShellyProcesarComando($pdo, $configLocal, $comandoId);
    responderJson($resultado['aplicado'] ? 200 : 202, [
        'ok' => true,
        'data' => $resultado,
    ]);
} catch (Throwable $error) {
    error_log('ID Industrial Shelly comando web: ' . $error->getMessage());
    responderJson(502, ['ok' => false, 'error' => $error->getMessage()]);
}

