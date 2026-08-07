<?php
declare(strict_types=1);

require __DIR__ . '/lib/database.php';

$pdo = crm_db();
crm_start_database_session($pdo);
crm_enforce_session_timeout('crm_user', 'crm_token', crm_admin_url('dashboard', 0, ['expired' => 1]));

if (empty($_SESSION['crm_user'])) {
  http_response_code(403);
  exit('No tienes acceso a este archivo.');
}

$attachmentId = max(0, (int) ($_GET['id'] ?? 0));
if ($attachmentId === 0) {
  http_response_code(404);
  exit('Archivo no encontrado.');
}

crm_output_opportunity_attachment($pdo, $attachmentId);