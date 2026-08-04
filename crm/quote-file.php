<?php
declare(strict_types=1);

require __DIR__ . '/lib/database.php';

$pdo = crm_db();
crm_start_database_session($pdo);
crm_enforce_session_timeout('crm_user', 'crm_token', crm_admin_url('dashboard', 0, ['expired' => 1]));
crm_enforce_session_timeout('bitacora_user', 'bitacora_token', crm_portal_url('resumen', 0, ['expired' => 1]));

$quoteId = max(0, (int) ($_GET['id'] ?? 0));
$fileType = ($_GET['type'] ?? '') === 'proposal' ? 'proposal' : 'request';
if ($quoteId === 0) {
  http_response_code(404);
  exit('Archivo de cotizacion no encontrado.');
}

if (!empty($_SESSION['crm_user'])) {
  crm_output_quote_attachment($pdo, $quoteId, null, $fileType);
}

if (!empty($_SESSION['bitacora_user'])) {
  crm_output_quote_attachment($pdo, $quoteId, (int) ($_SESSION['bitacora_user']['id'] ?? 0), $fileType);
}

http_response_code(403);
exit('No tienes acceso a este archivo.');