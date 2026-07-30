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
  'Proyecto entregado',
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

function crm_pill_class(string $value): string
{
  $key = strtolower($value);
  if (str_contains($key, 'ganado') || str_contains($key, 'entregado') || str_contains($key, 'aprobada') || $key === 'enviada') {
    return 'crm-pill--success';
  }
  if (str_contains($key, 'perdido') || str_contains($key, 'perdida')) {
    return 'crm-pill--danger';
  }
  if (str_contains($key, 'cotizacion') || str_contains($key, 'revision') || str_contains($key, 'negociacion')) {
    return 'crm-pill--warning';
  }
  return 'crm-pill--neutral';
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
  $email = trim((string) ($_POST['crm_email'] ?? $_POST['email'] ?? ''));
  $password = (string) ($_POST['crm_password'] ?? $_POST['password'] ?? '');

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $loginError = 'Ingresa un correo valido.';
  } elseif (strlen($password) < 8) {
    $loginError = 'La contrasena debe tener al menos 8 caracteres.';
  } else {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
      session_regenerate_id(true);
      $_SESSION['crm_user'] = ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email'], 'role' => $user['role']];
      crm_token();
      header('Location: index.php');
      exit;
    }
    $loginError = 'Credenciales incorrectas.';
  }
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
  <main class="crm-login__panel">
    <section class="crm-login__media" aria-label="Infraestructura industrial ID Industrial">
      <img src="../assets/img/optimized/home-industrial.jpg" alt="Infraestructura industrial ID Industrial" width="1920" height="500">
      <div class="crm-login__media-copy">
        <span>Pipeline industrial</span>
        <strong>Leads, cotizaciones y seguimiento tecnico en un solo lugar.</strong>
      </div>
    </section>

    <section class="crm-login__card" aria-labelledby="login-title">
      <div class="crm-login__brand">
        <img src="../assets/img/logo-idindustrial-small.webp" alt="ID Industrial" width="280" height="74">
        <div>
          <strong>ID CRM</strong>
          <span>Gestion comercial</span>
        </div>
      </div>
      <h1 id="login-title">Acceso administrador</h1>
      <p>Seguimiento de leads, cotizaciones y clientes industriales.</p>
      <?php if ($loginError): ?><p class="crm-alert"><?php echo h($loginError); ?></p><?php endif; ?>
      <form method="post" autocomplete="off" data-login-form novalidate>
        <input type="hidden" name="action" value="login">
        <label class="crm-field">
          Correo
          <input type="email" name="crm_email" inputmode="email" autocomplete="off" autocapitalize="none" spellcheck="false" required data-login-email>
          <span class="crm-field__error" data-error-email>Ingresa un correo valido.</span>
        </label>
        <label class="crm-field">
          Password
          <span class="crm-password-field">
            <input id="crm-password" type="password" name="crm_password" autocomplete="new-password" minlength="8" required data-login-password>
            <button class="crm-password-toggle" type="button" aria-label="Mostrar password" aria-controls="crm-password" data-password-toggle>
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5c5 0 8.5 4.2 9.7 6.1a1.7 1.7 0 0 1 0 1.8C20.5 14.8 17 19 12 19s-8.5-4.2-9.7-6.1a1.7 1.7 0 0 1 0-1.8C3.5 9.2 7 5 12 5Zm0 2C7.9 7 4.9 10.4 4 12c.9 1.6 3.9 5 8 5s7.1-3.4 8-5c-.9-1.6-3.9-5-8-5Zm0 2.2a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6Z"/></svg>
            </button>
          </span>
          <span class="crm-field__error" data-error-password>La contrasena debe tener al menos 8 caracteres.</span>
        </label>
        <button class="crm-button" type="submit">Entrar al CRM</button>
      </form>
    </section>
  </main>
  <script>
    (() => {
      const form = document.querySelector('[data-login-form]');
      const password = document.querySelector('[data-login-password]');
      const toggle = document.querySelector('[data-password-toggle]');
      const email = document.querySelector('[data-login-email]');
      if (!form || !password || !toggle || !email) return;

      toggle.addEventListener('click', () => {
        const showing = password.type === 'text';
        password.type = showing ? 'password' : 'text';
        toggle.setAttribute('aria-label', showing ? 'Mostrar password' : 'Ocultar password');
        toggle.classList.toggle('is-active', !showing);
      });

      form.addEventListener('submit', (event) => {
        form.classList.add('was-validated');
        if (!email.validity.valid || !password.validity.valid) {
          event.preventDefault();
          (email.validity.valid ? password : email).focus();
        }
      });
    })();
  </script>
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
  $opportunityId = (int) $_POST['opportunity_id'];
  $newStatus = trim($_POST['status'] ?? 'Nueva solicitud');
  $stmt = $pdo->prepare('UPDATE opportunities SET status = ?, priority = ?, estimated_value = ?, next_action_date = ?, notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
  $stmt->execute([
    $newStatus,
    trim($_POST['priority'] ?? 'Media'),
    (float) ($_POST['estimated_value'] ?? 0),
    $_POST['next_action_date'] ?: null,
    trim($_POST['notes'] ?? ''),
    $opportunityId,
  ]);
  $activity = $pdo->prepare('INSERT INTO activities (opportunity_id, type, summary, due_date) VALUES (?, "Actualizacion", ?, ?)');
  $activity->execute([$opportunityId, 'Estatus actualizado a ' . $newStatus, $_POST['next_action_date'] ?: null]);

  if ($newStatus === 'Proyecto entregado') {
    $portal = crm_enable_client_portal($pdo, $opportunityId);
    $_SESSION['crm_flash'] = $portal['created']
      ? [
        'type' => 'success',
        'title' => 'Bitacora ID activada',
        'text' => 'Comparte estos accesos con el cliente. La contrasena se muestra una sola vez.',
        'username' => $portal['username'],
        'password' => $portal['password'],
      ]
      : [
        'type' => 'info',
        'title' => 'Bitacora ID ya estaba activa',
        'text' => 'El cliente ya tiene acceso activo al panel de mantenimiento.',
        'username' => $portal['username'],
        'password' => null,
      ];
  }

  header('Location: index.php?view=opportunities');
  exit;
}

