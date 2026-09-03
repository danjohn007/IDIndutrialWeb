<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/mobile_auth.php';
require_once dirname(__DIR__) . '/lib/alertas_filtros.php';
requerirMetodo('GET');

$usuario = requerirTokenMovil();
$clienteId = (int) $usuario['cliente_id'];
$pagina = filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT);
$porPagina = filter_var($_GET['por_pagina'] ?? 20, FILTER_VALIDATE_INT);
$pagina = $pagina === false ? 1 : max(1, $pagina);
$porPagina = $porPagina === false ? 20 : max(10, min(50, $porPagina));
$filtros = construirFiltrosAlertas($_GET, $clienteId);

$stmtTotal = $pdo->prepare(
    "SELECT COUNT(*)
     FROM alertas a
     INNER JOIN dispositivos d ON d.id = a.dispositivo_id
     {$filtros['where']}"
);
enlazarParametrosAlerta($stmtTotal, $filtros['params']);
$stmtTotal->execute();
$total = (int) $stmtTotal->fetchColumn();
$paginas = max(1, (int) ceil($total / $porPagina));
$pagina = min($pagina, $paginas);
$offset = ($pagina - 1) * $porPagina;

$stmtAlertas = $pdo->prepare(
    "SELECT
        a.id,
        a.dispositivo_id,
        d.ubicacion,
        a.tipo_alerta,
        a.valor_sensor,
        a.severidad,
        a.atendida,
        a.fecha_hora,
        CASE
            WHEN a.atendida = 1 THEN 'RESUELTA'
            WHEN g.accion = 'RECONOCER' THEN 'RECONOCIDA'
            ELSE 'NUEVA'
        END AS estado_atencion
     FROM alertas a
     INNER JOIN dispositivos d ON d.id = a.dispositivo_id
     LEFT JOIN alerta_gestiones g ON g.id = (
       SELECT g2.id
       FROM alerta_gestiones g2
       WHERE g2.alerta_id = a.id
       ORDER BY g2.fecha_hora DESC, g2.id DESC
       LIMIT 1
     )
     {$filtros['where']}
     ORDER BY a.fecha_hora DESC, a.id DESC
     LIMIT :limite OFFSET :offset"
);
enlazarParametrosAlerta($stmtAlertas, $filtros['params']);
$stmtAlertas->bindValue(':limite', $porPagina, PDO::PARAM_INT);
$stmtAlertas->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmtAlertas->execute();

$stmtDispositivos = $pdo->prepare(
    "SELECT id, ubicacion
     FROM dispositivos
     WHERE cliente_id = :cliente_id
       AND estado <> 'Inactivo'
     ORDER BY ubicacion, id"
);
$stmtDispositivos->execute(['cliente_id' => $clienteId]);

responderJson(200, [
    'ok' => true,
    'data' => [
        'alertas' => $stmtAlertas->fetchAll(),
        'dispositivos' => $stmtDispositivos->fetchAll(),
        'paginacion' => [
            'pagina' => $pagina,
            'por_pagina' => $porPagina,
            'total' => $total,
            'paginas' => $paginas,
        ],
        'filtros' => $filtros['meta'],
    ],
]);
