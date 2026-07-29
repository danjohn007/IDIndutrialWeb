<?php
declare(strict_types=1);

require __DIR__ . '/lib/database.php';

$sessionDir = __DIR__ . '/data/sessions';
if (!is_dir($sessionDir)) {
  mkdir($sessionDir, 0755, true);
}
session_save_path($sessionDir);
session_start();
$pdo = crm_db();
$statuses = [
  'Nueva solicitud',
  'Contacto realizado',
  'Levantamiento programado',
  'Ingenieria en desarrollo',
  'Cotizacion enviada',
  'Seguimiento',
  'Negociacion',
  'Proyecto ganado',
  'Proyecto perdido',
];
$quoteStatuses = ['En elaboracion', 'Enviada', 'En revision cliente', 'Aprobada', 'Perdida'];

function h($value): string
{
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function crm_money($value): string
{
  return '$' . number_format((float) $value, 2);
}

function crm_require_login(): void
{
  if (empty($_SESSION['crm_user'])) {
    header('Location: index.php');
    exit;
  }
}

function crm_token(): string
{
  if (empty($_SESSION['crm_token'])) {
    $_SESSION['crm_token'] = bin2hex(random_bytes(16));
  }
  return $_SESSION['crm_token'];
}

function crm_check_token(): void
{
  if (!hash_equals($_SESSION['crm_token'] ?? '', $_POST['token'] ?? '')) {
    http_response_code(403);
    exit('Token invalido.');
  }
}

if (isset($_GET['logout'])) {
  session_destroy();
  header('Location: index.php');
  exit;
}

$loginError = '';
if (($_POST['action'] ?? '') === 'login') {
  $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
  $stmt->execute([trim($_POST['email'] ?? '')]);
  $user = $stmt->fetch();
  if ($user && password_verify((string) ($_POST['password'] ?? ''), $user['password_hash'])) {
    $_SESSION['crm_user'] = ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email'], 'role' => $user['role']];
    crm_token();
    header('Location: index.php');
    exit;
  }
  $loginError = 'Credenciales incorrectas.';
}

if (empty($_SESSION['crm_user'])):
?>
<!doctype html>
<html lang="es-MX">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ID Industrial CRM | Acceso</title>
  <link rel="stylesheet" href="../assets/css/crm.css">
</head>
<body class="crm-login">
  <main class="crm-login__card">
    <div class="crm-login__brand">
      <img src="../assets/img/logo-idindustrial-small.webp" alt="ID Industrial" width="280" height="74">
      <div>
        <strong>ID CRM</strong>
        <span>Gestion comercial</span>
      </div>
    </div>
    <h1>Acceso administrador</h1>
    <p>Seguimiento de leads, cotizaciones y clientes industriales.</p>
    <?php if ($loginError): ?><p class="crm-alert"><?php echo h($loginError); ?></p><?php endif; ?>
    <form method="post">
      <input type="hidden" name="action" value="login">
      <label>
        Correo
        <input type="email" name="email" value="admin@idindustrial.com.mx" autocomplete="username" required>
      </label>
      <label>
        Password
        <input type="password" name="password" autocomplete="current-password" required>
      </label>
      <button class="crm-button" type="submit">Entrar al CRM</button>
    </form>
    <p><small>Demo inicial: admin@idindustrial.com.mx / IDIndustrial2026!</small></p>
  </main>
</body>
</html>
<?php
exit;
endif;

crm_require_login();

if (($_POST['action'] ?? '') === 'create_opportunity') {
  crm_check_token();
  $stmt = $pdo->prepare('
    INSERT INTO opportunities (company_name, contact_name, contact_email, contact_phone, service, source, status, priority, estimated_value, next_action_date, notes)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
  ');
  $stmt->execute([
    trim($_POST['company_name'] ?? ''),
    trim($_POST['contact_name'] ?? ''),
    trim($_POST['contact_email'] ?? ''),
    trim($_POST['contact_phone'] ?? ''),
    trim($_POST['service'] ?? 'Por definir'),
    trim($_POST['source'] ?? 'Captura manual'),
    trim($_POST['status'] ?? 'Nueva solicitud'),
    trim($_POST['priority'] ?? 'Media'),
    (float) ($_POST['estimated_value'] ?? 0),
    $_POST['next_action_date'] ?: null,
    trim($_POST['notes'] ?? ''),
  ]);
  $opportunityId = (int) $pdo->lastInsertId();
  $activity = $pdo->prepare('INSERT INTO activities (opportunity_id, type, summary, due_date) VALUES (?, "Primer contacto", "Validar requerimiento tecnico y siguiente paso.", ?)');
  $activity->execute([$opportunityId, $_POST['next_action_date'] ?: date('Y-m-d', strtotime('+1 day'))]);
  header('Location: index.php?view=opportunities');
  exit;
}

if (($_POST['action'] ?? '') === 'update_opportunity') {
  crm_check_token();
  $stmt = $pdo->prepare('UPDATE opportunities SET status = ?, priority = ?, estimated_value = ?, next_action_date = ?, notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
  $stmt->execute([
    trim($_POST['status'] ?? 'Nueva solicitud'),
    trim($_POST['priority'] ?? 'Media'),
    (float) ($_POST['estimated_value'] ?? 0),
    $_POST['next_action_date'] ?: null,
    trim($_POST['notes'] ?? ''),
    (int) $_POST['opportunity_id'],
  ]);
  $activity = $pdo->prepare('INSERT INTO activities (opportunity_id, type, summary, due_date) VALUES (?, "Actualizacion", ?, ?)');
  $activity->execute([(int) $_POST['opportunity_id'], 'Estatus actualizado a ' . trim($_POST['status'] ?? ''), $_POST['next_action_date'] ?: null]);
  header('Location: index.php?view=opportunities');
  exit;
}

if (($_POST['action'] ?? '') === 'update_quote') {
  crm_check_token();
  $stmt = $pdo->prepare('UPDATE quotes SET amount = ?, status = ?, probability = ?, valid_until = ? WHERE id = ?');
  $stmt->execute([
    (float) ($_POST['amount'] ?? 0),
    trim($_POST['status'] ?? 'En elaboracion'),
    max(0, min(100, (int) ($_POST['probability'] ?? 40))),
    $_POST['valid_until'] ?: null,
    (int) $_POST['quote_id'],
  ]);
  header('Location: index.php?view=quotes');
  exit;
}

$view = $_GET['view'] ?? 'dashboard';
$token = crm_token();
$counts = [
  'leads' => (int) $pdo->query('SELECT COUNT(*) FROM opportunities')->fetchColumn(),
  'clients' => (int) $pdo->query('SELECT COUNT(*) FROM clients')->fetchColumn(),
  'open_quotes' => (int) $pdo->query("SELECT COUNT(*) FROM quotes WHERE status NOT IN ('Aprobada', 'Perdida')")->fetchColumn(),
  'pending' => 0,
];
$quoteTotal = (float) $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM quotes WHERE status NOT IN ('Perdida')")->fetchColumn();
$wonTotal = (float) $pdo->query("SELECT COALESCE(SUM(estimated_value), 0) FROM opportunities WHERE status = 'Proyecto ganado'")->fetchColumn();
$opportunities = $pdo->query('SELECT * FROM opportunities ORDER BY updated_at DESC, created_at DESC')->fetchAll();
$quotes = $pdo->query('
  SELECT q.*, o.company_name, o.service, o.status AS opportunity_status
  FROM quotes q
  JOIN opportunities o ON o.id = q.opportunity_id
  ORDER BY q.created_at DESC
')->fetchAll();
$clients = $pdo->query('SELECT * FROM clients ORDER BY is_public DESC, name')->fetchAll();
$pendingStmt = $pdo->prepare('SELECT COUNT(*) FROM activities WHERE completed_at IS NULL AND (due_date IS NULL OR due_date <= ?)');
$pendingStmt->execute([date('Y-m-d', strtotime('+2 days'))]);
$counts['pending'] = (int) $pendingStmt->fetchColumn();
$statusRows = $pdo->query('SELECT status, COUNT(*) AS total FROM opportunities GROUP BY status ORDER BY total DESC')->fetchAll();
$monthlySql = crm_driver($pdo) === 'mysql'
  ? "SELECT DATE_FORMAT(created_at, '%m') AS month, COUNT(*) AS total FROM opportunities GROUP BY DATE_FORMAT(created_at, '%m') ORDER BY month"
  : "SELECT strftime('%m', created_at) AS month, COUNT(*) AS total FROM opportunities GROUP BY strftime('%m', created_at) ORDER BY month";
$monthlyRows = $pdo->query($monthlySql)->fetchAll();

$services = ['Cableado estructurado', 'CCTV industrial', 'Control de accesos', 'HVAC industrial', 'Deteccion de incendios', 'Fibra optica', 'Subestaciones electricas', 'Mantenimiento'];
?>
<!doctype html>
<html lang="es-MX">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>ID Industrial CRM</title>
  <link rel="stylesheet" href="../assets/css/crm.css">
</head>
<body>
  <div class="crm-shell">
    <aside class="crm-sidebar">
      <div class="crm-brand">
        <img src="../assets/img/logo-idindustrial-small.webp" alt="ID Industrial" width="280" height="74">
        <div>
          <strong>ID CRM</strong>
          <span>Gestion comercial</span>
        </div>
      </div>
      <nav class="crm-nav" aria-label="CRM">
        <a class="<?php echo $view === 'dashboard' ? 'is-active' : ''; ?>" href="index.php">Dashboard</a>
        <a class="<?php echo $view === 'opportunities' ? 'is-active' : ''; ?>" href="index.php?view=opportunities">Oportunidades</a>
        <a class="<?php echo $view === 'quotes' ? 'is-active' : ''; ?>" href="index.php?view=quotes">Cotizaciones</a>
        <a class="<?php echo $view === 'clients' ? 'is-active' : ''; ?>" href="index.php?view=clients">Clientes</a>
        <a href="../">Vista publica</a>
      </nav>
      <div class="crm-sidebar__footer">
        <strong><?php echo h($_SESSION['crm_user']['name']); ?></strong><br>
        <small><?php echo h($_SESSION['crm_user']['role']); ?></small><br><br>
        <a href="index.php?logout=1">Cerrar sesion</a>
      </div>
    </aside>

    <main class="crm-main">
      <header class="crm-topbar">
        <div>
          <small>ID Industrial</small>
          <strong>CRM para servicios industriales</strong>
        </div>
        <span>Hola, <?php echo h($_SESSION['crm_user']['name']); ?></span>
      </header>

      <section class="crm-content">
        <?php if ($view === 'dashboard'): ?>
          <div class="crm-head">
            <div>
              <p class="eyebrow">Resumen general</p>
              <h1>Dashboard comercial</h1>
              <p>Seguimiento de solicitudes, levantamientos, ingenieria y cotizaciones.</p>
            </div>
            <a class="crm-button" href="index.php?view=opportunities">Ver oportunidades</a>
          </div>

          <div class="crm-kpis">
            <article class="crm-card crm-kpi"><span class="crm-kpi__icon">L</span><div><span>Leads / oportunidades</span><strong><?php echo $counts['leads']; ?></strong></div></article>
            <article class="crm-card crm-kpi"><span class="crm-kpi__icon">C</span><div><span>Clientes</span><strong><?php echo $counts['clients']; ?></strong></div></article>
            <article class="crm-card crm-kpi"><span class="crm-kpi__icon">T</span><div><span>Tareas proximas</span><strong><?php echo $counts['pending']; ?></strong></div></article>
            <article class="crm-card crm-kpi"><span class="crm-kpi__icon">$</span><div><span>Cotizado activo</span><strong><?php echo crm_money($quoteTotal); ?></strong></div></article>
          </div>

          <div class="crm-grid">
            <article class="crm-card">
              <h2>Nuevos contactos</h2>
              <p>Distribucion mensual de oportunidades.</p>
              <div class="crm-bars">
                <?php foreach ($monthlyRows as $row): ?>
                  <?php $width = max(8, min(100, ((int) $row['total']) * 18)); ?>
                  <div>
                    <div class="crm-bar__head"><span>Mes <?php echo h($row['month']); ?></span><strong><?php echo h($row['total']); ?></strong></div>
                    <div class="crm-bar__track"><div class="crm-bar__fill" style="width: <?php echo $width; ?>%"></div></div>
                  </div>
                <?php endforeach; ?>
              </div>
            </article>
            <article class="crm-card">
              <h2>Pipeline ID Industrial</h2>
              <p>Etapas reducidas para proyectos tecnicos.</p>
              <div class="crm-bars">
                <?php foreach ($statusRows as $row): ?>
                  <?php $width = max(8, min(100, ((int) $row['total']) * 18)); ?>
                  <div>
                    <div class="crm-bar__head"><span><?php echo h($row['status']); ?></span><strong><?php echo h($row['total']); ?></strong></div>
                    <div class="crm-bar__track"><div class="crm-bar__fill" style="width: <?php echo $width; ?>%"></div></div>
                  </div>
                <?php endforeach; ?>
              </div>
            </article>
          </div>

          <div class="crm-kpis" style="margin-top:22px">
            <article class="crm-card"><h2>Venta ganada</h2><p><?php echo crm_money($wonTotal); ?></p></article>
            <article class="crm-card"><h2>Modelo comercial</h2><p>Levantamiento, ingenieria, propuesta, seguimiento y cierre.</p></article>
            <article class="crm-card"><h2>Alertas</h2><p><?php echo $counts['pending']; ?> tareas requieren atencion comercial.</p></article>
            <article class="crm-card"><h2>Proxima mejora</h2><p>Adjuntar PDFs, OC y bitacora de llamadas por oportunidad.</p></article>
          </div>
        <?php elseif ($view === 'opportunities'): ?>
          <div class="crm-head">
            <div>
              <p class="eyebrow">Pipeline</p>
              <h1>Oportunidades</h1>
              <p>Captura y seguimiento de proyectos industriales.</p>
            </div>
          </div>

          <article class="crm-card" style="margin-bottom:22px">
            <h2>Nueva oportunidad</h2>
            <form class="crm-form" method="post">
              <input type="hidden" name="token" value="<?php echo h($token); ?>">
              <input type="hidden" name="action" value="create_opportunity">
              <div class="crm-form-grid">
                <label class="crm-field">Empresa<input name="company_name" required></label>
                <label class="crm-field">Contacto<input name="contact_name" required></label>
                <label class="crm-field">Telefono<input name="contact_phone"></label>
                <label class="crm-field">Correo<input type="email" name="contact_email"></label>
                <label class="crm-field">Servicio<select name="service"><?php foreach ($services as $service): ?><option><?php echo h($service); ?></option><?php endforeach; ?></select></label>
                <label class="crm-field">Estatus<select name="status"><?php foreach ($statuses as $status): ?><option><?php echo h($status); ?></option><?php endforeach; ?></select></label>
                <label class="crm-field">Prioridad<select name="priority"><option>Alta</option><option selected>Media</option><option>Baja</option></select></label>
                <label class="crm-field">Valor estimado<input type="number" name="estimated_value" min="0" step="1000"></label>
                <label class="crm-field">Siguiente accion<input type="date" name="next_action_date"></label>
                <label class="crm-field crm-field--wide">Notas<textarea name="notes" rows="3"></textarea></label>
              </div>
              <button class="crm-button" type="submit">Guardar oportunidad</button>
            </form>
          </article>

          <article class="crm-card">
            <h2>Seguimiento activo</h2>
            <div class="crm-table-wrap">
              <table class="crm-table">
                <thead><tr><th>Empresa</th><th>Contacto</th><th>Servicio</th><th>Estatus</th><th>Valor</th><th>Siguiente accion</th><th>Actualizar</th></tr></thead>
                <tbody>
                  <?php foreach ($opportunities as $opportunity): ?>
                    <tr>
                      <td><strong><?php echo h($opportunity['company_name']); ?></strong><br><small><?php echo h($opportunity['source']); ?></small></td>
                      <td><?php echo h($opportunity['contact_name']); ?><br><small><?php echo h($opportunity['contact_phone']); ?></small></td>
                      <td><?php echo h($opportunity['service']); ?></td>
                      <td><span class="crm-pill"><?php echo h($opportunity['status']); ?></span></td>
                      <td><?php echo crm_money($opportunity['estimated_value']); ?></td>
                      <td><?php echo h($opportunity['next_action_date'] ?: 'Sin fecha'); ?></td>
                      <td>
                        <form class="crm-actions" method="post">
                          <input type="hidden" name="token" value="<?php echo h($token); ?>">
                          <input type="hidden" name="action" value="update_opportunity">
                          <input type="hidden" name="opportunity_id" value="<?php echo (int) $opportunity['id']; ?>">
                          <select name="status"><?php foreach ($statuses as $status): ?><option <?php echo $status === $opportunity['status'] ? 'selected' : ''; ?>><?php echo h($status); ?></option><?php endforeach; ?></select>
                          <select name="priority"><option <?php echo $opportunity['priority'] === 'Alta' ? 'selected' : ''; ?>>Alta</option><option <?php echo $opportunity['priority'] === 'Media' ? 'selected' : ''; ?>>Media</option><option <?php echo $opportunity['priority'] === 'Baja' ? 'selected' : ''; ?>>Baja</option></select>
                          <input type="number" name="estimated_value" value="<?php echo h($opportunity['estimated_value']); ?>" style="width:110px">
                          <input type="date" name="next_action_date" value="<?php echo h($opportunity['next_action_date']); ?>">
                          <input type="hidden" name="notes" value="<?php echo h($opportunity['notes']); ?>">
                          <button class="crm-button crm-button--ghost" type="submit">Guardar</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </article>
        <?php elseif ($view === 'quotes'): ?>
          <div class="crm-head"><div><p class="eyebrow">Propuestas</p><h1>Cotizaciones</h1><p>Estatus economico y probabilidad de cierre.</p></div></div>
          <article class="crm-card">
            <div class="crm-table-wrap">
              <table class="crm-table">
                <thead><tr><th>Folio</th><th>Empresa</th><th>Servicio</th><th>Monto</th><th>Estatus</th><th>Prob.</th><th>Vigencia</th><th>Actualizar</th></tr></thead>
                <tbody>
                  <?php foreach ($quotes as $quote): ?>
                    <tr>
                      <td><strong><?php echo h($quote['quote_code']); ?></strong></td>
                      <td><?php echo h($quote['company_name']); ?></td>
                      <td><?php echo h($quote['service']); ?></td>
                      <td><?php echo crm_money($quote['amount']); ?></td>
                      <td><span class="crm-pill"><?php echo h($quote['status']); ?></span></td>
                      <td><?php echo (int) $quote['probability']; ?>%</td>
                      <td><?php echo h($quote['valid_until']); ?></td>
                      <td>
                        <form class="crm-actions" method="post">
                          <input type="hidden" name="token" value="<?php echo h($token); ?>">
                          <input type="hidden" name="action" value="update_quote">
                          <input type="hidden" name="quote_id" value="<?php echo (int) $quote['id']; ?>">
                          <input type="number" name="amount" value="<?php echo h($quote['amount']); ?>" style="width:120px">
                          <select name="status"><?php foreach ($quoteStatuses as $status): ?><option <?php echo $status === $quote['status'] ? 'selected' : ''; ?>><?php echo h($status); ?></option><?php endforeach; ?></select>
                          <input type="number" name="probability" min="0" max="100" value="<?php echo (int) $quote['probability']; ?>" style="width:76px">
                          <input type="date" name="valid_until" value="<?php echo h($quote['valid_until']); ?>">
                          <button class="crm-button crm-button--ghost" type="submit">Guardar</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </article>
        <?php else: ?>
          <div class="crm-head"><div><p class="eyebrow">Cartera</p><h1>Clientes</h1><p>Clientes de referencia y cartera comercial.</p></div></div>
          <article class="crm-card">
            <div class="crm-table-wrap">
              <table class="crm-table">
                <thead><tr><th>Cliente</th><th>Segmento</th><th>Ciudad</th><th>Publico</th><th>Notas</th></tr></thead>
                <tbody>
                  <?php foreach ($clients as $client): ?>
                    <tr>
                      <td><strong><?php echo h($client['name']); ?></strong></td>
                      <td><?php echo h($client['segment']); ?></td>
                      <td><?php echo h($client['city']); ?></td>
                      <td><?php echo $client['is_public'] ? 'Si' : 'No'; ?></td>
                      <td><?php echo h($client['notes']); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </article>
        <?php endif; ?>
      </section>
    </main>
  </div>
</body>
</html>
