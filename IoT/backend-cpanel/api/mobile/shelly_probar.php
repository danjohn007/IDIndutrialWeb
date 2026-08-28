<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/mobile_auth.php';
require_once dirname(__DIR__) . '/lib/shelly_admin.php';

requerirMetodo('POST');
$usuario = requerirTokenMovil(['ADMIN', 'OPERADOR']);
$data = obtenerJson();
$actuadorId = trim((string) ($data['actuador_id'] ?? ''));

try {
    idindShellyAdminRequerirMigracion($pdo);
    $actuadorId = idindShellyAdminId($actuadorId);
    $clienteId = (int) $usuario['cliente_id'];
    $actuador = idindShellyAdminObtener($pdo, $clienteId, $actuadorId);
    if (!$actuador) {
        responderJson(404, ['ok' => false, 'error' => 'Dispositivo Shelly no encontrado']);
    }
    $usarCloud = (string) ($actuador['modo_control'] ?? '') !== 'LOCAL'
        && idindShellyConfigurado($configLocal);
    $resultado = $usarCloud
        ? idindShellySincronizar($pdo, $configLocal, $clienteId, $actuadorId)
        : idindShellySincronizarLocal($pdo, $clienteId, $actuadorId);
    responderJson(200, [
        'ok' => true,
        'data' => [
            'resultado' => $resultado,
            'actuador' => idindShellyAdminObtener($pdo, $clienteId, $actuadorId),
        ],
    ]);
} catch (IdindShellyAdminException $error) {
    responderJson(422, ['ok' => false, 'error' => $error->getMessage()]);
} catch (Throwable $error) {
    error_log('ID Industrial probar Shelly movil: ' . $error->getMessage());
    responderJson(502, ['ok' => false, 'error' => $error->getMessage()]);
}
