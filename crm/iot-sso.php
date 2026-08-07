<?php
declare(strict_types=1);

require __DIR__ . '/lib/database.php';

function crm_iot_sso_fail(int $status, string $message): void
{
  http_response_code($status);
  header_remove('Content-Type');
  header('Content-Type: text/plain; charset=UTF-8');
  header('Cache-Control: no-store');
  echo $message;
  exit;
}

try {
  $crmPdo = crm_db();
  crm_start_database_session($crmPdo);
} catch (Throwable $error) {
  crm_log_event('iot.sso_bootstrap_failed', [
    'error_class' => get_class($error),
    'error_message' => substr($error->getMessage(), 0, 500),
  ]);
  crm_iot_sso_fail(500, 'No fue posible validar la sesion del CRM.');
}

if (empty($_SESSION['crm_user']) || !is_array($_SESSION['crm_user'])) {
  header('Location: ' . crm_admin_url());
  exit;
}

crm_enforce_session_timeout(
  'crm_user',
  'crm_token',
  crm_admin_url('dashboard', 0, ['expired' => 1])
);

$crmUser = $_SESSION['crm_user'];
$crmEmail = strtolower(trim((string) ($crmUser['email'] ?? '')));
$crmRole = strtolower(trim((string) ($crmUser['role'] ?? '')));
$crmUserId = (int) ($crmUser['id'] ?? 0);
session_write_close();

require dirname(__DIR__) . '/IoT/api/auth.php';

$configuredEmail = strtolower(trim((string) ($configLocal['crm_sso_iot_email'] ?? '')));
$iotEmail = $configuredEmail !== '' ? $configuredEmail : $crmEmail;
$iotUser = null;

if ($iotEmail !== '' && filter_var($iotEmail, FILTER_VALIDATE_EMAIL)) {
  $stmt = $pdo->prepare(
    "SELECT id, cliente_id, nombre, email, rol
     FROM usuarios
     WHERE LOWER(email) = :email
       AND estado = 'ACTIVO'
     LIMIT 1"
  );
  $stmt->execute(['email' => $iotEmail]);
  $iotUser = $stmt->fetch() ?: null;
}

// A superadministrador CRM puede usar el unico administrador IoT activo.
// Si hay mas de uno, se exige crm_sso_iot_email para no elegir otro cliente.
if (!$iotUser && in_array($crmRole, ['admin', 'superadmin'], true)) {
  $stmt = $pdo->query(
    "SELECT id, cliente_id, nombre, email, rol
     FROM usuarios
     WHERE rol = 'ADMIN'
       AND estado = 'ACTIVO'
     ORDER BY id
     LIMIT 2"
  );
  $iotAdmins = $stmt->fetchAll();
  if (count($iotAdmins) === 1) {
    $iotUser = $iotAdmins[0];
  }
}

if (!$iotUser) {
  crm_log_event('iot.sso_denied', [
    'crm_user_id' => $crmUserId,
    'crm_role' => $crmRole,
    'reason' => 'iot_user_mapping_missing_or_ambiguous',
  ]);
  crm_iot_sso_fail(
    403,
    'Tu usuario del CRM no tiene un usuario IoT activo asociado. Configura crm_sso_iot_email en IoT/api/config.local.php.'
  );
}

// El CRM usa un manejador de sesiones en base de datos. IoT conserva su
// cookie independiente en el manejador nativo para que sus APIs la reconozcan.
if (session_status() !== PHP_SESSION_ACTIVE && session_module_name() === 'user') {
  session_module_name('files');
}

crearSesionUsuario($iotUser);
crm_log_event('iot.sso_success', [
  'crm_user_id' => $crmUserId,
  'iot_user_id' => (int) $iotUser['id'],
  'iot_cliente_id' => (int) $iotUser['cliente_id'],
]);

header_remove('Content-Type');
header('Cache-Control: no-store');
header('Location: ' . rtrim(crm_public_url('iot'), '/') . '/');
exit;
