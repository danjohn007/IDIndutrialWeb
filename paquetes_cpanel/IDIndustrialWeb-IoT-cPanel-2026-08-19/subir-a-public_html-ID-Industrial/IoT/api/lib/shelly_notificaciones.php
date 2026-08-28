<?php
declare(strict_types=1);

require_once __DIR__ . '/push_notificaciones.php';

function idindShellyEncolarNotificacionEvento(
    PDO $pdo,
    int $eventoId,
    array $configLocal = []
): array {
    if ($eventoId <= 0) {
        return ['encoladas' => 0, 'envio' => null];
    }

    $stmt = $pdo->prepare(
        "SELECT e.id, e.evento, e.origen, e.salida_encendida,
                a.id AS actuador_id, a.nombre, a.ubicacion, a.canal,
                a.cliente_id, a.notificar_cambios_externos
         FROM eventos_shelly e
         INNER JOIN actuadores_shelly a ON a.id = e.actuador_id
         WHERE e.id = :id LIMIT 1"
    );
    $stmt->execute(['id' => $eventoId]);
    $evento = $stmt->fetch();
    if (
        !$evento
        || (int) ($evento['notificar_cambios_externos'] ?? 0) !== 1
        || !in_array((string) $evento['origen'], ['CLOUD', 'WEBHOOK'], true)
    ) {
        return ['encoladas' => 0, 'envio' => null];
    }

    $encendida = (int) ($evento['salida_encendida'] ?? 0) === 1;
    $nombre = trim((string) ($evento['nombre'] ?? ''));
    if ($nombre === '') {
        $nombre = (string) $evento['actuador_id'];
    }
    $ubicacion = trim((string) ($evento['ubicacion'] ?? ''));
    $titulo = $encendida ? 'Shelly encendido externamente' : 'Shelly apagado externamente';
    $cuerpo = $nombre . ($ubicacion !== '' ? ' en ' . $ubicacion : '')
        . ($encendida ? ' fue encendido fuera de ID Industrial.' : ' fue apagado fuera de ID Industrial.');
    $payload = [
        'tipo' => 'SHELLY',
        'categoria' => 'CAMBIO_EXTERNO',
        'evento' => $encendida ? 'SHELLY_ENCENDIDO' : 'SHELLY_APAGADO',
        'evento_shelly_id' => $eventoId,
        'actuadorId' => (string) $evento['actuador_id'],
        'actuador_id' => (string) $evento['actuador_id'],
        'canal' => (int) $evento['canal'],
        'url' => '/shelly/' . rawurlencode((string) $evento['actuador_id']),
    ];
    $dedupe = hash('sha256', 'SHELLY_EVENT:' . $eventoId);

    $stmtTokens = $pdo->prepare(
        "SELECT mp.id
         FROM moviles_push mp
         INNER JOIN usuarios u ON u.id = mp.usuario_id
         WHERE u.cliente_id = :cliente_id
           AND u.estado = 'ACTIVO'
           AND mp.activo = 1"
    );
    $stmtTokens->execute(['cliente_id' => (int) $evento['cliente_id']]);
    $stmtInsertar = $pdo->prepare(
        "INSERT IGNORE INTO notificaciones_push (
            alerta_id, origen_tipo, evento_shelly_id, dedupe_key,
            push_token_id, cliente_id, titulo, cuerpo, payload_json,
            estado, intentos, disponible_en
         ) VALUES (
            NULL, 'SHELLY', :evento_shelly_id, :dedupe_key,
            :push_token_id, :cliente_id, :titulo, :cuerpo, :payload_json,
            'PENDIENTE', 0, UTC_TIMESTAMP()
         )"
    );
    $ids = [];
    foreach ($stmtTokens->fetchAll() as $token) {
        $stmtInsertar->execute([
            'evento_shelly_id' => $eventoId,
            'dedupe_key' => $dedupe,
            'push_token_id' => (int) $token['id'],
            'cliente_id' => (int) $evento['cliente_id'],
            'titulo' => substr($titulo, 0, 120),
            'cuerpo' => substr($cuerpo, 0, 255),
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        if ($stmtInsertar->rowCount() > 0) {
            $ids[] = (int) $pdo->lastInsertId();
        }
    }

    $envio = $ids === [] ? null : idindPushEnviarPendientesPorIds($pdo, $ids, $configLocal);
    return ['encoladas' => count($ids), 'envio' => $envio];
}