if (($_POST['action'] ?? '') === 'reset_portal_access') {
  crm_check_token();
  $access = crm_reset_client_portal_password($pdo, (int) ($_POST['portal_user_id'] ?? 0));
  $_SESSION['crm_flash'] = [
    'type' => 'success',
    'title' => 'Acceso Bitacora ID regenerado',
    'text' => 'Comparte esta nueva contrasena con el cliente. Se muestra una sola vez.',
    'username' => $access['username'],
    'password' => $access['password'],
  ];
  header('Location: index.php?view=bitacora');
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
$flash = $_SESSION['crm_flash'] ?? null;
unset($_SESSION['crm_flash']);
$token = crm_token();
$counts = [
  'leads' => (int) $pdo->query('SELECT COUNT(*) FROM opportunities')->fetchColumn(),
  'clients' => (int) $pdo->query('SELECT COUNT(*) FROM clients')->fetchColumn(),
  'open_quotes' => (int) $pdo->query("SELECT COUNT(*) FROM quotes WHERE status NOT IN ('Aprobada', 'Perdida')")->fetchColumn(),
  'delivered' => (int) $pdo->query("SELECT COUNT(*) FROM opportunities WHERE status = 'Proyecto entregado'")->fetchColumn(),
  'portal' => (int) $pdo->query('SELECT COUNT(*) FROM client_portal_users WHERE is_active = 1')->fetchColumn(),
  'pending' => 0,
];
$quoteTotal = (float) $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM quotes WHERE status NOT IN ('Perdida')")->fetchColumn();
$wonTotal = (float) $pdo->query("SELECT COALESCE(SUM(estimated_value), 0) FROM opportunities WHERE status = 'Proyecto ganado'")->fetchColumn();
$opportunities = $pdo->query('
  SELECT o.*, cpu.username AS portal_username, cpu.is_active AS portal_active
  FROM opportunities o
  LEFT JOIN client_portal_users cpu ON cpu.opportunity_id = o.id
  ORDER BY o.updated_at DESC, o.created_at DESC
')->fetchAll();
$quotes = $pdo->query('
  SELECT q.*, o.company_name, o.service, o.status AS opportunity_status
  FROM quotes q
  JOIN opportunities o ON o.id = q.opportunity_id
  ORDER BY q.created_at DESC
')->fetchAll();
$clients = $pdo->query('SELECT * FROM clients ORDER BY is_public DESC, name')->fetchAll();
$portalUsers = $pdo->query('
  SELECT cpu.*, o.company_name, o.contact_name, o.contact_email, o.service, o.status AS opportunity_status
  FROM client_portal_users cpu
  JOIN opportunities o ON o.id = cpu.opportunity_id
  ORDER BY cpu.updated_at DESC, cpu.created_at DESC
')->fetchAll();
$clientRequests = $pdo->query('
  SELECT cr.*, o.company_name, cpu.username
  FROM client_requests cr
  JOIN opportunities o ON o.id = cr.opportunity_id
  JOIN client_portal_users cpu ON cpu.id = cr.portal_user_id
  ORDER BY cr.created_at DESC
')->fetchAll();
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
<body class="crm-app">
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
        <a class="<?php echo $view === 'bitacora' ? 'is-active' : ''; ?>" href="index.php?view=bitacora">Bitacora ID</a>
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
        <?php if ($flash): ?>
          <div class="crm-flash crm-flash--<?php echo h($flash['type'] ?? 'info'); ?>">
            <div>
              <strong><?php echo h($flash['title'] ?? 'Aviso'); ?></strong>
              <p><?php echo h($flash['text'] ?? ''); ?></p>
              <?php if (!empty($flash['username'])): ?><code>Usuario: <?php echo h($flash['username']); ?></code><?php endif; ?>
              <?php if (!empty($flash['password'])): ?><code>Password: <?php echo h($flash['password']); ?></code><?php endif; ?>
            </div>
            <a class="crm-button crm-button--ghost" href="cliente.php">Ver portal</a>
          </div>
        <?php endif; ?>
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
            <article class="crm-card crm-kpi"><span class="crm-kpi__icon">B</span><div><span>Bitacoras activas</span><strong><?php echo $counts['portal']; ?></strong></div></article>
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

          <div class="crm-kpis crm-kpis--secondary">
            <article class="crm-card"><h2>Venta ganada</h2><p><?php echo crm_money($wonTotal); ?></p></article>
            <article class="crm-card"><h2>Modelo comercial</h2><p>Levantamiento, ingenieria, propuesta, seguimiento y cierre.</p></article>
            <article class="crm-card"><h2>Alertas</h2><p><?php echo $counts['pending']; ?> tareas requieren atencion comercial.</p></article>
            <article class="crm-card"><h2>Proyectos entregados</h2><p><?php echo $counts['delivered']; ?> clientes pueden pasar a mantenimiento continuo.</p></article>
          </div>
        <?php elseif ($view === 'opportunities'): ?>
          <div class="crm-head">
            <div>
              <p class="eyebrow">Pipeline</p>
              <h1>Oportunidades</h1>
              <p>Captura y seguimiento de proyectos industriales.</p>
            </div>
          </div>

          <article class="crm-card crm-form-card">
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
                <thead><tr><th>Empresa</th><th>Contacto</th><th>Servicio</th><th>Estatus</th><th>Bitacora ID</th><th>Valor</th><th>Siguiente accion</th><th>Actualizar</th></tr></thead>
                <tbody>
                  <?php foreach ($opportunities as $opportunity): ?>
                    <tr>
                      <td><strong><?php echo h($opportunity['company_name']); ?></strong><br><small><?php echo h($opportunity['source']); ?></small></td>
                      <td><?php echo h($opportunity['contact_name']); ?><br><small><?php echo h($opportunity['contact_phone']); ?></small></td>
                      <td><?php echo h($opportunity['service']); ?></td>
                      <td><span class="crm-pill <?php echo h(crm_pill_class((string) $opportunity['status'])); ?>"><?php echo h($opportunity['status']); ?></span></td>
                      <td><?php echo crm_money($opportunity['estimated_value']); ?></td>
                      <td><?php echo h($opportunity['next_action_date'] ?: 'Sin fecha'); ?></td>
                      <td>
                        <form class="crm-actions" method="post">
                          <input type="hidden" name="token" value="<?php echo h($token); ?>">
                          <input type="hidden" name="action" value="update_opportunity">
                          <input type="hidden" name="opportunity_id" value="<?php echo (int) $opportunity['id']; ?>">
                          <select name="status"><?php foreach ($statuses as $status): ?><option <?php echo $status === $opportunity['status'] ? 'selected' : ''; ?>><?php echo h($status); ?></option><?php endforeach; ?></select>
                          <select name="priority"><option <?php echo $opportunity['priority'] === 'Alta' ? 'selected' : ''; ?>>Alta</option><option <?php echo $opportunity['priority'] === 'Media' ? 'selected' : ''; ?>>Media</option><option <?php echo $opportunity['priority'] === 'Baja' ? 'selected' : ''; ?>>Baja</option></select>
                          <input type="number" name="estimated_value" value="<?php echo h($opportunity['estimated_value']); ?>" class="crm-input--money">
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
                      <td><span class="crm-pill <?php echo h(crm_pill_class((string) $quote['status'])); ?>"><?php echo h($quote['status']); ?></span></td>
                      <td><?php echo (int) $quote['probability']; ?>%</td>
                      <td><?php echo h($quote['valid_until']); ?></td>
                      <td>
                        <form class="crm-actions" method="post">
                          <input type="hidden" name="token" value="<?php echo h($token); ?>">
                          <input type="hidden" name="action" value="update_quote">
                          <input type="hidden" name="quote_id" value="<?php echo (int) $quote['id']; ?>">
                          <input type="number" name="amount" value="<?php echo h($quote['amount']); ?>" class="crm-input--money">
                          <select name="status"><?php foreach ($quoteStatuses as $status): ?><option <?php echo $status === $quote['status'] ? 'selected' : ''; ?>><?php echo h($status); ?></option><?php endforeach; ?></select>
                          <input type="number" name="probability" min="0" max="100" value="<?php echo (int) $quote['probability']; ?>" class="crm-input--probability">
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
        <?php elseif ($view === 'clients'): ?>
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
                      <td><span class="crm-pill <?php echo $client['is_public'] ? 'crm-pill--success' : 'crm-pill--neutral'; ?>"><?php echo $client['is_public'] ? 'Si' : 'No'; ?></span></td>
                      <td><?php echo h($client['notes']); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </article>
        <?php else: ?>
          <div class="crm-head">
            <div>
              <p class="eyebrow">Mantenimiento continuo</p>
              <h1>Bitacora ID</h1>
              <p>Accesos de clientes y solicitudes recibidas desde el panel de mantenimiento.</p>
            </div>
            <a class="crm-button" href="cliente.php">Portal cliente</a>
          </div>

          <div class="crm-grid">
            <article class="crm-card">
              <h2>Clientes con acceso</h2>
              <div class="crm-table-wrap">
                <table class="crm-table">
                  <thead><tr><th>Cliente</th><th>Servicio</th><th>Usuario</th><th>Estatus</th><th>Ultimo acceso</th><th>Acceso</th></tr></thead>
                  <tbody>
                    <?php foreach ($portalUsers as $portalUser): ?>
                      <tr>
                        <td><strong><?php echo h($portalUser['company_name']); ?></strong><br><small><?php echo h($portalUser['contact_name']); ?></small></td>
                        <td><?php echo h($portalUser['service']); ?></td>
                        <td><code><?php echo h($portalUser['username']); ?></code></td>
                        <td><span class="crm-pill <?php echo (int) $portalUser['is_active'] ? 'crm-pill--success' : 'crm-pill--neutral'; ?>"><?php echo (int) $portalUser['is_active'] ? 'Activo' : 'Inactivo'; ?></span></td>
                        <td><?php echo h($portalUser['last_login_at'] ?: 'Sin acceso'); ?></td>
                        <td>
                          <form method="post">
                            <input type="hidden" name="token" value="<?php echo h($token); ?>">
                            <input type="hidden" name="action" value="reset_portal_access">
                            <input type="hidden" name="portal_user_id" value="<?php echo (int) $portalUser['id']; ?>">
                            <button class="crm-button crm-button--ghost" type="submit">Regenerar</button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </article>

            <article class="crm-card">
              <h2>Solicitudes del cliente</h2>
              <div class="crm-list">
                <?php foreach ($clientRequests as $request): ?>
                  <div class="crm-list__item">
                    <span class="crm-pill crm-pill--warning"><?php echo h($request['status']); ?></span>
                    <strong><?php echo h($request['title']); ?></strong>
                    <p><?php echo h($request['message']); ?></p>
                    <small><?php echo h($request['company_name']); ?> - <?php echo h($request['created_at']); ?></small>
                  </div>
                <?php endforeach; ?>
                <?php if (!$clientRequests): ?><p>No hay solicitudes de mantenimiento registradas.</p><?php endif; ?>
              </div>
            </article>
          </div>
        <?php endif; ?>
      </section>
    </main>
  </div>
</body>
</html>
