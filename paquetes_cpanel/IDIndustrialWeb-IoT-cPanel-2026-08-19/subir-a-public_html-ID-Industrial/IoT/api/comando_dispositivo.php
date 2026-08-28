<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

requerirMetodo('POST');
validarTokenDispositivo();
$data = obtenerJson();

$dispositivoId = trim((string) ($data['dispositivo_id'] ?? ''));
$confirmarId = filter_var(
    $data['comando_aplicado_id'] ?? 0,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 0]]
);

if ($dispositivoId === '' || strlen($dispositivoId) > 64) {
    responderJson(422, ['ok' => false, 'error' => 'Dispositivo no valido']);
}
if ($confirmarId === false) {
    responderJson(422, ['ok' => false, 'error' => 'Confirmacion no valida']);
}

try {
    $pdo->beginTransaction();

    $stmtDispositivo = $pdo->prepare(
        'SELECT id
         FROM dispositivos
         WHERE id = :id
         LIMIT 1
         FOR UPDATE'
    );
    $stmtDispositivo->execute(['id' => $dispositivoId]);
    if (!$stmtDispositivo->fetch()) {
        $pdo->rollBack();
        responderJson(404, ['ok' => false, 'error' => 'Dispositivo no registrado']);
    }

    $pdo->prepare(
        "UPDATE comandos_dispositivo
         SET estado = 'EXPIRADO'
         WHERE dispositivo_id = :dispositivo_id
           AND estado IN ('PENDIENTE', 'ENTREGADO')
           AND expira_en <= UTC_TIMESTAMP()"
    )->execute(['dispositivo_id' => $dispositivoId]);

    if ((int) $confirmarId > 0) {
        $stmtConfirmar = $pdo->prepare(
            "UPDATE comandos_dispositivo
             SET estado = 'APLICADO',
                 aplicado_en = UTC_TIMESTAMP()
             WHERE id = :id
               AND dispositivo_id = :dispositivo_id
               AND estado = 'ENTREGADO'"
        );
        $stmtConfirmar->execute([
            'id' => (int) $confirmarId,
            'dispositivo_id' => $dispositivoId,
        ]);
    }

    $stmtComando = $pdo->prepare(
        "SELECT id, tipo, alerta_id, expira_en
         FROM comandos_dispositivo
         WHERE dispositivo_id = :dispositivo_id
           AND expira_en > UTC_TIMESTAMP()
           AND (
             estado = 'PENDIENTE'
             OR (
               estado = 'ENTREGADO'
               AND entregado_en <= UTC_TIMESTAMP() - INTERVAL 5 SECOND
             )
           )
         ORDER BY id ASC
         LIMIT 1
         FOR UPDATE"
    );
    $stmtComando->execute(['dispositivo_id' => $dispositivoId]);
    $comando = $stmtComando->fetch();

    if ($comando) {
        $pdo->prepare(
            "UPDATE comandos_dispositivo
             SET estado = 'ENTREGADO',
                 entregado_en = UTC_TIMESTAMP(),
                 intentos_entrega = intentos_entrega + 1
             WHERE id = :id"
        )->execute(['id' => (int) $comando['id']]);
    }

    $pdo->commit();
    responderJson(200, [
        'ok' => true,
        'data' => [
            'comando' => $comando ? [
                'id' => (int) $comando['id'],
                'accion' => (string) $comando['tipo'],
                'alerta_id' => $comando['alerta_id'] === null
                    ? null
                    : (int) $comando['alerta_id'],
                'expira_en' => (string) $comando['expira_en'],
            ] : null,
        ],
    ]);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('ID Industrial comandos ESP32: ' . $error->getMessage());
    responderJson(500, ['ok' => false, 'error' => 'No fue posible consultar las ordenes']);
}
