<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/push_notificaciones.php';
require_once __DIR__ . '/lib/shelly.php';
require_once __DIR__ . '/lib/conectividad.php';

requerirMetodo('POST');
validarTokenDispositivo();
$data = obtenerJson();

function textoRequerido(array $data, string $campo, int $maximo): string
{
    $valor = trim((string) ($data[$campo] ?? ''));
    if ($valor === '' || strlen($valor) > $maximo) {
        responderJson(422, ['ok' => false, 'error' => "Campo invalido: {$campo}"]);
    }
    return $valor;
}

function numeroOpcional(
    array $data,
    string $campo,
    float $minimo,
    float $maximo
): ?float {
    if (!array_key_exists($campo, $data) || $data[$campo] === null || $data[$campo] === '') {
        return null;
    }

    if (!is_numeric($data[$campo])) {
        responderJson(422, ['ok' => false, 'error' => "Campo no numerico: {$campo}"]);
    }

    $valor = (float) $data[$campo];
    if (!is_finite($valor) || $valor < $minimo || $valor > $maximo) {
        responderJson(422, ['ok' => false, 'error' => "Campo fuera de rango: {$campo}"]);
    }
    return $valor;
}

function enteroOpcional(
    array $data,
    string $campo,
    int $minimo,
    int $maximo
): ?int {
    $valor = numeroOpcional($data, $campo, $minimo, $maximo);
    return $valor === null ? null : (int) round($valor);
}

function valorEnum(array $data, string $campo, array $permitidos, string $default): string
{
    $valor = strtoupper(trim((string) ($data[$campo] ?? $default)));
    if (!in_array($valor, $permitidos, true)) {
        responderJson(422, ['ok' => false, 'error' => "Valor no permitido: {$campo}"]);
    }
    return $valor;
}

function booleanoBinario(array $data, string $campo, ?int $default = null): ?int
{
    if (!array_key_exists($campo, $data) || $data[$campo] === null) {
        return $default;
    }

    $valor = $data[$campo];
    if (!in_array($valor, [0, 1, '0', '1', false, true], true)) {
        responderJson(422, ['ok' => false, 'error' => "Valor no permitido: {$campo}"]);
    }
    return in_array($valor, [1, '1', true], true) ? 1 : 0;
}

function encolarNotificacionesCriticas(PDO $pdo, array $alertaIds): void
{
    if ($alertaIds === []) {
        return;
    }

    $marcadores = implode(',', array_fill(0, count($alertaIds), '?'));
    $stmtAlertas = $pdo->prepare(
        "SELECT
            a.id, a.tipo_alerta, a.valor_sensor, a.severidad,
            a.dispositivo_id, d.cliente_id, d.ubicacion
         FROM alertas a
         INNER JOIN dispositivos d ON d.id = a.dispositivo_id
         WHERE a.id IN ({$marcadores})
           AND a.severidad = 'CRITICO'"
    );
    $stmtAlertas->execute(array_values($alertaIds));
    $alertasCriticas = $stmtAlertas->fetchAll();
    if ($alertasCriticas === []) {
        return;
    }

    $stmtTokens = $pdo->prepare(
        "SELECT mp.id
         FROM moviles_push mp
         INNER JOIN usuarios u ON u.id = mp.usuario_id
         WHERE u.cliente_id = :cliente_id
           AND u.estado = 'ACTIVO'
           AND mp.activo = 1"
    );
    $stmtInsertar = $pdo->prepare(
        'INSERT IGNORE INTO notificaciones_push (
            alerta_id, push_token_id, cliente_id, titulo, cuerpo,
            payload_json, estado, intentos, disponible_en
         ) VALUES (
            :alerta_id, :push_token_id, :cliente_id, :titulo, :cuerpo,
            :payload_json, \'PENDIENTE\', 0, UTC_TIMESTAMP()
         )'
    );

    foreach ($alertasCriticas as $alerta) {
        $tipo = (string) $alerta['tipo_alerta'];
        $ubicacion = (string) $alerta['ubicacion'];
        $dispositivo = (string) $alerta['dispositivo_id'];
        $valor = $alerta['valor_sensor'];
        $titulo = 'Alerta critica: ' . $tipo;

        if (stripos($tipo, 'Estacion manual') !== false || stripos($tipo, 'Pulsador') !== false) {
            $cuerpo = "Estacion manual activada en {$ubicacion} ({$dispositivo}).";
        } elseif (stripos($tipo, 'Flama') !== false) {
            $cuerpo = "Flama detectada en {$ubicacion} ({$dispositivo}).";
        } elseif (stripos($tipo, 'Gas') !== false || stripos($tipo, 'Humo') !== false) {
            $lectura = $valor === null ? 'sin lectura' : number_format((float) $valor, 0) . ' ADC';
            $cuerpo = "Humo/gas en {$ubicacion}: {$lectura}.";
        } elseif (stripos($tipo, 'Temperatura') !== false) {
            $lectura = $valor === null ? 'sin lectura' : number_format((float) $valor, 1) . ' C';
            $cuerpo = "Temperatura peligrosa en {$ubicacion}: {$lectura}.";
        } else {
            $cuerpo = "Evento critico en {$ubicacion} ({$dispositivo}).";
        }

        $stmtTokens->execute(['cliente_id' => (int) $alerta['cliente_id']]);
        foreach ($stmtTokens->fetchAll() as $token) {
            $stmtInsertar->execute([
                'alerta_id' => (int) $alerta['id'],
                'push_token_id' => (int) $token['id'],
                'cliente_id' => (int) $alerta['cliente_id'],
                'titulo' => substr($titulo, 0, 120),
                'cuerpo' => substr($cuerpo, 0, 255),
                'payload_json' => json_encode(
                    [
                        'alertaId' => (int) $alerta['id'],
                        'alerta_id' => (int) $alerta['id'],
                        'dispositivoId' => $dispositivo,
                        'tipo' => 'ALERTA',
                        'url' => '/alerta/' . (int) $alerta['id'],
                    ],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),
            ]);
        }
    }
}

