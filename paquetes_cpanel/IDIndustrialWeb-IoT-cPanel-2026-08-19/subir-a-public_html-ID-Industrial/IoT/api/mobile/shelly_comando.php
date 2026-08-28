<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/mobile_auth.php';
require_once dirname(__DIR__) . '/lib/shelly.php';

requerirMetodo('POST');
$usuario = requerirTokenMovil(['ADMIN', 'OPERADOR']);
$data = obtenerJson();
$actuadorId = trim((string) ($data['actuador_id'] ?? ''));
$accion = strtoupper(trim((string) ($data['accion'] ?? '')));
$confirmado = !empty($data['confirmado']);

if ($actuadorId === '' || strlen($actuadorId) > 64) {
    responderJson(422, ['ok' => false, 'error' => 'Actuador Shelly no valido']);
}
if (!in_array($accion, ['ENCENDER', 'APAGAR'], true)) {
    responderJson(422, ['ok' => false, 'error' => 'Accion Shelly no valida']);
}

try {
    $stmt = $pdo->prepare(
        "SELECT id, funcion, categoria, requiere_confirmacion FROM actuadores_shelly
         WHERE id = :id AND cliente_id = :cliente_id
           AND estado = 'Activo' AND modo_control IN ('LOCAL', 'CLOUD', 'HIBRIDO')
         LIMIT 1"
    );
    $stmt->execute([
        'id' => $actuadorId,
        'cliente_id' => (int) $usuario['cliente_id'],
    ]);
    $actuador = $stmt->fetch();
    if (!$actuador) {
        responderJson(404, ['ok' => false, 'error' => 'Actuador Shelly no disponible']);
    }
    if ((string) $actuador['categoria'] === 'MONITOREO') {
        responderJson(409, ['ok' => false, 'error' => 'Este equipo esta configurado solo para monitoreo']);
    }
    if ($accion === 'ENCENDER' && !empty($actuador['requiere_confirmacion']) && !$confirmado) {
        responderJson(409, ['ok' => false, 'error' => 'Confirma expresamente el encendido del dispositivo']);
    }

    $comandoId = idindShellyCrearComando(
        $pdo,
        $actuadorId,
        $accion,
        'APP',
        (int) $usuario['id'],
        null,
        'Control manual desde la app movil'
    );
    $resultado = idindShellyProcesarComando($pdo, $configLocal, $comandoId);
    responderJson(!empty($resultado['aplicado']) ? 200 : 202, [
        'ok' => true,
        'data' => $resultado,
    ]);
} catch (Throwable $error) {
    error_log('ID Industrial comando Shelly app: ' . $error->getMessage());
    responderJson(500, ['ok' => false, 'error' => 'No fue posible controlar el Shelly']);
}
