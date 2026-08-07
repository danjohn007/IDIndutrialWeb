<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/mobile_auth.php';
require_once dirname(__DIR__) . '/lib/rutinas.php';
require_once dirname(__DIR__) . '/lib/alexa.php';

requerirMetodo('GET');
$usuario = requerirTokenMovil();

try {
    idindRutinasRequerirMigracion($pdo);
    $clienteId = (int) $usuario['cliente_id'];
    $stmt = $pdo->prepare(
        "SELECT proveedor, nombre, estado, identificador_externo,
                ultima_sincronizacion, actualizado_en
         FROM integraciones_domoticas WHERE cliente_id = :cliente_id"
    );
    $stmt->execute(['cliente_id' => $clienteId]);
    $integraciones = $stmt->fetchAll();
    $integracionAlexa = $integraciones[0] ?? [
        'proveedor' => 'ALEXA',
        'nombre' => 'Amazon Alexa',
        'estado' => 'PENDIENTE',
        'identificador_externo' => null,
        'ultima_sincronizacion' => null,
        'actualizado_en' => null,
    ];
    $stmtAlexa = $pdo->prepare(
        "SELECT COUNT(*) FROM alexa_oauth_tokens t
         INNER JOIN usuarios u ON u.id = t.usuario_id
         WHERE u.cliente_id = :cliente_id
           AND t.revocado_en IS NULL AND t.refresh_expira_en > UTC_TIMESTAMP()"
    );
    try {
        $stmtAlexa->execute(['cliente_id' => $clienteId]);
        $alexaVinculada = (int) $stmtAlexa->fetchColumn() > 0;
    } catch (Throwable $error) {
        $alexaVinculada = false;
    }
    $estadoAlexaConfig = idindAlexaConfigEstado($configLocal);
    $integracionAlexa['estado'] = $alexaVinculada
        ? 'CONFIGURADA'
        : (($integracionAlexa['estado'] ?? '') === 'INACTIVA' ? 'INACTIVA' : 'PENDIENTE');
    $integracionAlexa['vinculada'] = $alexaVinculada;
    $integracionAlexa['oauth_listo'] = $estadoAlexaConfig['oauth_listo'];
    $integracionAlexa['lambda_lista'] = $estadoAlexaConfig['lambda_lista'];
    $integracionAlexa['equipos_disponibles'] = count(idindAlexaActuadores($pdo, $clienteId));
    $integracionAlexa['rutinas_disponibles'] = count(idindAlexaRutinas($pdo, $clienteId));
    responderJson(200, [
        'ok' => true,
        'data' => [
            'rutinas' => idindRutinaListar($pdo, $clienteId),
            'actuadores' => idindRutinaActuadoresDisponibles($pdo, $clienteId),
            'ejecuciones' => idindRutinaEjecuciones($pdo, $clienteId, null, 20),
            'integraciones' => [
                'shelly' => [
                    'estado' => idindShellyConfigurado($configLocal) ? 'CONFIGURADA' : 'PENDIENTE',
                    'equipos_disponibles' => count(idindRutinaActuadoresDisponibles($pdo, $clienteId)),
                ],
                'alexa' => $integracionAlexa,
            ],
            'permisos' => [
                'administrar' => $usuario['rol'] === 'ADMIN',
                'ejecutar' => in_array($usuario['rol'], ['ADMIN', 'OPERADOR'], true),
            ],
        ],
    ]);
} catch (IdindRutinaException $error) {
    responderJson(503, ['ok' => false, 'error' => $error->getMessage()]);
} catch (Throwable $error) {
    error_log('ID Industrial listar rutinas: ' . $error->getMessage());
    responderJson(500, ['ok' => false, 'error' => 'No fue posible cargar las rutinas']);
}
