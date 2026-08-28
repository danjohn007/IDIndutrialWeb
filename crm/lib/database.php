<?php
declare(strict_types=1);

if (!function_exists('str_starts_with')) {
  function str_starts_with($haystack, $needle): bool
  {
    $needle = (string) $needle;
    return $needle === '' || strpos((string) $haystack, $needle) === 0;
  }
}

function crm_normalize_legacy_url(string $url): string
{
  $normalized = preg_replace('~^(https?://[^/]+)/sistema(?=/|$)~i', '$1', trim($url));
  $normalized = preg_replace('~^/sistema(?=/|$)~i', '', (string) $normalized);
  // Case-sensitive: the real IoT module routes stay lowercase (/iot/...); only the old
  // uppercase /IoT container prefix from the previous deployment layout is stripped here.
  $normalized = preg_replace('~^(https?://[^/]+)/IoT(?=/|$)~', '$1', (string) $normalized);
  $normalized = preg_replace('~^/IoT(?=/|$)~', '', (string) $normalized);
  return (string) $normalized;
}

function crm_config_source(): string
{
  if (is_file(__DIR__ . '/../config.php')) {
    return 'crm/config.php';
  }
  if (is_file(dirname(__DIR__, 2) . '/sistema/crm/config.php')) {
    return 'sistema/crm/config.php';
  }
  return 'defaults';
}


function crm_mask_identifier(string $identifier): string
{
  $identifier = strtolower(trim($identifier));
  if ($identifier === '') {
    return 'empty';
  }
  if (strpos($identifier, '@') !== false) {
    [$local, $domain] = array_pad(explode('@', $identifier, 2), 2, '');
    return substr($local, 0, 2) . '***@' . $domain;
  }
  return substr($identifier, 0, 3) . '***';
}

function crm_log_event(string $event, array $context = []): void
{
  static $requestId = null;
  if ($requestId === null) {
    try {
      $requestId = bin2hex(random_bytes(6));
    } catch (Throwable $error) {
      $requestId = substr(sha1(uniqid('', true)), 0, 12);
    }
  }

  $ip = trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? ''))[0]);
  $uri = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
  $payload = array_merge([
    'time' => date(DATE_ATOM),
    'event' => $event,
    'request_id' => $requestId,
    'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? 'CLI'),
    'uri' => $uri,
    'ip_hash' => $ip !== '' ? substr(hash('sha256', $ip), 0, 12) : 'none',
  ], $context);
  $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  if ($encoded === false) {
    $encoded = json_encode(['time' => date(DATE_ATOM), 'event' => $event, 'request_id' => $requestId]);
  }
  $line = '[IDCRM] ' . $encoded . PHP_EOL;
  $logPath = dirname(__DIR__, 2) . '/error_log';
  if (@file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX) === false) {
    @error_log(rtrim($line));
  }
}

function crm_login_diagnostic_context(PDO $pdo, string $identifier): array
{
  $config = crm_config();
  $driver = crm_driver($pdo);
  return [
    'identifier' => crm_mask_identifier($identifier),
    'driver' => $driver,
    'database' => $driver === 'mysql' ? (string) ($config['database'] ?? '') : crm_db_path(),
    'config_source' => crm_config_source(),
    'app_url' => (string) ($config['app_url'] ?? ''),
    'session_storage' => 'database',
    'session_table' => 'app_sessions',
    'session_table_exists' => crm_table_exists($pdo, 'app_sessions'),
  ];
}

function crm_config(): array
{
  $config = [
    'driver' => 'sqlite',
    'host' => 'localhost',
    'database' => '',
    'username' => '',
    'password' => '',
    'sqlite_path' => '',
    'charset' => 'utf8mb4',
    'app_url' => 'https://idindustrial.com.mx/crm',
    'quote_request_admin_email' => 'tecnologia@idindustrial.com.mx',
    'quote_request_secondary_email' => '',
    'smtp' => [
      'enabled' => false,
      'host' => 'mail.idindustrial.com.mx',
      'port' => 465,
      'secure' => 'ssl',
      'username' => '',
      'password' => '',
      'from_email' => 'no-reply@idindustrial.com.mx',
      'from_name' => 'ID Industrial',
    ],
  ];

  $configPaths = [
    __DIR__ . '/../config.php',
    dirname(__DIR__, 2) . '/sistema/crm/config.php',
  ];
  foreach ($configPaths as $configPath) {
    if (!is_file($configPath)) {
      continue;
    }
    $customConfig = require $configPath;
    if (is_array($customConfig)) {
      $config = array_replace_recursive($config, $customConfig);
    }
    break;
  }

  $config['app_url'] = rtrim(crm_normalize_legacy_url((string) ($config['app_url'] ?? 'https://idindustrial.com.mx/crm')), '/');
  return $config;
}


function crm_default_setting(string $key): string
{
  $config = crm_config();
  $defaults = [
    'quote_request_admin_email' => (string) ($config['quote_request_admin_email'] ?? 'tecnologia@idindustrial.com.mx'),
    'quote_request_secondary_email' => (string) ($config['quote_request_secondary_email'] ?? ''),
  ];
  return $defaults[$key] ?? '';
}

function crm_setting(PDO $pdo, string $key, ?string $default = null): string
{
  $fallback = $default ?? crm_default_setting($key);
  if (!crm_table_exists($pdo, 'crm_settings')) {
    return $fallback;
  }

  $stmt = $pdo->prepare('SELECT setting_value FROM crm_settings WHERE setting_key = ? LIMIT 1');
  $stmt->execute([$key]);
  $value = $stmt->fetchColumn();
  return $value === false ? $fallback : (string) $value;
}

function crm_set_setting(PDO $pdo, string $key, string $value): void
{
  if (crm_driver($pdo) === 'mysql') {
    $stmt = $pdo->prepare('INSERT INTO crm_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP');
  } else {
    $stmt = $pdo->prepare('INSERT INTO crm_settings (setting_key, setting_value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP) ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value, updated_at = CURRENT_TIMESTAMP');
  }
  $stmt->execute([$key, $value]);
}

function crm_quote_request_admin_email(PDO $pdo, string $fallback = 'tecnologia@idindustrial.com.mx'): string
{
  $email = trim(crm_setting($pdo, 'quote_request_admin_email', $fallback));
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $email = $fallback;
  }
  return $email;
}

function crm_quote_request_secondary_email(PDO $pdo): string
{
  $email = trim(crm_setting($pdo, 'quote_request_secondary_email', ''));
  return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
}
function crm_web_base_path(): string
{
  $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
  $crmPosition = strpos($scriptName, '/crm/');
  if ($crmPosition !== false) {
    return rtrim(substr($scriptName, 0, $crmPosition) . '/crm', '/');
  }

  $publicDirectory = trim(str_replace('\\', '/', dirname($scriptName)), '/.');
  return ($publicDirectory !== '' ? '/' . $publicDirectory : '') . '/crm';
}

function crm_build_path(string $base, string $path = '', array $query = [], string $fragment = ''): string
{
  $url = rtrim($base, '/');
  $path = trim($path, '/');
  if ($path !== '') {
    $url .= '/' . $path;
  }
  if ($query) {
    $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
  }
  if ($fragment !== '') {
    $url .= '#' . rawurlencode(ltrim($fragment, '#'));
  }
  return $url !== '' ? $url : '/';
}

function crm_public_url(string $path = '', array $query = [], string $fragment = ''): string
{
  $crmBase = crm_web_base_path();
  $publicBase = substr($crmBase, 0, -4);
  if (trim($path, '/') !== '') {
    return crm_build_path($publicBase, $path, $query, $fragment);
  }

  $url = rtrim($publicBase, '/') . '/';
  if ($query) {
    $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
  }
  if ($fragment !== '') {
    $url .= '#' . rawurlencode(ltrim($fragment, '#'));
  }
  return $url;
}

function crm_admin_url(string $view = 'dashboard', int $id = 0, array $query = [], string $fragment = ''): string
{
  $paths = [
    'dashboard' => '',
    'opportunities' => 'oportunidades',
    'quotes' => 'cotizaciones',
    'calendar' => 'calendario',
    'clients' => 'clientes',
    'prospects' => 'prospectos',
    'bitacora' => 'bitacora',
    'notifications' => 'notificaciones',
    'profile' => 'perfil',
    'settings' => 'configuracion',
    'logout' => 'salir',
    'notification_poll' => 'notificaciones/estado',
  ];
  if ($view === 'opportunity') {
    return crm_build_path(crm_web_base_path(), 'oportunidades/' . max(0, $id), $query, $fragment);
  }
  if ($view === 'quote') {
    return crm_build_path(crm_web_base_path(), 'cotizaciones/' . max(0, $id), $query, $fragment);
  }
  return crm_build_path(crm_web_base_path(), $paths[$view] ?? '', $query, $fragment);
}

function crm_portal_url(string $view = 'resumen', int $projectId = 0, array $query = [], string $fragment = ''): string
{
  $paths = [
    'resumen' => 'portal',
    'proyectos' => 'portal/proyectos',
    'bitacora' => 'portal/bitacora',
    'solicitudes' => 'portal/solicitudes',
    'cotizaciones' => 'portal/cotizaciones',
    'notificaciones' => 'portal/notificaciones',
    'perfil' => 'portal/perfil',
    'iot' => 'portal/iot',
    'logout' => 'portal/salir',
    'notification_poll' => 'portal/notificaciones/estado',
  ];
  if ($projectId > 0) {
    $query['project_id'] = $projectId;
  }
  return crm_build_path(crm_web_base_path(), $paths[$view] ?? $paths['resumen'], $query, $fragment);
}

function crm_evidence_url(int $requestId): string
{
  return crm_build_path(crm_web_base_path(), 'evidencias/' . max(0, $requestId));
}

function crm_quote_attachment_url(int $quoteId, string $type = 'request'): string
{
  $query = $type === 'proposal' ? ['type' => 'proposal'] : [];
  return crm_build_path(crm_web_base_path(), 'archivos-cotizacion/' . max(0, $quoteId), $query);
}

