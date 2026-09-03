<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/lib/alexa.php';

requerirMetodo('POST');

try {
    idindAlexaRequerirMigracion($pdo);
    $alexa = idindAlexaRequerirConfig($configLocal);
    $bridgeToken = trim((string) ($_SERVER['HTTP_X_ALEXA_BRIDGE_TOKEN'] ?? ''));
    if ($bridgeToken === '' || !hash_equals($alexa['lambda_secret'], $bridgeToken)) {
        responderJson(401, ['ok' => false, 'error' => 'Lambda no autorizada']);
    }
    $entrada = obtenerJson();
    $directiva = $entrada['directive'] ?? null;
    if (!is_array($directiva) || !is_array($directiva['header'] ?? null)) {
        responderJson(400, ['ok' => false, 'error' => 'Directiva Alexa invalida']);
    }
    $cabecera = $directiva['header'];
    $namespace = (string) ($cabecera['namespace'] ?? '');
    $name = (string) ($cabecera['name'] ?? '');
    $token = idindAlexaTokenDirectiva($directiva);
    $usuario = idindAlexaAutenticarToken($pdo, $token);
    $clienteId = (int) $usuario['cliente_id'];

    if ($namespace === 'Alexa.Discovery' && $name === 'Discover') {
        echo json_encode(idindAlexaDescubrimiento($pdo, $clienteId, $cabecera), JSON_UNESCAPED_SLASHES);
        exit;
    }
    if ($namespace === 'Alexa.Authorization' && $name === 'AcceptGrant') {
        try {
            $codigoGrant = trim((string) ($directiva['payload']['grant']['code'] ?? ''));
            idindAlexaEventGuardarGrant(
                $pdo,
                $configLocal,
                (int) $usuario['usuario_id'],
                $codigoGrant
            );
        } catch (Throwable $errorGrant) {
            error_log('ID Industrial Alexa AcceptGrant: ' . $errorGrant->getMessage());
            echo json_encode([
                'event' => [
                    'header' => idindAlexaCabecera(
                        'Alexa.Authorization',
                        'ErrorResponse',
                        $cabecera
                    ),
                    'payload' => [
                        'type' => 'ACCEPT_GRANT_FAILED',
                        'message' => 'No fue posible habilitar la sincronizacion proactiva.',
                    ],
                ],
            ], JSON_UNESCAPED_SLASHES);
            exit;
        }
        echo json_encode([
            'event' => [
                'header' => idindAlexaCabecera('Alexa.Authorization', 'AcceptGrant.Response', $cabecera),
                'payload' => (object) [],
            ],
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    $endpointId = trim((string) ($directiva['endpoint']['endpointId'] ?? ''));
    if ($namespace === 'Alexa.SceneController' && $name === 'Activate') {
        $rutina = idindAlexaRutina($pdo, $clienteId, $endpointId);
        $resultadoRutina = idindRutinaEjecutar(
            $pdo,
            $configLocal,
            $clienteId,
            (int) $rutina['id'],
            'MANUAL',
            (int) $usuario['usuario_id']
        );
        if (empty($resultadoRutina['ejecutada']) || ($resultadoRutina['estado'] ?? '') !== 'COMPLETADA') {
            throw new IdindAlexaException('La rutina no pudo completar todas sus acciones', 'ENDPOINT_UNREACHABLE');
        }
        echo json_encode(idindAlexaRespuestaEscena($directiva), JSON_UNESCAPED_SLASHES);
        exit;
    }
    $actuador = idindAlexaActuador($pdo, $clienteId, $endpointId);
    if ($namespace === 'Alexa' && $name === 'ReportState') {
        $sincronizacion = idindShellySincronizar(
            $pdo,
            $configLocal,
            $clienteId,
            (string) $actuador['id']
        );
        if (!empty($sincronizacion['errores'])) {
            throw new IdindAlexaException(
                'No fue posible consultar el estado actual del equipo',
                'ENDPOINT_UNREACHABLE'
            );
        }
        $actuador = idindAlexaActuador($pdo, $clienteId, $endpointId);
        echo json_encode(idindAlexaRespuestaEstado($actuador, $directiva), JSON_UNESCAPED_SLASHES);
        exit;
    }
    if ($namespace === 'Alexa.PowerController' && in_array($name, ['TurnOn', 'TurnOff'], true)) {
        $accion = $name === 'TurnOn' ? 'ENCENDER' : 'APAGAR';
        $comandoId = idindShellyCrearComando(
            $pdo,
            (string) $actuador['id'],
            $accion,
            'ALEXA',
            (int) $usuario['usuario_id'],
            null,
            'Comando de voz Amazon Alexa'
        );
        $resultado = idindShellyProcesarComando($pdo, $configLocal, $comandoId);
        if (empty($resultado['aplicado'])) {
            throw new IdindAlexaException(
                (string) ($resultado['error'] ?? 'No fue posible controlar el equipo'),
                'ENDPOINT_UNREACHABLE'
            );
        }
        $actuador = idindAlexaActuador($pdo, $clienteId, $endpointId);
        echo json_encode(idindAlexaRespuestaEstado($actuador, $directiva, 'Response'), JSON_UNESCAPED_SLASHES);
        exit;
    }
    throw new IdindAlexaException('Directiva no soportada', 'INVALID_DIRECTIVE');
} catch (IdindAlexaException $error) {
    $directiva = isset($directiva) && is_array($directiva) ? $directiva : [];
    echo json_encode(
        idindAlexaRespuestaError($directiva, $error->alexaType(), $error->getMessage()),
        JSON_UNESCAPED_SLASHES
    );
} catch (Throwable $error) {
    error_log('ID Industrial Alexa Smart Home: ' . $error->getMessage());
    $directiva = isset($directiva) && is_array($directiva) ? $directiva : [];
    echo json_encode(
        idindAlexaRespuestaError($directiva, 'INTERNAL_ERROR', 'Error interno de ID Industrial'),
        JSON_UNESCAPED_SLASHES
    );
}
