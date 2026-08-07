<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => false, 'error' => 'No encontrado']);
    exit;
}

require_once __DIR__ . '/config.php';

const IDIND_PUSH_BATCH_SIZE = 50;
const IDIND_PUSH_MAX_ATTEMPTS = 5;
const IDIND_EXPO_PUSH_URL = 'https://exp.host/--/api/v2/push/send';

function salidaCron(array $datos, int $codigo = 0): void
{
    echo json_encode(
        $datos,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) . PHP_EOL;
    exit($codigo);
}

function proximaEjecucion(int $intento): string
{
    $segundos = min(1800, 30 * (2 ** max(0, $intento - 1)));
    return gmdate('Y-m-d H:i:s', time() + $segundos);
}

function actualizarCola(
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

if (!function_exists('curl_init')) {
    salidaCron(
        ['ok' => false, 'error' => 'La extension cURL no esta habilitada'],
        1
    );
}

try {
    $pdo->exec(
        "UPDATE notificaciones_push
         SET estado = 'REINTENTAR',
             disponible_en = UTC_TIMESTAMP(),
             ultimo_error = 'Envio interrumpido; se recupero la cola'
         WHERE estado = 'ENVIANDO'
           AND actualizado_en < UTC_TIMESTAMP() - INTERVAL 10 MINUTE"
    );

    $pdo->beginTransaction();
    $stmtPendientes = $pdo->prepare(
        "SELECT
            np.id, np.alerta_id, np.push_token_id, np.titulo, np.cuerpo,
            np.payload_json, np.intentos, mp.expo_push_token
         FROM notificaciones_push np
         INNER JOIN moviles_push mp ON mp.id = np.push_token_id
         WHERE np.estado IN ('PENDIENTE', 'REINTENTAR')
           AND np.disponible_en <= UTC_TIMESTAMP()
           AND np.intentos < :max_intentos
           AND mp.activo = 1
         ORDER BY np.id ASC
         LIMIT " . IDIND_PUSH_BATCH_SIZE . '
         FOR UPDATE'
    );
    $stmtPendientes->bindValue(
        ':max_intentos',
        IDIND_PUSH_MAX_ATTEMPTS,
        PDO::PARAM_INT
    );
    $stmtPendientes->execute();
    $pendientes = $stmtPendientes->fetchAll();

    if ($pendientes === []) {
        $pdo->commit();
        salidaCron(['ok' => true, 'procesadas' => 0]);
    }

    $ids = array_map(
        static function (array $fila): int {
            return (int) $fila['id'];
        },
        $pendientes
    );
    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    $stmtTomar = $pdo->prepare(
        "UPDATE notificaciones_push
         SET estado = 'ENVIANDO',
             intentos = intentos + 1,
             ultimo_error = NULL
         WHERE id IN ({$marcadores})"
    );
    $stmtTomar->execute($ids);
    $pdo->commit();

    $mensajes = array_map(
        static function (array $fila): array {
            $data = json_decode((string) ($fila['payload_json'] ?? ''), true);
            if (!is_array($data)) {
                $data = [];
            }
            $alertaId = (int) $fila['alerta_id'];
            $data['alertaId'] = $alertaId;
            $data['alerta_id'] = $alertaId;
            $data['tipo'] = 'ALERTA';
            $data['url'] = '/alerta/' . $alertaId;
            return [
                'to' => (string) $fila['expo_push_token'],
                'sound' => 'default',
                'priority' => 'high',
                'channelId' => 'critical-alerts',
                'title' => (string) $fila['titulo'],
                'body' => (string) $fila['cuerpo'],
                'data' => $data,
            ];
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

    $curl = curl_init(IDIND_EXPO_PUSH_URL);
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode(
            $mensajes,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 25,
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
            $estado = $intento >= IDIND_PUSH_MAX_ATTEMPTS
                ? 'DESCARTADA'
                : 'REINTENTAR';
            actualizarCola(
                $pdo,
                (int) $fila['id'],
                $estado,
                $motivo,
                null,
                $estado === 'REINTENTAR' ? proximaEjecucion($intento) : null
            );
        }
        salidaCron(['ok' => false, 'error' => $motivo], 1);
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
            actualizarCola(
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
            || $intento >= IDIND_PUSH_MAX_ATTEMPTS;
        actualizarCola(
            $pdo,
            (int) $fila['id'],
            $descartar ? 'DESCARTADA' : 'REINTENTAR',
            trim($codigo . ' ' . $mensaje),
            null,
            $descartar ? null : proximaEjecucion($intento)
        );
        if ($codigo === 'DeviceNotRegistered') {
            $pdo->prepare(
                'UPDATE moviles_push SET activo = 0 WHERE id = :id'
            )->execute(['id' => (int) $fila['push_token_id']]);
        }
        $descartar ? $descartadas++ : $reintentos++;
    }

    salidaCron([
        'ok' => true,
        'procesadas' => count($pendientes),
        'enviadas' => $enviadas,
        'reintentos' => $reintentos,
        'descartadas' => $descartadas,
    ]);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('ID Industrial cron push: ' . $error->getMessage());
    salidaCron(['ok' => false, 'error' => 'Fallo interno del cron push'], 1);
}
