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

CREATE INDEX IF NOT EXISTS idx_opportunities_status ON opportunities(status);
CREATE INDEX IF NOT EXISTS idx_opportunities_next_action ON opportunities(next_action_date);
CREATE INDEX IF NOT EXISTS idx_quotes_status ON quotes(status);
CREATE INDEX IF NOT EXISTS idx_activities_due ON activities(completed_at, due_date);

INSERT OR IGNORE INTO users (name, email, password_hash, role)
VALUES (
  'Administrador',
  'admin@idindustrial.com.mx',
  '$2y$10$rwvvn7OEgovO6E76JAKIhOqH7jKBFSs3tJYdg0HK97JlOnULOYAxe',
  'superadmin'
);

INSERT OR IGNORE INTO clients (name, segment, city, is_public, notes) VALUES
  ('Daechang', 'Automotriz', 'Queretaro', 1, 'Cliente de referencia para credibilidad comercial.'),
  ('DR-ENC', 'Manufactura', 'Queretaro', 1, 'Cliente de referencia para credibilidad comercial.'),
  ('Pollux', 'Industrial', 'Bajio', 1, 'Cliente de referencia para credibilidad comercial.'),
  ('PSSL Seguridad', 'Seguridad', 'Queretaro', 1, 'Cliente de referencia para credibilidad comercial.'),
  ('AB Mexco', 'Manufactura', 'Queretaro', 1, 'Cliente de referencia para credibilidad comercial.'),
  ('Deadong HEMEX', 'Automotriz', 'Bajio', 1, 'Cliente de referencia para credibilidad comercial.'),
  ('Harman', 'Electronica', 'Queretaro', 1, 'Cliente de referencia para credibilidad comercial.'),
  ('Samsung', 'Electronica', 'Queretaro', 1, 'Cliente de referencia para credibilidad comercial.'),
  ('Michelin', 'Manufactura', 'Queretaro', 1, 'Cliente de referencia para credibilidad comercial.'),
  ('AIQ', 'Infraestructura', 'Queretaro', 1, 'Cliente de referencia para credibilidad comercial.');