$dispositivoId = textoRequerido($data, 'dispositivo_id', 64);
if (!preg_match('/^[A-Za-z0-9_-]+$/', $dispositivoId)) {
    responderJson(422, ['ok' => false, 'error' => 'dispositivo_id contiene caracteres no permitidos']);
}

$temperatura = numeroOpcional($data, 'temperatura', -40, 125);
$humedad = numeroOpcional($data, 'humedad', 0, 100);
$indiceCalor = numeroOpcional($data, 'indice_calor', -40, 150);
$gasRaw = enteroOpcional($data, 'gas_raw', 0, 4095);
$gasPorcentaje = numeroOpcional($data, 'gas_porcentaje', 0, 100);
$gasUmbral = enteroOpcional($data, 'gas_umbral', 1, 4095) ?? 1600;
$temperaturaAlerta = numeroOpcional($data, 'temperatura_alerta', -40, 125) ?? 30.0;
$temperaturaAlarma = numeroOpcional($data, 'temperatura_alarma', -40, 125) ?? 35.0;
$wifiRssi = enteroOpcional($data, 'wifi_rssi', -120, 0);
$tiempoEncendido = enteroOpcional($data, 'tiempo_encendido', 0, 2147483647);
$calentamientoMq2Total = enteroOpcional(
    $data,
    'mq2_calentamiento_total_s',
    1,
    86400
) ?? 120;
$contadorAlarmas = enteroOpcional($data, 'contador_alarmas', 0, 2147483647) ?? 0;
$contadorSilenciosEnLinea = enteroOpcional(
    $data,
    'contador_silencios_en_linea',
    0,
    2147483647
) ?? 0;
$contadorSilenciosFisicos = enteroOpcional(
    $data,
    'contador_silencios_fisicos',
    0,
    2147483647
) ?? 0;
$contadorResetsFisicos = enteroOpcional(
    $data,
    'contador_resets_fisicos',
    0,
    2147483647
) ?? 0;

if ($temperaturaAlerta > $temperaturaAlarma) {
    responderJson(422, ['ok' => false, 'error' => 'temperatura_alerta no puede ser mayor que temperatura_alarma']);
}

if ($gasPorcentaje === null && $gasRaw !== null) {
    $gasPorcentaje = round(($gasRaw / 4095) * 100, 2);
}

