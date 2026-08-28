<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
requerirMetodo('POST');

$usuario = requerirSesion(['ADMIN', 'OPERADOR']);
requerirCsrf($usuario);
$data = obtenerJson();

$dispositivoId = trim((string) ($data['dispositivo_id'] ?? ''));
$comentario = trim((string) ($data['comentario'] ?? ''));

if (
    $dispositivoId === ''
    || strlen($dispositivoId) > 64
    || !preg_match('/^[A-Za-z0-9_-]+$/', $dispositivoId)
) {
    responderJson(422, ['ok' => false, 'error' => 'Dispositivo invalido']);
}
if (strlen($comentario) > 500) {
    responderJson(422, ['ok' => false, 'error' => 'El comentario supera 500 caracteres']);
}

$stmtLectura = $pdo->prepare(
    "SELECT
        e.gas_raw,
        e.gas_detectado,
        e.salud_mq2,
        COALESCE(c.umbral_adc, 1600) AS umbral_adc
     FROM dispositivos d
     INNER JOIN estado_sensores e ON e.dispositivo_id = d.id
     LEFT JOIN configuracion_mq2 c ON c.dispositivo_id = d.id
     WHERE d.id = :dispositivo_id
       AND d.cliente_id = :cliente_id
       AND d.estado <> 'Inactivo'
     LIMIT 1"
);
$stmtLectura->execute([
    'dispositivo_id' => $dispositivoId,
    'cliente_id' => (int) $usuario['cliente_id'],
]);
$lectura = $stmtLectura->fetch();

if (!$lectura || $lectura['gas_raw'] === null) {
    responderJson(404, ['ok' => false, 'error' => 'El MQ-2 no tiene una lectura disponible']);
}
if (($lectura['salud_mq2'] ?? '') === 'CALENTANDO') {
    responderJson(409, ['ok' => false, 'error' => 'Espera a que termine el calentamiento del MQ-2']);
}
if ((int) ($lectura['gas_detectado'] ?? 0) === 1) {
    responderJson(409, ['ok' => false, 'error' => 'No calibres mientras exista deteccion de humo o gas']);
}

$adc = (int) $lectura['gas_raw'];
$umbral = (int) $lectura['umbral_adc'];

try {
    $pdo->beginTransaction();

    $stmtConfig = $pdo->prepare(
        'INSERT INTO configuracion_mq2 (
            dispositivo_id, umbral_adc, ultima_lectura_adc,
            ultima_calibracion, adc_aire_limpio, calibrado_por,
            nota_calibracion, actualizado_en
         ) VALUES (
            :dispositivo_id, :umbral_adc, :adc_aire_limpio,
            UTC_TIMESTAMP(), :adc_aire_limpio, :usuario_id,
            :comentario, UTC_TIMESTAMP()
         )
         ON DUPLICATE KEY UPDATE
            ultima_calibracion = UTC_TIMESTAMP(),
            adc_aire_limpio = VALUES(adc_aire_limpio),
            calibrado_por = VALUES(calibrado_por),
            nota_calibracion = VALUES(nota_calibracion),
            actualizado_en = UTC_TIMESTAMP()'
    );
    $stmtConfig->execute([
        'dispositivo_id' => $dispositivoId,
        'umbral_adc' => $umbral,
        'adc_aire_limpio' => $adc,
        'usuario_id' => (int) $usuario['id'],
        'comentario' => $comentario !== '' ? $comentario : null,
    ]);

    $stmtHistorial = $pdo->prepare(
        'INSERT INTO mq2_calibraciones (
            dispositivo_id, usuario_id, adc_aire_limpio,
            umbral_reportado, comentario
         ) VALUES (
            :dispositivo_id, :usuario_id, :adc_aire_limpio,
            :umbral_reportado, :comentario
         )'
    );
    $stmtHistorial->execute([
        'dispositivo_id' => $dispositivoId,
        'usuario_id' => (int) $usuario['id'],
        'adc_aire_limpio' => $adc,
        'umbral_reportado' => $umbral,
        'comentario' => $comentario !== '' ? $comentario : null,
    ]);

    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('ID Industrial calibrar MQ-2: ' . $error->getMessage());
    responderJson(500, ['ok' => false, 'error' => 'No fue posible registrar la calibracion']);
}

responderJson(201, [
    'ok' => true,
    'data' => [
        'dispositivo_id' => $dispositivoId,
        'adc_aire_limpio' => $adc,
        'umbral_reportado' => $umbral,
        'calibrado_por' => (string) $usuario['nombre'],
        'ultima_calibracion' => gmdate('Y-m-d H:i:s'),
    ],
]);
