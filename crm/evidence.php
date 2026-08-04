<?php
declare(strict_types=1);

require __DIR__ . '/lib/database.php';
if (crm_uses_legacy_php_url('evidence.php')) {
  header('Location: ' . crm_evidence_url((int) ($_GET['id'] ?? 0)), true, 301);
  exit;
}

$sessionDir = __DIR__ . '/data/sessions';
if (!is_dir($sessionDir)) {
  mkdir($sessionDir, 0755, true);
}
session_save_path($sessionDir);
session_start();

crm_enforce_session_timeout('crm_user', 'crm_token', crm_admin_url('dashboard', 0, ['expired' => 1]));
crm_enforce_session_timeout('bitacora_user', 'bitacora_token', crm_portal_url('resumen', 0, ['expired' => 1]));

$requestId = max(0, (int) ($_GET['id'] ?? 0));
if ($requestId === 0) {
  http_response_code(404);
  exit('Evidencia no encontrada.');
}

$pdo = crm_db();
if (!empty($_SESSION['crm_user'])) {
  crm_output_request_evidence($pdo, $requestId);
}

if (!empty($_SESSION['bitacora_user'])) {
  crm_output_request_evidence($pdo, $requestId, (int) ($_SESSION['bitacora_user']['id'] ?? 0));
}

http_response_code(403);
exit('No tienes acceso a esta evidencia.');