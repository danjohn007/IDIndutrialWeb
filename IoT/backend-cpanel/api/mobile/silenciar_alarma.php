<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/mobile_auth.php';
require_once dirname(__DIR__) . '/lib/shelly.php';

requerirMetodo('POST');
$usuario = requerirTokenMovil(['ADMIN', 'OPERADOR']);
$data = obtenerJson();
$alertaId = filter_var($data['alerta_id'] ?? null, FILTER_VALIDATE_INT);

if ($alertaId === false || $alertaId < 1) {
    responderJson(422, ['ok' => false, 'error' => 'La alerta no es valida']);
}

try {
    $pdo->beginTransaction();

    $stmtAlerta = $pdo->prepare(
        'SELECT
            a.id,
            a.dispositivo_id,
            e.alarma_enclavada,
            e.alarma_silenciada,
            e.buzzer_encendido,
            CASE
              WHEN d.ultima_conexion IS NOT NULL
               AND d.ultima_conexion >= UTC_TIMESTAMP() - INTERVAL 2 MINUTE
              THEN 1
              ELSE 0
            END AS online
         FROM alertas a
         INNER JOIN dispositivos d ON d.id = a.dispositivo_id
         LEFT JOIN estado_sensores e ON e.dispositivo_id = d.id
         WHERE a.id = :alerta_id
           AND d.cliente_id = :cliente_id
         LIMIT 1
         FOR UPDATE'
    );
    $stmtAlerta->execute([
        'alerta_id' => $alertaId,
        'cliente_id' => (int) $usuario['cliente_id'],
    ]);
    $alerta = $stmtAlerta->fetch();

    if (!$alerta) {
        $pdo->rollBack();
        responderJson(404, ['ok' => false, 'error' => 'La alerta no existe']);
    }
    if ((int) $alerta['online'] !== 1) {
        $pdo->rollBack();
        responderJson(409, [
            'ok' => false,
            'error' => 'El ESP32 esta offline. No se dejo una orden pendiente.',
        ]);
    }
    if ((int) ($alerta['alarma_enclavada'] ?? 0) !== 1) {
        $pdo->rollBack();
        responderJson(409, ['ok' => false, 'error' => 'El dispositivo no reporta una alarma enclavada']);
    }
    if (
        (int) ($alerta['alarma_silenciada'] ?? 0) === 1
        || (int) ($alerta['buzzer_encendido'] ?? 0) !== 1
    ) {
        $pdo->rollBack();
        responderJson(409, ['ok' => false, 'error' => 'El buzzer ya esta silenciado']);
    }

    $dispositivoId = (string) $alerta['dispositivo_id'];
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
                $pdo, $configLocal, $dispositivoId, 'APAGAR', 'APP',
                (int) $usuario['id'], (int) $alertaId, 'Alarma silenciada desde la app movil'
            );
        } catch (Throwable $errorShelly) {
            error_log('ID Industrial Shelly silencio app: ' . $errorShelly->getMessage());
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
            :alerta_id,
            'SILENCIAR_ALARMA',
            'PENDIENTE',
            :solicitado_por,
            UTC_TIMESTAMP() + INTERVAL 2 MINUTE
         )"
    );
    $stmtInsertar->execute([
        'dispositivo_id' => $dispositivoId,
        'alerta_id' => $alertaId,
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
            $pdo, $configLocal, $dispositivoId, 'APAGAR', 'APP',
            (int) $usuario['id'], (int) $alertaId, 'Alarma silenciada desde la app movil'
        );
    } catch (Throwable $errorShelly) {
        error_log('ID Industrial Shelly silencio app: ' . $errorShelly->getMessage());
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
    error_log('ID Industrial silenciar alarma: ' . $error->getMessage());
    responderJson(500, ['ok' => false, 'error' => 'No fue posible enviar la orden al ESP32']);
}
