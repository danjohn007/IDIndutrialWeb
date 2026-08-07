<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/mobile_auth.php';
requerirMetodo('GET');

requerirTokenMovil(['ADMIN']);

$opportunityId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($opportunityId === false || $opportunityId === null || $opportunityId < 1) {
    responderJson(422, ['ok' => false, 'error' => 'Cotizacion invalida']);
}

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
    responderJson(404, ['ok' => false, 'error' => 'La solicitud no existe']);
}

$stmtAttachments = $pdo->prepare(
    "SELECT id, original_name, mime, size, created_at
     FROM opportunity_attachments
     WHERE opportunity_id = :opportunity_id
     ORDER BY created_at ASC, id ASC"
);
$stmtAttachments->execute(['opportunity_id' => $opportunityId]);

$crmBaseUrl = rtrim(
    (string) ($sharedConfig['app_url'] ?? 'https://idindustrial.com.mx/IoT/crm'),
    '/'
);

responderJson(200, [
    'ok' => true,
    'data' => [
        'solicitud' => $solicitud,
        'adjuntos' => $stmtAttachments->fetchAll(),
        'crm_url' => $crmBaseUrl . '/oportunidades/' . $opportunityId,
    ],
]);
