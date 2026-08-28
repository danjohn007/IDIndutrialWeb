<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/mobile_auth.php';
require_once dirname(__DIR__) . '/lib/shelly_admin.php';

requerirMetodo('POST');
$usuario = requerirTokenMovil(['ADMIN']);
$data = obtenerJson();

try {
    idindShellyAdminRequerirMigracion($pdo);
    $deviceId = idindShellyAdminTexto(
        $data['shelly_device_id'] ?? '',
        'El Device ID de Shelly',
        3,
        100
    );
    $respuesta = idindShellyPeticionCloud(
        $pdo,
        $configLocal,
        (int) $usuario['cliente_id'],
        '/v2/devices/api/get',
        ['ids' => [$deviceId], 'select' => ['status', 'settings']]
    );
    $dispositivos = idindShellyListaRespuesta($respuesta);
    $dispositivo = null;
    foreach ($dispositivos as $item) {
        if (strtolower((string) ($item['id'] ?? '')) === strtolower($deviceId)) {
            $dispositivo = $item;
            break;
        }
    }
    if (!$dispositivo) {
        responderJson(404, ['ok' => false, 'error' => 'Shelly Cloud no devolvio ese Device ID']);
    }

    $status = is_array($dispositivo['status'] ?? null) ? $dispositivo['status'] : [];
    $settings = is_array($dispositivo['settings'] ?? null) ? $dispositivo['settings'] : [];
    $canales = [];
    foreach (array_unique(array_merge(array_keys($status), array_keys($settings))) as $clave) {
        if (preg_match('/^(?:switch|relay):(\d+)$/', (string) $clave, $coincidencia)) {
            $canales[] = (int) $coincidencia[1];
        }
    }
    sort($canales);
    if ($canales === []) {
        $canales = [0];
    }
    $generacionCloud = strtoupper((string) ($dispositivo['gen'] ?? $dispositivo['generation'] ?? ''));
    $modelo = trim((string) (
        $dispositivo['model']
        ?? $dispositivo['code']
        ?? $settings['device']['name']
        ?? 'Shelly'
    ));

    responderJson(200, [
        'ok' => true,
        'data' => [
            'shelly_device_id' => $deviceId,
            'modelo' => substr($modelo, 0, 80),
            'generacion' => in_array($generacionCloud, ['G1', 'GEN1'], true) ? 'GEN1' : 'GEN2_PLUS',
            'online' => !empty($dispositivo['online']),
            'canales' => $canales,
        ],
    ]);
} catch (IdindShellyAdminException $error) {
    responderJson(422, ['ok' => false, 'error' => $error->getMessage()]);
} catch (Throwable $error) {
    error_log('ID Industrial detectar Shelly movil: ' . $error->getMessage());
    responderJson(502, ['ok' => false, 'error' => $error->getMessage()]);
}
