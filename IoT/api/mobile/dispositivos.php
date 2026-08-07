<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/mobile_auth.php';
require_once dirname(__DIR__) . '/lib/shelly.php';
requerirMetodo('GET');

$usuario = requerirTokenMovil();
$clienteId = (int) $usuario['cliente_id'];

if (($_GET['sincronizar_shelly'] ?? '') === '1' && idindShellyConfigurado($configLocal)) {
    $lockName = 'idind_shelly_mobile_' . $clienteId;
    $stmtLock = $pdo->prepare('SELECT GET_LOCK(:nombre, 1)');
    $stmtLock->execute(['nombre' => $lockName]);
    $lockTomado = (int) $stmtLock->fetchColumn() === 1;

    if ($lockTomado) {
        try {
            $stmtEdad = $pdo->prepare(
                "SELECT COUNT(*) AS total,
                        SUM(CASE WHEN es.sincronizado_en IS NULL THEN 1 ELSE 0 END) AS sin_datos,
                        TIMESTAMPDIFF(SECOND, MIN(es.sincronizado_en), UTC_TIMESTAMP()) AS edad_segundos
                 FROM actuadores_shelly a
                 LEFT JOIN estado_shelly es ON es.actuador_id = a.id
                 WHERE a.cliente_id = :cliente_id
                   AND a.estado = 'Activo'
                   AND a.modo_control IN ('CLOUD', 'HIBRIDO')"
            );
            $stmtEdad->execute(['cliente_id' => $clienteId]);
            $edad = $stmtEdad->fetch() ?: [];
            $debeSincronizar = (int) ($edad['total'] ?? 0) > 0
                && (
                    (int) ($edad['sin_datos'] ?? 0) > 0
                    || $edad['edad_segundos'] === null
                    || (int) $edad['edad_segundos'] >= 8
                );
            if ($debeSincronizar) {
                idindShellySincronizar($pdo, $configLocal, $clienteId);
            }
        } catch (Throwable $errorShellySync) {
            error_log('ID Industrial sincronizacion Shelly movil: ' . $errorShellySync->getMessage());
        } finally {
            try {
                $stmtUnlock = $pdo->prepare('SELECT RELEASE_LOCK(:nombre)');
                $stmtUnlock->execute(['nombre' => $lockName]);
            } catch (Throwable $errorUnlock) {
                error_log('ID Industrial unlock Shelly movil: ' . $errorUnlock->getMessage());
            }
        }
    }
}

$stmt = $pdo->prepare(
    "SELECT
        d.id,
        d.ubicacion,
        d.estado AS estado_registro,
        CASE
            WHEN d.ultima_conexion IS NULL
              OR d.ultima_conexion < UTC_TIMESTAMP() - INTERVAL 2 MINUTE
            THEN 'OFFLINE'
            ELSE 'ONLINE'
        END AS conexion,
        e.estado_general,
        e.temperatura,
        e.humedad,
        e.indice_calor,
        e.gas_raw,
        e.gas_porcentaje,
        e.gas_detectado,
        e.flama_detectada,
        e.peligro_activo,
        e.alarma_enclavada,
        e.alarma_silenciada,
        e.revision_fisica_pendiente,
        e.buzzer_encendido,
        e.modo_operacion,
        e.silenciada_por,
        e.salud_dht,
        e.salud_mq2,
        e.salud_flama,
        e.wifi_rssi,
        e.tiempo_encendido,
        e.contador_alarmas,
        e.contador_silencios_en_linea,
        e.contador_silencios_fisicos,
        e.contador_resets_fisicos,
        e.actualizado_en AS ultima_lectura,
        COALESCE(c.umbral_adc, 1600) AS mq2_umbral_adc,
        COALESCE(c.calentamiento_total_s, 120) AS mq2_calentamiento_total_s,
        GREATEST(
          COALESCE(c.calentamiento_total_s, 120) - COALESCE(e.tiempo_encendido, 0),
          0
        ) AS mq2_calentamiento_restante_s,
        c.ultima_calibracion AS mq2_ultima_calibracion,
        c.adc_aire_limpio AS mq2_adc_aire_limpio,
        (
          SELECT a.fecha_hora
          FROM alertas a
          WHERE a.dispositivo_id = d.id
          ORDER BY a.fecha_hora DESC, a.id DESC
          LIMIT 1
        ) AS ultima_alerta
     FROM dispositivos d
     LEFT JOIN estado_sensores e ON e.dispositivo_id = d.id
     LEFT JOIN configuracion_mq2 c ON c.dispositivo_id = d.id
     WHERE d.cliente_id = :cliente_id
       AND d.estado <> 'Inactivo'
     ORDER BY
       CASE
         WHEN d.ultima_conexion IS NULL
           OR d.ultima_conexion < UTC_TIMESTAMP() - INTERVAL 2 MINUTE
         THEN 1 ELSE 0
       END,
       FIELD(COALESCE(e.estado_general, 'NORMAL'), 'ALARMA', 'ALERTA', 'NORMAL'),
       d.ubicacion,
       d.id"
);
$stmt->execute(['cliente_id' => $clienteId]);

$actuadoresShelly = [];
try {
    $actuadoresShelly = idindShellyEstadoCliente($pdo, $clienteId);
} catch (Throwable $errorShelly) {
    error_log('ID Industrial estado Shelly movil: ' . $errorShelly->getMessage());
}

responderJson(200, [
    'ok' => true,
    'data' => [
        'generado_en' => gmdate('Y-m-d H:i:s'),
        'dispositivos' => $stmt->fetchAll(),
        'actuadores_shelly' => $actuadoresShelly,
    ],
]);
