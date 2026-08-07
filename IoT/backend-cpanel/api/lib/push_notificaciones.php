<?php
declare(strict_types=1);

const IDIND_PUSH_MAX_ATTEMPTS_HELPER = 5;
const IDIND_EXPO_PUSH_URL_HELPER = 'https://exp.host/--/api/v2/push/send';

function idindPushProximaEjecucion(int $intento): string
{
    $segundos = min(1800, 30 * (2 ** max(0, $intento - 1)));
    return gmdate('Y-m-d H:i:s', time() + $segundos);
}

function idindPushActualizarCola(
    PDO $pdo,
    int $id,
    string $estado,
    ?string $error = null,
    ?string $ticketId = null,
    ?string $disponibleEn = null
): void {
    $stmt = $pdo->prepare(
        'UPDATE notificaciones_push
         SET estado = :estado,
             ultimo_error = :ultimo_error,
             ticket_id = COALESCE(:ticket_id, ticket_id),
             disponible_en = COALESCE(:disponible_en, disponible_en),
             enviado_en = CASE
                 WHEN :estado_enviado = \'ENVIADA\' THEN UTC_TIMESTAMP()
                 ELSE enviado_en
             END
         WHERE id = :id'
    );
    $stmt->execute([
        'estado' => $estado,
        'ultimo_error' => $error === null ? null : substr($error, 0, 500),
        'ticket_id' => $ticketId,
        'disponible_en' => $disponibleEn,
        'estado_enviado' => $estado,
        'id' => $id,
    ]);
}

function idindPushTomarPendientes(
    PDO $pdo,
    int $maximo = 20,
    ?string $dedupeKey = null
): array
{
    $limite = max(1, min(50, $maximo));
    $dedupeKey = trim((string) $dedupeKey);
    $filtroDedupe = $dedupeKey !== ''
        ? ' AND np.dedupe_key = :dedupe_key'
        : '';
    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        "SELECT
            np.id, np.alerta_id, np.origen_tipo, np.push_token_id,
            np.titulo, np.cuerpo, np.payload_json, np.intentos,
            mp.expo_push_token
         FROM notificaciones_push np
         INNER JOIN moviles_push mp ON mp.id = np.push_token_id
         WHERE np.estado IN ('PENDIENTE', 'REINTENTAR')
           AND np.disponible_en <= UTC_TIMESTAMP()
           AND np.intentos < :max_intentos
           AND mp.activo = 1
           {$filtroDedupe}
         ORDER BY np.id ASC
         LIMIT {$limite}
         FOR UPDATE"
    );
    $stmt->bindValue(':max_intentos', IDIND_PUSH_MAX_ATTEMPTS_HELPER, PDO::PARAM_INT);
    if ($dedupeKey !== '') {
        $stmt->bindValue(':dedupe_key', $dedupeKey, PDO::PARAM_STR);
    }
    $stmt->execute();
    $pendientes = $stmt->fetchAll();

    if ($pendientes === []) {
        $pdo->commit();
        return [];
    }

    $ids = array_map(static function (array $fila): int {
        return (int) $fila['id'];
    }, $pendientes);
    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    $stmtTomar = $pdo->prepare(
        "UPDATE notificaciones_push
         SET estado = 'ENVIANDO', intentos = intentos + 1, ultimo_error = NULL
         WHERE id IN ({$marcadores})"
    );
    $stmtTomar->execute($ids);
    $pdo->commit();

    return $pendientes;
}

function idindPushTomarPendientesPorAlertas(
    PDO $pdo,
    array $alertaIds,
    int $maximo = 20
): array {
    $ids = array_values(array_unique(array_filter(array_map('intval', $alertaIds))));
    if ($ids === []) {
        return [];
    }

    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    $limite = max(1, min(50, $maximo));

    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        "SELECT
            np.id, np.alerta_id, np.origen_tipo, np.push_token_id, np.titulo, np.cuerpo,
            np.payload_json, np.intentos, mp.expo_push_token
         FROM notificaciones_push np
         INNER JOIN moviles_push mp ON mp.id = np.push_token_id
         WHERE np.alerta_id IN ({$marcadores})
           AND np.estado IN ('PENDIENTE', 'REINTENTAR')
           AND np.disponible_en <= UTC_TIMESTAMP()
           AND np.intentos < ?
           AND mp.activo = 1
         ORDER BY np.id ASC
         LIMIT {$limite}
         FOR UPDATE"
    );
    $stmt->execute(array_merge($ids, [IDIND_PUSH_MAX_ATTEMPTS_HELPER]));
    $pendientes = $stmt->fetchAll();

    if ($pendientes === []) {
        $pdo->commit();
        return [];
    }

    $notificacionIds = array_map(
        static function (array $fila): int {
            return (int) $fila['id'];
        },
        $pendientes
    );
    $marcadoresNotificaciones = implode(',', array_fill(0, count($notificacionIds), '?'));
    $stmtTomar = $pdo->prepare(
        "UPDATE notificaciones_push
         SET estado = 'ENVIANDO',
             intentos = intentos + 1,
             ultimo_error = NULL
         WHERE id IN ({$marcadoresNotificaciones})"
    );
    $stmtTomar->execute($notificacionIds);
    $pdo->commit();

    return $pendientes;
}

