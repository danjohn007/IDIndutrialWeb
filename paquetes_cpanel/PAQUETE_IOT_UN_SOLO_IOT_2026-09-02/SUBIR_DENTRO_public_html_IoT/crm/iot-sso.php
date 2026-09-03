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

function crm_iot_has_column(PDO $pdo, string $table, string $column): bool
{
  try {
    return crm_table_exists($pdo, $table) && crm_column_exists($pdo, $table, $column);
  } catch (Throwable $error) {
    return false;
  }
}

function crm_iot_require_core_tables(PDO $pdo): void
{
  foreach (['clientes', 'usuarios'] as $table) {
    if (!crm_table_exists($pdo, $table)) {
      crm_iot_sso_fail(503, 'Falta importar la base IoT: tabla ' . $table . '.');
    }
  }
}

function crm_iot_normalize_email(string $email, string $fallback): string
{
  $email = strtolower(trim($email));
  if (filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 160) {
    return $email;
  }
  return strtolower($fallback);
}

function crm_iot_short_text(string $value, int $max): string
{
  $value = trim(preg_replace('/\s+/', ' ', $value) ?: '');
  if ($value === '') {
    return '';
  }
  if (function_exists('mb_substr')) {
    return mb_substr($value, 0, $max, 'UTF-8');
  }
  return substr($value, 0, $max);
}

function crm_iot_first_non_empty(array $values): string
{
  foreach ($values as $value) {
    $value = trim((string) $value);
    if ($value !== '') {
      return $value;
    }
  }
  return '';
}

function crm_iot_panel_url(array $configLocal): string
{
  $configured = trim((string) (
    $configLocal['iot_public_url']
    ?? $configLocal['public_url']
    ?? ''
  ));
  if ($configured !== '') {
    return rtrim($configured, '/') . '/';
  }

  $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
  if (strpos($scriptName, '/IoT/crm/') !== false) {
    return '/IoT/';
  }

  return rtrim(crm_public_url('iot'), '/') . '/';
}

function crm_iot_find_user_by_email(PDO $pdo, string $email): ?array
{
  if ($email === '') {
    return null;
  }

  $stmt = $pdo->prepare(
    "SELECT id, cliente_id, nombre, email, rol
     FROM usuarios
     WHERE LOWER(email) = :email
       AND estado = 'ACTIVO'
     LIMIT 1"
  );
  $stmt->execute(['email' => strtolower($email)]);
  $user = $stmt->fetch();
  return $user ?: null;
}

function crm_iot_find_any_user_by_email(PDO $pdo, string $email): ?array
{
  if ($email === '') {
    return null;
  }

  $stmt = $pdo->prepare(
    "SELECT id, cliente_id, nombre, email, rol, estado
     FROM usuarios
     WHERE LOWER(email) = :email
     LIMIT 1"
  );
  $stmt->execute(['email' => strtolower($email)]);
  $user = $stmt->fetch();
  return $user ?: null;
}

function crm_iot_is_admin_role(string $role): bool
{
  return in_array(strtolower(trim($role)), ['admin', 'superadmin'], true);
}

function crm_iot_primary_client_id(PDO $pdo): int
{
  $clientId = (int) ($pdo->query('SELECT id FROM clientes ORDER BY id LIMIT 1')->fetchColumn() ?: 0);
  if ($clientId > 0) {
    return $clientId;
  }

  $stmt = $pdo->prepare(
    'INSERT INTO clientes (nombre_empresa, email, password_hash)
     VALUES (:nombre, :email, :password_hash)'
  );
  $stmt->execute([
    'nombre' => 'ID Industrial',
    'email' => 'admin@idindustrial.com.mx',
    'password_hash' => password_hash(bin2hex(random_bytes(18)), PASSWORD_DEFAULT),
  ]);
  return (int) $pdo->lastInsertId();
}

