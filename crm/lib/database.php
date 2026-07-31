<?php
declare(strict_types=1);

function crm_config(): array
{
  $config = [
    'driver' => 'sqlite',
    'host' => 'localhost',
    'database' => '',
    'username' => '',
    'password' => '',
    'charset' => 'utf8mb4',
    'app_url' => 'https://idindustrial.com.mx/sistema/crm',
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

  $configPath = __DIR__ . '/../config.php';
  if (is_file($configPath)) {
    $customConfig = require $configPath;
    if (is_array($customConfig)) {
      $config = array_replace_recursive($config, $customConfig);
    }
  }

  return $config;
}

function crm_db_path(): string
{
  return __DIR__ . '/../data/idindustrial_crm.sqlite';
}

function crm_driver(?PDO $pdo = null): string
{
  if ($pdo instanceof PDO) {
    return (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
  }

  return (string) crm_config()['driver'];
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

function crm_ensure_columns(PDO $pdo): void
{
  $isMysql = crm_driver($pdo) === 'mysql';
  $columns = [
    'clients' => [
      'lifecycle_stage' => $isMysql
        ? "ALTER TABLE clients ADD COLUMN lifecycle_stage VARCHAR(40) NOT NULL DEFAULT 'Cliente' AFTER segment"
        : "ALTER TABLE clients ADD COLUMN lifecycle_stage TEXT NOT NULL DEFAULT 'Cliente'",
      'converted_at' => $isMysql
        ? 'ALTER TABLE clients ADD COLUMN converted_at DATETIME NULL AFTER created_at'
        : 'ALTER TABLE clients ADD COLUMN converted_at TEXT NULL',
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
    ],
  ];

  foreach ($columns as $table => $tableColumns) {
    foreach ($tableColumns as $column => $definition) {
      if (!crm_column_exists($pdo, $table, $column)) {
        $pdo->exec($definition);
      }
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

    CREATE TABLE IF NOT EXISTS quotes (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      opportunity_id INTEGER NOT NULL,
      quote_code TEXT NOT NULL UNIQUE,
      amount REAL NOT NULL DEFAULT 0,
      status TEXT NOT NULL DEFAULT 'En elaboracion',
      probability INTEGER NOT NULL DEFAULT 40,
      sent_at TEXT,
      valid_until TEXT,
      created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
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
      admin_response TEXT,
      status TEXT NOT NULL DEFAULT 'Recibida',
      priority TEXT NOT NULL DEFAULT 'Media',
      created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
      resolved_at TEXT,
      last_admin_update_at TEXT,
      FOREIGN KEY (opportunity_id) REFERENCES opportunities(id) ON DELETE CASCADE,
      FOREIGN KEY (portal_user_id) REFERENCES client_portal_users(id) ON DELETE CASCADE
    );

    CREATE INDEX IF NOT EXISTS idx_opportunities_status ON opportunities(status);
    CREATE INDEX IF NOT EXISTS idx_opportunities_next_action ON opportunities(next_action_date);
    CREATE INDEX IF NOT EXISTS idx_quotes_status ON quotes(status);
    CREATE INDEX IF NOT EXISTS idx_activities_due ON activities(completed_at, due_date);
    CREATE INDEX IF NOT EXISTS idx_maintenance_logs_opportunity ON maintenance_logs(opportunity_id, scheduled_date);
    CREATE INDEX IF NOT EXISTS idx_client_requests_portal ON client_requests(portal_user_id, created_at);
    CREATE INDEX IF NOT EXISTS idx_client_requests_status ON client_requests(status, updated_at);
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

    CREATE TABLE IF NOT EXISTS quotes (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT,
      opportunity_id INT UNSIGNED NOT NULL,
      quote_code VARCHAR(60) NOT NULL,
      amount DECIMAL(12,2) NOT NULL DEFAULT 0,
      status VARCHAR(80) NOT NULL DEFAULT 'En elaboracion',
      probability TINYINT UNSIGNED NOT NULL DEFAULT 40,
      sent_at DATE NULL,
      valid_until DATE NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY uq_quotes_code (quote_code),
      KEY idx_quotes_opportunity (opportunity_id),
      KEY idx_quotes_status (status),
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
      admin_response TEXT NULL,
      status VARCHAR(80) NOT NULL DEFAULT 'Recibida',
      priority VARCHAR(40) NOT NULL DEFAULT 'Media',
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

function crm_capture_public_lead(array $data): bool
{
  try {
    $pdo = crm_db();
    $pdo->beginTransaction();
    $clientId = crm_find_or_create_prospect_client($pdo, $data);
    $companyName = trim((string) ($data['company_name'] ?? '')) ?: 'Prospecto web';
    $contactName = trim((string) ($data['contact_name'] ?? '')) ?: $companyName;
    $service = trim((string) ($data['service'] ?? '')) ?: 'Por definir';
    $notes = trim((string) ($data['notes'] ?? ''));

    $stmt = $pdo->prepare('
      INSERT INTO opportunities (client_id, company_name, contact_name, contact_email, contact_phone, service, source, status, priority, estimated_value, next_action_date, notes)
      VALUES (?, ?, ?, ?, ?, ?, "Formulario web", "Nueva solicitud", "Alta", 0, ?, ?)
    ');
    $stmt->execute([
      $clientId,
      $companyName,
      $contactName,
      trim((string) ($data['contact_email'] ?? '')),
      trim((string) ($data['contact_phone'] ?? '')),
      $service,
      date('Y-m-d', strtotime('+1 day')),
      $notes,
    ]);
    $opportunityId = (int) $pdo->lastInsertId();

    $quote = $pdo->prepare('INSERT INTO quotes (opportunity_id, quote_code, amount, status, probability, sent_at, valid_until) VALUES (?, ?, 0, "Solicitud recibida", 10, NULL, NULL)');
    $quote->execute([$opportunityId, crm_next_quote_code($pdo, 'SOL')]);

    $activity = $pdo->prepare('INSERT INTO activities (opportunity_id, type, summary, due_date) VALUES (?, "Primer contacto", "Contactar prospecto y preparar cotizacion.", ?)');
    $activity->execute([$opportunityId, date('Y-m-d', strtotime('+1 day'))]);
    $pdo->commit();
    return true;
  } catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
      $pdo->rollBack();
    }
    error_log('CRM lead capture failed: ' . $error->getMessage());
    return false;
  }
}

function crm_random_password(int $length = 12): string
{
  $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
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
  $stmt = $pdo->prepare('SELECT COUNT(*) FROM client_portal_users WHERE username = ?');
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
  $stmt = $pdo->prepare('SELECT * FROM opportunities WHERE id = ? LIMIT 1');
  $stmt->execute([$opportunityId]);
  $opportunity = $stmt->fetch();
  if (!$opportunity) {
    throw new RuntimeException('No se encontro la oportunidad para activar Bitacora ID.');
  }

  $existingStmt = $pdo->prepare('SELECT * FROM client_portal_users WHERE opportunity_id = ? LIMIT 1');
  $existingStmt->execute([$opportunityId]);
  $existing = $existingStmt->fetch();
  if ($existing && (int) $existing['is_active'] === 1) {
    return ['created' => false, 'username' => $existing['username'], 'password' => null, 'opportunity' => $opportunity];
  }

  $password = crm_random_password();
  $passwordHash = password_hash($password, PASSWORD_DEFAULT);
  if ($existing) {
    $update = $pdo->prepare('UPDATE client_portal_users SET password_hash = ?, is_active = 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
    $update->execute([$passwordHash, (int) $existing['id']]);
    $username = (string) $existing['username'];
    $portalUserId = (int) $existing['id'];
  } else {
    $username = crm_unique_portal_username($pdo, $opportunity);
    $insert = $pdo->prepare('INSERT INTO client_portal_users (opportunity_id, client_id, username, password_hash, is_active) VALUES (?, ?, ?, ?, 1)');
    $insert->execute([$opportunityId, $opportunity['client_id'] ?: null, $username, $passwordHash]);
    $portalUserId = (int) $pdo->lastInsertId();
  }

  $log = $pdo->prepare('INSERT INTO maintenance_logs (opportunity_id, portal_user_id, type, title, status, scheduled_date, notes, visible_to_client) VALUES (?, ?, "Entrega", "Bitacora ID activada", "Activo", ?, "Portal de mantenimiento habilitado para el cliente.", 1)');
  $log->execute([$opportunityId, $portalUserId, date('Y-m-d')]);

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
  $update = $pdo->prepare('UPDATE client_portal_users SET password_hash = ?, is_active = 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
  $update->execute([$passwordHash, $portalUserId]);

  $log = $pdo->prepare('INSERT INTO maintenance_logs (opportunity_id, portal_user_id, type, title, status, scheduled_date, notes, visible_to_client) VALUES (?, ?, "Acceso", "Password Bitacora ID regenerado", "Activo", ?, "El equipo administrativo regenero el acceso del cliente.", 0)');
  $log->execute([(int) $portalUser['opportunity_id'], $portalUserId, date('Y-m-d')]);

  return ['username' => $portalUser['username'], 'password' => $password, 'company_name' => $portalUser['company_name'], 'contact_name' => $portalUser['contact_name'], 'contact_email' => $portalUser['contact_email'], 'service' => $portalUser['service']];
}
function crm_app_url(string $path = ''): string
{
  $base = rtrim((string) (crm_config()['app_url'] ?? 'https://idindustrial.com.mx/sistema/crm'), '/');
  $path = ltrim($path, '/');
  return $path === '' ? $base : $base . '/' . $path;
}

function crm_send_email(string $to, string $subject, string $body): bool
{
  $config = crm_config();
  $smtp = is_array($config['smtp'] ?? null) ? $config['smtp'] : [];
  if (!empty($smtp['enabled'])) {
    return crm_smtp_mail($smtp, $to, $subject, $body);
  }

  $fromEmail = (string) ($smtp['from_email'] ?? 'no-reply@idindustrial.com.mx');
  $fromName = (string) ($smtp['from_name'] ?? 'ID Industrial');
  $headers = [
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'From: ' . $fromName . ' <' . $fromEmail . '>',
  ];
  return @mail($to, $subject, $body, implode("\r\n", $headers));
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

function crm_smtp_mail(array $smtp, string $to, string $subject, string $body): bool
{
  $host = (string) ($smtp['host'] ?? '');
  $port = (int) ($smtp['port'] ?? 465);
  $secure = strtolower((string) ($smtp['secure'] ?? 'ssl'));
  $username = (string) ($smtp['username'] ?? '');
  $password = (string) ($smtp['password'] ?? '');
  $fromEmail = (string) ($smtp['from_email'] ?? $username);
  $fromName = (string) ($smtp['from_name'] ?? 'ID Industrial');
  if ($host === '' || $username === '' || $password === '' || $fromEmail === '') {
    return false;
  }

  $target = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
  $socket = @stream_socket_client($target, $errno, $errstr, 20);
  if (!$socket) {
    error_log('CRM SMTP connection failed: ' . $errstr);
    return false;
  }
  stream_set_timeout($socket, 20);
  $ready = crm_smtp_read($socket);
  if ((int) substr($ready, 0, 3) !== 220) {
    fclose($socket);
    return false;
  }

  $domain = preg_replace('/^mail\./', '', $host) ?: 'localhost';
  if (!crm_smtp_command($socket, 'EHLO ' . $domain, [250])) {
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
    && crm_smtp_command($socket, 'MAIL FROM:<' . $fromEmail . '>', [250])
    && crm_smtp_command($socket, 'RCPT TO:<' . $to . '>', [250, 251])
    && crm_smtp_command($socket, 'DATA', [354]);
  if (!$ok) {
    fclose($socket);
    return false;
  }

  $headers = [
    'Date: ' . date('r'),
    'From: ' . $fromName . ' <' . $fromEmail . '>',
    'To: <' . $to . '>',
    'Subject: ' . $subject,
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
  ];
  $message = implode("\r\n", $headers) . "\r\n\r\n" . $body;
  $message = preg_replace('/^\./m', '..', $message);
  fwrite($socket, $message . "\r\n.\r\n");
  $sent = in_array((int) substr(crm_smtp_read($socket), 0, 3), [250], true);
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
  $body = "Hola " . $name . ",\n\n"
    . "Tu acceso a Bitacora ID ya esta activo.\n\n"
    . "Proyecto: " . ($service !== '' ? $service : 'Mantenimiento ID Industrial') . "\n"
    . "Link: " . crm_app_url('cliente.php') . "\n"
    . "Usuario: " . $username . "\n"
    . "Password: " . $password . "\n\n"
    . "Por seguridad, conserva estos datos y no los compartas fuera de tu equipo autorizado.\n\n"
    . "ID Industrial";

  return crm_send_email($email, 'Acceso a Bitacora ID - ID Industrial', $body);
}

function crm_portal_user_by_username(PDO $pdo, string $username): ?array
{
  $stmt = $pdo->prepare('
    SELECT cpu.*, o.company_name, o.contact_name, o.contact_email, o.contact_phone, o.service, o.status AS opportunity_status, o.next_action_date, o.notes AS opportunity_notes
    FROM client_portal_users cpu
    JOIN opportunities o ON o.id = cpu.opportunity_id
    WHERE cpu.username = ? AND cpu.is_active = 1
    LIMIT 1
  ');
  $stmt->execute([trim($username)]);
  $user = $stmt->fetch();
  return $user ?: null;
}

function crm_update_portal_last_login(PDO $pdo, int $portalUserId): void
{
  $stmt = $pdo->prepare('UPDATE client_portal_users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?');
  $stmt->execute([$portalUserId]);
}
