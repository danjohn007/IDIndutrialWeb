<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/mobile_auth.php';
requerirMetodo('GET');

function idindLogCotizaciones(string $event, array $context = []): void
{
    error_log('[IDIND mobile/cotizaciones] ' . $event . ' ' . json_encode(
        $context,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    ));
}

$requestId = bin2hex(random_bytes(6));
$mobileUser = requerirTokenMovil(['ADMIN']);

$pagina = filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT);
$porPagina = filter_var($_GET['por_pagina'] ?? 20, FILTER_VALIDATE_INT);
$pagina = $pagina === false ? 1 : max(1, $pagina);
$porPagina = $porPagina === false ? 20 : max(10, min(50, $porPagina));

try {
    $databaseName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    $totalAll = (int) $pdo->query('SELECT COUNT(*) FROM opportunities')->fetchColumn();
    $sourceRows = $pdo->query(
        "SELECT COALESCE(NULLIF(TRIM(source), ''), '(vacio)') AS source_value, COUNT(*) AS total
         FROM opportunities
         GROUP BY COALESCE(NULLIF(TRIM(source), ''), '(vacio)')
         ORDER BY total DESC"
    )->fetchAll();

    idindLogCotizaciones('request', [
        'request_id' => $requestId,
        'database' => $databaseName,
        'user_id' => (int) $mobileUser['id'],
        'role' => (string) $mobileUser['rol'],
        'page' => $pagina,
        'per_page' => $porPagina,
        'opportunities_total' => $totalAll,
        'sources' => $sourceRows,
    ]);

    $stmtTotal = $pdo->prepare(
        "SELECT COUNT(*)
         FROM opportunities
         WHERE source IN ('Formulario web', 'Sitio web')"
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
         WHERE o.source IN ('Formulario web', 'Sitio web')
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
    $solicitudes = $stmt->fetchAll();

    idindLogCotizaciones($total === 0 ? 'empty_result' : 'result', [
        'request_id' => $requestId,
        'database' => $databaseName,
        'filtered_total' => $total,
        'returned' => count($solicitudes),
        'page' => $pagina,
        'offset' => $offset,
    ]);

    responderJson(200, [
        'ok' => true,
        'data' => [
            'solicitudes' => $solicitudes,
            'paginacion' => [
                'pagina' => $pagina,
                'por_pagina' => $porPagina,
                'total' => $total,
                'paginas' => $paginas,
            ],
        ],
    ]);
} catch (Throwable $error) {
    idindLogCotizaciones('query_error', [
        'request_id' => $requestId,
        'error_class' => get_class($error),
        'error' => $error->getMessage(),
    ]);
    responderJson(500, ['ok' => false, 'error' => 'No fue posible consultar las cotizaciones']);
}
