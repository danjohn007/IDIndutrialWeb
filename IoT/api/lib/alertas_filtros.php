<?php
declare(strict_types=1);

function construirFiltrosAlertas(array $query, int $clienteId): array
{
    $condiciones = ['d.cliente_id = :cliente_id'];
    $params = ['cliente_id' => $clienteId];
    $meta = [
        'dispositivo_id' => '',
        'sensor' => '',
        'severidad' => '',
        'estado' => '',
        'desde' => '',
        'hasta' => '',
    ];

    $dispositivoId = trim((string) ($query['dispositivo_id'] ?? ''));
    if ($dispositivoId !== '') {
        if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $dispositivoId)) {
            responderJson(422, ['ok' => false, 'error' => 'dispositivo_id invalido']);
        }
        $condiciones[] = 'a.dispositivo_id = :dispositivo_id';
        $params['dispositivo_id'] = $dispositivoId;
        $meta['dispositivo_id'] = $dispositivoId;
    }

    $sensor = strtoupper(trim((string) ($query['sensor'] ?? '')));
    $filtrosSensor = [
        'GAS' => "(a.tipo_alerta LIKE '%GAS%' OR a.tipo_alerta LIKE '%HUMO%' OR a.tipo_alerta LIKE '%MQ-2%')",
        'FLAMA' => "(a.tipo_alerta LIKE '%FLAMA%' OR a.tipo_alerta LIKE '%FUEGO%' OR a.tipo_alerta LIKE '%KY-026%')",
        'TEMPERATURA' => "(a.tipo_alerta LIKE '%TEMPERATURA%' OR a.tipo_alerta LIKE '%CALOR%')",
        'DHT' => "a.tipo_alerta LIKE '%DHT%'",
        'CONECTIVIDAD' => "(a.tipo_alerta LIKE '%SIN CONEXION%' OR a.tipo_alerta LIKE '%DESCONECT%')",
    ];
    if ($sensor !== '') {
        if (!isset($filtrosSensor[$sensor])) {
            responderJson(422, ['ok' => false, 'error' => 'sensor invalido']);
        }
        $condiciones[] = $filtrosSensor[$sensor];
        $meta['sensor'] = $sensor;
    }

    $severidad = strtoupper(trim((string) ($query['severidad'] ?? '')));
    if ($severidad !== '') {
        if (!in_array($severidad, ['NORMAL', 'PRECAUCION', 'CRITICO'], true)) {
            responderJson(422, ['ok' => false, 'error' => 'severidad invalida']);
        }
        $condiciones[] = 'a.severidad = :severidad';
        $params['severidad'] = $severidad;
        $meta['severidad'] = $severidad;
    }

    $estado = strtoupper(trim((string) ($query['estado'] ?? '')));
    if ($estado !== '') {
        if (!in_array($estado, ['NUEVA', 'RECONOCIDA', 'RESUELTA'], true)) {
            responderJson(422, ['ok' => false, 'error' => 'estado invalido']);
        }
        if ($estado === 'RESUELTA') {
            $condiciones[] = 'a.atendida = 1';
        } elseif ($estado === 'RECONOCIDA') {
            $condiciones[] = "a.atendida = 0 AND EXISTS (
                SELECT 1
                FROM alerta_gestiones ge
                WHERE ge.alerta_id = a.id
                  AND ge.accion = 'RECONOCER'
            )";
        } else {
            $condiciones[] = "a.atendida = 0 AND NOT EXISTS (
                SELECT 1
                FROM alerta_gestiones ge
                WHERE ge.alerta_id = a.id
            )";
        }
        $meta['estado'] = $estado;
    }

    foreach (['desde' => '>=', 'hasta' => '<='] as $campo => $operador) {
        $valor = trim((string) ($query[$campo] ?? ''));
        if ($valor === '') {
            continue;
        }

        try {
            $fecha = new DateTimeImmutable($valor);
            $fecha = $fecha->setTimezone(new DateTimeZone('UTC'));
        } catch (Throwable $error) {
            responderJson(422, ['ok' => false, 'error' => "Fecha invalida: {$campo}"]);
        }

        $condiciones[] = "a.fecha_hora {$operador} :{$campo}";
        $params[$campo] = $fecha->format('Y-m-d H:i:s');
        $meta[$campo] = $valor;
    }

    return [
        'where' => 'WHERE ' . implode(' AND ', $condiciones),
        'params' => $params,
        'meta' => $meta,
    ];
}

function enlazarParametrosAlerta(PDOStatement $stmt, array $params): void
{
    foreach ($params as $nombre => $valor) {
        $tipo = $nombre === 'cliente_id' ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue(':' . $nombre, $valor, $tipo);
    }
}