function crm_iot_promote_admin_user(PDO $pdo, array $user, string $name): array
{
  $stmt = $pdo->prepare(
    "UPDATE usuarios
     SET nombre = :nombre,
         rol = 'ADMIN',
         estado = 'ACTIVO',
         intentos_fallidos = 0,
         bloqueado_hasta = NULL
     WHERE id = :id"
  );
  $stmt->execute([
    'nombre' => $name,
    'id' => (int) $user['id'],
  ]);

  return [
    'id' => (int) $user['id'],
    'cliente_id' => (int) $user['cliente_id'],
    'nombre' => $name,
    'email' => (string) $user['email'],
    'rol' => 'ADMIN',
  ];
}

function crm_iot_ensure_admin_user(PDO $pdo, array $crmUser): array
{
  $crmUserId = (int) ($crmUser['id'] ?? 0);
  $email = crm_iot_normalize_email(
    (string) ($crmUser['email'] ?? ''),
    'crm-admin-' . max(1, $crmUserId) . '@crm.local'
  );
  $name = crm_iot_short_text((string) ($crmUser['name'] ?? ''), 100);
  if ($name === '') {
    $name = 'Administrador CRM ' . max(1, $crmUserId);
  }

  $existing = crm_iot_find_any_user_by_email($pdo, $email);
  if ($existing) {
    return crm_iot_promote_admin_user($pdo, $existing, $name);
  }

  $clienteId = crm_iot_primary_client_id($pdo);
  $stmt = $pdo->prepare(
    "INSERT INTO usuarios
     (cliente_id, nombre, email, password_hash, rol, estado)
     VALUES
     (:cliente_id, :nombre, :email, :password_hash, 'ADMIN', 'ACTIVO')"
  );
  $stmt->execute([
    'cliente_id' => $clienteId,
    'nombre' => $name,
    'email' => $email,
    'password_hash' => password_hash(bin2hex(random_bytes(18)), PASSWORD_DEFAULT),
  ]);

  return [
    'id' => (int) $pdo->lastInsertId(),
    'cliente_id' => $clienteId,
    'nombre' => $name,
    'email' => $email,
    'rol' => 'ADMIN',
  ];
}

