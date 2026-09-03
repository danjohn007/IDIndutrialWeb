<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/shelly.php';

requerirMetodo('POST');
$usuario = requerirSesion(['ADMIN', 'OPERADOR']);
requerirCsrf($usuario);

$requestedWith = (string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
$fetchSite = strtolower((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? ''));
if (
    !hash_equals('XMLHttpRequest', $requestedWith)
    || in_array($fetchSite, ['cross-site', 'none'], true)
) {
    responderJson(403, ['ok' => false, 'error' => 'Solicitud no autorizada']);
}

$data = obtenerJson();
$dispositivoId = trim((string) ($data['dispositivo_id'] ?? ''));
if (
    $dispositivoId === ''
    || strlen($dispositivoId) > 64
    || !preg_match('/^[A-Za-z0-9_-]+$/', $dispositivoId)
) {
    responderJson(422, ['ok' => false, 'error' => 'Dispositivo no valido']);
}

try {
    $pdo->beginTransaction();

    $stmtDispositivo = $pdo->prepare(
        'SELECT
            d.id,
            e.alarma_enclavada,
            e.alarma_silenciada,
            e.buzzer_encendido,
            CASE
              WHEN d.ultima_conexion IS NOT NULL
               AND d.ultima_conexion >= UTC_TIMESTAMP() - INTERVAL 2 MINUTE
              THEN 1
              ELSE 0
            END AS online
         FROM dispositivos d
         LEFT JOIN estado_sensores e ON e.dispositivo_id = d.id
         WHERE d.id = :dispositivo_id
           AND d.cliente_id = :cliente_id
         LIMIT 1
         FOR UPDATE'
    );
    $stmtDispositivo->execute([
        'dispositivo_id' => $dispositivoId,
        'cliente_id' => (int) $usuario['cliente_id'],
    ]);
    $dispositivo = $stmtDispositivo->fetch();

    if (!$dispositivo) {
        $pdo->rollBack();
        responderJson(404, ['ok' => false, 'error' => 'El dispositivo no existe']);
    }
    if ((int) $dispositivo['online'] !== 1) {
        $pdo->rollBack();
        responderJson(409, [
            'ok' => false,
            'error' => 'El ESP32 esta offline. No se dejo una orden pendiente.',
        ]);
    }
    if ((int) ($dispositivo['alarma_enclavada'] ?? 0) !== 1) {
        $pdo->rollBack();
        responderJson(409, ['ok' => false, 'error' => 'No hay una alarma enclavada']);
    }
    if (
        (int) ($dispositivo['alarma_silenciada'] ?? 0) === 1
        || (int) ($dispositivo['buzzer_encendido'] ?? 0) !== 1
    ) {
        $pdo->rollBack();
        responderJson(409, ['ok' => false, 'error' => 'El buzzer ya esta silenciado']);
    }

    $pdo->prepare(
        "UPDATE comandos_dispositivo
         SET estado = 'EXPIRADO'
         WHERE dispositivo_id = :dispositivo_id
           AND estado IN ('PENDIENTE', 'ENTREGADO')
           AND expira_en <= UTC_TIMESTAMP()"
    )->execute(['dispositivo_id' => $dispositivoId]);

    $stmtExistente = $pdo->prepare(
        "SELECT id, estado, expira_en
         FROM comandos_dispositivo
         WHERE dispositivo_id = :dispositivo_id
           AND tipo = 'SILENCIAR_ALARMA'
           AND estado IN ('PENDIENTE', 'ENTREGADO')
           AND expira_en > UTC_TIMESTAMP()
         ORDER BY id DESC
         LIMIT 1
         FOR UPDATE"
    );
    $stmtExistente->execute(['dispositivo_id' => $dispositivoId]);
    $existente = $stmtExistente->fetch();

    if ($existente) {
        $pdo->commit();
        $shelly = [];
        try {
            $shelly = idindShellyComandarVinculados(
                $pdo, $configLocal, $dispositivoId, 'APAGAR', 'WEB',
                (int) $usuario['id'], null, 'Alarma silenciada desde el panel web'
            );
        } catch (Throwable $errorShelly) {
            error_log('ID Industrial Shelly silencio web: ' . $errorShelly->getMessage());
        }
        responderJson(200, [
            'ok' => true,
            'data' => [
                'comando_id' => (int) $existente['id'],
                'dispositivo_id' => $dispositivoId,
                'estado' => (string) $existente['estado'],
                'expira_en' => (string) $existente['expira_en'],
                'shelly' => $shelly,
            ],
        ]);
    }

    $stmtInsertar = $pdo->prepare(
        "INSERT INTO comandos_dispositivo (
            dispositivo_id,
            alerta_id,
            tipo,
            estado,
            solicitado_por,
            expira_en
         ) VALUES (
            :dispositivo_id,
            NULL,
            'SILENCIAR_ALARMA',
            'PENDIENTE',
            :solicitado_por,
            UTC_TIMESTAMP() + INTERVAL 2 MINUTE
         )"
    );
    $stmtInsertar->execute([
        'dispositivo_id' => $dispositivoId,
        'solicitado_por' => (int) $usuario['id'],
    ]);
    $comandoId = (int) $pdo->lastInsertId();

    $stmtCreado = $pdo->prepare(
        'SELECT expira_en FROM comandos_dispositivo WHERE id = :id'
    );
    $stmtCreado->execute(['id' => $comandoId]);
    $creado = $stmtCreado->fetch();

    $pdo->commit();
    $shelly = [];
    try {
        $shelly = idindShellyComandarVinculados(
            $pdo, $configLocal, $dispositivoId, 'APAGAR', 'WEB',
            (int) $usuario['id'], null, 'Alarma silenciada desde el panel web'
        );
    } catch (Throwable $errorShelly) {
        error_log('ID Industrial Shelly silencio web: ' . $errorShelly->getMessage());
    }
    responderJson(201, [
        'ok' => true,
        'data' => [
            'comando_id' => $comandoId,
            'dispositivo_id' => $dispositivoId,
            'estado' => 'PENDIENTE',
            'expira_en' => (string) ($creado['expira_en'] ?? ''),
            'shelly' => $shelly,
        ],
    ]);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('ID Industrial silenciar alarma web: ' . $error->getMessage());
    responderJson(500, ['ok' => false, 'error' => 'No fue posible enviar la orden al ESP32']);
}
