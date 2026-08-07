<?php
declare(strict_types=1);

function idindEncolarNotificacionesAlertas(
    PDO $pdo,
    array $alertaIds,
    array $severidades = ['CRITICO']
): void {
    $ids = array_values(array_unique(array_filter(array_map('intval', $alertaIds))));
    $permitidas = array_values(array_intersect(
        ['NORMAL', 'PRECAUCION', 'CRITICO'],
        array_map('strtoupper', $severidades)
    ));
    if ($ids === [] || $permitidas === []) {
        return;
    }

    $marcadoresIds = implode(',', array_fill(0, count($ids), '?'));
    $marcadoresSeveridad = implode(',', array_fill(0, count($permitidas), '?'));
    $stmtAlertas = $pdo->prepare(
        "SELECT
            a.id, a.tipo_alerta, a.valor_sensor, a.severidad,
            a.dispositivo_id, d.cliente_id, d.ubicacion
         FROM alertas a
         INNER JOIN dispositivos d ON d.id = a.dispositivo_id
         WHERE a.id IN ({$marcadoresIds})
           AND a.severidad IN ({$marcadoresSeveridad})"
    );
    $stmtAlertas->execute(array_merge($ids, $permitidas));
    $alertas = $stmtAlertas->fetchAll();
    if ($alertas === []) {
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

    foreach ($alertas as $alerta) {
        $tipo = (string) $alerta['tipo_alerta'];
        $ubicacion = (string) $alerta['ubicacion'];
        $dispositivo = (string) $alerta['dispositivo_id'];
        $valor = $alerta['valor_sensor'];
        $esDesconexion = stripos($tipo, 'sin conexion') !== false
            || stripos($tipo, 'desconect') !== false;

        if ($esDesconexion) {
            $titulo = 'Dispositivo sin conexion';
            $cuerpo = "{$dispositivo} dejo de reportar en {$ubicacion}. Revisa energia, Wi-Fi o internet.";
            $categoria = 'CONECTIVIDAD';
        } else {
            $titulo = 'Alerta critica: ' . $tipo;
            $categoria = 'SENSOR';
            if (stripos($tipo, 'Flama') !== false) {
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
                        'categoria' => $categoria,
                        'url' => '/alerta/' . (int) $alerta['id'],
                    ],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),
            ]);
        }
    }
}
