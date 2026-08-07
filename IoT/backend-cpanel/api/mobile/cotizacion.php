<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/mobile_auth.php';
requerirMetodo('GET');

function idindLogCotizacion(string $event, array $context = []): void
{
    error_log('[IDIND mobile/cotizacion] ' . $event . ' ' . json_encode(
        $context,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    ));
}

$requestId = bin2hex(random_bytes(6));
$mobileUser = requerirTokenMovil(['ADMIN']);
$opportunityId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($opportunityId === false || $opportunityId === null || $opportunityId < 1) {
    idindLogCotizacion('invalid_id', [
        'request_id' => $requestId,
        'received_id' => is_scalar($_GET['id'] ?? null) ? (string) $_GET['id'] : gettype($_GET['id'] ?? null),
        'user_id' => (int) $mobileUser['id'],
    ]);
    responderJson(422, ['ok' => false, 'error' => 'Cotizacion invalida']);
}

try {
    $databaseName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    idindLogCotizacion('request', [
        'request_id' => $requestId,
        'database' => $databaseName,
        'user_id' => (int) $mobileUser['id'],
        'role' => (string) $mobileUser['rol'],
        'opportunity_id' => $opportunityId,
    ]);

    $stmt = $pdo->prepare(
        "SELECT
            id,
            company_name,
            contact_name,
            contact_email,
            contact_phone,
            service,
            request_type,
            project_location,
            desired_execution_date,
            status,
            priority,
            notes,
            source,
            created_at,
            updated_at
         FROM opportunities
         WHERE id = :id
           AND source IN ('Formulario web', 'Sitio web')
         LIMIT 1"
    );
    $stmt->execute(['id' => $opportunityId]);
    $solicitud = $stmt->fetch();
    if (!$solicitud) {
        $lookup = $pdo->prepare('SELECT id, source, status, created_at FROM opportunities WHERE id = :id LIMIT 1');
        $lookup->execute(['id' => $opportunityId]);
        $existingOpportunity = $lookup->fetch() ?: null;
        $databaseStats = $pdo->query('SELECT COUNT(*) AS total, MAX(id) AS max_id FROM opportunities')->fetch();

        idindLogCotizacion('not_found', [
            'request_id' => $requestId,
            'database' => $databaseName,
            'requested_id' => $opportunityId,
            'exists_without_source_filter' => $existingOpportunity !== null,
            'existing_record' => $existingOpportunity,
            'database_stats' => $databaseStats,
        ]);
        responderJson(404, ['ok' => false, 'error' => 'La solicitud no existe']);
    }

    $stmtAttachments = $pdo->prepare(
        "SELECT id, original_name, mime, size, created_at
         FROM opportunity_attachments
         WHERE opportunity_id = :opportunity_id
         ORDER BY created_at ASC, id ASC"
    );
    $stmtAttachments->execute(['opportunity_id' => $opportunityId]);
    $attachments = $stmtAttachments->fetchAll();

    idindLogCotizacion('result', [
        'request_id' => $requestId,
        'database' => $databaseName,
        'opportunity_id' => $opportunityId,
        'source' => (string) $solicitud['source'],
        'attachments' => count($attachments),
    ]);

    unset($solicitud['source']);
    $crmBaseUrl = rtrim(
        (string) ($sharedConfig['app_url'] ?? 'https://idindustrial.com.mx/IoT/crm'),
        '/'
    );

    responderJson(200, [
        'ok' => true,
        'data' => [
            'solicitud' => $solicitud,
            'adjuntos' => $attachments,
            'crm_url' => $crmBaseUrl . '/oportunidades/' . $opportunityId,
        ],
    ]);
} catch (Throwable $error) {
    idindLogCotizacion('query_error', [
        'request_id' => $requestId,
        'opportunity_id' => $opportunityId,
        'error_class' => get_class($error),
        'error' => $error->getMessage(),
    ]);
    responderJson(500, ['ok' => false, 'error' => 'No fue posible consultar la cotizacion']);
}