$flamaDetectada = booleanoBinario($data, 'flama_detectada', 0) ?? 0;
$estacionManualActivada = booleanoBinario($data, 'estacion_manual_activada', 0) ?? 0;
$gasDetectadoRecibido = booleanoBinario($data, 'gas_detectado');
$gasDetectado = $gasDetectadoRecibido
    ?? (int) ($gasRaw !== null && $gasRaw >= $gasUmbral);

$estadoGeneral = valorEnum($data, 'estado_general', ['NORMAL', 'ALERTA', 'ALARMA'], 'NORMAL');
$peligroActivo = booleanoBinario($data, 'peligro_activo', 0) ?? 0;
$alarmaEnclavada = booleanoBinario($data, 'alarma_enclavada', 0) ?? 0;
$alarmaSilenciada = booleanoBinario($data, 'alarma_silenciada', 0) ?? 0;
$revisionFisicaPendiente = booleanoBinario(
    $data,
    'revision_fisica_pendiente',
    0
) ?? 0;
$buzzerEncendido = booleanoBinario($data, 'buzzer_encendido', 0) ?? 0;
$modoOperacion = valorEnum(
    $data,
    'modo_operacion',
    ['NORMAL', 'ALERTA', 'ALARMA_SONORA', 'ALARMA_SILENCIADA', 'REVISION_PENDIENTE'],
    $estadoGeneral === 'ALARMA' ? 'ALARMA_SONORA' : $estadoGeneral
);
$silenciadaPor = valorEnum(
    $data,
    'silenciada_por',
    ['NINGUNO', 'APP_MOVIL', 'BOTON_FISICO'],
    'NINGUNO'
);
$estadosSalud = ['INICIANDO', 'CALENTANDO', 'OK', 'REVISAR', 'FALLO', 'DESCONOCIDO'];
$saludDht = valorEnum($data, 'salud_dht', $estadosSalud, 'DESCONOCIDO');
$saludMq2 = valorEnum($data, 'salud_mq2', $estadosSalud, 'DESCONOCIDO');
$saludFlama = valorEnum($data, 'salud_flama', $estadosSalud, 'DESCONOCIDO');

$tipoAlerta = trim((string) ($data['tipo_alerta'] ?? ''));
if (strlen($tipoAlerta) > 80) {
    responderJson(422, ['ok' => false, 'error' => 'Campo invalido: tipo_alerta']);
}
$valorAlertaSolicitado = numeroOpcional($data, 'valor_alerta', -1000000, 1000000);