function idindPushEnviarFilas(
    PDO $pdo,
    array $pendientes,
    array $configLocal = [],
    int $connectTimeout = 3,
    int $timeout = 8
): array {
    if ($pendientes === []) {
        return ['ok' => true, 'procesadas' => 0, 'enviadas' => 0, 'reintentos' => 0, 'descartadas' => 0];
    }

    if (!function_exists('curl_init')) {
        foreach ($pendientes as $fila) {
            $intento = (int) $fila['intentos'] + 1;
            idindPushActualizarCola(
                $pdo,
                (int) $fila['id'],
                'REINTENTAR',
                'La extension cURL no esta habilitada',
                null,
                idindPushProximaEjecucion($intento)
            );
        }
        return ['ok' => false, 'error' => 'La extension cURL no esta habilitada'];
    }

    $mensajes = array_map(
        static function (array $fila): array {
            $data = json_decode((string) ($fila['payload_json'] ?? ''), true);
            if (!is_array($data)) {
                $data = [];
            }
            $origin = strtoupper((string) ($fila['origen_tipo'] ?? $data['tipo'] ?? 'ALERTA'));
            $message = [
                'to' => (string) $fila['expo_push_token'],
                'sound' => 'default',
                'title' => (string) $fila['titulo'],
                'body' => (string) $fila['cuerpo'],
            ];

            if ($origin === 'ALERTA') {
                $alertaId = (int) $fila['alerta_id'];
                $data['alertaId'] = $alertaId;
                $data['alerta_id'] = $alertaId;
                $data['tipo'] = 'ALERTA';
                $data['url'] = '/alerta/' . $alertaId;
                $message['priority'] = 'high';
                $message['channelId'] = 'critical-alerts';
            } elseif ($origin === 'COTIZACION') {
                $data['tipo'] = 'COTIZACION';
                $message['priority'] = 'default';
                $message['channelId'] = 'crm-updates';
            } else {
                $data['tipo'] = $origin;
                $message['priority'] = 'default';
            }

            $message['data'] = $data;
            return $message;
        },
        $pendientes
    );

    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
        'Accept-Encoding: gzip, deflate',
    ];
    $expoAccessToken = trim(
        (string) (
            getenv('IDIND_EXPO_ACCESS_TOKEN')
            ?: ($configLocal['expo_access_token'] ?? '')
        )
    );
    if ($expoAccessToken !== '') {
        $headers[] = 'Authorization: Bearer ' . $expoAccessToken;
    }

    $curl = curl_init(IDIND_EXPO_PUSH_URL_HELPER);
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode(
            $mensajes,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => $connectTimeout,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_ENCODING => '',
    ]);
    $respuesta = curl_exec($curl);
    $httpStatus = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    if ($respuesta === false || $httpStatus < 200 || $httpStatus >= 300) {
        $motivo = $curlError !== ''
            ? $curlError
            : "Expo respondio HTTP {$httpStatus}";
        foreach ($pendientes as $fila) {
            $intento = (int) $fila['intentos'] + 1;
            $estado = $intento >= IDIND_PUSH_MAX_ATTEMPTS_HELPER
                ? 'DESCARTADA'
                : 'REINTENTAR';
            idindPushActualizarCola(
                $pdo,
                (int) $fila['id'],
                $estado,
                $motivo,
                null,
                $estado === 'REINTENTAR' ? idindPushProximaEjecucion($intento) : null
            );
        }
        return ['ok' => false, 'error' => $motivo, 'procesadas' => count($pendientes)];
    }

    $json = json_decode((string) $respuesta, true);
    $tickets = is_array($json['data'] ?? null) ? $json['data'] : [];
    if (isset($tickets['status'])) {
        $tickets = [$tickets];
    }

    $enviadas = 0;
    $reintentos = 0;
    $descartadas = 0;
    foreach ($pendientes as $indice => $fila) {
        $ticket = is_array($tickets[$indice] ?? null) ? $tickets[$indice] : [];
        $intento = (int) $fila['intentos'] + 1;
        if (($ticket['status'] ?? '') === 'ok') {
            idindPushActualizarCola(
                $pdo,
                (int) $fila['id'],
                'ENVIADA',
                null,
                (string) ($ticket['id'] ?? '')
            );
            $enviadas++;
            continue;
        }

        $detalle = is_array($ticket['details'] ?? null)
            ? $ticket['details']
            : [];
        $codigo = (string) ($detalle['error'] ?? '');
        $mensaje = (string) ($ticket['message'] ?? 'Respuesta push invalida');
        $descartar = $codigo === 'DeviceNotRegistered'
            || $intento >= IDIND_PUSH_MAX_ATTEMPTS_HELPER;
        idindPushActualizarCola(
            $pdo,
            (int) $fila['id'],
            $descartar ? 'DESCARTADA' : 'REINTENTAR',
            trim($codigo . ' ' . $mensaje),
            null,
            $descartar ? null : idindPushProximaEjecucion($intento)
        );
        if ($codigo === 'DeviceNotRegistered') {
            $pdo->prepare(
                'UPDATE moviles_push SET activo = 0 WHERE id = :id'
            )->execute(['id' => (int) $fila['push_token_id']]);
        }
        $descartar ? $descartadas++ : $reintentos++;
    }

    return [
        'ok' => true,
        'procesadas' => count($pendientes),
        'enviadas' => $enviadas,
        'reintentos' => $reintentos,
        'descartadas' => $descartadas,
    ];
}

function idindPushEnviarPendientesPorAlertas(
    PDO $pdo,
    array $alertaIds,
    array $configLocal = []
): array {
    $pendientes = idindPushTomarPendientesPorAlertas($pdo, $alertaIds);
    return idindPushEnviarFilas($pdo, $pendientes, $configLocal, 2, 5);
}
