<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/mobile_auth.php';
require_once dirname(__DIR__) . '/lib/rutinas.php';
require_once dirname(__DIR__) . '/lib/alexa.php';

requerirMetodo('POST');
$usuario = requerirTokenMovil(['ADMIN']);
$data = obtenerJson();

try {
    idindRutinasRequerirMigracion($pdo);
    idindAlexaRequerirMigracion($pdo);
    $accion = strtoupper(trim((string) ($data['accion'] ?? 'PREPARAR_ALEXA')));
    if (!in_array($accion, ['PREPARAR_ALEXA', 'DESACTIVAR_ALEXA'], true)) {
        throw new IdindRutinaException('Accion de integracion no valida');
    }
    $clienteId = (int) $usuario['cliente_id'];
    if ($accion === 'DESACTIVAR_ALEXA') {
        $pdo->prepare(
            "UPDATE alexa_oauth_tokens t
             INNER JOIN usuarios u ON u.id = t.usuario_id
             SET t.revocado_en = UTC_TIMESTAMP()
             WHERE u.cliente_id = :cliente_id AND t.revocado_en IS NULL"
        )->execute(['cliente_id' => $clienteId]);
        $pdo->prepare(
            "UPDATE integraciones_domoticas SET estado = 'INACTIVA'
             WHERE cliente_id = :cliente_id AND proveedor = 'ALEXA'"
        )->execute(['cliente_id' => $clienteId]);
    } else {
        $nombre = idindRutinaTexto($data['nombre'] ?? 'Amazon Alexa', 'El nombre', 3, 120);
        $skillId = idindRutinaTexto($data['skill_id'] ?? '', 'El Skill ID', 0, 190, true);
        $estadoConfig = idindAlexaConfigEstado($configLocal);
        $stmtVinculada = $pdo->prepare(
            "SELECT COUNT(*) FROM alexa_oauth_tokens t
             INNER JOIN usuarios u ON u.id = t.usuario_id
             WHERE u.cliente_id = :cliente_id
               AND t.revocado_en IS NULL AND t.refresh_expira_en > UTC_TIMESTAMP()"
        );
        try {
            $stmtVinculada->execute(['cliente_id' => $clienteId]);
            $vinculada = (int) $stmtVinculada->fetchColumn() > 0;
        } catch (Throwable $error) {
            $vinculada = false;
        }
        $detalle = [
            'oauth_listo' => $estadoConfig['oauth_listo'],
            'lambda_lista' => $estadoConfig['lambda_lista'],
            'vinculada' => $vinculada,
            'nota' => $vinculada
                ? 'Cuenta vinculada con Amazon Alexa'
                : 'Habilita la Skill en Alexa para completar Account Linking',
        ];
        $stmt = $pdo->prepare(
            "INSERT INTO integraciones_domoticas (
               cliente_id, proveedor, nombre, estado, identificador_externo, detalle_json
             ) VALUES (
               :cliente_id, 'ALEXA', :nombre, :estado, :externo, :detalle
             ) ON DUPLICATE KEY UPDATE
               nombre = VALUES(nombre), estado = VALUES(estado),
               identificador_externo = VALUES(identificador_externo),
               detalle_json = VALUES(detalle_json)"
        );
        $stmt->execute([
            'cliente_id' => $clienteId,
            'nombre' => $nombre,
            'estado' => $vinculada ? 'CONFIGURADA' : 'PENDIENTE',
            'externo' => $skillId,
            'detalle' => idindShellyJsonSeguro($detalle),
        ]);
    }
    responderJson(200, ['ok' => true, 'data' => [
        'estado' => $accion === 'DESACTIVAR_ALEXA'
            ? 'INACTIVA'
            : (!empty($vinculada) ? 'CONFIGURADA' : 'PENDIENTE'),
    ]]);
} catch (IdindAlexaException $error) {
    responderJson(422, ['ok' => false, 'error' => $error->getMessage()]);
} catch (IdindRutinaException $error) {
    responderJson(422, ['ok' => false, 'error' => $error->getMessage()]);
} catch (Throwable $error) {
    error_log('ID Industrial integracion Alexa: ' . $error->getMessage());
    responderJson(500, ['ok' => false, 'error' => 'No fue posible guardar la integracion']);
}