function crm_iot_ensure_portal_client(PDO $pdo, array $portalUser): int
{
  $crmClientId = (int) ($portalUser['crm_client_id'] ?? 0);
  if ($crmClientId < 1) {
    crm_iot_sso_fail(409, 'El acceso cliente no esta ligado a un cliente del CRM.');
  }

  $hasCrmClientId = crm_iot_has_column($pdo, 'clientes', 'crm_client_id');
  $company = crm_iot_short_text(
    crm_iot_first_non_empty([$portalUser['client_name'] ?? '', $portalUser['company_name'] ?? '']),
    120
  );
  if ($company === '') {
    $company = 'Cliente CRM ' . $crmClientId;
  }

  $emailSource = crm_iot_first_non_empty([
    $portalUser['contact_email'] ?? '',
    $portalUser['client_contact_email'] ?? '',
    $portalUser['username'] ?? '',
  ]);
  $email = crm_iot_normalize_email($emailSource, 'cliente-' . $crmClientId . '@crm.local');

  $iotClientId = 0;
  if ($hasCrmClientId) {
    $stmt = $pdo->prepare('SELECT id FROM clientes WHERE crm_client_id = :crm_client_id LIMIT 1');
    $stmt->execute(['crm_client_id' => $crmClientId]);
    $iotClientId = (int) ($stmt->fetchColumn() ?: 0);
  }

  if ($iotClientId < 1) {
    $stmt = $pdo->prepare('SELECT id FROM clientes WHERE LOWER(email) = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $iotClientId = (int) ($stmt->fetchColumn() ?: 0);
  }

  if ($iotClientId < 1) {
    $passwordHash = password_hash(bin2hex(random_bytes(18)), PASSWORD_DEFAULT);
    if ($hasCrmClientId) {
      $stmt = $pdo->prepare(
        'INSERT INTO clientes (crm_client_id, nombre_empresa, email, password_hash)
         VALUES (:crm_client_id, :nombre, :email, :password_hash)'
      );
      $stmt->execute([
        'crm_client_id' => $crmClientId,
        'nombre' => $company,
        'email' => $email,
        'password_hash' => $passwordHash,
      ]);
    } else {
      $stmt = $pdo->prepare(
        'INSERT INTO clientes (nombre_empresa, email, password_hash)
         VALUES (:nombre, :email, :password_hash)'
      );
      $stmt->execute([
        'nombre' => $company,
        'email' => $email,
        'password_hash' => $passwordHash,
      ]);
    }
    return (int) $pdo->lastInsertId();
  }

  if ($hasCrmClientId) {
    $stmt = $pdo->prepare(
      'UPDATE clientes
       SET nombre_empresa = :nombre,
           crm_client_id = CASE
             WHEN crm_client_id IS NULL THEN :crm_client_id
             ELSE crm_client_id
           END
       WHERE id = :id'
    );
    $stmt->execute([
      'nombre' => $company,
      'crm_client_id' => $crmClientId,
      'id' => $iotClientId,
    ]);
  } else {
    $stmt = $pdo->prepare('UPDATE clientes SET nombre_empresa = :nombre WHERE id = :id');
    $stmt->execute(['nombre' => $company, 'id' => $iotClientId]);
  }

  return $iotClientId;
}

function crm_iot_ensure_portal_user(PDO $pdo, array $portalUser): array
{
  $iotClientId = crm_iot_ensure_portal_client($pdo, $portalUser);
  $portalUserId = (int) ($portalUser['id'] ?? 0);
  $hasPortalUserId = crm_iot_has_column($pdo, 'usuarios', 'crm_portal_user_id');
  $email = crm_iot_normalize_email(
    crm_iot_first_non_empty([$portalUser['username'] ?? '', $portalUser['contact_email'] ?? '']),
    'portal-' . $portalUserId . '@crm.local'
  );
  $name = crm_iot_short_text(
    crm_iot_first_non_empty([
      $portalUser['contact_name'] ?? '',
      $portalUser['client_name'] ?? '',
      $portalUser['company_name'] ?? '',
    ]),
    100
  );
  if ($name === '') {
    $name = 'Usuario portal ' . $portalUserId;
  }

  $iotUser = null;
  if ($hasPortalUserId && $portalUserId > 0) {
    $stmt = $pdo->prepare(
      'SELECT id, cliente_id, nombre, email, rol
       FROM usuarios
       WHERE crm_portal_user_id = :portal_user_id
       LIMIT 1'
    );
    $stmt->execute(['portal_user_id' => $portalUserId]);
    $iotUser = $stmt->fetch() ?: null;
  }

  if (!$iotUser) {
    $stmt = $pdo->prepare(
      'SELECT id, cliente_id, nombre, email, rol
       FROM usuarios
       WHERE LOWER(email) = :email
       LIMIT 1'
    );
    $stmt->execute(['email' => $email]);
    $iotUser = $stmt->fetch() ?: null;
  }

  if (!$iotUser) {
    $passwordHash = password_hash(bin2hex(random_bytes(18)), PASSWORD_DEFAULT);
    if ($hasPortalUserId) {
      $stmt = $pdo->prepare(
        "INSERT INTO usuarios
         (cliente_id, crm_portal_user_id, nombre, email, password_hash, rol, estado)
         VALUES
         (:cliente_id, :portal_user_id, :nombre, :email, :password_hash, 'ADMIN', 'ACTIVO')"
      );
      $stmt->execute([
        'cliente_id' => $iotClientId,
        'portal_user_id' => $portalUserId > 0 ? $portalUserId : null,
        'nombre' => $name,
        'email' => $email,
        'password_hash' => $passwordHash,
      ]);
    } else {
      $stmt = $pdo->prepare(
        "INSERT INTO usuarios
         (cliente_id, nombre, email, password_hash, rol, estado)
         VALUES
         (:cliente_id, :nombre, :email, :password_hash, 'ADMIN', 'ACTIVO')"
      );
      $stmt->execute([
        'cliente_id' => $iotClientId,
        'nombre' => $name,
        'email' => $email,
        'password_hash' => $passwordHash,
      ]);
    }

    return [
      'id' => (int) $pdo->lastInsertId(),
      'cliente_id' => $iotClientId,
      'nombre' => $name,
      'email' => $email,
      'rol' => 'ADMIN',
    ];
  }

  $params = [
    'cliente_id' => $iotClientId,
    'nombre' => $name,
    'id' => (int) $iotUser['id'],
  ];
  $sql = "UPDATE usuarios
          SET cliente_id = :cliente_id,
              nombre = :nombre,
              rol = 'ADMIN',
              estado = 'ACTIVO',
              intentos_fallidos = 0,
              bloqueado_hasta = NULL";
  if ($hasPortalUserId && $portalUserId > 0) {
    $sql .= ', crm_portal_user_id = :portal_user_id';
    $params['portal_user_id'] = $portalUserId;
  }
  $sql .= ' WHERE id = :id';
  $pdo->prepare($sql)->execute($params);

  return [
    'id' => (int) $iotUser['id'],
    'cliente_id' => $iotClientId,
    'nombre' => $name,
    'email' => (string) $iotUser['email'],
    'rol' => 'ADMIN',
  ];
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

$requestPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
$isPortalRoute = strpos($requestPath, '/crm/portal/iot') !== false;
$contextType = '';
$crmUserId = 0;
$crmRole = '';
$crmEmail = '';
$portalUser = null;
$hasAdminSession = !empty($_SESSION['crm_user']) && is_array($_SESSION['crm_user']);
$hasPortalSession = !empty($_SESSION['bitacora_user']) && is_array($_SESSION['bitacora_user']);

if (($isPortalRoute && $hasPortalSession) || (!$hasAdminSession && $hasPortalSession)) {
  crm_enforce_session_timeout(
    'bitacora_user',
    'bitacora_token',
    crm_portal_url('resumen', 0, ['expired' => 1])
  );
  $portalSession = $_SESSION['bitacora_user'];
  $stmt = $crmPdo->prepare(
    'SELECT
        cpu.id,
        COALESCE(cpu.client_id, o.client_id) AS crm_client_id,
        cpu.username,
        o.company_name,
        o.contact_name,
        o.contact_email,
        c.name AS client_name,
        c.contact_email AS client_contact_email
     FROM client_portal_users cpu
     JOIN opportunities o ON o.id = cpu.opportunity_id
     LEFT JOIN clients c ON c.id = COALESCE(cpu.client_id, o.client_id)
     WHERE cpu.id = :id
       AND cpu.is_active = 1
     LIMIT 1'
  );
  $stmt->execute(['id' => (int) ($portalSession['id'] ?? 0)]);
  $portalUser = $stmt->fetch() ?: null;
  if (!$portalUser) {
    crm_iot_sso_fail(403, 'Tu acceso cliente ya no esta activo.');
  }
  $contextType = 'client';
  $crmUserId = (int) $portalUser['id'];
  $crmRole = 'client_portal';
  $_SESSION['crm_iot_return_area'] = 'portal';
} elseif ($hasAdminSession) {
  crm_enforce_session_timeout(
    'crm_user',
    'crm_token',
    crm_admin_url('dashboard', 0, ['expired' => 1])
  );
  $crmUser = $_SESSION['crm_user'];
  $contextType = 'admin';
  $crmUserId = (int) ($crmUser['id'] ?? 0);
  $crmRole = strtolower(trim((string) ($crmUser['role'] ?? '')));
  $crmEmail = strtolower(trim((string) ($crmUser['email'] ?? '')));
  $_SESSION['crm_iot_return_area'] = 'admin';
} else {
  header('Location: ' . ($isPortalRoute ? crm_portal_url() : crm_admin_url()));
  exit;
}

session_write_close();

// La autenticacion CRM ya comprobo la conexion a la base unificada.
// Reutilizar este PDO evita una segunda conexion dentro del SSO.
$pdo = $crmPdo;
$sharedConfig = crm_config();
$configLocal = is_array($sharedConfig['iot'] ?? null)
  ? $sharedConfig['iot']
  : [];
$iotAuthCandidates = [
  dirname(__DIR__) . '/IoT/api/auth.php',
  dirname(__DIR__) . '/api/auth.php',
  dirname(__DIR__, 2) . '/IoT/api/auth.php',
  dirname(__DIR__, 2) . '/iot/api/auth.php',
];
$iotAuthPath = '';
foreach ($iotAuthCandidates as $candidate) {
  if (is_file($candidate)) {
    $iotAuthPath = $candidate;
    break;
  }
}
if ($iotAuthPath === '') {
  crm_iot_sso_fail(500, 'No se encontro la API IoT para iniciar sesion.');
}
require $iotAuthPath;

crm_iot_require_core_tables($pdo);
$iotUser = null;

if ($contextType === 'client') {
  try {
    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
      $pdo->beginTransaction();
    }
    $iotUser = crm_iot_ensure_portal_user($pdo, $portalUser ?? []);
    if ($startedTransaction) {
      $pdo->commit();
    }
  } catch (Throwable $error) {
    if (isset($startedTransaction) && $startedTransaction && $pdo->inTransaction()) {
      $pdo->rollBack();
    }
    crm_log_event('iot.sso_client_sync_failed', [
      'portal_user_id' => $crmUserId,
      'error_class' => get_class($error),
      'error_message' => substr($error->getMessage(), 0, 500),
    ]);
    crm_iot_sso_fail(500, 'No fue posible preparar tu panel IoT.');
  }
} else {
  $configuredEmail = strtolower(trim((string) ($configLocal['crm_sso_iot_email'] ?? '')));
  $iotEmail = $configuredEmail !== '' ? $configuredEmail : $crmEmail;
  if (crm_iot_is_admin_role($crmRole)) {
    $iotUser = crm_iot_find_any_user_by_email($pdo, $iotEmail);
    if ($iotUser) {
      $iotUser = crm_iot_promote_admin_user($pdo, $iotUser, (string) ($crmUser['name'] ?? $iotUser['nombre'] ?? 'Administrador'));
    }
  } else {
    $iotUser = crm_iot_find_user_by_email($pdo, $iotEmail);
  }

  // A superadministrador CRM puede usar el unico administrador IoT activo.
  // Si hay mas de uno, se exige crm_sso_iot_email para no elegir otro cliente.
  if (!$iotUser && crm_iot_is_admin_role($crmRole)) {
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

  if (!$iotUser && crm_iot_is_admin_role($crmRole)) {
    $iotUser = crm_iot_ensure_admin_user($pdo, $crmUser);
  }
}

if (!$iotUser) {
  crm_log_event('iot.sso_denied', [
    'context' => $contextType,
    'crm_user_id' => $crmUserId,
    'crm_role' => $crmRole,
    'reason' => 'iot_user_mapping_missing_or_ambiguous',
  ]);
  crm_iot_sso_fail(
    403,
    'Tu usuario no tiene un usuario IoT activo asociado. Configura crm/config.php o importa la migracion de enlace CRM-IoT.'
  );
}

// El CRM usa un manejador de sesiones en base de datos. IoT conserva su
// cookie independiente en el manejador nativo para que sus APIs la reconozcan.
if (session_status() !== PHP_SESSION_ACTIVE && session_module_name() === 'user') {
  session_module_name('files');
}

crearSesionUsuario($iotUser);
crm_log_event('iot.sso_success', [
  'context' => $contextType,
  'crm_user_id' => $crmUserId,
  'iot_user_id' => (int) $iotUser['id'],
  'iot_cliente_id' => (int) $iotUser['cliente_id'],
]);

header_remove('Content-Type');
header('Cache-Control: no-store');
header('Location: ' . crm_iot_panel_url($configLocal));
exit;
