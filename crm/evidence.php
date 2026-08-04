<?php
declare(strict_types=1);

require __DIR__ . '/lib/database.php';

$sessionDir = __DIR__ . '/data/sessions';
if (!is_dir($sessionDir)) {
  mkdir($sessionDir, 0755, true);
}
session_save_path($sessionDir);
session_start();

crm_enforce_session_timeout('crm_user', 'crm_token', 'index.php?expired=1');
crm_enforce_session_timeout('bitacora_user', 'bitacora_token', 'cliente.php?expired=1');

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