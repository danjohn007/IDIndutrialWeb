<?php
declare(strict_types=1);

function crm_db_path(): string
{
  return __DIR__ . '/../data/idindustrial_crm.sqlite';
}

function crm_db(): PDO
{
  static $pdo = null;
  if ($pdo instanceof PDO) {
    return $pdo;
  }

  $dataDir = dirname(crm_db_path());
  if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
  }

  $pdo = new PDO('sqlite:' . crm_db_path());
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
  $pdo->exec('PRAGMA foreign_keys = ON');
  crm_migrate($pdo);
  crm_seed($pdo);
  return $pdo;
}

function crm_migrate(PDO $pdo): void
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
      VALUES ((SELECT id FROM clients WHERE name = ?), ?, ?, ?, ?, ?, ?, ?, date("now", ?), ?, "Referido / cartera")
    ');
    $quoteStmt = $pdo->prepare('INSERT INTO quotes (opportunity_id, quote_code, amount, status, probability, sent_at, valid_until) VALUES (?, ?, ?, ?, ?, date("now", "-2 days"), date("now", "+15 days"))');
    $activityStmt = $pdo->prepare('INSERT INTO activities (opportunity_id, type, summary, due_date) VALUES (?, ?, ?, date("now", ?))');
    foreach ($seed as $index => $row) {
      $opStmt->execute([$row[0], $row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $row[6], $row[7], $row[8]]);
      $opportunityId = (int) $pdo->lastInsertId();
      $quoteStmt->execute([$opportunityId, 'ID-' . date('Y') . '-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT), $row[6], $row[5] === 'Cotizacion enviada' ? 'Enviada' : 'En elaboracion', $row[5] === 'Cotizacion enviada' ? 65 : 40]);
      $activityStmt->execute([$opportunityId, 'Seguimiento comercial', 'Actualizar avance y siguiente paso tecnico.', $row[7]]);
    }
  }
}

function crm_public_clients(int $limit = 10): array
{
  $stmt = crm_db()->prepare('SELECT name, segment FROM clients WHERE is_public = 1 ORDER BY name LIMIT ?');
  $stmt->bindValue(1, $limit, PDO::PARAM_INT);
  $stmt->execute();
  return $stmt->fetchAll();
}

function crm_capture_public_lead(array $data): void
{
  try {
    $pdo = crm_db();
    $stmt = $pdo->prepare('
      INSERT INTO opportunities (company_name, contact_name, contact_email, contact_phone, service, source, status, priority, estimated_value, next_action_date, notes)
      VALUES (?, ?, ?, ?, ?, "Formulario web", "Nueva solicitud", "Alta", 0, date("now", "+1 day"), ?)
    ');
    $stmt->execute([
      trim((string) ($data['company_name'] ?? 'Sin empresa')),
      trim((string) ($data['contact_name'] ?? 'Contacto web')),
      trim((string) ($data['contact_email'] ?? '')),
      trim((string) ($data['contact_phone'] ?? '')),
      trim((string) ($data['service'] ?? 'Por definir')),
      trim((string) ($data['notes'] ?? '')),
    ]);
    $opportunityId = (int) $pdo->lastInsertId();
    $activity = $pdo->prepare('INSERT INTO activities (opportunity_id, type, summary, due_date) VALUES (?, "Primer contacto", "Contactar lead recibido desde el sitio.", date("now", "+1 day"))');
    $activity->execute([$opportunityId]);
  } catch (Throwable $error) {
    error_log('CRM lead capture failed: ' . $error->getMessage());
  }
}
