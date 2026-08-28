<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/shelly.php';

requerirMetodo('POST');
$usuario = requerirSesion(['ADMIN', 'OPERADOR']);
requerirCsrf($usuario);
$data = obtenerJson();
$actuadorId = trim((string) ($data['actuador_id'] ?? ''));
if ($actuadorId === '' || strlen($actuadorId) > 64) {
    responderJson(422, ['ok' => false, 'error' => 'Actuador Shelly no valido']);
}

try {
    $estadoActual = array_values(array_filter(
        idindShellyEstadoCliente($pdo, (int) $usuario['cliente_id']),
        static function (array $fila) use ($actuadorId): bool {
            return (string) $fila['id'] === $actuadorId;
        }
    ));
    $actuadorActual = $estadoActual[0] ?? null;
    if (!$actuadorActual) {
        responderJson(404, ['ok' => false, 'error' => 'Actuador Shelly no encontrado']);
    }
    $usarCloud = (string) ($actuadorActual['modo_control'] ?? '') !== 'LOCAL'
        && idindShellyConfigurado($configLocal);
    $resultado = $usarCloud
        ? idindShellySincronizar(
            $pdo,
            $configLocal,
            (int) $usuario['cliente_id'],
            $actuadorId
        )
        : idindShellySincronizarLocal($pdo, (int) $usuario['cliente_id'], $actuadorId);
    $estado = array_values(array_filter(
        idindShellyEstadoCliente($pdo, (int) $usuario['cliente_id']),
        static function (array $fila) use ($actuadorId): bool {
            return (string) $fila['id'] === $actuadorId;
        }
    ));
    responderJson(200, [
        'ok' => true,
        'data' => [
            'resultado' => $resultado,
            'actuador' => $estado[0] ?? null,
        ],
    ]);
} catch (Throwable $error) {
    error_log('ID Industrial Shelly probar: ' . $error->getMessage());
    responderJson(502, ['ok' => false, 'error' => $error->getMessage()]);
}