function crm_uses_legacy_php_url(string $filename): bool
{
  if (!in_array(strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')), ['GET', 'HEAD'], true)) {
    return false;
  }
  $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
  return strtolower(basename($path)) === strtolower($filename);
}

function crm_clean_internal_url(?string $url, string $recipientType = 'admin'): string
{
  $url = crm_normalize_legacy_url((string) $url);
  if ($url === '') {
    return $recipientType === 'client' ? crm_portal_url('notificaciones') : crm_admin_url('notifications', 0, [], 'reportes-recibidos');
  }

  $parts = parse_url(html_entity_decode($url, ENT_QUOTES, 'UTF-8'));
  if ($parts === false) {
    return $recipientType === 'client' ? crm_portal_url() : crm_admin_url();
  }
  $path = (string) ($parts['path'] ?? '');
  $file = strtolower(basename($path));
  parse_str((string) ($parts['query'] ?? ''), $query);
  $fragment = (string) ($parts['fragment'] ?? '');

  if ($file === 'index.php') {
    $view = (string) ($query['view'] ?? 'dashboard');
    $id = (int) ($query['id'] ?? 0);
    $isNotifications = $view === 'bitacora' && isset($query['notifications']);
    unset($query['view'], $query['id'], $query['notifications']);
    return crm_admin_url($isNotifications ? 'notifications' : $view, $id, $query, $fragment);
  }
  if ($file === 'cliente.php') {
    $view = (string) ($query['view'] ?? 'resumen');
    $projectId = (int) ($query['project_id'] ?? 0);
    if (isset($query['logout'])) {
      $view = 'logout';
    } elseif (isset($query['notification_poll'])) {
      $view = 'notification_poll';
    }
    unset($query['view'], $query['project_id'], $query['logout'], $query['notification_poll']);
    return crm_portal_url($view, $projectId, $query, $fragment);
  }
  if ($file === 'evidence.php') {
    return crm_evidence_url((int) ($query['id'] ?? 0));
  }

  return $url;
}
function crm_db_path(): string
{
  $configuredPath = trim((string) (crm_config()['sqlite_path'] ?? ''));
  if ($configuredPath !== '') {
    $isAbsolute = preg_match('~^(?:[A-Za-z]:[\\/]|/)~', $configuredPath) === 1;
    return $isAbsolute ? $configuredPath : __DIR__ . '/../' . ltrim($configuredPath, '/\\');
  }

  $currentPath = __DIR__ . '/../data/idindustrial_crm.sqlite';
  $currentConfig = __DIR__ . '/../config.php';
  $legacyPath = dirname(__DIR__, 2) . '/sistema/crm/data/idindustrial_crm.sqlite';
  if (!is_file($currentConfig) && is_file($legacyPath)) {
    return $legacyPath;
  }

  return $currentPath;
}

function crm_driver(?PDO $pdo = null): string
{
  if ($pdo instanceof PDO) {
    return (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
  }

  return (string) crm_config()['driver'];
}

final class CrmDatabaseSessionHandler implements SessionHandlerInterface
{
  private PDO $pdo;
  private int $lifetime;

  public function __construct(PDO $pdo, int $lifetime)
  {
    $this->pdo = $pdo;
    $this->lifetime = max(300, $lifetime);
  }

  #[\ReturnTypeWillChange]
  public function open($path, $name)
  {
    return true;
  }

  #[\ReturnTypeWillChange]
  public function close()
  {
    return true;
  }

  #[\ReturnTypeWillChange]
  public function read($id)
  {
    $stmt = $this->pdo->prepare('SELECT payload FROM app_sessions WHERE session_id = ? AND last_activity >= ? LIMIT 1');
    $stmt->execute([$id, time() - $this->lifetime]);
    $payload = $stmt->fetchColumn();
    return $payload === false ? '' : (string) $payload;
  }

  #[\ReturnTypeWillChange]
  public function write($id, $data)
  {
    if (crm_driver($this->pdo) === 'mysql') {
      $stmt = $this->pdo->prepare('
        INSERT INTO app_sessions (session_id, payload, last_activity)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE payload = VALUES(payload), last_activity = VALUES(last_activity)
      ');
    } else {
      $stmt = $this->pdo->prepare('
        INSERT INTO app_sessions (session_id, payload, last_activity)
        VALUES (?, ?, ?)
        ON CONFLICT(session_id) DO UPDATE SET payload = excluded.payload, last_activity = excluded.last_activity
      ');
    }
    return $stmt->execute([$id, $data, time()]);
  }

  #[\ReturnTypeWillChange]
  public function destroy($id)
  {
    $stmt = $this->pdo->prepare('DELETE FROM app_sessions WHERE session_id = ?');
    return $stmt->execute([$id]);
  }

  #[\ReturnTypeWillChange]
  public function gc($max_lifetime)
  {
    $stmt = $this->pdo->prepare('DELETE FROM app_sessions WHERE last_activity < ?');
    $stmt->execute([time() - max($this->lifetime, $max_lifetime)]);
    return $stmt->rowCount();
  }
}

function crm_start_database_session(PDO $pdo): void
{
  static $handler = null;
  if (session_status() === PHP_SESSION_ACTIVE) {
    return;
  }

  $lifetime = (int) crm_login_settings()['session_timeout'];
  $handler = new CrmDatabaseSessionHandler($pdo, $lifetime);
  session_name('IDINDUSTRIAL_CRM');
  session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
  session_set_save_handler($handler, true);
  if (!session_start()) {
    throw new RuntimeException('No se pudo iniciar la sesion respaldada por la base de datos.');
  }
}

function crm_db(): PDO
{
  static $pdo = null;
  if ($pdo instanceof PDO) {
    return $pdo;
  }

  $config = crm_config();
  if (($config['driver'] ?? 'sqlite') === 'mysql') {
    $charset = $config['charset'] ?: 'utf8mb4';
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $config['host'], $config['database'], $charset);
    $pdo = new PDO($dsn, (string) $config['username'], (string) $config['password']);
  } else {
    $dataDir = dirname(crm_db_path());
    if (!is_dir($dataDir)) {
      mkdir($dataDir, 0755, true);
    }
    $pdo = new PDO('sqlite:' . crm_db_path());
  }

  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

  if (crm_driver($pdo) === 'sqlite') {
    $pdo->exec('PRAGMA foreign_keys = ON');
  }

  crm_migrate($pdo);
  crm_seed($pdo);
  return $pdo;
}

function crm_migrate(PDO $pdo): void
{
  if (crm_driver($pdo) === 'mysql') {
    crm_migrate_mysql($pdo);
  } else {
    crm_migrate_sqlite($pdo);
  }

  crm_ensure_columns($pdo);
  crm_sync_portal_client_links($pdo);
  crm_normalize_client_lifecycle($pdo);
  crm_normalize_notification_urls($pdo);
  crm_apply_data_migrations($pdo);
}

function crm_apply_data_migrations(PDO $pdo): void
{
  $isMysql = crm_driver($pdo) === 'mysql';
  $pdo->exec($isMysql
    ? 'CREATE TABLE IF NOT EXISTS app_migrations (migration_key VARCHAR(190) NOT NULL PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    : 'CREATE TABLE IF NOT EXISTS app_migrations (migration_key TEXT PRIMARY KEY, applied_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');

  $hasMigration = $pdo->prepare('SELECT COUNT(*) FROM app_migrations WHERE migration_key = ?');
  $recordMigration = $pdo->prepare('INSERT INTO app_migrations (migration_key) VALUES (?)');

  $runMigration = function (string $migrationKey, callable $callback) use ($pdo, $hasMigration, $recordMigration): void {
    $hasMigration->execute([$migrationKey]);
    if ((int) $hasMigration->fetchColumn() > 0) {
      return;
    }

    $pdo->beginTransaction();
    try {
      $callback();
      $recordMigration->execute([$migrationKey]);
      $pdo->commit();
    } catch (Throwable $error) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      throw $error;
    }
  };

  $runMigration('2026_08_existing_contacts_are_clients', function () use ($pdo): void {
    $pdo->exec("UPDATE clients SET lifecycle_stage = 'Cliente', segment = CASE WHEN segment = 'Prospecto' THEN 'Industrial' ELSE segment END, converted_at = COALESCE(converted_at, CURRENT_TIMESTAMP)");
  });

  $runMigration('2026_08_quote_request_admin_email_setting', function () use ($pdo): void {
    $email = trim(crm_default_setting('quote_request_admin_email'));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $email = 'tecnologia@idindustrial.com.mx';
    }
    crm_set_setting($pdo, 'quote_request_admin_email', $email);
  });

  $runMigration('2026_08_quote_request_secondary_email_setting', function () use ($pdo): void {
    $email = trim(crm_default_setting('quote_request_secondary_email'));
    crm_set_setting($pdo, 'quote_request_secondary_email', filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '');
  });
}

function crm_normalize_notification_urls(PDO $pdo): void
{
  if (!crm_table_exists($pdo, 'notifications')) {
    return;
  }

  $rows = $pdo->query("SELECT id, target_url FROM notifications WHERE target_url LIKE '%/sistema%' OR target_url LIKE '%/IoT%'")->fetchAll();
  if (!$rows) {
    return;
  }

  $update = $pdo->prepare('UPDATE notifications SET target_url = ? WHERE id = ?');
  foreach ($rows as $row) {
    $normalizedUrl = crm_normalize_legacy_url((string) ($row['target_url'] ?? ''));
    if ($normalizedUrl !== (string) ($row['target_url'] ?? '')) {
      $update->execute([$normalizedUrl, (int) $row['id']]);
    }
  }
}
function crm_sync_portal_client_links(PDO $pdo): void
{
  if (!crm_column_exists($pdo, 'client_portal_users', 'client_id')) {
    return;
  }

  $pdo->exec('UPDATE client_portal_users SET client_id = (SELECT client_id FROM opportunities WHERE opportunities.id = client_portal_users.opportunity_id) WHERE client_id IS NULL');
}

function crm_normalize_client_lifecycle(PDO $pdo): void
{
  if (!crm_table_exists($pdo, 'clients') || !crm_column_exists($pdo, 'clients', 'lifecycle_stage')) {
    return;
  }

  $stmt = $pdo->prepare("
    UPDATE clients
    SET lifecycle_stage = ?,
        segment = CASE WHEN segment = ? THEN ? ELSE segment END,
        converted_at = COALESCE(converted_at, CURRENT_TIMESTAMP)
    WHERE (lifecycle_stage IS NULL OR lifecycle_stage <> ?)
      AND (
        is_public = 1
        OR EXISTS (
          SELECT 1
          FROM opportunities o
          WHERE o.client_id = clients.id
            AND o.status IN (?, ?, ?, ?)
        )
        OR EXISTS (
          SELECT 1
          FROM client_portal_users cpu
          LEFT JOIN opportunities po ON po.id = cpu.opportunity_id
          WHERE cpu.client_id = clients.id OR po.client_id = clients.id
        )
      )
  ");
  $stmt->execute([
    'Cliente',
    'Prospecto',
    'Industrial',
    'Cliente',
    'Proyecto ganado',
    'Proyecto iniciado',
    'Proyecto entregado',
    'Cotizacion aprobada',
  ]);
}
function crm_table_exists(PDO $pdo, string $table): bool
{
  if (crm_driver($pdo) === 'mysql') {
    $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
    $stmt->execute([$table]);
    return (bool) $stmt->fetch();
  }

  $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?");
  $stmt->execute([$table]);
  return (bool) $stmt->fetch();
}

function crm_column_exists(PDO $pdo, string $table, string $column): bool
{
  if (crm_driver($pdo) === 'mysql') {
    $stmt = $pdo->prepare("SHOW COLUMNS FROM {$table} LIKE ?");
    $stmt->execute([$column]);
    return (bool) $stmt->fetch();
  }

  $stmt = $pdo->query("PRAGMA table_info({$table})");
  foreach ($stmt->fetchAll() as $field) {
    if (($field['name'] ?? '') === $column) {
      return true;
    }
  }
  return false;
}

function crm_index_exists(PDO $pdo, string $table, string $index): bool
{
  if (crm_driver($pdo) !== 'mysql' || !crm_table_exists($pdo, $table)) {
    return false;
  }

  try {
    $stmt = $pdo->prepare("SHOW INDEX FROM {$table} WHERE Key_name = ?");
    $stmt->execute([$index]);
    return (bool) $stmt->fetch();
  } catch (Throwable $error) {
    error_log('CRM index check failed: ' . $error->getMessage());
    return false;
  }
}

function crm_create_notification(PDO $pdo, array $data): void
{
  $stmt = $pdo->prepare('
    INSERT INTO notifications
      (recipient_type, recipient_user_id, portal_user_id, opportunity_id, client_request_id, event_type, title, message, target_url)
    VALUES
      (?, ?, ?, ?, ?, ?, ?, ?, ?)
  ');
  $stmt->execute([
    (string) ($data['recipient_type'] ?? 'admin'),
    isset($data['recipient_user_id']) ? (int) $data['recipient_user_id'] : null,
    isset($data['portal_user_id']) ? (int) $data['portal_user_id'] : null,
    isset($data['opportunity_id']) ? (int) $data['opportunity_id'] : null,
    isset($data['client_request_id']) ? (int) $data['client_request_id'] : null,
    (string) ($data['event_type'] ?? 'general'),
    substr((string) ($data['title'] ?? 'Notificacion'), 0, 190),
    (string) ($data['message'] ?? ''),
    (string) ($data['target_url'] ?? ''),
  ]);
}

function crm_enqueue_quote_push_notifications(
  PDO $pdo,
  int $opportunityId,
  string $companyName,
  string $service,
  string $crmUrl
): int {
  if ($opportunityId < 1) {
    return 0;
  }

  $stmtTokens = $pdo->query("
    SELECT DISTINCT mp.id AS push_token_id, u.cliente_id
    FROM moviles_push mp
    INNER JOIN usuarios u ON u.id = mp.usuario_id
    WHERE u.rol = 'ADMIN'
      AND u.estado = 'ACTIVO'
      AND mp.activo = 1
  ");
  $tokens = $stmtTokens->fetchAll();
  if ($tokens === []) {
    return 0;
  }

  $companyName = trim($companyName) !== '' ? trim($companyName) : 'Un cliente';
  $service = trim($service) !== '' ? trim($service) : 'servicio por definir';
  $title = 'Nueva solicitud de cotizacion';
  $body = $companyName . ' solicito ' . $service . '.';
  $dedupeKey = hash('sha256', 'COTIZACION:' . $opportunityId);
  $payload = json_encode([
    'tipo' => 'COTIZACION',
    'opportunityId' => $opportunityId,
    'opportunity_id' => $opportunityId,
    'url' => $crmUrl,
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  if ($payload === false) {
    throw new RuntimeException('No fue posible generar el payload de la cotizacion');
  }

  $stmtInsert = $pdo->prepare("
    INSERT IGNORE INTO notificaciones_push
      (alerta_id, origen_tipo, dedupe_key, push_token_id, cliente_id,
       titulo, cuerpo, payload_json, estado, intentos, disponible_en)
    VALUES
      (NULL, 'COTIZACION', ?, ?, ?, ?, ?, ?, 'PENDIENTE', 0, UTC_TIMESTAMP())
  ");
  $enqueued = 0;
  foreach ($tokens as $token) {
    $stmtInsert->execute([
      $dedupeKey,
      (int) $token['push_token_id'],
      (int) $token['cliente_id'],
      substr($title, 0, 120),
      substr($body, 0, 255),
      $payload,
    ]);
    $enqueued += $stmtInsert->rowCount();
  }

  return $enqueued;
}

function crm_dispatch_quote_push_notifications(PDO $pdo, int $opportunityId): array
{
  if ($opportunityId < 1) {
    return ['ok' => true, 'procesadas' => 0, 'enviadas' => 0];
  }

  if (!function_exists('idindPushEnviarFilas')) {
    $pushLibraries = [
      dirname(__DIR__, 2) . '/IoT/api/lib/push_notificaciones.php',
      dirname(__DIR__, 2) . '/IoT/backend-cpanel/api/lib/push_notificaciones.php',
    ];
    foreach ($pushLibraries as $pushLibrary) {
      if (is_file($pushLibrary)) {
        require_once $pushLibrary;
        break;
      }
    }
  }

  if (!function_exists('idindPushTomarPendientes') || !function_exists('idindPushEnviarFilas')) {
    throw new RuntimeException('No se encontro el emisor de notificaciones push de IoT');
  }

  $dedupeKey = hash('sha256', 'COTIZACION:' . $opportunityId);
  $pendientes = idindPushTomarPendientes($pdo, 50, $dedupeKey);
  $config = crm_config();
  $iotConfig = is_array($config['iot'] ?? null) ? $config['iot'] : [];

  return idindPushEnviarFilas($pdo, $pendientes, $iotConfig, 3, 8);
}

function crm_notification_scope(string $recipientType, ?int $portalUserId = null): array
{
  $where = ['n.recipient_type = ?'];
  $params = [$recipientType];
  if ($recipientType === 'client') {
    $where[] = 'n.portal_user_id = ?';
    $params[] = (int) $portalUserId;
  }
  return [$where, $params];
}

function crm_unread_notification_count(PDO $pdo, string $recipientType, ?int $portalUserId = null): int
{
  [$where, $params] = crm_notification_scope($recipientType, $portalUserId);
  $where[] = 'n.is_read = 0';
  $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications n WHERE ' . implode(' AND ', $where));
  $stmt->execute($params);
  return (int) $stmt->fetchColumn();
}

function crm_recent_notifications(PDO $pdo, string $recipientType, ?int $portalUserId = null, int $limit = 20): array
{
  [$where, $params] = crm_notification_scope($recipientType, $portalUserId);
  $limit = max(1, min(60, $limit));
  $stmt = $pdo->prepare('
    SELECT n.*, cr.title AS request_title, cr.message AS request_message, cr.status AS request_status, cr.priority AS request_priority, cr.category AS request_category, cr.location AS request_location, cr.equipment AS request_equipment, cr.impact AS request_impact, cr.occurred_at AS request_occurred_at, cr.actions_taken AS request_actions_taken, cr.evidence_path AS request_evidence_path, cr.evidence_original_name AS request_evidence_name, o.company_name, o.service
    FROM notifications n
    LEFT JOIN client_requests cr ON cr.id = n.client_request_id
    LEFT JOIN opportunities o ON o.id = n.opportunity_id
    WHERE ' . implode(' AND ', $where) . '
    ORDER BY n.is_read ASC, n.created_at DESC, n.id DESC
    LIMIT ' . $limit
  );
  $stmt->execute($params);
  return $stmt->fetchAll();
}

function crm_mark_notification_read(PDO $pdo, int $notificationId, string $recipientType, ?int $portalUserId = null): void
{
  [$where, $params] = crm_notification_scope($recipientType, $portalUserId);
  $where[] = 'n.id = ?';
  $where = array_map(static fn(string $clause): string => str_replace('n.', '', $clause), $where);
  $params[] = $notificationId;
  $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1, read_at = CURRENT_TIMESTAMP WHERE ' . implode(' AND ', $where));
  $stmt->execute($params);
}

function crm_mark_all_notifications_read(PDO $pdo, string $recipientType, ?int $portalUserId = null): void
{
  [$where, $params] = crm_notification_scope($recipientType, $portalUserId);
  $where[] = 'n.is_read = 0';
  $where = array_map(static fn(string $clause): string => str_replace('n.', '', $clause), $where);
  $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1, read_at = CURRENT_TIMESTAMP WHERE ' . implode(' AND ', $where));
  $stmt->execute($params);
}

function crm_store_request_evidence(?array $file): array
{
  if (!$file || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    return ['path' => null, 'name' => null, 'mime' => null, 'size' => null];
  }

  $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
  if ($error !== UPLOAD_ERR_OK) {
    throw new RuntimeException('No se pudo recibir la fotografia. Intenta nuevamente.');
  }

  $size = (int) ($file['size'] ?? 0);
  if ($size <= 0 || $size > 5 * 1024 * 1024) {
    throw new RuntimeException('La evidencia debe pesar menos de 5 MB.');
  }

  $temporaryPath = (string) ($file['tmp_name'] ?? '');
  if ($temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
    throw new RuntimeException('La evidencia recibida no es un archivo valido.');
  }

  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime = (string) $finfo->file($temporaryPath);
  $extensions = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
  ];
  $imageInfo = @getimagesize($temporaryPath);
  if (!isset($extensions[$mime]) || !$imageInfo) {
    throw new RuntimeException('Usa una fotografia JPG, PNG o WEBP.');
  }
  if (($imageInfo[0] ?? 0) < 1 || ($imageInfo[1] ?? 0) < 1 || ($imageInfo[0] ?? 0) > 8000 || ($imageInfo[1] ?? 0) > 8000) {
    throw new RuntimeException('La fotografia no es valida o sus dimensiones son demasiado grandes.');
  }

  $directory = dirname(__DIR__) . '/data/request-evidence';
  if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
    throw new RuntimeException('No se pudo preparar el almacenamiento de evidencias.');
  }

  $fileName = bin2hex(random_bytes(20)) . '.' . $extensions[$mime];
  $destination = $directory . '/' . $fileName;
  if (!move_uploaded_file($temporaryPath, $destination)) {
    throw new RuntimeException('No se pudo guardar la fotografia de evidencia.');
  }

  $originalName = trim((string) ($file['name'] ?? 'evidencia.' . $extensions[$mime]));
  $originalName = preg_replace('/[^a-zA-Z0-9._ -]/', '_', basename($originalName)) ?: 'evidencia.' . $extensions[$mime];

  return [
    'path' => 'request-evidence/' . $fileName,
    'name' => substr($originalName, 0, 190),
    'mime' => $mime,
    'size' => $size,
  ];
}

function crm_store_quote_attachment(?array $file): array
{
  if (!$file || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    return ['path' => null, 'name' => null, 'mime' => null, 'size' => null];
  }

  if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    throw new RuntimeException('No se pudo recibir el archivo tecnico. Intenta nuevamente.');
  }

  $size = (int) ($file['size'] ?? 0);
  if ($size <= 0 || $size > 8 * 1024 * 1024) {
    throw new RuntimeException('El archivo tecnico debe pesar menos de 8 MB.');
  }

  $temporaryPath = (string) ($file['tmp_name'] ?? '');
  if ($temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
    throw new RuntimeException('El archivo recibido no es valido.');
  }

  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime = (string) $finfo->file($temporaryPath);
  $extensions = [
    'application/pdf' => 'pdf',
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
  ];
  if (!isset($extensions[$mime])) {
    throw new RuntimeException('Adjunta un archivo PDF, JPG, PNG o WEBP.');
  }

  if (str_starts_with($mime, 'image/')) {
    $imageInfo = @getimagesize($temporaryPath);
    if (!$imageInfo || ($imageInfo[0] ?? 0) > 8000 || ($imageInfo[1] ?? 0) > 8000) {
      throw new RuntimeException('La imagen no es valida o sus dimensiones son demasiado grandes.');
    }
  }

  $directory = dirname(__DIR__) . '/data/quote-attachments';
  if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
    throw new RuntimeException('No se pudo preparar el almacenamiento de archivos tecnicos.');
  }

  $fileName = bin2hex(random_bytes(20)) . '.' . $extensions[$mime];
  $destination = $directory . '/' . $fileName;
  if (!move_uploaded_file($temporaryPath, $destination)) {
    throw new RuntimeException('No se pudo guardar el archivo tecnico.');
  }

  $originalName = trim((string) ($file['name'] ?? 'archivo.' . $extensions[$mime]));
  $originalName = preg_replace('/[^a-zA-Z0-9._ -]/', '_', basename($originalName)) ?: 'archivo.' . $extensions[$mime];

  return [
    'path' => 'quote-attachments/' . $fileName,
    'name' => substr($originalName, 0, 190),
    'mime' => $mime,
    'size' => $size,
  ];
}

function crm_store_quote_proposal(?array $file): array
{
  if (!$file || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    return ['path' => null, 'name' => null, 'mime' => null, 'size' => null];
  }
  if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    throw new RuntimeException('No se pudo recibir el PDF de propuesta.');
  }
  $size = (int) ($file['size'] ?? 0);
  if ($size <= 0 || $size > 12 * 1024 * 1024) {
    throw new RuntimeException('El PDF de propuesta debe pesar menos de 12 MB.');
  }
  $temporaryPath = (string) ($file['tmp_name'] ?? '');
  if ($temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
    throw new RuntimeException('El PDF recibido no es valido.');
  }
  $finfo = new finfo(FILEINFO_MIME_TYPE);
  if ((string) $finfo->file($temporaryPath) !== 'application/pdf') {
    throw new RuntimeException('La propuesta debe adjuntarse en formato PDF.');
  }
  $directory = dirname(__DIR__) . '/data/quote-proposals';
  if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
    throw new RuntimeException('No se pudo preparar el almacenamiento de propuestas.');
  }
  $fileName = bin2hex(random_bytes(20)) . '.pdf';
  if (!move_uploaded_file($temporaryPath, $directory . '/' . $fileName)) {
    throw new RuntimeException('No se pudo guardar el PDF de propuesta.');
  }
  $originalName = preg_replace('/[^a-zA-Z0-9._ -]/', '_', basename((string) ($file['name'] ?? 'propuesta.pdf'))) ?: 'propuesta.pdf';
  return ['path' => 'quote-proposals/' . $fileName, 'name' => substr($originalName, 0, 190), 'mime' => 'application/pdf', 'size' => $size];
}

function crm_delete_quote_attachment(?string $relativePath): void
{
  $relativePath = trim((string) $relativePath);
  if ($relativePath === '') {
    return;
  }

  $directoryName = str_starts_with(str_replace('\\', '/', $relativePath), 'quote-proposals/')
    ? 'quote-proposals'
    : 'quote-attachments';
  $baseDirectory = realpath(dirname(__DIR__) . '/data/' . $directoryName);
  $filePath = realpath(dirname(__DIR__) . '/data/' . ltrim(str_replace('\\', '/', $relativePath), '/'));
  if ($baseDirectory && $filePath && str_starts_with($filePath, $baseDirectory . DIRECTORY_SEPARATOR) && is_file($filePath)) {
    @unlink($filePath);
  }
}

function crm_output_quote_attachment(PDO $pdo, int $quoteId, ?int $portalUserId = null, string $type = 'request'): void
{
  $isProposal = $type === 'proposal';
  $pathColumn = $isProposal ? 'proposal_path' : 'attachment_path';
  $nameColumn = $isProposal ? 'proposal_original_name' : 'attachment_original_name';
  $mimeColumn = $isProposal ? 'proposal_mime' : 'attachment_mime';

  $sql = 'SELECT q.' . $pathColumn . ' AS file_path, q.' . $nameColumn . ' AS file_name, q.' . $mimeColumn . ' AS file_mime FROM quotes q JOIN opportunities o ON o.id = q.opportunity_id';
  $params = [$quoteId];
  if ($portalUserId !== null) {
    $sql .= ' JOIN client_portal_users cpu ON cpu.client_id = o.client_id AND cpu.id = ? AND cpu.is_active = 1';
    array_unshift($params, $portalUserId);
  }
  $sql .= ' WHERE q.id = ?';
  if ($portalUserId !== null) {
    $sql .= $isProposal
      ? ' AND q.visible_to_client = 1'
      : ' AND (q.visible_to_client = 1 OR q.requested_by_portal_user_id IS NOT NULL)';
  }
  $sql .= ' LIMIT 1';

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $file = $stmt->fetch();
  if (!$file || empty($file['file_path'])) {
    http_response_code(404);
    exit('Archivo de cotizacion no encontrado.');
  }

  $directoryName = $isProposal ? 'quote-proposals' : 'quote-attachments';
  $baseDirectory = realpath(dirname(__DIR__) . '/data/' . $directoryName);
  $filePath = realpath(dirname(__DIR__) . '/data/' . ltrim(str_replace('\\', '/', (string) $file['file_path']), '/'));
  if (!$baseDirectory || !$filePath || !str_starts_with($filePath, $baseDirectory . DIRECTORY_SEPARATOR) || !is_file($filePath)) {
    http_response_code(404);
    exit('Archivo de cotizacion no disponible.');
  }

  $mime = (string) ($file['file_mime'] ?: 'application/octet-stream');
  $name = preg_replace('/[^a-zA-Z0-9._ -]/', '_', (string) ($file['file_name'] ?: basename($filePath))) ?: 'archivo-cotizacion';
  header('Content-Type: ' . $mime);
  header('Content-Length: ' . filesize($filePath));
  header('Content-Disposition: inline; filename="' . addcslashes($name, '"\\') . '"');
  header('X-Content-Type-Options: nosniff');
  header('Cache-Control: private, no-store');
  readfile($filePath);
  exit;
}
function crm_delete_request_evidence(?string $relativePath): void
{
  $relativePath = trim((string) $relativePath);
  if ($relativePath === '') {
    return;
  }

  $baseDirectory = realpath(dirname(__DIR__) . '/data/request-evidence');
  $filePath = realpath(dirname(__DIR__) . '/data/' . ltrim(str_replace('\\', '/', $relativePath), '/'));
  if ($baseDirectory && $filePath && str_starts_with($filePath, $baseDirectory . DIRECTORY_SEPARATOR) && is_file($filePath)) {
    @unlink($filePath);
  }
}

function crm_output_request_evidence(PDO $pdo, int $requestId, ?int $portalUserId = null): void
{
  $sql = 'SELECT evidence_path, evidence_original_name, evidence_mime, evidence_size FROM client_requests WHERE id = ?';
  $params = [$requestId];
  if ($portalUserId !== null) {
    $sql .= ' AND portal_user_id = ?';
    $params[] = $portalUserId;
  }
  $sql .= ' LIMIT 1';

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $evidence = $stmt->fetch();
  if (!$evidence || empty($evidence['evidence_path'])) {
    http_response_code(404);
    exit('Evidencia no encontrada.');
  }

  $baseDirectory = realpath(dirname(__DIR__) . '/data/request-evidence');
  $filePath = realpath(dirname(__DIR__) . '/data/' . ltrim(str_replace('\\', '/', (string) $evidence['evidence_path']), '/'));
  if (!$baseDirectory || !$filePath || !str_starts_with($filePath, $baseDirectory . DIRECTORY_SEPARATOR) || !is_file($filePath)) {
    http_response_code(404);
    exit('Evidencia no encontrada.');
  }

  $mime = (string) ($evidence['evidence_mime'] ?: 'application/octet-stream');
  if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
    http_response_code(415);
    exit('Tipo de evidencia no permitido.');
  }

  $name = preg_replace('/[^a-zA-Z0-9._ -]/', '_', (string) ($evidence['evidence_original_name'] ?: basename($filePath))) ?: 'evidencia';
  header('Content-Type: ' . $mime);
  header('Content-Length: ' . filesize($filePath));
  header('Content-Disposition: inline; filename="' . $name . '"');
  header('Cache-Control: private, max-age=300');
  header('X-Content-Type-Options: nosniff');
  readfile($filePath);
  exit;
}
function crm_login_settings(): array
{
  return [
    'max_attempts' => 5,
    'lock_minutes' => 10,
    'session_timeout' => 900,
  ];
}

function crm_client_ip(): string
{
  foreach (['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
    $value = trim((string) ($_SERVER[$key] ?? ''));
    if ($value === '') {
      continue;
    }
    $ip = trim(explode(',', $value)[0]);
    return substr($ip, 0, 64);
  }
  return 'unknown';
}

function crm_login_identifier(string $identifier): string
{
  $identifier = strtolower(trim($identifier));
  return $identifier !== '' ? substr($identifier, 0, 190) : 'anonimo';
}

function crm_login_attempt_row(PDO $pdo, string $area, string $identifier, string $ip): ?array
{
  $stmt = $pdo->prepare('SELECT * FROM login_attempts WHERE area = ? AND identifier_hash = ? AND ip_address = ? LIMIT 1');
  $stmt->execute([$area, hash('sha256', $identifier), $ip]);
  $row = $stmt->fetch();
  return $row ?: null;
}

function crm_login_lock_status(PDO $pdo, string $area, string $identifier): array
{
  $identifier = crm_login_identifier($identifier);
  $ip = crm_client_ip();
  $row = crm_login_attempt_row($pdo, $area, $identifier, $ip);
  if (!$row) {
    return ['locked' => false, 'seconds' => 0, 'attempts' => 0];
  }

  $lockedUntil = strtotime((string) ($row['locked_until'] ?? '')) ?: 0;
  if ($lockedUntil > time()) {
    return ['locked' => true, 'seconds' => $lockedUntil - time(), 'attempts' => (int) ($row['attempts'] ?? 0)];
  }

  if ($lockedUntil > 0) {
    $stmt = $pdo->prepare('UPDATE login_attempts SET attempts = 0, locked_until = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
    $stmt->execute([(int) $row['id']]);
    return ['locked' => false, 'seconds' => 0, 'attempts' => 0];
  }

  return ['locked' => false, 'seconds' => 0, 'attempts' => (int) ($row['attempts'] ?? 0)];
}

function crm_record_login_failure(PDO $pdo, string $area, string $identifier): array
{
  $settings = crm_login_settings();
  $identifier = crm_login_identifier($identifier);
  $ip = crm_client_ip();
  $row = crm_login_attempt_row($pdo, $area, $identifier, $ip);
  $attempts = $row ? ((int) $row['attempts'] + 1) : 1;
  $lockedUntil = null;
  if ($attempts >= (int) $settings['max_attempts']) {
    $lockedUntil = date('Y-m-d H:i:s', time() + ((int) $settings['lock_minutes'] * 60));
  }

  if ($row) {
    $stmt = $pdo->prepare('UPDATE login_attempts SET identifier = ?, attempts = ?, locked_until = ?, last_attempt_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
    $stmt->execute([$identifier, $attempts, $lockedUntil, (int) $row['id']]);
  } else {
    $stmt = $pdo->prepare('INSERT INTO login_attempts (area, identifier, identifier_hash, ip_address, attempts, locked_until, last_attempt_at) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)');
    $stmt->execute([$area, $identifier, hash('sha256', $identifier), $ip, $attempts, $lockedUntil]);
  }

  return [
    'locked' => $lockedUntil !== null,
    'seconds' => $lockedUntil !== null ? max(1, strtotime($lockedUntil) - time()) : 0,
    'attempts' => $attempts,
    'remaining' => max(0, (int) $settings['max_attempts'] - $attempts),
  ];
}

function crm_record_login_success(PDO $pdo, string $area, string $identifier): void
{
  $identifier = crm_login_identifier($identifier);
  $stmt = $pdo->prepare('DELETE FROM login_attempts WHERE area = ? AND identifier_hash = ? AND ip_address = ?');
  $stmt->execute([$area, hash('sha256', $identifier), crm_client_ip()]);
}

function crm_clear_login_failures(PDO $pdo, string $area, string $identifier): void
{
  $identifier = crm_login_identifier($identifier);
  $stmt = $pdo->prepare('DELETE FROM login_attempts WHERE area = ? AND identifier_hash = ?');
  $stmt->execute([$area, hash('sha256', $identifier)]);
}

function crm_login_lock_message(array $status): string
{
  $minutes = max(1, (int) ceil(((int) ($status['seconds'] ?? 0)) / 60));
  return 'Demasiados intentos fallidos. Intenta nuevamente en ' . $minutes . ' min.';
}

function crm_login_attempt_message(string $message, array $status): string
{
  if (!empty($status['locked'])) {
    return crm_login_lock_message($status);
  }
  $remaining = (int) ($status['remaining'] ?? 0);
  return $remaining > 0
    ? $message . ' Te quedan ' . $remaining . ' intento(s).'
    : $message;
}

function crm_login_failure_message(array $status, string $message = 'Credenciales incorrectas.'): string
{
  return crm_login_attempt_message($message, $status);
}
function crm_refresh_math_challenge(string $key): array
{
  $a = random_int(1, 9);
  $b = random_int(1, 9);
  $_SESSION[$key] = ['a' => $a, 'b' => $b, 'answer' => $a + $b];
  return $_SESSION[$key];
}

function crm_math_challenge(string $key): array
{
  if (empty($_SESSION[$key]) || !is_array($_SESSION[$key])) {
    return crm_refresh_math_challenge($key);
  }
  return $_SESSION[$key];
}

function crm_validate_math_challenge(string $key, string $answer): bool
{
  $challenge = $_SESSION[$key] ?? null;
  if (!is_array($challenge) || trim($answer) === '') {
    return false;
  }
  return (int) trim($answer) === (int) ($challenge['answer'] ?? -1);
}

function crm_expire_iot_session_cookie(): void
{
  $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
  setcookie('IDINDSESSID', '', [
    'expires' => time() - 42000,
    'path' => '/',
    'domain' => '',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
}
function crm_enforce_session_timeout(string $sessionKey, string $tokenKey, string $redirect): void
{
  if (empty($_SESSION[$sessionKey])) {
    return;
  }

  $timeout = (int) crm_login_settings()['session_timeout'];
  $activityKey = $sessionKey . '_last_activity';
  $lastActivity = (int) ($_SESSION[$activityKey] ?? time());
  if ((time() - $lastActivity) > $timeout) {
    if ($sessionKey === 'crm_user') {
      crm_expire_iot_session_cookie();
    }
    unset($_SESSION[$sessionKey], $_SESSION[$tokenKey], $_SESSION[$activityKey]);
    session_regenerate_id(true);
    header('Location: ' . $redirect);
    exit;
  }

  $_SESSION[$activityKey] = time();
}
function crm_ensure_columns(PDO $pdo): void
{
  $isMysql = crm_driver($pdo) === 'mysql';
  $columns = [
    'opportunities' => [
      'request_type' => $isMysql
        ? 'ALTER TABLE opportunities ADD COLUMN request_type VARCHAR(40) NULL AFTER service'
        : 'ALTER TABLE opportunities ADD COLUMN request_type TEXT NULL',
      'project_location' => $isMysql
        ? 'ALTER TABLE opportunities ADD COLUMN project_location VARCHAR(160) NULL AFTER request_type'
        : 'ALTER TABLE opportunities ADD COLUMN project_location TEXT NULL',
      'desired_execution_date' => $isMysql
        ? 'ALTER TABLE opportunities ADD COLUMN desired_execution_date DATE NULL AFTER project_location'
        : 'ALTER TABLE opportunities ADD COLUMN desired_execution_date TEXT NULL',
    ],
    'clients' => [
      'lifecycle_stage' => $isMysql
        ? "ALTER TABLE clients ADD COLUMN lifecycle_stage VARCHAR(40) NOT NULL DEFAULT 'Cliente' AFTER segment"
        : "ALTER TABLE clients ADD COLUMN lifecycle_stage TEXT NOT NULL DEFAULT 'Cliente'",
      'converted_at' => $isMysql
        ? 'ALTER TABLE clients ADD COLUMN converted_at DATETIME NULL AFTER created_at'
        : 'ALTER TABLE clients ADD COLUMN converted_at TEXT NULL',
    ],
    'quotes' => [
      'related_opportunity_id' => $isMysql
        ? 'ALTER TABLE quotes ADD COLUMN related_opportunity_id INT UNSIGNED NULL AFTER opportunity_id'
        : 'ALTER TABLE quotes ADD COLUMN related_opportunity_id INTEGER NULL',
      'requested_by_portal_user_id' => $isMysql
        ? 'ALTER TABLE quotes ADD COLUMN requested_by_portal_user_id INT UNSIGNED NULL AFTER related_opportunity_id'
        : 'ALTER TABLE quotes ADD COLUMN requested_by_portal_user_id INTEGER NULL',
      'request_scope' => $isMysql
        ? 'ALTER TABLE quotes ADD COLUMN request_scope TEXT NULL AFTER requested_by_portal_user_id'
        : 'ALTER TABLE quotes ADD COLUMN request_scope TEXT NULL',
      'request_location' => $isMysql
        ? 'ALTER TABLE quotes ADD COLUMN request_location VARCHAR(190) NULL AFTER request_scope'
        : 'ALTER TABLE quotes ADD COLUMN request_location TEXT NULL',
      'requested_date' => $isMysql
        ? 'ALTER TABLE quotes ADD COLUMN requested_date DATE NULL AFTER request_location'
        : 'ALTER TABLE quotes ADD COLUMN requested_date TEXT NULL',
      'budget_range' => $isMysql
        ? 'ALTER TABLE quotes ADD COLUMN budget_range VARCHAR(80) NULL AFTER requested_date'
        : 'ALTER TABLE quotes ADD COLUMN budget_range TEXT NULL',
      'terms' => $isMysql
        ? 'ALTER TABLE quotes ADD COLUMN terms TEXT NULL AFTER budget_range'
        : 'ALTER TABLE quotes ADD COLUMN terms TEXT NULL',
      'client_status' => $isMysql
        ? "ALTER TABLE quotes ADD COLUMN client_status VARCHAR(40) NOT NULL DEFAULT 'Pendiente' AFTER terms"
        : "ALTER TABLE quotes ADD COLUMN client_status TEXT NOT NULL DEFAULT 'Pendiente'",
      'client_comments' => $isMysql
        ? 'ALTER TABLE quotes ADD COLUMN client_comments TEXT NULL AFTER client_status'
        : 'ALTER TABLE quotes ADD COLUMN client_comments TEXT NULL',
      'visible_to_client' => $isMysql
        ? 'ALTER TABLE quotes ADD COLUMN visible_to_client TINYINT(1) NOT NULL DEFAULT 0 AFTER client_comments'
        : 'ALTER TABLE quotes ADD COLUMN visible_to_client INTEGER NOT NULL DEFAULT 0',
      'published_at' => $isMysql
        ? 'ALTER TABLE quotes ADD COLUMN published_at DATETIME NULL AFTER visible_to_client'
        : 'ALTER TABLE quotes ADD COLUMN published_at TEXT NULL',
      'responded_at' => $isMysql
        ? 'ALTER TABLE quotes ADD COLUMN responded_at DATETIME NULL AFTER published_at'
        : 'ALTER TABLE quotes ADD COLUMN responded_at TEXT NULL',
      'attachment_path' => $isMysql
        ? 'ALTER TABLE quotes ADD COLUMN attachment_path VARCHAR(255) NULL AFTER responded_at'
        : 'ALTER TABLE quotes ADD COLUMN attachment_path TEXT NULL',
      'attachment_original_name' => $isMysql
        ? 'ALTER TABLE quotes ADD COLUMN attachment_original_name VARCHAR(190) NULL AFTER attachment_path'
        : 'ALTER TABLE quotes ADD COLUMN attachment_original_name TEXT NULL',
      'attachment_mime' => $isMysql
        ? 'ALTER TABLE quotes ADD COLUMN attachment_mime VARCHAR(100) NULL AFTER attachment_original_name'
        : 'ALTER TABLE quotes ADD COLUMN attachment_mime TEXT NULL',
      'attachment_size' => $isMysql
        ? 'ALTER TABLE quotes ADD COLUMN attachment_size INT UNSIGNED NULL AFTER attachment_mime'
        : 'ALTER TABLE quotes ADD COLUMN attachment_size INTEGER NULL',
      'proposal_path' => $isMysql
        ? 'ALTER TABLE quotes ADD COLUMN proposal_path VARCHAR(255) NULL AFTER attachment_size'
        : 'ALTER TABLE quotes ADD COLUMN proposal_path TEXT NULL',
      'proposal_original_name' => $isMysql
        ? 'ALTER TABLE quotes ADD COLUMN proposal_original_name VARCHAR(190) NULL AFTER proposal_path'
        : 'ALTER TABLE quotes ADD COLUMN proposal_original_name TEXT NULL',
      'proposal_mime' => $isMysql
        ? 'ALTER TABLE quotes ADD COLUMN proposal_mime VARCHAR(100) NULL AFTER proposal_original_name'
        : 'ALTER TABLE quotes ADD COLUMN proposal_mime TEXT NULL',
      'proposal_size' => $isMysql
        ? 'ALTER TABLE quotes ADD COLUMN proposal_size INT UNSIGNED NULL AFTER proposal_mime'
        : 'ALTER TABLE quotes ADD COLUMN proposal_size INTEGER NULL',
      'updated_at' => $isMysql
        ? 'ALTER TABLE quotes ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at'
        : 'ALTER TABLE quotes ADD COLUMN updated_at TEXT NULL',
    ],
    'client_portal_users' => [
      'password_change_required' => $isMysql
        ? 'ALTER TABLE client_portal_users ADD COLUMN password_change_required TINYINT(1) NOT NULL DEFAULT 1 AFTER is_active'
        : 'ALTER TABLE client_portal_users ADD COLUMN password_change_required INTEGER NOT NULL DEFAULT 1',
      'password_changed_at' => $isMysql
        ? 'ALTER TABLE client_portal_users ADD COLUMN password_changed_at DATETIME NULL AFTER password_change_required'
        : 'ALTER TABLE client_portal_users ADD COLUMN password_changed_at TEXT NULL',
    ],
    'client_requests' => [
      'priority' => $isMysql
        ? "ALTER TABLE client_requests ADD COLUMN priority VARCHAR(40) NOT NULL DEFAULT 'Media' AFTER status"
        : "ALTER TABLE client_requests ADD COLUMN priority TEXT NOT NULL DEFAULT 'Media'",
      'admin_response' => $isMysql
        ? 'ALTER TABLE client_requests ADD COLUMN admin_response TEXT NULL AFTER message'
        : 'ALTER TABLE client_requests ADD COLUMN admin_response TEXT NULL',
      'resolved_at' => $isMysql
        ? 'ALTER TABLE client_requests ADD COLUMN resolved_at DATETIME NULL AFTER updated_at'
        : 'ALTER TABLE client_requests ADD COLUMN resolved_at TEXT NULL',
      'last_admin_update_at' => $isMysql
        ? 'ALTER TABLE client_requests ADD COLUMN last_admin_update_at DATETIME NULL AFTER resolved_at'
        : 'ALTER TABLE client_requests ADD COLUMN last_admin_update_at TEXT NULL',
      'due_date' => $isMysql
        ? 'ALTER TABLE client_requests ADD COLUMN due_date DATE NULL AFTER priority'
        : 'ALTER TABLE client_requests ADD COLUMN due_date TEXT NULL',
      'scheduled_date' => $isMysql
        ? 'ALTER TABLE client_requests ADD COLUMN scheduled_date DATE NULL AFTER due_date'
        : 'ALTER TABLE client_requests ADD COLUMN scheduled_date TEXT NULL',
      'assigned_to' => $isMysql
        ? 'ALTER TABLE client_requests ADD COLUMN assigned_to VARCHAR(160) NULL AFTER scheduled_date'
        : 'ALTER TABLE client_requests ADD COLUMN assigned_to TEXT NULL',
      'internal_notes' => $isMysql
        ? 'ALTER TABLE client_requests ADD COLUMN internal_notes TEXT NULL AFTER admin_response'
        : 'ALTER TABLE client_requests ADD COLUMN internal_notes TEXT NULL',
      'category' => $isMysql
        ? "ALTER TABLE client_requests ADD COLUMN category VARCHAR(80) NOT NULL DEFAULT 'Mantenimiento correctivo' AFTER message"
        : "ALTER TABLE client_requests ADD COLUMN category TEXT NOT NULL DEFAULT 'Mantenimiento correctivo'",
      'location' => $isMysql
        ? 'ALTER TABLE client_requests ADD COLUMN location VARCHAR(190) NULL AFTER category'
        : 'ALTER TABLE client_requests ADD COLUMN location TEXT NULL',
      'equipment' => $isMysql
        ? 'ALTER TABLE client_requests ADD COLUMN equipment VARCHAR(190) NULL AFTER location'
        : 'ALTER TABLE client_requests ADD COLUMN equipment TEXT NULL',
      'impact' => $isMysql
        ? "ALTER TABLE client_requests ADD COLUMN impact VARCHAR(80) NOT NULL DEFAULT 'Sin paro' AFTER equipment"
        : "ALTER TABLE client_requests ADD COLUMN impact TEXT NOT NULL DEFAULT 'Sin paro'",
      'occurred_at' => $isMysql
        ? 'ALTER TABLE client_requests ADD COLUMN occurred_at DATETIME NULL AFTER impact'
        : 'ALTER TABLE client_requests ADD COLUMN occurred_at TEXT NULL',
      'actions_taken' => $isMysql
        ? 'ALTER TABLE client_requests ADD COLUMN actions_taken TEXT NULL AFTER occurred_at'
        : 'ALTER TABLE client_requests ADD COLUMN actions_taken TEXT NULL',
      'evidence_path' => $isMysql
        ? 'ALTER TABLE client_requests ADD COLUMN evidence_path VARCHAR(255) NULL AFTER actions_taken'
        : 'ALTER TABLE client_requests ADD COLUMN evidence_path TEXT NULL',
      'evidence_original_name' => $isMysql
        ? 'ALTER TABLE client_requests ADD COLUMN evidence_original_name VARCHAR(190) NULL AFTER evidence_path'
        : 'ALTER TABLE client_requests ADD COLUMN evidence_original_name TEXT NULL',
      'evidence_mime' => $isMysql
        ? 'ALTER TABLE client_requests ADD COLUMN evidence_mime VARCHAR(80) NULL AFTER evidence_original_name'
        : 'ALTER TABLE client_requests ADD COLUMN evidence_mime TEXT NULL',
      'evidence_size' => $isMysql
        ? 'ALTER TABLE client_requests ADD COLUMN evidence_size INT UNSIGNED NULL AFTER evidence_mime'
        : 'ALTER TABLE client_requests ADD COLUMN evidence_size INTEGER NULL',
    ],
  ];

  foreach ($columns as $table => $tableColumns) {
    foreach ($tableColumns as $column => $definition) {
      if (!crm_column_exists($pdo, $table, $column)) {
        $pdo->exec($definition);
      }
    }
  }

  if (!$isMysql && crm_table_exists($pdo, 'quotes') && crm_column_exists($pdo, 'quotes', 'updated_at')) {
    $pdo->exec("UPDATE quotes SET updated_at = COALESCE(updated_at, created_at, CURRENT_TIMESTAMP) WHERE updated_at IS NULL OR updated_at = ''");
  }

  if ($isMysql && crm_table_exists($pdo, 'client_portal_users')) {
    try {
      if (!crm_index_exists($pdo, 'client_portal_users', 'idx_client_portal_client')) {
        $pdo->exec('ALTER TABLE client_portal_users ADD INDEX idx_client_portal_client (client_id)');
      }
      if (crm_index_exists($pdo, 'client_portal_users', 'uq_client_portal_client')) {
        $pdo->exec('ALTER TABLE client_portal_users DROP INDEX uq_client_portal_client');
      }
    } catch (Throwable $error) {
      error_log('CRM portal index repair failed: ' . $error->getMessage());
    }
  }
}
function crm_migrate_sqlite(PDO $pdo): void
{
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name TEXT NOT NULL,
      email TEXT NOT NULL UNIQUE,
      password_hash TEXT NOT NULL,
      role TEXT NOT NULL DEFAULT 'admin',
      created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS clients (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name TEXT NOT NULL UNIQUE,
      segment TEXT NOT NULL DEFAULT 'Industrial',
      lifecycle_stage TEXT NOT NULL DEFAULT 'Cliente',
      city TEXT,
      contact_name TEXT,
      contact_email TEXT,
      contact_phone TEXT,
      notes TEXT,
      is_public INTEGER NOT NULL DEFAULT 0,
      created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS opportunities (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      client_id INTEGER,
      company_name TEXT NOT NULL,
      contact_name TEXT NOT NULL,
      contact_email TEXT,
      contact_phone TEXT,
      service TEXT NOT NULL,
      request_type TEXT,
      project_location TEXT,
      desired_execution_date TEXT,
      source TEXT NOT NULL DEFAULT 'Sitio web',
      status TEXT NOT NULL DEFAULT 'Nueva solicitud',
      priority TEXT NOT NULL DEFAULT 'Media',
      estimated_value REAL NOT NULL DEFAULT 0,
      next_action_date TEXT,
      notes TEXT,
      created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
    );

    CREATE TABLE IF NOT EXISTS opportunity_attachments (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      opportunity_id INTEGER NOT NULL,
      file_path TEXT NOT NULL,
      original_name TEXT NOT NULL,
      mime TEXT NOT NULL,
      size INTEGER NOT NULL,
      created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (opportunity_id) REFERENCES opportunities(id) ON DELETE CASCADE
    );

    CREATE INDEX IF NOT EXISTS idx_opportunity_attachments_opportunity ON opportunity_attachments(opportunity_id);

    CREATE TABLE IF NOT EXISTS quotes (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      opportunity_id INTEGER NOT NULL,
      related_opportunity_id INTEGER,
      requested_by_portal_user_id INTEGER,
      quote_code TEXT NOT NULL UNIQUE,
      amount REAL NOT NULL DEFAULT 0,
      status TEXT NOT NULL DEFAULT 'En elaboracion',
      probability INTEGER NOT NULL DEFAULT 40,
      sent_at TEXT,
      valid_until TEXT,
      request_scope TEXT,
      request_location TEXT,
      requested_date TEXT,
      budget_range TEXT,
      terms TEXT,
      client_status TEXT NOT NULL DEFAULT 'Pendiente',
      client_comments TEXT,
      visible_to_client INTEGER NOT NULL DEFAULT 0,
      published_at TEXT,
      responded_at TEXT,
      attachment_path TEXT,
      attachment_original_name TEXT,
      attachment_mime TEXT,
      attachment_size INTEGER,
      proposal_path TEXT,
      proposal_original_name TEXT,
      proposal_mime TEXT,
      proposal_size INTEGER,
      created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (opportunity_id) REFERENCES opportunities(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS activities (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      opportunity_id INTEGER NOT NULL,
      type TEXT NOT NULL DEFAULT 'Seguimiento',
      summary TEXT NOT NULL,
      due_date TEXT,
      completed_at TEXT,
      created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (opportunity_id) REFERENCES opportunities(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS client_portal_users (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      opportunity_id INTEGER NOT NULL UNIQUE,
      client_id INTEGER,
      username TEXT NOT NULL UNIQUE,
      password_hash TEXT NOT NULL,
      is_active INTEGER NOT NULL DEFAULT 1,
      password_change_required INTEGER NOT NULL DEFAULT 1,
      password_changed_at TEXT,
      created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
      last_login_at TEXT,
      FOREIGN KEY (opportunity_id) REFERENCES opportunities(id) ON DELETE CASCADE,
      FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
    );

    CREATE TABLE IF NOT EXISTS maintenance_logs (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      opportunity_id INTEGER NOT NULL,
      portal_user_id INTEGER,
      type TEXT NOT NULL DEFAULT 'Mantenimiento',
      title TEXT NOT NULL,
      status TEXT NOT NULL DEFAULT 'Programado',
      scheduled_date TEXT,
      completed_at TEXT,
      notes TEXT,
      visible_to_client INTEGER NOT NULL DEFAULT 1,
      created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (opportunity_id) REFERENCES opportunities(id) ON DELETE CASCADE,
      FOREIGN KEY (portal_user_id) REFERENCES client_portal_users(id) ON DELETE SET NULL
    );

    CREATE TABLE IF NOT EXISTS client_requests (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      opportunity_id INTEGER NOT NULL,
      portal_user_id INTEGER NOT NULL,
      title TEXT NOT NULL,
      message TEXT NOT NULL,
      category TEXT NOT NULL DEFAULT 'Mantenimiento correctivo',
      location TEXT,
      equipment TEXT,
      impact TEXT NOT NULL DEFAULT 'Sin paro',
      occurred_at TEXT,
      actions_taken TEXT,
      evidence_path TEXT,
      evidence_original_name TEXT,
      evidence_mime TEXT,
      evidence_size INTEGER,
      admin_response TEXT,
      status TEXT NOT NULL DEFAULT 'Recibida',
      priority TEXT NOT NULL DEFAULT 'Media',
      due_date TEXT,
      scheduled_date TEXT,
      assigned_to TEXT,
      internal_notes TEXT,
      created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
      resolved_at TEXT,
      last_admin_update_at TEXT,
      FOREIGN KEY (opportunity_id) REFERENCES opportunities(id) ON DELETE CASCADE,
      FOREIGN KEY (portal_user_id) REFERENCES client_portal_users(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS notifications (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      recipient_type TEXT NOT NULL,
      recipient_user_id INTEGER,
      portal_user_id INTEGER,
      opportunity_id INTEGER,
      client_request_id INTEGER,
      event_type TEXT NOT NULL DEFAULT 'general',
      title TEXT NOT NULL,
      message TEXT NOT NULL,
      target_url TEXT,
      is_read INTEGER NOT NULL DEFAULT 0,
      read_at TEXT,
      created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (portal_user_id) REFERENCES client_portal_users(id) ON DELETE CASCADE,
      FOREIGN KEY (opportunity_id) REFERENCES opportunities(id) ON DELETE CASCADE,
      FOREIGN KEY (client_request_id) REFERENCES client_requests(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS crm_settings (
      setting_key TEXT PRIMARY KEY,
      setting_value TEXT NOT NULL,
      updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS app_sessions (
      session_id TEXT PRIMARY KEY,
      payload BLOB NOT NULL,
      last_activity INTEGER NOT NULL
    );

    CREATE TABLE IF NOT EXISTS login_attempts (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      area TEXT NOT NULL,
      identifier TEXT NOT NULL,
      identifier_hash TEXT NOT NULL,
      ip_address TEXT NOT NULL,
      attempts INTEGER NOT NULL DEFAULT 0,
      locked_until TEXT,
      last_attempt_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
      created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
      UNIQUE (area, identifier_hash, ip_address)
    );
    CREATE INDEX IF NOT EXISTS idx_opportunities_status ON opportunities(status);
    CREATE INDEX IF NOT EXISTS idx_opportunities_next_action ON opportunities(next_action_date);
    CREATE INDEX IF NOT EXISTS idx_quotes_status ON quotes(status);
    CREATE INDEX IF NOT EXISTS idx_activities_due ON activities(completed_at, due_date);
    CREATE INDEX IF NOT EXISTS idx_maintenance_logs_opportunity ON maintenance_logs(opportunity_id, scheduled_date);
    CREATE INDEX IF NOT EXISTS idx_client_requests_portal ON client_requests(portal_user_id, created_at);
    CREATE INDEX IF NOT EXISTS idx_client_requests_status ON client_requests(status, updated_at);
    CREATE INDEX IF NOT EXISTS idx_notifications_recipient ON notifications(recipient_type, portal_user_id, is_read, created_at);
    CREATE INDEX IF NOT EXISTS idx_notifications_request ON notifications(client_request_id);
    CREATE INDEX IF NOT EXISTS idx_login_attempts_locked ON login_attempts(locked_until);
    CREATE INDEX IF NOT EXISTS idx_app_sessions_activity ON app_sessions(last_activity);
  ");
}

function crm_migrate_mysql(PDO $pdo): void
{
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT,
      name VARCHAR(160) NOT NULL,
      email VARCHAR(190) NOT NULL,
      password_hash VARCHAR(255) NOT NULL,
      role VARCHAR(60) NOT NULL DEFAULT 'admin',
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY uq_users_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS clients (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT,
      name VARCHAR(190) NOT NULL,
      segment VARCHAR(120) NOT NULL DEFAULT 'Industrial',
      lifecycle_stage VARCHAR(40) NOT NULL DEFAULT 'Cliente',
      city VARCHAR(120) NULL,
      contact_name VARCHAR(160) NULL,
      contact_email VARCHAR(190) NULL,
      contact_phone VARCHAR(60) NULL,
      notes TEXT NULL,
      is_public TINYINT(1) NOT NULL DEFAULT 0,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY uq_clients_name (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS opportunities (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT,
      client_id INT UNSIGNED NULL,
      company_name VARCHAR(190) NOT NULL,
      contact_name VARCHAR(160) NOT NULL,
      contact_email VARCHAR(190) NULL,
      contact_phone VARCHAR(60) NULL,
      service VARCHAR(160) NOT NULL,
      request_type VARCHAR(40) NULL,
      project_location VARCHAR(160) NULL,
      desired_execution_date DATE NULL,
      source VARCHAR(120) NOT NULL DEFAULT 'Sitio web',
      status VARCHAR(80) NOT NULL DEFAULT 'Nueva solicitud',
      priority VARCHAR(30) NOT NULL DEFAULT 'Media',
      estimated_value DECIMAL(12,2) NOT NULL DEFAULT 0,
      next_action_date DATE NULL,
      notes TEXT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY idx_opportunities_client (client_id),
      KEY idx_opportunities_status (status),
      KEY idx_opportunities_next_action (next_action_date),
      CONSTRAINT fk_opportunities_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS opportunity_attachments (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT,
      opportunity_id INT UNSIGNED NOT NULL,
      file_path VARCHAR(255) NOT NULL,
      original_name VARCHAR(190) NOT NULL,
      mime VARCHAR(100) NOT NULL,
      size INT UNSIGNED NOT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY idx_opportunity_attachments_opportunity (opportunity_id),
      CONSTRAINT fk_opportunity_attachments_opportunity FOREIGN KEY (opportunity_id) REFERENCES opportunities(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS quotes (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT,
      opportunity_id INT UNSIGNED NOT NULL,
      related_opportunity_id INT UNSIGNED NULL,
      requested_by_portal_user_id INT UNSIGNED NULL,
      quote_code VARCHAR(60) NOT NULL,
      amount DECIMAL(12,2) NOT NULL DEFAULT 0,
      status VARCHAR(80) NOT NULL DEFAULT 'En elaboracion',
      probability TINYINT UNSIGNED NOT NULL DEFAULT 40,
      sent_at DATE NULL,
      valid_until DATE NULL,
      request_scope TEXT NULL,
      request_location VARCHAR(190) NULL,
      requested_date DATE NULL,
      budget_range VARCHAR(80) NULL,
      terms TEXT NULL,
      client_status VARCHAR(40) NOT NULL DEFAULT 'Pendiente',
      client_comments TEXT NULL,
      visible_to_client TINYINT(1) NOT NULL DEFAULT 0,
      published_at DATETIME NULL,
      responded_at DATETIME NULL,
      attachment_path VARCHAR(255) NULL,
      attachment_original_name VARCHAR(190) NULL,
      attachment_mime VARCHAR(100) NULL,
      attachment_size INT UNSIGNED NULL,
      proposal_path VARCHAR(255) NULL,
      proposal_original_name VARCHAR(190) NULL,
      proposal_mime VARCHAR(100) NULL,
      proposal_size INT UNSIGNED NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY uq_quotes_code (quote_code),
      KEY idx_quotes_opportunity (opportunity_id),
      KEY idx_quotes_status (status),
      KEY idx_quotes_visibility (visible_to_client, client_status),
      KEY idx_quotes_requested_by (requested_by_portal_user_id),
      CONSTRAINT fk_quotes_opportunity FOREIGN KEY (opportunity_id) REFERENCES opportunities(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS activities (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT,
      opportunity_id INT UNSIGNED NOT NULL,
      type VARCHAR(80) NOT NULL DEFAULT 'Seguimiento',
      summary TEXT NOT NULL,
      due_date DATE NULL,
      completed_at DATETIME NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY idx_activities_opportunity (opportunity_id),
      KEY idx_activities_due (completed_at, due_date),
      CONSTRAINT fk_activities_opportunity FOREIGN KEY (opportunity_id) REFERENCES opportunities(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS client_portal_users (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT,
      opportunity_id INT UNSIGNED NOT NULL,
      client_id INT UNSIGNED NULL,
      username VARCHAR(190) NOT NULL,
      password_hash VARCHAR(255) NOT NULL,
      is_active TINYINT(1) NOT NULL DEFAULT 1,
      password_change_required TINYINT(1) NOT NULL DEFAULT 1,
      password_changed_at DATETIME NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      last_login_at DATETIME NULL,
      PRIMARY KEY (id),
      UNIQUE KEY uq_client_portal_opportunity (opportunity_id),
      UNIQUE KEY uq_client_portal_username (username),
      KEY idx_client_portal_client (client_id),
      CONSTRAINT fk_client_portal_opportunity FOREIGN KEY (opportunity_id) REFERENCES opportunities(id) ON DELETE CASCADE,
      CONSTRAINT fk_client_portal_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS maintenance_logs (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT,
      opportunity_id INT UNSIGNED NOT NULL,
      portal_user_id INT UNSIGNED NULL,
      type VARCHAR(80) NOT NULL DEFAULT 'Mantenimiento',
      title VARCHAR(190) NOT NULL,
      status VARCHAR(80) NOT NULL DEFAULT 'Programado',
      scheduled_date DATE NULL,
      completed_at DATETIME NULL,
      notes TEXT NULL,
      visible_to_client TINYINT(1) NOT NULL DEFAULT 1,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY idx_maintenance_logs_opportunity (opportunity_id, scheduled_date),
      KEY idx_maintenance_logs_portal (portal_user_id),
      CONSTRAINT fk_maintenance_logs_opportunity FOREIGN KEY (opportunity_id) REFERENCES opportunities(id) ON DELETE CASCADE,
      CONSTRAINT fk_maintenance_logs_portal FOREIGN KEY (portal_user_id) REFERENCES client_portal_users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS client_requests (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT,
      opportunity_id INT UNSIGNED NOT NULL,
      portal_user_id INT UNSIGNED NOT NULL,
      title VARCHAR(190) NOT NULL,
      message TEXT NOT NULL,
      category VARCHAR(80) NOT NULL DEFAULT 'Mantenimiento correctivo',
      location VARCHAR(190) NULL,
      equipment VARCHAR(190) NULL,
      impact VARCHAR(80) NOT NULL DEFAULT 'Sin paro',
      occurred_at DATETIME NULL,
      actions_taken TEXT NULL,
      evidence_path VARCHAR(255) NULL,
      evidence_original_name VARCHAR(190) NULL,
      evidence_mime VARCHAR(80) NULL,
      evidence_size INT UNSIGNED NULL,
      admin_response TEXT NULL,
      status VARCHAR(80) NOT NULL DEFAULT 'Recibida',
      priority VARCHAR(40) NOT NULL DEFAULT 'Media',
      due_date DATE NULL,
      scheduled_date DATE NULL,
      assigned_to VARCHAR(160) NULL,
      internal_notes TEXT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      resolved_at DATETIME NULL,
      last_admin_update_at DATETIME NULL,
      PRIMARY KEY (id),
      KEY idx_client_requests_portal (portal_user_id, created_at),
      KEY idx_client_requests_opportunity (opportunity_id),
      KEY idx_client_requests_status (status, updated_at),
      CONSTRAINT fk_client_requests_opportunity FOREIGN KEY (opportunity_id) REFERENCES opportunities(id) ON DELETE CASCADE,
      CONSTRAINT fk_client_requests_portal FOREIGN KEY (portal_user_id) REFERENCES client_portal_users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    CREATE TABLE IF NOT EXISTS notifications (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT,
      recipient_type VARCHAR(20) NOT NULL,
      recipient_user_id INT UNSIGNED NULL,
      portal_user_id INT UNSIGNED NULL,
      opportunity_id INT UNSIGNED NULL,
      client_request_id INT UNSIGNED NULL,
      event_type VARCHAR(80) NOT NULL DEFAULT 'general',
      title VARCHAR(190) NOT NULL,
      message TEXT NOT NULL,
      target_url VARCHAR(255) NULL,
      is_read TINYINT(1) NOT NULL DEFAULT 0,
      read_at DATETIME NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY idx_notifications_recipient (recipient_type, portal_user_id, is_read, created_at),
      KEY idx_notifications_request (client_request_id),
      KEY idx_notifications_opportunity (opportunity_id),
      CONSTRAINT fk_notifications_portal FOREIGN KEY (portal_user_id) REFERENCES client_portal_users(id) ON DELETE CASCADE,
      CONSTRAINT fk_notifications_opportunity FOREIGN KEY (opportunity_id) REFERENCES opportunities(id) ON DELETE CASCADE,
      CONSTRAINT fk_notifications_request FOREIGN KEY (client_request_id) REFERENCES client_requests(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS crm_settings (
      setting_key VARCHAR(120) NOT NULL,
      setting_value TEXT NOT NULL,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (setting_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS app_sessions (
      session_id VARCHAR(128) NOT NULL,
      payload MEDIUMBLOB NOT NULL,
      last_activity INT UNSIGNED NOT NULL,
      PRIMARY KEY (session_id),
      KEY idx_app_sessions_activity (last_activity)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS login_attempts (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT,
      area VARCHAR(30) NOT NULL,
      identifier VARCHAR(190) NOT NULL,
      identifier_hash CHAR(64) NOT NULL,
      ip_address VARCHAR(64) NOT NULL,
      attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
      locked_until DATETIME NULL,
      last_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY uq_login_attempt_scope (area, identifier_hash, ip_address),
      KEY idx_login_attempts_locked (locked_until),
      KEY idx_login_attempts_last (last_attempt_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  ");
}

function crm_seed(PDO $pdo): void
{
  $userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
  if ($userCount === 0) {
    $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
    $stmt->execute([
      'Administrador',
      'admin@idindustrial.com.mx',
      password_hash('IDIndustrial2026!', PASSWORD_DEFAULT),
      'superadmin',
    ]);
  }

  $clientCount = (int) $pdo->query('SELECT COUNT(*) FROM clients')->fetchColumn();
  if ($clientCount === 0) {
    $clients = [
      ['Daechang', 'Automotriz', 'Queretaro'],
      ['DR-ENC', 'Manufactura', 'Queretaro'],
      ['Pollux', 'Industrial', 'Bajio'],
      ['PSSL Seguridad', 'Seguridad', 'Queretaro'],
      ['AB Mexco', 'Manufactura', 'Queretaro'],
      ['Deadong HEMEX', 'Automotriz', 'Bajio'],
      ['Harman', 'Electronica', 'Queretaro'],
      ['Samsung', 'Electronica', 'Queretaro'],
      ['Michelin', 'Manufactura', 'Queretaro'],
      ['AIQ', 'Infraestructura', 'Queretaro'],
    ];
    $stmt = $pdo->prepare('INSERT INTO clients (name, segment, city, is_public, notes) VALUES (?, ?, ?, 1, ?)');
    foreach ($clients as $client) {
      $stmt->execute([$client[0], $client[1], $client[2], 'Cliente de referencia para credibilidad comercial.']);
    }
  }

  $opportunityCount = (int) $pdo->query('SELECT COUNT(*) FROM opportunities')->fetchColumn();
  if ($opportunityCount === 0) {
    $seed = [
      ['Daechang', 'Mantenimiento', 'compras@daechang.example', '+52 442 000 1001', 'Cableado estructurado', 'Levantamiento programado', 185000, '+3 days', 'Revisar rutas de fibra y rack principal.'],
      ['Harman', 'Ingenieria de planta', 'ingenieria@harman.example', '+52 442 000 1002', 'CCTV industrial', 'Cotizacion enviada', 242000, '+2 days', 'Dar seguimiento a propuesta de camaras en perimetro.'],
      ['Samsung', 'Facilities', 'facilities@samsung.example', '+52 442 000 1003', 'HVAC industrial', 'Ingenieria en desarrollo', 410000, '+5 days', 'Calcular cargas termicas y validar horarios de visita.'],
      ['AIQ', 'Operaciones', 'operaciones@aiq.example', '+52 442 000 1004', 'Control de accesos', 'Seguimiento', 96000, '+1 day', 'Esperando confirmacion de usuarios y zonas.'],
    ];
    $opStmt = $pdo->prepare('
      INSERT INTO opportunities (client_id, company_name, contact_name, contact_email, contact_phone, service, status, estimated_value, next_action_date, notes, source)
      VALUES ((SELECT id FROM clients WHERE name = ? LIMIT 1), ?, ?, ?, ?, ?, ?, ?, ?, ?, "Referido / cartera")
    ');
    $quoteStmt = $pdo->prepare('INSERT INTO quotes (opportunity_id, quote_code, amount, status, probability, sent_at, valid_until) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $activityStmt = $pdo->prepare('INSERT INTO activities (opportunity_id, type, summary, due_date) VALUES (?, ?, ?, ?)');
    foreach ($seed as $index => $row) {
      $nextActionDate = date('Y-m-d', strtotime($row[7]));
      $opStmt->execute([$row[0], $row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $row[6], $nextActionDate, $row[8]]);
      $opportunityId = (int) $pdo->lastInsertId();
      $quoteStmt->execute([$opportunityId, 'ID-' . date('Y') . '-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT), $row[6], $row[5] === 'Cotizacion enviada' ? 'Enviada' : 'En elaboracion', $row[5] === 'Cotizacion enviada' ? 65 : 40, date('Y-m-d', strtotime('-2 days')), date('Y-m-d', strtotime('+15 days'))]);
      $activityStmt->execute([$opportunityId, 'Seguimiento comercial', 'Actualizar avance y siguiente paso tecnico.', $nextActionDate]);
    }
  }
}

function crm_public_clients(int $limit = 10): array
{
  $stmt = crm_db()->prepare("SELECT name, segment FROM clients WHERE is_public = 1 AND lifecycle_stage = 'Cliente' ORDER BY name LIMIT ?");
  $stmt->bindValue(1, $limit, PDO::PARAM_INT);
  $stmt->execute();
  return $stmt->fetchAll();
}

function crm_next_quote_code(PDO $pdo, string $prefix = 'ID'): string
{
  $year = date('Y');
  $like = $prefix . '-' . $year . '-%';
  $stmt = $pdo->prepare('SELECT COUNT(*) FROM quotes WHERE quote_code LIKE ?');
  $stmt->execute([$like]);
  $nextNumber = (int) $stmt->fetchColumn() + 1;
  do {
    $quoteCode = $prefix . '-' . $year . '-' . str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT);
    $exists = $pdo->prepare('SELECT COUNT(*) FROM quotes WHERE quote_code = ?');
    $exists->execute([$quoteCode]);
    $nextNumber++;
  } while ((int) $exists->fetchColumn() > 0);
  return $quoteCode;
}

function crm_find_or_create_prospect_client(PDO $pdo, array $data): int
{
  $name = trim((string) ($data['company_name'] ?? '')) ?: trim((string) ($data['contact_name'] ?? 'Prospecto web'));
  $email = trim((string) ($data['contact_email'] ?? ''));
  $phone = trim((string) ($data['contact_phone'] ?? ''));
  $contactName = trim((string) ($data['contact_name'] ?? $name));

  if ($email !== '') {
    $stmt = $pdo->prepare('SELECT id FROM clients WHERE contact_email = ? LIMIT 1');
    $stmt->execute([$email]);
    $clientId = (int) $stmt->fetchColumn();
    if ($clientId > 0) {
      $update = $pdo->prepare('UPDATE clients SET contact_name = ?, contact_phone = ?, notes = ? WHERE id = ?');
      $update->execute([$contactName, $phone, 'Prospecto actualizado desde formulario web.', $clientId]);
      return $clientId;
    }
  }

  $stmt = $pdo->prepare('SELECT id FROM clients WHERE name = ? LIMIT 1');
  $stmt->execute([$name]);
  $clientId = (int) $stmt->fetchColumn();
  if ($clientId > 0) {
    $update = $pdo->prepare('UPDATE clients SET contact_name = ?, contact_email = ?, contact_phone = ?, notes = ? WHERE id = ?');
    $update->execute([$contactName, $email, $phone, 'Prospecto actualizado desde formulario web.', $clientId]);
    return $clientId;
  }

  $insert = $pdo->prepare('INSERT INTO clients (name, segment, lifecycle_stage, city, contact_name, contact_email, contact_phone, notes, is_public) VALUES (?, "Prospecto", "Prospecto", "", ?, ?, ?, ?, 0)');
  $insert->execute([$name, $contactName, $email, $phone, 'Solicitud recibida desde el formulario publico.']);
  return (int) $pdo->lastInsertId();
}

function crm_prepare_opportunity_attachments(?array $upload): array
{
  if (!$upload || !isset($upload['name'], $upload['tmp_name'], $upload['error'], $upload['size'])) {
    throw new RuntimeException('Adjunta al menos un archivo del proyecto.');
  }

  $names = is_array($upload['name']) ? $upload['name'] : [$upload['name']];
  $tmpNames = is_array($upload['tmp_name']) ? $upload['tmp_name'] : [$upload['tmp_name']];
  $errors = is_array($upload['error']) ? $upload['error'] : [$upload['error']];
  $sizes = is_array($upload['size']) ? $upload['size'] : [$upload['size']];
  $allowed = [
    'application/pdf' => 'pdf',
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
  ];
  $files = [];
  $totalSize = 0;

  foreach ($names as $index => $name) {
    $error = (int) ($errors[$index] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
      continue;
    }
    if ($error !== UPLOAD_ERR_OK) {
      throw new RuntimeException('Uno de los archivos no pudo cargarse. Intenta nuevamente.');
    }
    if (count($files) >= 5) {
      throw new RuntimeException('Puedes adjuntar un máximo de 5 archivos.');
    }

    $size = (int) ($sizes[$index] ?? 0);
    if ($size <= 0 || $size > 8 * 1024 * 1024) {
      throw new RuntimeException('Cada archivo debe pesar como máximo 8 MB.');
    }
    $totalSize += $size;
    if ($totalSize > 20 * 1024 * 1024) {
      throw new RuntimeException('El total de los archivos no puede superar 20 MB.');
    }

    $tmpPath = (string) ($tmpNames[$index] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
      throw new RuntimeException('No fue posible validar uno de los archivos adjuntos.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmpPath) ?: '';
    if (!isset($allowed[$mime])) {
      throw new RuntimeException('Solo se permiten archivos PDF, JPG, PNG o WEBP.');
    }
    if (str_starts_with($mime, 'image/') && @getimagesize($tmpPath) === false) {
      throw new RuntimeException('Uno de los archivos de imagen no es válido.');
    }

    $originalName = trim(basename(str_replace("\0", '', (string) $name)));
    $files[] = [
      'tmp_path' => $tmpPath,
      'original_name' => mb_substr($originalName !== '' ? $originalName : ('archivo.' . $allowed[$mime]), 0, 190),
      'mime' => $mime,
      'size' => $size,
      'extension' => $allowed[$mime],
    ];
  }

  if (!$files) {
    throw new RuntimeException('Adjunta al menos un archivo del proyecto.');
  }

  return $files;
}

function crm_store_opportunity_attachments(PDO $pdo, int $opportunityId, array $attachments): array
{
  $directory = dirname(__DIR__) . '/data/opportunity-attachments';
  if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
    throw new RuntimeException('No fue posible preparar el almacenamiento de adjuntos.');
  }

  $storedPaths = [];
  $insert = $pdo->prepare('INSERT INTO opportunity_attachments (opportunity_id, file_path, original_name, mime, size) VALUES (?, ?, ?, ?, ?)');
  foreach ($attachments as $attachment) {
    $storedName = bin2hex(random_bytes(20)) . '.' . $attachment['extension'];
    $absolutePath = $directory . '/' . $storedName;
    if (!move_uploaded_file($attachment['tmp_path'], $absolutePath)) {
      foreach ($storedPaths as $storedPath) {
        @unlink($storedPath);
      }
      throw new RuntimeException('No fue posible guardar uno de los archivos adjuntos.');
    }
    $storedPaths[] = $absolutePath;
    $relativePath = 'data/opportunity-attachments/' . $storedName;
    $insert->execute([
      $opportunityId,
      $relativePath,
      $attachment['original_name'],
      $attachment['mime'],
      $attachment['size'],
    ]);
  }
  return $storedPaths;
}

function crm_output_opportunity_attachment(PDO $pdo, int $attachmentId): void
{
  $stmt = $pdo->prepare('SELECT file_path, original_name, mime, size FROM opportunity_attachments WHERE id = ? LIMIT 1');
  $stmt->execute([$attachmentId]);
  $attachment = $stmt->fetch();
  if (!$attachment) {
    http_response_code(404);
    exit('Archivo no encontrado.');
  }

  $baseDirectory = realpath(dirname(__DIR__) . '/data/opportunity-attachments');
  $absolutePath = realpath(dirname(__DIR__) . '/' . ltrim((string) $attachment['file_path'], '/\\'));
  if (!$baseDirectory || !$absolutePath || !str_starts_with($absolutePath, $baseDirectory . DIRECTORY_SEPARATOR) || !is_file($absolutePath)) {
    http_response_code(404);
    exit('Archivo no encontrado.');
  }

  $downloadName = str_replace(["\r", "\n", '"'], '', (string) $attachment['original_name']);
  header('Content-Type: ' . ((string) $attachment['mime'] ?: 'application/octet-stream'));
  header('Content-Length: ' . filesize($absolutePath));
  header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($downloadName));
  header('X-Content-Type-Options: nosniff');
  readfile($absolutePath);
  exit;
}

function crm_capture_public_lead(array $data, array $attachments = []): ?int
{
  $storedAttachmentPaths = [];
  try {
    $pdo = crm_db();
    $pdo->beginTransaction();
    $clientId = crm_find_or_create_prospect_client($pdo, $data);
    $companyName = trim((string) ($data['company_name'] ?? '')) ?: 'Prospecto web';
    $contactName = trim((string) ($data['contact_name'] ?? '')) ?: $companyName;
    $service = trim((string) ($data['service'] ?? '')) ?: 'Por definir';
    $notes = trim((string) ($data['notes'] ?? ''));

    $stmt = $pdo->prepare('
      INSERT INTO opportunities (client_id, company_name, contact_name, contact_email, contact_phone, service, request_type, project_location, desired_execution_date, source, status, priority, estimated_value, next_action_date, notes)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "Formulario web", "Nueva solicitud", "Alta", 0, ?, ?)
    ');
    $stmt->execute([
      $clientId,
      $companyName,
      $contactName,
      trim((string) ($data['contact_email'] ?? '')),
      trim((string) ($data['contact_phone'] ?? '')),
      $service,
      trim((string) ($data['request_type'] ?? '')),
      trim((string) ($data['project_location'] ?? '')),
      trim((string) ($data['desired_execution_date'] ?? '')) ?: null,
      date('Y-m-d', strtotime('+1 day')),
      $notes,
    ]);
    $opportunityId = (int) $pdo->lastInsertId();

    $quote = $pdo->prepare('INSERT INTO quotes (opportunity_id, quote_code, amount, status, probability, sent_at, valid_until) VALUES (?, ?, 0, "Solicitud recibida", 10, NULL, NULL)');
    $quote->execute([$opportunityId, crm_next_quote_code($pdo, 'SOL')]);

    $activity = $pdo->prepare('INSERT INTO activities (opportunity_id, type, summary, due_date) VALUES (?, "Primer contacto", "Contactar prospecto y preparar cotizacion.", ?)');
    $activity->execute([$opportunityId, date('Y-m-d', strtotime('+1 day'))]);

    if ($attachments) {
      $storedAttachmentPaths = crm_store_opportunity_attachments($pdo, $opportunityId, $attachments);
    }

    $pdo->commit();
    return $opportunityId;
  } catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
      $pdo->rollBack();
    }
    foreach ($storedAttachmentPaths as $storedPath) {
      @unlink($storedPath);
    }
    crm_log_event('public_lead.capture_failed', [
      'driver' => isset($pdo) && $pdo instanceof PDO ? crm_driver($pdo) : (string) (crm_config()['driver'] ?? ''),
      'database' => (string) (crm_config()['database'] ?? ''),
      'error_class' => get_class($error),
      'error_message' => substr($error->getMessage(), 0, 500),
    ]);
    return null;
  }
}
function crm_random_password(int $length = 12): string
{
  $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
  $password = '';
  $max = strlen($alphabet) - 1;
  for ($i = 0; $i < $length; $i++) {
    $password .= $alphabet[random_int(0, $max)];
  }
  return $password;
}

function crm_slug(string $value): string
{
  $value = strtolower(trim($value));
  $value = preg_replace('/[^a-z0-9]+/i', '.', $value) ?: 'cliente';
  return trim($value, '.') ?: 'cliente';
}

function crm_unique_portal_username(PDO $pdo, array $opportunity): string
{
  $email = trim((string) ($opportunity['contact_email'] ?? ''));
  $base = filter_var($email, FILTER_VALIDATE_EMAIL)
    ? strtolower($email)
    : crm_slug((string) ($opportunity['company_name'] ?? 'cliente')) . '.' . (int) $opportunity['id'] . '@bitacora.id';

  $username = $base;
  $suffix = 1;
  $stmt = $pdo->prepare('SELECT COUNT(*) FROM client_portal_users WHERE LOWER(username) = LOWER(?)');
  while (true) {
    $stmt->execute([$username]);
    if ((int) $stmt->fetchColumn() === 0) {
      return $username;
    }
    $suffix++;
    $username = preg_replace('/@/', '+' . $suffix . '@', $base, 1) ?: ($base . '.' . $suffix);
  }
}

function crm_enable_client_portal(PDO $pdo, int $opportunityId): array
{
  $stmt = $pdo->prepare('SELECT o.*, c.lifecycle_stage FROM opportunities o LEFT JOIN clients c ON c.id = o.client_id WHERE o.id = ? LIMIT 1');
  $stmt->execute([$opportunityId]);
  $opportunity = $stmt->fetch();
  if (!$opportunity) {
    throw new RuntimeException('No se encontro la oportunidad para activar Bitacora ID.');
  }
  if ((string) ($opportunity['lifecycle_stage'] ?? '') !== 'Cliente') {
    throw new RuntimeException('Bitacora ID solo puede activarse para clientes.');
  }
  if ((string) ($opportunity['status'] ?? '') !== 'Proyecto entregado') {
    throw new RuntimeException('Bitacora ID solo puede activarse despues de entregar el proyecto.');
  }

  $existingStmt = $pdo->prepare('SELECT * FROM client_portal_users WHERE opportunity_id = ? LIMIT 1');
  $existingStmt->execute([$opportunityId]);
  $existing = $existingStmt->fetch();
  if ($existing && (int) $existing['is_active'] === 1) {
    crm_clear_login_failures($pdo, 'client', (string) $existing['username']);
    crm_log_event('portal.access_already_active', [
      'portal_user_id' => (int) $existing['id'],
      'opportunity_id' => $opportunityId,
      'identifier' => crm_mask_identifier((string) $existing['username']),
      'driver' => crm_driver($pdo),
      'config_source' => crm_config_source(),
    ]);
    return ['created' => false, 'username' => $existing['username'], 'password' => null, 'opportunity' => $opportunity];
  }

  $password = crm_random_password();
  $passwordHash = password_hash($password, PASSWORD_DEFAULT);
  $startedTransaction = false;
  try {
    if (!$pdo->inTransaction()) {
      $pdo->beginTransaction();
      $startedTransaction = true;
    }

    if ($existing) {
      $update = $pdo->prepare('UPDATE client_portal_users SET password_hash = ?, is_active = 1, password_change_required = 1, password_changed_at = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
      $update->execute([$passwordHash, (int) $existing['id']]);
      $username = (string) $existing['username'];
      $portalUserId = (int) $existing['id'];
    } else {
      $username = crm_unique_portal_username($pdo, $opportunity);
      $insert = $pdo->prepare('INSERT INTO client_portal_users (opportunity_id, client_id, username, password_hash, is_active, password_change_required) VALUES (?, ?, ?, ?, 1, 1)');
      $insert->execute([$opportunityId, $opportunity['client_id'] ?: null, $username, $passwordHash]);
      $portalUserId = (int) $pdo->lastInsertId();
    }

    if ($portalUserId <= 0) {
      throw new RuntimeException('MySQL no devolvio el ID del acceso cliente.');
    }

    crm_clear_login_failures($pdo, 'client', $username);
    $log = $pdo->prepare('INSERT INTO maintenance_logs (opportunity_id, portal_user_id, type, title, status, scheduled_date, notes, visible_to_client) VALUES (?, ?, "Entrega", "Bitacora ID activada", "Activo", ?, "Portal de mantenimiento habilitado para el cliente.", 1)');
    $log->execute([$opportunityId, $portalUserId, date('Y-m-d')]);

    $verify = $pdo->prepare('SELECT id, opportunity_id, username, password_hash, is_active FROM client_portal_users WHERE id = ? AND opportunity_id = ? LIMIT 1');
    $verify->execute([$portalUserId, $opportunityId]);
    $persisted = $verify->fetch();
    if (!$persisted || (int) $persisted['is_active'] !== 1 || !password_verify($password, (string) $persisted['password_hash'])) {
      throw new RuntimeException('La verificacion posterior al guardado del acceso cliente fallo.');
    }

    if ($startedTransaction) {
      $pdo->commit();
    }

    crm_log_event('portal.access_persisted', [
      'portal_user_id' => $portalUserId,
      'opportunity_id' => $opportunityId,
      'identifier' => crm_mask_identifier($username),
      'driver' => crm_driver($pdo),
      'database' => crm_driver($pdo) === 'mysql' ? (string) (crm_config()['database'] ?? '') : crm_db_path(),
      'config_source' => crm_config_source(),
      'active' => true,
      'password_hash_verified' => true,
      'transaction_committed' => $startedTransaction,
    ]);
  } catch (Throwable $error) {
    if ($startedTransaction && $pdo->inTransaction()) {
      $pdo->rollBack();
    }
    crm_log_event('portal.access_persistence_failed', [
      'opportunity_id' => $opportunityId,
      'driver' => crm_driver($pdo),
      'database' => crm_driver($pdo) === 'mysql' ? (string) (crm_config()['database'] ?? '') : crm_db_path(),
      'config_source' => crm_config_source(),
      'error_class' => get_class($error),
      'error_message' => substr($error->getMessage(), 0, 500),
    ]);
    throw $error;
  }

  return ['created' => true, 'username' => $username, 'password' => $password, 'opportunity' => $opportunity];
}

function crm_reset_client_portal_password(PDO $pdo, int $portalUserId): array
{
  $stmt = $pdo->prepare('
    SELECT cpu.*, o.company_name, o.contact_name, o.contact_email, o.service, o.id AS opportunity_id
    FROM client_portal_users cpu
    JOIN opportunities o ON o.id = cpu.opportunity_id
    WHERE cpu.id = ?
    LIMIT 1
  ');
  $stmt->execute([$portalUserId]);
  $portalUser = $stmt->fetch();
  if (!$portalUser) {
    throw new RuntimeException('No se encontro el acceso Bitacora ID.');
  }

  $password = crm_random_password();
  $passwordHash = password_hash($password, PASSWORD_DEFAULT);
  $update = $pdo->prepare('UPDATE client_portal_users SET password_hash = ?, is_active = 1, password_change_required = 1, password_changed_at = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
  $update->execute([$passwordHash, $portalUserId]);

  $verify = $pdo->prepare('SELECT id, username, password_hash, is_active FROM client_portal_users WHERE id = ? LIMIT 1');
  $verify->execute([$portalUserId]);
  $persisted = $verify->fetch();
  if (!$persisted || (int) $persisted['is_active'] !== 1 || !password_verify($password, (string) $persisted['password_hash'])) {
    crm_log_event('portal.password_persistence_failed', ['portal_user_id' => $portalUserId, 'identifier' => crm_mask_identifier((string) $portalUser['username'])]);
    throw new RuntimeException('La nueva contrasena no pudo verificarse despues del guardado.');
  }
  crm_clear_login_failures($pdo, 'client', (string) $portalUser['username']);
  crm_log_event('portal.password_persisted', ['portal_user_id' => $portalUserId, 'identifier' => crm_mask_identifier((string) $portalUser['username']), 'password_hash_verified' => true]);

  $log = $pdo->prepare('INSERT INTO maintenance_logs (opportunity_id, portal_user_id, type, title, status, scheduled_date, notes, visible_to_client) VALUES (?, ?, "Acceso", "Password Bitacora ID regenerado", "Activo", ?, "El equipo administrativo regenero el acceso del cliente.", 0)');
  $log->execute([(int) $portalUser['opportunity_id'], $portalUserId, date('Y-m-d')]);

  return ['username' => $portalUser['username'], 'password' => $password, 'company_name' => $portalUser['company_name'], 'contact_name' => $portalUser['contact_name'], 'contact_email' => $portalUser['contact_email'], 'service' => $portalUser['service']];
}
function crm_app_url(string $path = ''): string
{
  $base = rtrim(crm_normalize_legacy_url((string) (crm_config()['app_url'] ?? 'https://idindustrial.com.mx/crm')), '/');
  $path = ltrim($path, '/');
  return $path === '' ? $base : $base . '/' . $path;
}

function crm_email_h($value): string
{
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function crm_email_message(string $textBody, $htmlBody = null): array
{
  if ($htmlBody === null || trim((string) $htmlBody) === '') {
    return [
      'headers' => ['Content-Type: text/plain; charset=UTF-8'],
      'body' => $textBody,
    ];
  }

  $boundary = 'crm_' . str_replace('.', '', uniqid('', true));
  $body = '--' . $boundary . "\r\n"
    . "Content-Type: text/plain; charset=UTF-8\r\n"
    . "Content-Transfer-Encoding: 8bit\r\n\r\n"
    . $textBody . "\r\n\r\n"
    . '--' . $boundary . "\r\n"
    . "Content-Type: text/html; charset=UTF-8\r\n"
    . "Content-Transfer-Encoding: 8bit\r\n\r\n"
    . $htmlBody . "\r\n\r\n"
    . '--' . $boundary . '--';

  return [
    'headers' => [
      'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    ],
    'body' => $body,
  ];
}

function crm_normalize_email_list($emails): array
{
  $rawEmails = is_array($emails) ? $emails : preg_split('/[;,]/', (string) $emails);
  $normalized = [];
  foreach ($rawEmails ?: [] as $email) {
    $email = trim((string) $email);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      continue;
    }
    $normalized[strtolower($email)] = $email;
  }
  return array_values($normalized);
}

function crm_email_header_address(string $email, string $name = ''): string
{
  $email = trim(str_replace(["\r", "\n"], '', $email));
  $name = trim(str_replace(["\r", "\n"], '', $name));
  if ($name === '') {
    return '<' . $email . '>';
  }
  return '"' . addcslashes($name, '"\\') . '" <' . $email . '>';
}

function crm_portal_credentials_email_html(string $name, string $project, string $portalUrl, string $username, string $password): string
{
  $safeName = crm_email_h($name);
  $safeProject = crm_email_h($project);
  $safePortalUrl = crm_email_h($portalUrl);
  $safeUsername = crm_email_h($username);
  $safePassword = crm_email_h($password);

  return '<!doctype html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Acceso a Bitacora ID</title>
</head>
<body style="margin:0;padding:0;background:#f4f1eb;font-family:Arial,Helvetica,sans-serif;color:#11151c;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f1eb;margin:0;padding:24px 12px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;max-width:640px;background:#ffffff;border:1px solid #ded6c8;border-radius:14px;overflow:hidden;">
          <tr>
            <td style="background:#111412;padding:26px 28px;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                <tr>
                  <td style="vertical-align:middle;">
                    <div style="font-size:13px;letter-spacing:4px;font-weight:800;color:#f3c433;text-transform:uppercase;">ID Industrial</div>
                    <div style="margin-top:8px;font-size:22px;line-height:1.2;font-weight:800;color:#ffffff;">Bitacora ID</div>
                  </td>
                  <td align="right" style="vertical-align:middle;">
                    <span style="display:inline-block;border:1px solid rgba(243,196,51,.45);border-radius:999px;padding:8px 12px;font-size:12px;font-weight:700;color:#f7e2a5;">Acceso activo</span>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td style="padding:34px 28px 30px;">
              <h1 style="margin:0 0 12px;font-size:30px;line-height:1.12;font-weight:800;color:#11151c;">Tu portal de mantenimiento ya esta listo</h1>
              <p style="margin:0 0 24px;font-size:16px;line-height:1.6;color:#555f6d;">Hola ' . $safeName . ', tu acceso a Bitacora ID fue activado para dar seguimiento a solicitudes, reportes y mantenimiento de tu proyecto.</p>

              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 22px;background:#fbfaf7;border:1px solid #e5dccb;border-radius:12px;">
                <tr>
                  <td style="padding:18px 20px;">
                    <div style="font-size:11px;letter-spacing:2.8px;text-transform:uppercase;font-weight:800;color:#9b7200;">Proyecto</div>
                    <div style="margin-top:8px;font-size:18px;line-height:1.35;font-weight:800;color:#11151c;">' . $safeProject . '</div>
                  </td>
                </tr>
              </table>

              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 24px;">
                <tr>
                  <td align="center" bgcolor="#d6a91f" style="border-radius:10px;">
                    <a href="' . $safePortalUrl . '" style="display:block;padding:16px 22px;font-size:16px;font-weight:800;color:#11151c;text-decoration:none;">Entrar a Bitacora ID</a>
                  </td>
                </tr>
              </table>

              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 22px;border:1px solid #e5dccb;border-radius:12px;overflow:hidden;">
                <tr>
                  <td style="padding:16px 18px;background:#f6eed8;border-bottom:1px solid #e5dccb;font-size:12px;letter-spacing:2.4px;text-transform:uppercase;font-weight:800;color:#7d5b00;">Credenciales de acceso</td>
                </tr>
                <tr>
                  <td style="padding:18px;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                      <tr>
                        <td style="padding:0 0 14px;font-size:13px;font-weight:800;color:#555f6d;width:130px;">Usuario</td>
                        <td style="padding:0 0 14px;font-size:15px;font-weight:800;color:#11151c;word-break:break-word;">' . $safeUsername . '</td>
                      </tr>
                      <tr>
                        <td style="padding:0;font-size:13px;font-weight:800;color:#555f6d;width:130px;">Password temporal</td>
                        <td style="padding:0;font-size:15px;font-weight:800;color:#11151c;word-break:break-word;">' . $safePassword . '</td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>

              <div style="border-left:4px solid #d6a91f;background:#fff9e8;border-radius:10px;padding:16px 18px;margin:0 0 24px;">
                <p style="margin:0;font-size:14px;line-height:1.6;color:#4d5662;"><strong>Recomendacion de seguridad:</strong> conserva estos datos solo con tu equipo autorizado. Al entrar por primera vez, cambia tu password desde el portal.</p>
              </div>

              <p style="margin:0;font-size:13px;line-height:1.6;color:#6b7280;">Si el boton no abre, copia este enlace en tu navegador:<br><a href="' . $safePortalUrl . '" style="color:#9b7200;text-decoration:underline;word-break:break-all;">' . $safePortalUrl . '</a></p>
            </td>
          </tr>
          <tr>
            <td style="padding:20px 28px;background:#f7f4ee;border-top:1px solid #e5dccb;">
              <p style="margin:0;font-size:12px;line-height:1.6;color:#69727f;">ID Industrial - CRM para servicios industriales</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>';
}

function crm_send_email(string $to, string $subject, string $textBody, $htmlBody = null, array $options = []): bool
{
  $config = crm_config();
  $smtp = is_array($config['smtp'] ?? null) ? $config['smtp'] : [];
  $toList = crm_normalize_email_list($to);
  $ccList = crm_normalize_email_list($options['cc'] ?? []);
  $replyToList = crm_normalize_email_list($options['reply_to'] ?? []);
  if (!$toList) {
    return false;
  }
  if (!empty($smtp['enabled'])) {
    return crm_smtp_mail($smtp, $toList, $subject, $textBody, $htmlBody, [
      'cc' => $ccList,
      'reply_to' => $replyToList,
    ]);
  }

  $fromEmail = (string) ($smtp['from_email'] ?? 'no-reply@idindustrial.com.mx');
  $fromName = (string) ($smtp['from_name'] ?? 'ID Industrial');
  $emailMessage = crm_email_message($textBody, $htmlBody);
  $headers = array_merge([
    'MIME-Version: 1.0',
    'From: ' . $fromName . ' <' . $fromEmail . '>',
  ], $emailMessage['headers']);
  if ($ccList) {
    $headers[] = 'Cc: ' . implode(', ', array_map('crm_email_header_address', $ccList));
  }
  if ($replyToList) {
    $headers[] = 'Reply-To: ' . implode(', ', array_map('crm_email_header_address', $replyToList));
  }

  return @mail(implode(', ', $toList), $subject, $emailMessage['body'], implode("\r\n", $headers));
}
function crm_smtp_read($socket): string
{
  $response = '';
  while (!feof($socket)) {
    $line = fgets($socket, 515);
    if ($line === false) {
      break;
    }
    $response .= $line;
    if (preg_match('/^\d{3} /', $line)) {
      break;
    }
  }
  return $response;
}

function crm_smtp_command($socket, string $command, array $codes): bool
{
  fwrite($socket, $command . "\r\n");
  $response = crm_smtp_read($socket);
  $code = (int) substr($response, 0, 3);
  return in_array($code, $codes, true);
}

function crm_smtp_mail(array $smtp, $to, string $subject, string $textBody, $htmlBody = null, array $options = []): bool
{
  $host = (string) ($smtp['host'] ?? '');
  $port = (int) ($smtp['port'] ?? 465);
  $secure = strtolower((string) ($smtp['secure'] ?? 'ssl'));
  $username = (string) ($smtp['username'] ?? '');
  $password = (string) ($smtp['password'] ?? '');
  $fromEmail = (string) ($smtp['from_email'] ?? $username);
  $fromName = (string) ($smtp['from_name'] ?? 'ID Industrial');
  $toList = crm_normalize_email_list($to);
  $ccList = crm_normalize_email_list($options['cc'] ?? []);
  $replyToList = crm_normalize_email_list($options['reply_to'] ?? []);
  if ($host === '' || $username === '' || $password === '' || $fromEmail === '' || !$toList) {
    return false;
  }

  $target = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
  $socket = @stream_socket_client($target, $errno, $errstr, 20);
  if (!$socket) {
    error_log('CRM SMTP connection failed: ' . $errstr . ' (' . $errno . ')');
    return false;
  }
  stream_set_timeout($socket, 20);
  $ready = crm_smtp_read($socket);
  if ((int) substr($ready, 0, 3) !== 220) {
    error_log('CRM SMTP server not ready: ' . trim($ready));
    fclose($socket);
    return false;
  }

  $domain = preg_replace('/^mail\./', '', $host) ?: 'localhost';
  if (!crm_smtp_command($socket, 'EHLO ' . $domain, [250])) {
    error_log('CRM SMTP EHLO failed for domain ' . $domain);
    fclose($socket);
    return false;
  }
  if ($secure === 'tls') {
    if (!crm_smtp_command($socket, 'STARTTLS', [220]) || !stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
      fclose($socket);
      return false;
    }
    if (!crm_smtp_command($socket, 'EHLO ' . $domain, [250])) {
      fclose($socket);
      return false;
    }
  }

  $ok = crm_smtp_command($socket, 'AUTH LOGIN', [334])
    && crm_smtp_command($socket, base64_encode($username), [334])
    && crm_smtp_command($socket, base64_encode($password), [235])
    && crm_smtp_command($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
  foreach (array_merge($toList, $ccList) as $recipientEmail) {
    $ok = $ok && crm_smtp_command($socket, 'RCPT TO:<' . $recipientEmail . '>', [250, 251]);
  }
  $ok = $ok && crm_smtp_command($socket, 'DATA', [354]);
  if (!$ok) {
    error_log('CRM SMTP envelope/auth/data command failed for from ' . crm_mask_identifier($fromEmail) . ' to ' . implode(', ', array_map('crm_mask_identifier', array_merge($toList, $ccList))));
    fclose($socket);
    return false;
  }

  $emailMessage = crm_email_message($textBody, $htmlBody);
  $headers = array_merge([
    'Date: ' . date('r'),
    'From: ' . $fromName . ' <' . $fromEmail . '>',
    'To: ' . implode(', ', array_map('crm_email_header_address', $toList)),
    'Subject: ' . $subject,
    'MIME-Version: 1.0',
  ], $emailMessage['headers']);
  if ($ccList) {
    $headers[] = 'Cc: ' . implode(', ', array_map('crm_email_header_address', $ccList));
  }
  if ($replyToList) {
    $headers[] = 'Reply-To: ' . implode(', ', array_map('crm_email_header_address', $replyToList));
  }
  $message = implode("\r\n", $headers) . "\r\n\r\n" . $emailMessage['body'];
  $message = preg_replace('/^\./m', '..', $message);
  fwrite($socket, $message . "\r\n.\r\n");
  $finalResponse = crm_smtp_read($socket);
  $sent = in_array((int) substr($finalResponse, 0, 3), [250], true);
  if (!$sent) {
    error_log('CRM SMTP final send failed: ' . trim($finalResponse));
  }
  crm_smtp_command($socket, 'QUIT', [221, 250]);
  fclose($socket);
  return $sent;
}
function crm_send_portal_credentials(array $opportunity, string $username, string $password): bool
{
  $email = trim((string) ($opportunity['contact_email'] ?? ''));
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    return false;
  }

  $contact = trim((string) ($opportunity['contact_name'] ?? ''));
  $company = trim((string) ($opportunity['company_name'] ?? ''));
  $service = trim((string) ($opportunity['service'] ?? ''));
  $name = $contact !== '' ? $contact : ($company !== '' ? $company : 'cliente');
  $project = $service !== '' ? $service : 'Mantenimiento ID Industrial';
  $portalUrl = crm_app_url('portal');
  $plainBody = "Hola " . $name . ",\n\n"
    . "Tu acceso a Bitacora ID ya esta activo.\n\n"
    . "Proyecto: " . $project . "\n"
    . "Link: " . $portalUrl . "\n"
    . "Usuario: " . $username . "\n"
    . "Password temporal: " . $password . "\n\n"
    . "Por seguridad, conserva estos datos y no los compartas fuera de tu equipo autorizado. Cambia tu password al entrar por primera vez.\n\n"
    . "ID Industrial";
  $htmlBody = crm_portal_credentials_email_html($name, $project, $portalUrl, $username, $password);

  return crm_send_email($email, 'Acceso a Bitacora ID - ID Industrial', $plainBody, $htmlBody);
}
function crm_portal_users_by_identifier(PDO $pdo, string $identifier): array
{
  $identifier = strtolower(trim($identifier));
  if ($identifier === '') {
    return [];
  }

  $stmt = $pdo->prepare('
    SELECT cpu.*, o.company_name, o.contact_name, o.contact_email, o.contact_phone, o.service, o.status AS opportunity_status, o.next_action_date, o.notes AS opportunity_notes
    FROM client_portal_users cpu
    JOIN opportunities o ON o.id = cpu.opportunity_id
    WHERE cpu.is_active = 1
      AND (LOWER(cpu.username) = ? OR LOWER(o.contact_email) = ?)
    ORDER BY CASE WHEN LOWER(cpu.username) = ? THEN 0 ELSE 1 END, cpu.id DESC
  ');
  $stmt->execute([$identifier, $identifier, $identifier]);
  return $stmt->fetchAll();
}

function crm_portal_user_by_username(PDO $pdo, string $username): ?array
{
  $users = crm_portal_users_by_identifier($pdo, $username);
  return $users[0] ?? null;
}

function crm_update_portal_last_login(PDO $pdo, int $portalUserId): void
{
  $stmt = $pdo->prepare('UPDATE client_portal_users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?');
  $stmt->execute([$portalUserId]);
}

