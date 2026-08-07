<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/mobile_auth.php';
requerirMetodo('GET');

requerirTokenMovil(['ADMIN']);

$pagina = filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT);
$porPagina = filter_var($_GET['por_pagina'] ?? 20, FILTER_VALIDATE_INT);
$pagina = $pagina === false ? 1 : max(1, $pagina);
$porPagina = $porPagina === false ? 20 : max(10, min(50, $porPagina));

$stmtTotal = $pdo->prepare(
    "SELECT COUNT(*)
     FROM opportunities
     WHERE source = 'Sitio web'"
);
$stmtTotal->execute();
$total = (int) $stmtTotal->fetchColumn();
$paginas = max(1, (int) ceil($total / $porPagina));
$pagina = min($pagina, $paginas);
$offset = ($pagina - 1) * $porPagina;

$stmt = $pdo->prepare(
    "SELECT
        o.id,
        o.company_name,
        o.contact_name,
        o.service,
        o.request_type,
        o.project_location,
        o.desired_execution_date,
        o.status,
        o.priority,
        o.created_at,
        o.updated_at,
        COUNT(oa.id) AS attachments_count
     FROM opportunities o
     LEFT JOIN opportunity_attachments oa ON oa.opportunity_id = o.id
     WHERE o.source = 'Sitio web'
     GROUP BY
        o.id, o.company_name, o.contact_name, o.service, o.request_type,
        o.project_location, o.desired_execution_date, o.status, o.priority,
        o.created_at, o.updated_at
     ORDER BY o.created_at DESC, o.id DESC
     LIMIT :limite OFFSET :offset"
);
$stmt->bindValue(':limite', $porPagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

responderJson(200, [
    'ok' => true,
    'data' => [
        'solicitudes' => $stmt->fetchAll(),
        'paginacion' => [
            'pagina' => $pagina,
            'por_pagina' => $porPagina,
            'total' => $total,
            'paginas' => $paginas,
        ],
    ],
]);