try {
    $pdo->beginTransaction();

    $stmtDispositivo = $pdo->prepare(
        'SELECT estado FROM dispositivos WHERE id = :id LIMIT 1 FOR UPDATE'
    );
    $stmtDispositivo->execute(['id' => $dispositivoId]);
    $dispositivo = $stmtDispositivo->fetch();

    if (!$dispositivo) {
        $pdo->rollBack();
        responderJson(404, ['ok' => false, 'error' => 'Dispositivo no registrado']);
    }
    if ($dispositivo['estado'] === 'Inactivo') {
        $pdo->rollBack();
        responderJson(409, ['ok' => false, 'error' => 'Dispositivo inactivo']);
    }

    $stmtAnterior = $pdo->prepare(
        'SELECT
            estado_general, temperatura, gas_raw, gas_detectado,
            flama_detectada, estacion_manual_activada,
            salud_dht, salud_mq2, salud_flama,
            alarma_silenciada, contador_alarmas, contador_silencios_en_linea,
            contador_silencios_fisicos, contador_resets_fisicos
         FROM estado_sensores
         WHERE dispositivo_id = :dispositivo_id
         LIMIT 1
         FOR UPDATE'
    );
    $stmtAnterior->execute(['dispositivo_id' => $dispositivoId]);
    $lecturaAnterior = $stmtAnterior->fetch() ?: null;
    $estadoAnterior = $lecturaAnterior['estado_general'] ?? null;
    $alarmaSilenciadaAnterior = (int) ($lecturaAnterior['alarma_silenciada'] ?? 0);
    $gasDetectadoAnterior = (int) ($lecturaAnterior['gas_detectado'] ?? 0);
    $flamaDetectadaAnterior = (int) ($lecturaAnterior['flama_detectada'] ?? 0);
    $estacionManualAnterior = (int) ($lecturaAnterior['estacion_manual_activada'] ?? 0);

    $stmtEstado = $pdo->prepare(
        'INSERT INTO estado_sensores (
            dispositivo_id, temperatura, humedad, indice_calor,
            gas_raw, gas_porcentaje, gas_detectado, flama_detectada,
            estacion_manual_activada,
            estado_general, peligro_activo, alarma_enclavada,
            alarma_silenciada, revision_fisica_pendiente, buzzer_encendido,
            modo_operacion, silenciada_por,
            salud_dht, salud_mq2, salud_flama,
            wifi_rssi, tiempo_encendido, contador_alarmas,
            contador_silencios_en_linea, contador_silencios_fisicos,
            contador_resets_fisicos, actualizado_en
        ) VALUES (
            :dispositivo_id, :temperatura, :humedad, :indice_calor,
            :gas_raw, :gas_porcentaje, :gas_detectado, :flama_detectada,
            :estacion_manual_activada,
            :estado_general, :peligro_activo, :alarma_enclavada,
            :alarma_silenciada, :revision_fisica_pendiente, :buzzer_encendido,
            :modo_operacion, :silenciada_por,
            :salud_dht, :salud_mq2, :salud_flama,
            :wifi_rssi, :tiempo_encendido, :contador_alarmas,
            :contador_silencios_en_linea, :contador_silencios_fisicos,
            :contador_resets_fisicos, UTC_TIMESTAMP()
        )
        ON DUPLICATE KEY UPDATE
            temperatura = VALUES(temperatura),
            humedad = VALUES(humedad),
            indice_calor = VALUES(indice_calor),
            gas_raw = VALUES(gas_raw),
            gas_porcentaje = VALUES(gas_porcentaje),
            gas_detectado = VALUES(gas_detectado),
            flama_detectada = VALUES(flama_detectada),
            estacion_manual_activada = VALUES(estacion_manual_activada),
            estado_general = VALUES(estado_general),
            peligro_activo = VALUES(peligro_activo),
            alarma_enclavada = VALUES(alarma_enclavada),
            alarma_silenciada = VALUES(alarma_silenciada),
            revision_fisica_pendiente = VALUES(revision_fisica_pendiente),
            buzzer_encendido = VALUES(buzzer_encendido),
            modo_operacion = VALUES(modo_operacion),
            silenciada_por = VALUES(silenciada_por),
            salud_dht = VALUES(salud_dht),
            salud_mq2 = VALUES(salud_mq2),
            salud_flama = VALUES(salud_flama),
            wifi_rssi = VALUES(wifi_rssi),
            tiempo_encendido = VALUES(tiempo_encendido),
            contador_alarmas = VALUES(contador_alarmas),
            contador_silencios_en_linea = VALUES(contador_silencios_en_linea),
            contador_silencios_fisicos = VALUES(contador_silencios_fisicos),
            contador_resets_fisicos = VALUES(contador_resets_fisicos),
            actualizado_en = UTC_TIMESTAMP()'
    );
    $stmtEstado->execute([
        'dispositivo_id' => $dispositivoId,
        'temperatura' => $temperatura,
        'humedad' => $humedad,
        'indice_calor' => $indiceCalor,
        'gas_raw' => $gasRaw,
        'gas_porcentaje' => $gasPorcentaje,
        'gas_detectado' => $gasDetectado,
        'flama_detectada' => $flamaDetectada,
        'estacion_manual_activada' => $estacionManualActivada,
        'estado_general' => $estadoGeneral,
        'peligro_activo' => $peligroActivo,
        'alarma_enclavada' => $alarmaEnclavada,
        'alarma_silenciada' => $alarmaSilenciada,
        'revision_fisica_pendiente' => $revisionFisicaPendiente,
        'buzzer_encendido' => $buzzerEncendido,
        'modo_operacion' => $modoOperacion,
        'silenciada_por' => $silenciadaPor,
        'salud_dht' => $saludDht,
        'salud_mq2' => $saludMq2,
        'salud_flama' => $saludFlama,
        'wifi_rssi' => $wifiRssi,
        'tiempo_encendido' => $tiempoEncendido,
        'contador_alarmas' => $contadorAlarmas,
        'contador_silencios_en_linea' => $contadorSilenciosEnLinea,
        'contador_silencios_fisicos' => $contadorSilenciosFisicos,
        'contador_resets_fisicos' => $contadorResetsFisicos,
    ]);

    $stmtMq2 = $pdo->prepare(
        'INSERT INTO configuracion_mq2 (
            dispositivo_id, umbral_adc, calentamiento_total_s,
            ultima_lectura_adc, actualizado_en
         ) VALUES (
            :dispositivo_id, :umbral_adc, :calentamiento_total_s,
            :ultima_lectura_adc, UTC_TIMESTAMP()
         )
         ON DUPLICATE KEY UPDATE
            umbral_adc = VALUES(umbral_adc),
            calentamiento_total_s = VALUES(calentamiento_total_s),
            ultima_lectura_adc = VALUES(ultima_lectura_adc),
            actualizado_en = UTC_TIMESTAMP()'
    );
    $stmtMq2->execute([
        'dispositivo_id' => $dispositivoId,
        'umbral_adc' => $gasUmbral,
        'calentamiento_total_s' => $calentamientoMq2Total,
        'ultima_lectura_adc' => $gasRaw,
    ]);

    $stmtConexion = $pdo->prepare(
        'UPDATE dispositivos SET ultima_conexion = UTC_TIMESTAMP() WHERE id = :id'
    );
    $stmtConexion->execute(['id' => $dispositivoId]);
    idindResolverAlertasDesconexion(
        $pdo,
        $dispositivoId,
        'Conexion restablecida al recibir una nueva lectura.'
    );

    $stmtAlerta = $pdo->prepare(
        'INSERT INTO alertas (
            dispositivo_id, tipo_alerta, valor_sensor, severidad
         ) VALUES (
            :dispositivo_id, :tipo_alerta, :valor_sensor, :severidad
         )'
    );
    $alertaIds = [];
    $agregarAlerta = function (
        string $tipoAlertaNueva,
        ?float $valorSensor,
        string $severidad
    ) use ($stmtAlerta, $pdo, $dispositivoId, &$alertaIds): void {
        $stmtAlerta->execute([
            'dispositivo_id' => $dispositivoId,
            'tipo_alerta' => $tipoAlertaNueva,
            'valor_sensor' => $valorSensor,
            'severidad' => $severidad,
        ]);
        $alertaIds[] = (int) $pdo->lastInsertId();
    };

    if ($gasDetectado === 1 && $gasDetectadoAnterior === 0) {
        $agregarAlerta('Humo/Gas', $gasRaw === null ? null : (float) $gasRaw, 'CRITICO');
    }

    if ($flamaDetectada === 1 && $flamaDetectadaAnterior === 0) {
        $agregarAlerta('Flama', 1.0, 'CRITICO');
    }

    if ($estacionManualActivada === 1 && $estacionManualAnterior === 0) {
        $agregarAlerta('Estacion manual', 1.0, 'CRITICO');
    }

    $temperaturaAnterior = isset($lecturaAnterior['temperatura'])
        ? (float) $lecturaAnterior['temperatura']
        : null;
    $temperaturaCritica = $temperatura !== null && $temperatura >= $temperaturaAlarma;
    $temperaturaPrecaucion = $temperatura !== null && $temperatura >= $temperaturaAlerta;
    $temperaturaCriticaAnterior = $temperaturaAnterior !== null
        && $temperaturaAnterior >= $temperaturaAlarma;
    $temperaturaPrecaucionAnterior = $temperaturaAnterior !== null
        && $temperaturaAnterior >= $temperaturaAlerta;

    if ($temperaturaCritica && !$temperaturaCriticaAnterior) {
        $agregarAlerta('Temperatura peligrosa', $temperatura, 'CRITICO');
    } elseif ($temperaturaPrecaucion && !$temperaturaPrecaucionAnterior) {
        $agregarAlerta('Temperatura alta', $temperatura, 'PRECAUCION');
    }

    if ($saludDht === 'FALLO' && ($lecturaAnterior['salud_dht'] ?? '') !== 'FALLO') {
        $agregarAlerta('Fallo DHT11', null, 'PRECAUCION');
    }

    if ($saludMq2 === 'REVISAR' && !in_array(($lecturaAnterior['salud_mq2'] ?? ''), ['REVISAR', 'FALLO'], true)) {
        $agregarAlerta('Revisar MQ-2', $gasRaw === null ? null : (float) $gasRaw, 'PRECAUCION');
    }

    if ($saludFlama === 'FALLO' && ($lecturaAnterior['salud_flama'] ?? '') !== 'FALLO') {
        $agregarAlerta('Fallo KY-026', null, 'PRECAUCION');
    }

    $esAlertaSecundaria =
        $alertaIds === []
        && $estadoGeneral !== 'NORMAL'
        && $estadoGeneral !== $estadoAnterior
        && $gasDetectado === 0
        && $flamaDetectada === 0
        && $estacionManualActivada === 0;

    if ($esAlertaSecundaria) {
        $tipoSecundario = $tipoAlerta !== ''
            ? $tipoAlerta
            : ($estadoGeneral === 'ALERTA' ? 'Temperatura alta' : 'Alarma general');
        $valorSecundario = $valorAlertaSolicitado;
        if ($valorSecundario === null && stripos($tipoSecundario, 'DHT') === false) {
            $valorSecundario = $temperatura;
        }

        $agregarAlerta(
            $tipoSecundario,
            $valorSecundario,
            $estadoGeneral === 'ALARMA' ? 'CRITICO' : 'PRECAUCION'
        );
    }

    $historialGuardado =
        $estadoGeneral === 'ALARMA'
        && (
            $lecturaAnterior === null
            || $estadoAnterior !== 'ALARMA'
            || $gasDetectado !== $gasDetectadoAnterior
            || $flamaDetectada !== $flamaDetectadaAnterior
            || $estacionManualActivada !== $estacionManualAnterior
            || $contadorAlarmas !== (int) ($lecturaAnterior['contador_alarmas'] ?? 0)
        );

    $pdo->commit();
    $shellyComandos = [];
    try {
        if (
            $estadoGeneral === 'ALARMA'
            && (
                $estadoAnterior !== 'ALARMA'
                || ($alarmaSilenciadaAnterior === 1 && $alarmaSilenciada === 0)
            )
        ) {
            $shellyComandos = idindShellyComandarVinculados(
                $pdo,
                $configLocal,
                $dispositivoId,
                'ENCENDER',
                'AUTOMATICO',
                null,
                $alertaIds[0] ?? null,
                'Alarma iniciada o rearmada por el ESP32'
            );
        } elseif ($alarmaSilenciada === 1 && $alarmaSilenciadaAnterior === 0) {
            $shellyComandos = idindShellyComandarVinculados(
                $pdo,
                $configLocal,
                $dispositivoId,
                'APAGAR',
                'AUTOMATICO',
                null,
                $alertaIds[0] ?? null,
                'El ESP32 confirmo el silencio de la alarma'
            );
        }
    } catch (Throwable $errorShelly) {
        error_log('ID Industrial Shelly automatico: ' . $errorShelly->getMessage());
    }
    $pushInmediato = ['ok' => true, 'procesadas' => 0];
    try {
        encolarNotificacionesCriticas($pdo, $alertaIds);
        $pushInmediato = idindPushEnviarPendientesPorAlertas($pdo, $alertaIds, $configLocal);
    } catch (Throwable $errorPush) {
        error_log('ID Industrial push inmediato: ' . $errorPush->getMessage());
    }

    responderJson(201, [
        'ok' => true,
        'estado_actualizado' => true,
        'historial_guardado' => $historialGuardado,
        'lectura_id' => null,
        'alerta_id' => $alertaIds[0] ?? null,
        'alerta_ids' => $alertaIds,
        'gas_detectado' => $gasDetectado,
        'flama_detectada' => $flamaDetectada,
        'estacion_manual_activada' => $estacionManualActivada,
        'estado_anterior' => $estadoAnterior,
        'estado_actual' => $estadoGeneral,
        'shelly_comandos' => $shellyComandos,
        'push_inmediato' => [
            'procesadas' => (int) ($pushInmediato['procesadas'] ?? 0),
            'enviadas' => (int) ($pushInmediato['enviadas'] ?? 0),
            'reintentos' => (int) ($pushInmediato['reintentos'] ?? 0),
            'descartadas' => (int) ($pushInmediato['descartadas'] ?? 0),
        ],
    ]);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('ID Industrial guardar lectura: ' . $error->getMessage());
    responderJson(500, ['ok' => false, 'error' => 'No fue posible actualizar el estado']);
}
