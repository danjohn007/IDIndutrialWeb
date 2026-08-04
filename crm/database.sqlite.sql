PRAGMA foreign_keys = ON;

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

CREATE INDEX IF NOT EXISTS idx_notifications_recipient ON notifications(recipient_type, portal_user_id, is_read, created_at);
CREATE INDEX IF NOT EXISTS idx_notifications_request ON notifications(client_request_id);
