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
$quoteStatuses = ['Solicitud recibida', 'En elaboracion', 'Enviada', 'En revision cliente', 'Aprobada', 'Perdida'];
$requestStatuses = ['Recibida', 'En revision', 'Programada', 'En proceso', 'Resuelta', 'Cerrada'];
$requestPriorities = ['Baja', 'Media', 'Alta', 'Urgente'];

function h($value): string
{
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function crm_money($value): string
{
  return '$' . number_format((float) $value, 2);
}

function crm_has_text(string $value, string $needle): bool
{
  return strpos($value, $needle) !== false;
}

function crm_pill_class(string $value): string
{
  $key = strtolower($value);
  if (crm_has_text($key, 'ganado') || crm_has_text($key, 'entregado') || crm_has_text($key, 'aprobada') || $key === 'enviada') {
    return 'crm-pill--success';
  }
  if (crm_has_text($key, 'perdido') || crm_has_text($key, 'perdida')) {
    return 'crm-pill--danger';
  }
  if (crm_has_text($key, 'solicitud') || crm_has_text($key, 'cotizacion') || crm_has_text($key, 'revision') || crm_has_text($key, 'negociacion')) {
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

if (($_POST['action'] ?? '') === 'change_password') {
  crm_check_token();
  $userId = (int) ($_SESSION['crm_user']['id'] ?? 0);
  $currentPassword = (string) ($_POST['current_password'] ?? '');
  $newPassword = (string) ($_POST['new_password'] ?? '');
  $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

  $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE id = ? LIMIT 1');
  $stmt->execute([$userId]);
  $user = $stmt->fetch();

  if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
    $_SESSION['crm_flash'] = [
      'type' => 'error',
      'title' => 'No se pudo cambiar el password',
      'text' => 'El password actual no coincide.',
    ];
  } elseif (strlen($newPassword) < 10) {
    $_SESSION['crm_flash'] = [
      'type' => 'error',
      'title' => 'Password demasiado corto',
      'text' => 'Usa al menos 10 caracteres para el nuevo password.',
    ];
  } elseif ($newPassword !== $confirmPassword) {
    $_SESSION['crm_flash'] = [
      'type' => 'error',
      'title' => 'Confirmacion incorrecta',
      'text' => 'El nuevo password y la confirmacion no coinciden.',
    ];
  } elseif (password_verify($newPassword, $user['password_hash'])) {
    $_SESSION['crm_flash'] = [
      'type' => 'error',
      'title' => 'Password sin cambios',
      'text' => 'El nuevo password debe ser diferente al actual.',
    ];
  } else {
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $update = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $update->execute([$hash, $userId]);
    session_regenerate_id(true);
    $_SESSION['crm_flash'] = [
      'type' => 'success',
      'title' => 'Password actualizado',
      'text' => 'Tu acceso de administrador fue actualizado correctamente.',
    ];
  }

  header('Location: index.php?view=profile');
  exit;
}

if (($_POST['action'] ?? '') === 'create_opportunity') {
  crm_check_token();
  $clientId = crm_find_or_create_prospect_client($pdo, [
    'company_name' => trim($_POST['company_name'] ?? ''),
    'contact_name' => trim($_POST['contact_name'] ?? ''),
    'contact_email' => trim($_POST['contact_email'] ?? ''),
    'contact_phone' => trim($_POST['contact_phone'] ?? ''),
  ]);
  $stmt = $pdo->prepare('
    INSERT INTO opportunities (client_id, company_name, contact_name, contact_email, contact_phone, service, source, status, priority, estimated_value, next_action_date, notes)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
  ');
  $stmt->execute([
    $clientId,
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

  if (in_array($newStatus, ['Proyecto ganado', 'Proyecto entregado'], true)) {
    $clientStmt = $pdo->prepare('UPDATE clients SET lifecycle_stage = ?, segment = CASE WHEN segment = ? THEN ? ELSE segment END, converted_at = COALESCE(converted_at, CURRENT_TIMESTAMP) WHERE id = (SELECT client_id FROM opportunities WHERE id = ?)');
    $clientStmt->execute(['Cliente', 'Prospecto', 'Industrial', $opportunityId]);
  }

  if ($newStatus === 'Proyecto entregado') {
    $portal = crm_enable_client_portal($pdo, $opportunityId);
    $emailSent = $portal['created'] && !empty($portal['password'])
      ? crm_send_portal_credentials($portal['opportunity'], $portal['username'], $portal['password'])
      : false;
    $_SESSION['crm_flash'] = $portal['created']
      ? [
        'type' => 'success',
        'title' => 'Bitacora ID activada',
        'text' => $emailSent ? 'Accesos generados y enviados al correo del cliente.' : 'Accesos generados. Si SMTP aun no esta activo, compartelos manualmente; la contrasena se muestra una sola vez.',
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

  $redirect = ($_POST['return_to'] ?? '') === 'opportunity' ? 'index.php?view=opportunity&id=' . $opportunityId : 'index.php?view=opportunities';
  header('Location: ' . $redirect);
  exit;
}

if (($_POST['action'] ?? '') === 'convert_client') {
  crm_check_token();
  $clientId = (int) ($_POST['client_id'] ?? 0);
  $stmt = $pdo->prepare('UPDATE clients SET lifecycle_stage = ?, segment = CASE WHEN segment = ? THEN ? ELSE segment END, converted_at = COALESCE(converted_at, CURRENT_TIMESTAMP), is_public = 0 WHERE id = ?');
  $stmt->execute(['Cliente', 'Prospecto', 'Industrial', $clientId]);
  $activity = $pdo->prepare('INSERT INTO activities (opportunity_id, type, summary, due_date) SELECT id, "Conversion", "Prospecto convertido a cliente.", NULL FROM opportunities WHERE client_id = ? ORDER BY created_at DESC LIMIT 1');
  $activity->execute([$clientId]);
  $_SESSION['crm_flash'] = [
    'type' => 'success',
    'title' => 'Cliente convertido',
    'text' => 'El prospecto ahora aparece como cliente en la cartera comercial.',
  ];
  header('Location: index.php?view=clients');
  exit;
}
if (($_POST['action'] ?? '') === 'reset_portal_access') {
  crm_check_token();
  $access = crm_reset_client_portal_password($pdo, (int) ($_POST['portal_user_id'] ?? 0));
  $emailSent = crm_send_portal_credentials($access, $access['username'], $access['password']);
  $_SESSION['crm_flash'] = [
    'type' => 'success',
    'title' => 'Acceso Bitacora ID regenerado',
    'text' => $emailSent ? 'Nueva contrasena enviada al correo del cliente.' : 'Comparte esta nueva contrasena con el cliente. Se muestra una sola vez.',
    'username' => $access['username'],
    'password' => $access['password'],
  ];
  header('Location: index.php?view=bitacora');
  exit;
}

if (($_POST['action'] ?? '') === 'update_client_request') {
  crm_check_token();
  $requestId = (int) ($_POST['request_id'] ?? 0);
  $status = trim((string) ($_POST['status'] ?? 'Recibida'));
  $priority = trim((string) ($_POST['priority'] ?? 'Media'));
  $adminResponse = trim((string) ($_POST['admin_response'] ?? ''));
  if (!in_array($status, $requestStatuses, true)) {
    $status = 'Recibida';
  }
  if (!in_array($priority, $requestPriorities, true)) {
    $priority = 'Media';
  }

  $requestStmt = $pdo->prepare('SELECT cr.*, o.company_name FROM client_requests cr JOIN opportunities o ON o.id = cr.opportunity_id WHERE cr.id = ? LIMIT 1');
  $requestStmt->execute([$requestId]);
  $request = $requestStmt->fetch();
  if ($request) {
    $resolvedAt = in_array($status, ['Resuelta', 'Cerrada'], true) ? date('Y-m-d H:i:s') : null;
    $update = $pdo->prepare('UPDATE client_requests SET status = ?, priority = ?, admin_response = ?, resolved_at = ?, last_admin_update_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
    $update->execute([$status, $priority, $adminResponse !== '' ? $adminResponse : null, $resolvedAt, $requestId]);

    $notes = $adminResponse !== '' ? $adminResponse : 'Estatus actualizado a ' . $status . '.';
    $log = $pdo->prepare('INSERT INTO maintenance_logs (opportunity_id, portal_user_id, type, title, status, scheduled_date, notes, visible_to_client) VALUES (?, ?, "Solicitud", ?, ?, ?, ?, 1)');
    $log->execute([(int) $request['opportunity_id'], (int) $request['portal_user_id'], 'Seguimiento: ' . $request['title'], $status, date('Y-m-d'), $notes]);

    $_SESSION['crm_flash'] = [
      'type' => 'success',
      'title' => 'Solicitud actualizada',
      'text' => 'El cliente ya puede ver el nuevo estatus y la respuesta en su perfil.',
    ];
  }
  header('Location: index.php?view=bitacora');
  exit;
}
if (($_POST['action'] ?? '') === 'create_quote') {
  crm_check_token();
  $opportunityId = (int) ($_POST['opportunity_id'] ?? 0);
  $amount = max(0, (float) ($_POST['amount'] ?? 0));
  $status = trim($_POST['status'] ?? 'En elaboracion') ?: 'En elaboracion';
  $probability = max(0, min(100, (int) ($_POST['probability'] ?? 40)));
  $validUntil = $_POST['valid_until'] ?: null;

  $quoteCode = crm_next_quote_code($pdo, 'ID');

  $stmt = $pdo->prepare('INSERT INTO quotes (opportunity_id, quote_code, amount, status, probability, sent_at, valid_until) VALUES (?, ?, ?, ?, ?, ?, ?)');
  $stmt->execute([$opportunityId, $quoteCode, $amount, $status, $probability, $status === 'Enviada' ? date('Y-m-d') : null, $validUntil]);
  $quoteId = (int) $pdo->lastInsertId();
  $activity = $pdo->prepare('INSERT INTO activities (opportunity_id, type, summary, due_date) VALUES (?, "Cotizacion", ?, ?)');
  $activity->execute([$opportunityId, 'Cotizacion creada: ' . $quoteCode, $validUntil]);
  header('Location: index.php?view=quote&id=' . $quoteId);
  exit;
}
if (($_POST['action'] ?? '') === 'update_quote') {
  crm_check_token();
  $status = trim($_POST['status'] ?? '') ?: 'En elaboracion';
  $quoteId = (int) $_POST['quote_id'];
  $stmt = $pdo->prepare('UPDATE quotes SET amount = ?, status = ?, probability = ?, valid_until = ? WHERE id = ?');
  $stmt->execute([
    max(0, (float) ($_POST['amount'] ?? 0)),
    $status,
    max(0, min(100, (int) ($_POST['probability'] ?? 40))),
    $_POST['valid_until'] ?: null,
    $quoteId,
  ]);

  if ($status === 'Aprobada') {
    $pdo->prepare('UPDATE opportunities SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = (SELECT opportunity_id FROM quotes WHERE id = ?)')->execute(['Proyecto ganado', $quoteId]);
    $pdo->prepare('UPDATE clients SET lifecycle_stage = ?, segment = CASE WHEN segment = ? THEN ? ELSE segment END, converted_at = COALESCE(converted_at, CURRENT_TIMESTAMP) WHERE id = (SELECT o.client_id FROM opportunities o JOIN quotes q ON q.opportunity_id = o.id WHERE q.id = ?)')->execute(['Cliente', 'Prospecto', 'Industrial', $quoteId]);
  } elseif ($status === 'Perdida') {
    $pdo->prepare('UPDATE opportunities SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = (SELECT opportunity_id FROM quotes WHERE id = ?)')->execute(['Proyecto perdido', $quoteId]);
  }

  $redirect = ($_POST['return_to'] ?? '') === 'quote' ? 'index.php?view=quote&id=' . $quoteId : 'index.php?view=quotes';
  header('Location: ' . $redirect);
  exit;
}

$view = $_GET['view'] ?? 'dashboard';
$flash = $_SESSION['crm_flash'] ?? null;
unset($_SESSION['crm_flash']);
$token = crm_token();
$counts = [
  'leads' => (int) $pdo->query('SELECT COUNT(*) FROM opportunities')->fetchColumn(),
  'clients' => (int) $pdo->query("SELECT COUNT(*) FROM clients WHERE lifecycle_stage = 'Cliente'")->fetchColumn(),
  'prospects' => (int) $pdo->query("SELECT COUNT(*) FROM clients WHERE lifecycle_stage = 'Prospecto'")->fetchColumn(),
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
$selectedOpportunity = null;
$selectedOpportunityQuotes = [];
$selectedOpportunityActivities = [];
if ($view === 'opportunity') {
  $opportunityId = max(0, (int) ($_GET['id'] ?? 0));
  $stmt = $pdo->prepare('
    SELECT o.*, c.lifecycle_stage, c.segment, cpu.username AS portal_username, cpu.is_active AS portal_active
    FROM opportunities o
    LEFT JOIN clients c ON c.id = o.client_id
    LEFT JOIN client_portal_users cpu ON cpu.opportunity_id = o.id
    WHERE o.id = ?
    LIMIT 1
  ');
  $stmt->execute([$opportunityId]);
  $selectedOpportunity = $stmt->fetch() ?: null;
  if ($selectedOpportunity) {
    $quoteStmt = $pdo->prepare('SELECT id, quote_code, amount, status, probability, valid_until, created_at FROM quotes WHERE opportunity_id = ? ORDER BY created_at DESC');
    $quoteStmt->execute([(int) $selectedOpportunity['id']]);
    $selectedOpportunityQuotes = $quoteStmt->fetchAll();
    $activityStmt = $pdo->prepare('SELECT type, summary, due_date, completed_at, created_at FROM activities WHERE opportunity_id = ? ORDER BY created_at DESC LIMIT 8');
    $activityStmt->execute([(int) $selectedOpportunity['id']]);
    $selectedOpportunityActivities = $activityStmt->fetchAll();
  }
}
$quotes = $pdo->query('
  SELECT q.*, o.company_name, o.service, o.status AS opportunity_status
  FROM quotes q
  JOIN opportunities o ON o.id = q.opportunity_id
  ORDER BY q.created_at DESC
')->fetchAll();
$quoteableOpportunities = $pdo->query("SELECT id, company_name, service, estimated_value FROM opportunities WHERE status NOT IN ('Proyecto perdido') ORDER BY updated_at DESC, created_at DESC")->fetchAll();
$selectedQuote = null;
$selectedQuoteActivities = [];
if ($view === 'quote') {
  $quoteId = max(0, (int) ($_GET['id'] ?? 0));
  $stmt = $pdo->prepare('
    SELECT q.*, o.company_name, o.contact_name, o.contact_email, o.contact_phone, o.service, o.source, o.status AS opportunity_status, o.priority, o.estimated_value, o.next_action_date, o.notes AS opportunity_notes, o.created_at AS opportunity_created_at, c.lifecycle_stage, c.segment
    FROM quotes q
    JOIN opportunities o ON o.id = q.opportunity_id
    LEFT JOIN clients c ON c.id = o.client_id
    WHERE q.id = ?
    LIMIT 1
  ');
  $stmt->execute([$quoteId]);
  $selectedQuote = $stmt->fetch() ?: null;
  if ($selectedQuote) {
    $activityStmt = $pdo->prepare('SELECT type, summary, due_date, completed_at, created_at FROM activities WHERE opportunity_id = ? ORDER BY created_at DESC LIMIT 8');
    $activityStmt->execute([(int) $selectedQuote['opportunity_id']]);
    $selectedQuoteActivities = $activityStmt->fetchAll();
  }
}
$clients = $pdo->query("SELECT * FROM clients ORDER BY CASE WHEN lifecycle_stage = 'Prospecto' THEN 0 ELSE 1 END, created_at DESC, name")->fetchAll();
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
$requestStatusTotals = array_fill_keys($requestStatuses, 0);
$requestPriorityTotals = array_fill_keys($requestPriorities, 0);
$requestMonthlyTotals = [];
$maintenanceMetrics = [
  'total' => count($clientRequests),
  'open' => 0,
  'urgent' => 0,
  'resolved' => 0,
  'answered' => 0,
];

for ($i = 5; $i >= 0; $i--) {
  $key = date('Y-m', strtotime('-' . $i . ' months'));
  $requestMonthlyTotals[$key] = 0;
}

foreach ($clientRequests as $request) {
  $requestStatus = trim((string) ($request['status'] ?? 'Recibida')) ?: 'Recibida';
  $requestPriority = trim((string) ($request['priority'] ?? 'Media')) ?: 'Media';
  if (!isset($requestStatusTotals[$requestStatus])) {
    $requestStatusTotals[$requestStatus] = 0;
  }
  if (!isset($requestPriorityTotals[$requestPriority])) {
    $requestPriorityTotals[$requestPriority] = 0;
  }
  $requestStatusTotals[$requestStatus]++;
  $requestPriorityTotals[$requestPriority]++;

  if (!in_array($requestStatus, ['Resuelta', 'Cerrada'], true)) {
    $maintenanceMetrics['open']++;
  } else {
    $maintenanceMetrics['resolved']++;
  }
  if ($requestPriority === 'Urgente') {
    $maintenanceMetrics['urgent']++;
  }
  if (trim((string) ($request['admin_response'] ?? '')) !== '') {
    $maintenanceMetrics['answered']++;
  }

  $createdAt = strtotime((string) ($request['created_at'] ?? '')) ?: time();
  $monthKey = date('Y-m', $createdAt);
  if (isset($requestMonthlyTotals[$monthKey])) {
    $requestMonthlyTotals[$monthKey]++;
  }
}

$maintenanceMetrics['response_rate'] = $maintenanceMetrics['total'] > 0
  ? round(($maintenanceMetrics['answered'] / $maintenanceMetrics['total']) * 100)
  : 0;
$maxStatusTotal = max(1, ...array_values($requestStatusTotals));
$maxPriorityTotal = max(1, ...array_values($requestPriorityTotals));
$maxMonthlyTotal = max(1, ...array_values($requestMonthlyTotals));
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
        <a class="<?php echo $view === 'profile' ? 'is-active' : ''; ?>" href="index.php?view=profile">Perfil</a>
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
            <?php if (!empty($flash['username'])): ?><a class="crm-button crm-button--ghost" href="cliente.php">Ver portal</a><?php endif; ?>
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
            <article class="crm-card crm-kpi"><span class="crm-kpi__icon">P</span><div><span>Prospectos</span><strong><?php echo $counts['prospects']; ?></strong></div></article>
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
              <p>Lista resumida. Abre cada oportunidad para actualizar estatus y activar Bitacora ID.</p>
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
              <table class="crm-table crm-table--compact">
                <thead><tr><th>Empresa</th><th>Contacto</th><th>Servicio</th><th>Estatus</th><th>Ver</th></tr></thead>
                <tbody>
                  <?php foreach ($opportunities as $opportunity): ?>
                    <tr>
                      <td><strong><?php echo h($opportunity['company_name']); ?></strong><br><small><?php echo h($opportunity['source']); ?></small></td>
                      <td><?php echo h($opportunity['contact_name']); ?><br><small><?php echo h($opportunity['contact_phone']); ?></small></td>
                      <td><?php echo h($opportunity['service']); ?></td>
                      <td><span class="crm-pill <?php echo h(crm_pill_class((string) $opportunity['status'])); ?>"><?php echo h($opportunity['status']); ?></span></td>
                      <td><a class="crm-button crm-button--ghost" href="index.php?view=opportunity&id=<?php echo (int) $opportunity['id']; ?>">Ver</a></td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if (!$opportunities): ?>
                    <tr><td colspan="5">Aun no hay oportunidades registradas.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </article>
        <?php elseif ($view === 'opportunity'): ?>
          <?php if (!$selectedOpportunity): ?>
            <div class="crm-head"><div><p class="eyebrow">Pipeline</p><h1>Oportunidad no encontrada</h1><p>El registro solicitado no existe o fue eliminado.</p></div><a class="crm-button crm-button--ghost" href="index.php?view=opportunities">Volver</a></div>
          <?php else: ?>
            <div class="crm-head">
              <div>
                <p class="eyebrow">Detalle de oportunidad</p>
                <h1><?php echo h($selectedOpportunity['company_name']); ?></h1>
                <p><?php echo h($selectedOpportunity['service']); ?> - <?php echo h($selectedOpportunity['contact_name']); ?></p>
              </div>
              <a class="crm-button crm-button--ghost" href="index.php?view=opportunities">Volver</a>
            </div>

            <div class="crm-kpis crm-kpis--secondary">
              <article class="crm-card"><h2>Estatus</h2><p><span class="crm-pill <?php echo h(crm_pill_class((string) $selectedOpportunity['status'])); ?>"><?php echo h($selectedOpportunity['status']); ?></span></p></article>
              <article class="crm-card"><h2>Valor</h2><p><?php echo crm_money($selectedOpportunity['estimated_value']); ?></p></article>
              <article class="crm-card"><h2>Siguiente accion</h2><p><?php echo h($selectedOpportunity['next_action_date'] ?: 'Sin fecha'); ?></p></article>
              <article class="crm-card"><h2>Bitacora ID</h2><p><?php if (!empty($selectedOpportunity['portal_username'])): ?><span class="crm-pill crm-pill--success">Activa</span><?php elseif ($selectedOpportunity['status'] === 'Proyecto entregado'): ?><span class="crm-pill crm-pill--warning">Pendiente</span><?php else: ?><span class="crm-pill crm-pill--neutral">No aplica</span><?php endif; ?></p></article>
            </div>

            <div class="crm-grid">
              <article class="crm-card">
                <h2>Datos del contacto</h2>
                <div class="crm-list crm-list--compact">
                  <div class="crm-list__item"><strong>Empresa</strong><span><?php echo h($selectedOpportunity['company_name']); ?></span></div>
                  <div class="crm-list__item"><strong>Contacto</strong><span><?php echo h($selectedOpportunity['contact_name']); ?></span></div>
                  <div class="crm-list__item"><strong>Correo</strong><span><?php echo h($selectedOpportunity['contact_email'] ?: 'Sin correo'); ?></span></div>
                  <div class="crm-list__item"><strong>Telefono</strong><span><?php echo h($selectedOpportunity['contact_phone'] ?: 'Sin telefono'); ?></span></div>
                  <div class="crm-list__item"><strong>Origen</strong><span><?php echo h($selectedOpportunity['source']); ?></span></div>
                </div>
              </article>

              <article class="crm-card crm-form-card">
                <h2>Actualizar seguimiento</h2>
                <form class="crm-form" method="post">
                  <input type="hidden" name="token" value="<?php echo h($token); ?>">
                  <input type="hidden" name="action" value="update_opportunity">
                  <input type="hidden" name="return_to" value="opportunity">
                  <input type="hidden" name="opportunity_id" value="<?php echo (int) $selectedOpportunity['id']; ?>">
                  <div class="crm-form-grid">
                    <label class="crm-field">Estatus<select name="status"><?php foreach ($statuses as $status): ?><option <?php echo $status === $selectedOpportunity['status'] ? 'selected' : ''; ?>><?php echo h($status); ?></option><?php endforeach; ?></select></label>
                    <label class="crm-field">Prioridad<select name="priority"><option <?php echo $selectedOpportunity['priority'] === 'Alta' ? 'selected' : ''; ?>>Alta</option><option <?php echo $selectedOpportunity['priority'] === 'Media' ? 'selected' : ''; ?>>Media</option><option <?php echo $selectedOpportunity['priority'] === 'Baja' ? 'selected' : ''; ?>>Baja</option></select></label>
                    <label class="crm-field">Valor estimado<input type="number" name="estimated_value" value="<?php echo h($selectedOpportunity['estimated_value']); ?>" min="0" step="1000"></label>
                    <label class="crm-field">Siguiente accion<input type="date" name="next_action_date" value="<?php echo h($selectedOpportunity['next_action_date']); ?>"></label>
                    <label class="crm-field crm-field--wide">Notas<textarea name="notes" rows="4"><?php echo h($selectedOpportunity['notes']); ?></textarea></label>
                  </div>
                  <button class="crm-button" type="submit">Guardar seguimiento</button>
                </form>
              </article>
            </div>

            <div class="crm-grid">
              <article class="crm-card">
                <h2>Cotizaciones vinculadas</h2>
                <div class="crm-list">
                  <?php foreach ($selectedOpportunityQuotes as $quote): ?>
                    <div class="crm-list__item">
                      <span class="crm-pill <?php echo h(crm_pill_class((string) $quote['status'])); ?>"><?php echo h($quote['status']); ?></span>
                      <strong><?php echo h($quote['quote_code']); ?> - <?php echo crm_money($quote['amount']); ?></strong>
                      <small><?php echo (int) $quote['probability']; ?>% - <?php echo h($quote['valid_until'] ?: 'Sin vigencia'); ?></small>
                      <a class="crm-button crm-button--ghost" href="index.php?view=quote&id=<?php echo (int) $quote['id']; ?>">Ver cotizacion</a>
                    </div>
                  <?php endforeach; ?>
                  <?php if (!$selectedOpportunityQuotes): ?><p>No hay cotizaciones vinculadas.</p><?php endif; ?>
                </div>
              </article>

              <article class="crm-card">
                <h2>Actividad reciente</h2>
                <div class="crm-list">
                  <?php foreach ($selectedOpportunityActivities as $activity): ?>
                    <div class="crm-list__item">
                      <span class="crm-pill crm-pill--neutral"><?php echo h($activity['type']); ?></span>
                      <strong><?php echo h($activity['summary']); ?></strong>
                      <small><?php echo h($activity['created_at']); ?><?php if (!empty($activity['due_date'])): ?> - vence <?php echo h($activity['due_date']); ?><?php endif; ?></small>
                    </div>
                  <?php endforeach; ?>
                  <?php if (!$selectedOpportunityActivities): ?><p>No hay actividad registrada.</p><?php endif; ?>
                </div>
              </article>
            </div>
          <?php endif; ?>        <?php elseif ($view === 'quotes'): ?>
          <div class="crm-head"><div><p class="eyebrow">Propuestas</p><h1>Cotizaciones</h1><p>Solicitudes y propuestas resumidas. Abre cada registro para ver todos los datos.</p></div></div>

          <article class="crm-card crm-form-card">
            <h2>Nueva cotizacion</h2>
            <form class="crm-form" method="post">
              <input type="hidden" name="token" value="<?php echo h($token); ?>">
              <input type="hidden" name="action" value="create_quote">
              <div class="crm-form-grid crm-form-grid--quotes">
                <label class="crm-field">Oportunidad
                  <select name="opportunity_id" required>
                    <?php foreach ($quoteableOpportunities as $opportunity): ?>
                      <option value="<?php echo (int) $opportunity['id']; ?>"><?php echo h($opportunity['company_name'] . ' - ' . $opportunity['service']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </label>
                <label class="crm-field">Monto<input type="number" name="amount" min="0" step="1000" required></label>
                <label class="crm-field">Estatus<select name="status"><?php foreach ($quoteStatuses as $status): ?><option><?php echo h($status); ?></option><?php endforeach; ?></select></label>
                <label class="crm-field">Probabilidad<input type="number" name="probability" min="0" max="100" value="40"></label>
                <label class="crm-field">Vigencia<input type="date" name="valid_until"></label>
              </div>
              <button class="crm-button" type="submit">Crear cotizacion</button>
            </form>
          </article>

          <article class="crm-card">
            <div class="crm-table-wrap">
              <table class="crm-table crm-table--compact">
                <thead><tr><th>Folio</th><th>Empresa</th><th>Servicio</th><th>Estatus</th><th>Ver</th></tr></thead>
                <tbody>
                  <?php foreach ($quotes as $quote): ?>
                    <?php $quoteStatus = trim((string) ($quote['status'] ?? '')); ?>
                    <?php if ($quoteStatus === '') { $quoteStatus = 'En elaboracion'; } ?>
                    <tr>
                      <td><strong><?php echo h($quote['quote_code']); ?></strong></td>
                      <td><?php echo h($quote['company_name']); ?></td>
                      <td><?php echo h($quote['service']); ?></td>
                      <td><span class="crm-pill crm-pill--neutral"><?php echo h($quoteStatus); ?></span></td>
                      <td>
                        <form method="get" action="index.php">
                          <input type="hidden" name="view" value="quote">
                          <input type="hidden" name="id" value="<?php echo (int) $quote['id']; ?>">
                          <button class="crm-button crm-button--ghost" type="submit">Ver</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if (!$quotes): ?>
                    <tr><td colspan="5">Aun no hay cotizaciones. Crea la primera desde una oportunidad activa.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </article>
        <?php elseif ($view === 'quote'): ?>
          <?php if (!$selectedQuote): ?>
            <div class="crm-head"><div><p class="eyebrow">Propuestas</p><h1>Cotizacion no encontrada</h1><p>El registro solicitado no existe o fue eliminado.</p></div><a class="crm-button crm-button--ghost" href="index.php?view=quotes">Volver</a></div>
          <?php else: ?>
            <?php
              $quoteStatus = trim((string) ($selectedQuote['status'] ?? '')) ?: 'En elaboracion';
              $quoteProbability = $selectedQuote['probability'] === null || $selectedQuote['probability'] === '' ? 40 : (int) $selectedQuote['probability'];
              $quoteValidUntil = trim((string) ($selectedQuote['valid_until'] ?? ''));
            ?>
            <div class="crm-head">
              <div>
                <p class="eyebrow">Detalle de cotizacion</p>
                <h1><?php echo h($selectedQuote['quote_code']); ?></h1>
                <p><?php echo h($selectedQuote['company_name']); ?> - <?php echo h($selectedQuote['service']); ?></p>
              </div>
              <a class="crm-button crm-button--ghost" href="index.php?view=quotes">Volver</a>
            </div>

            <div class="crm-kpis crm-kpis--secondary">
              <article class="crm-card"><h2>Monto</h2><p><?php echo crm_money($selectedQuote['amount']); ?></p></article>
              <article class="crm-card"><h2>Estatus</h2><p><span class="crm-pill <?php echo h(crm_pill_class($quoteStatus)); ?>"><?php echo h($quoteStatus); ?></span></p></article>
              <article class="crm-card"><h2>Probabilidad</h2><p><?php echo $quoteProbability; ?>%</p></article>
              <article class="crm-card"><h2>Vigencia</h2><p><?php echo h($quoteValidUntil !== '' ? $quoteValidUntil : 'Sin vigencia'); ?></p></article>
            </div>

            <div class="crm-grid">
              <article class="crm-card">
                <h2>Datos del prospecto</h2>
                <div class="crm-list crm-list--compact">
                  <div class="crm-list__item"><strong>Empresa</strong><span><?php echo h($selectedQuote['company_name']); ?></span></div>
                  <div class="crm-list__item"><strong>Contacto</strong><span><?php echo h($selectedQuote['contact_name'] ?: 'Sin contacto'); ?></span></div>
                  <div class="crm-list__item"><strong>Correo</strong><span><?php echo h($selectedQuote['contact_email'] ?: 'Sin correo'); ?></span></div>
                  <div class="crm-list__item"><strong>Telefono</strong><span><?php echo h($selectedQuote['contact_phone'] ?: 'Sin telefono'); ?></span></div>
                  <div class="crm-list__item"><strong>Etapa cliente</strong><span><?php echo h($selectedQuote['lifecycle_stage'] ?: 'Prospecto'); ?></span></div>
                </div>
              </article>

              <article class="crm-card">
                <h2>Datos de oportunidad</h2>
                <div class="crm-list crm-list--compact">
                  <div class="crm-list__item"><strong>Origen</strong><span><?php echo h($selectedQuote['source']); ?></span></div>
                  <div class="crm-list__item"><strong>Estatus comercial</strong><span><?php echo h($selectedQuote['opportunity_status']); ?></span></div>
                  <div class="crm-list__item"><strong>Prioridad</strong><span><?php echo h($selectedQuote['priority']); ?></span></div>
                  <div class="crm-list__item"><strong>Siguiente accion</strong><span><?php echo h($selectedQuote['next_action_date'] ?: 'Sin fecha'); ?></span></div>
                  <div class="crm-list__item"><strong>Notas</strong><span><?php echo h($selectedQuote['opportunity_notes'] ?: 'Sin notas'); ?></span></div>
                </div>
              </article>
            </div>

            <article class="crm-card crm-form-card">
              <h2>Actualizar cotizacion</h2>
              <form class="crm-form" method="post">
                <input type="hidden" name="token" value="<?php echo h($token); ?>">
                <input type="hidden" name="action" value="update_quote">
                <input type="hidden" name="return_to" value="quote">
                <input type="hidden" name="quote_id" value="<?php echo (int) $selectedQuote['id']; ?>">
                <div class="crm-form-grid crm-form-grid--quotes">
                  <label class="crm-field">Monto<input type="number" name="amount" value="<?php echo h($selectedQuote['amount']); ?>" min="0" step="1000"></label>
                  <label class="crm-field">Estatus<select name="status"><?php foreach ($quoteStatuses as $status): ?><option <?php echo $status === $quoteStatus ? 'selected' : ''; ?>><?php echo h($status); ?></option><?php endforeach; ?></select></label>
                  <label class="crm-field">Probabilidad<input type="number" name="probability" min="0" max="100" value="<?php echo $quoteProbability; ?>"></label>
                  <label class="crm-field">Vigencia<input type="date" name="valid_until" value="<?php echo h($quoteValidUntil); ?>"></label>
                </div>
                <button class="crm-button" type="submit">Guardar cambios</button>
              </form>
            </article>

            <article class="crm-card">
              <h2>Actividad reciente</h2>
              <div class="crm-list">
                <?php foreach ($selectedQuoteActivities as $activity): ?>
                  <div class="crm-list__item">
                    <span class="crm-pill crm-pill--neutral"><?php echo h($activity['type']); ?></span>
                    <strong><?php echo h($activity['summary']); ?></strong>
                    <small><?php echo h($activity['created_at']); ?><?php if (!empty($activity['due_date'])): ?> - vence <?php echo h($activity['due_date']); ?><?php endif; ?></small>
                  </div>
                <?php endforeach; ?>
                <?php if (!$selectedQuoteActivities): ?><p>No hay actividad registrada para esta cotizacion.</p><?php endif; ?>
              </div>
            </article>
          <?php endif; ?>        <?php elseif ($view === 'clients'): ?>
          <div class="crm-head"><div><p class="eyebrow">Cartera</p><h1>Clientes y prospectos</h1><p>Prospectos del sitio publico, clientes convertidos y referencias comerciales.</p></div></div>
          <article class="crm-card">
            <div class="crm-table-wrap">
              <table class="crm-table">
                <thead><tr><th>Cliente</th><th>Etapa</th><th>Segmento</th><th>Ciudad</th><th>Contacto</th><th>Publico</th><th>Notas</th><th>Accion</th></tr></thead>
                <tbody>
                  <?php foreach ($clients as $client): ?>
                    <tr>
                      <td><strong><?php echo h($client['name']); ?></strong></td>
                      <td><span class="crm-pill <?php echo ($client['lifecycle_stage'] ?? 'Cliente') === 'Prospecto' ? 'crm-pill--warning' : 'crm-pill--success'; ?>"><?php echo h($client['lifecycle_stage'] ?? 'Cliente'); ?></span></td>
                      <td><?php echo h($client['segment']); ?></td>
                      <td><?php echo h($client['city']); ?></td>
                      <td><?php echo h($client['contact_name'] ?: 'Sin contacto'); ?><br><small><?php echo h($client['contact_email'] ?: $client['contact_phone']); ?></small></td>
                      <td><span class="crm-pill <?php echo $client['is_public'] ? 'crm-pill--success' : 'crm-pill--neutral'; ?>"><?php echo $client['is_public'] ? 'Si' : 'No'; ?></span></td>
                      <td><?php echo h($client['notes']); ?></td>
                      <td>
                        <?php if (($client['lifecycle_stage'] ?? 'Cliente') === 'Prospecto'): ?>
                          <form method="post">
                            <input type="hidden" name="token" value="<?php echo h($token); ?>">
                            <input type="hidden" name="action" value="convert_client">
                            <input type="hidden" name="client_id" value="<?php echo (int) $client['id']; ?>">
                            <button class="crm-button crm-button--ghost" type="submit">Convertir</button>
                          </form>
                        <?php else: ?>
                          <span class="crm-pill crm-pill--neutral">Activo</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </article>
        <?php elseif ($view === 'profile'): ?>
          <div class="crm-head">
            <div>
              <p class="eyebrow">Cuenta</p>
              <h1>Perfil administrador</h1>
              <p>Actualiza el password de acceso al CRM.</p>
            </div>
          </div>

          <article class="crm-card crm-form-card">
            <h2>Cambiar password</h2>
            <form class="crm-form" method="post" autocomplete="off">
              <input type="hidden" name="token" value="<?php echo h($token); ?>">
              <input type="hidden" name="action" value="change_password">
              <div class="crm-form-grid">
                <label class="crm-field">Usuario<input value="<?php echo h($_SESSION['crm_user']['email']); ?>" disabled></label>
                <label class="crm-field">Password actual<span class="crm-password-field"><input id="profile-current-password" type="password" name="current_password" autocomplete="current-password" required><button class="crm-password-toggle" type="button" aria-label="Mostrar password actual" aria-controls="profile-current-password" data-password-toggle><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5c5 0 8.5 4.2 9.7 6.1a1.7 1.7 0 0 1 0 1.8C20.5 14.8 17 19 12 19s-8.5-4.2-9.7-6.1a1.7 1.7 0 0 1 0-1.8C3.5 9.2 7 5 12 5Zm0 2C7.9 7 4.9 10.4 4 12c.9 1.6 3.9 5 8 5s7.1-3.4 8-5c-.9-1.6-3.9-5-8-5Zm0 2.2a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6Z"/></svg></button></span></label>
                <label class="crm-field">Nuevo password<span class="crm-password-field"><input id="profile-new-password" type="password" name="new_password" autocomplete="new-password" minlength="10" required><button class="crm-password-toggle" type="button" aria-label="Mostrar nuevo password" aria-controls="profile-new-password" data-password-toggle><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5c5 0 8.5 4.2 9.7 6.1a1.7 1.7 0 0 1 0 1.8C20.5 14.8 17 19 12 19s-8.5-4.2-9.7-6.1a1.7 1.7 0 0 1 0-1.8C3.5 9.2 7 5 12 5Zm0 2C7.9 7 4.9 10.4 4 12c.9 1.6 3.9 5 8 5s7.1-3.4 8-5c-.9-1.6-3.9-5-8-5Zm0 2.2a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6Z"/></svg></button></span></label>
                <label class="crm-field">Confirmar password<span class="crm-password-field"><input id="profile-confirm-password" type="password" name="confirm_password" autocomplete="new-password" minlength="10" required><button class="crm-password-toggle" type="button" aria-label="Mostrar confirmacion de password" aria-controls="profile-confirm-password" data-password-toggle><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5c5 0 8.5 4.2 9.7 6.1a1.7 1.7 0 0 1 0 1.8C20.5 14.8 17 19 12 19s-8.5-4.2-9.7-6.1a1.7 1.7 0 0 1 0-1.8C3.5 9.2 7 5 12 5Zm0 2C7.9 7 4.9 10.4 4 12c.9 1.6 3.9 5 8 5s7.1-3.4 8-5c-.9-1.6-3.9-5-8-5Zm0 2.2a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6Z"/></svg></button></span></label>
              </div>
              <button class="crm-button" type="submit">Actualizar password</button>
            </form>
          </article>
        <?php else: ?>
          <div class="crm-head crm-report-head">
            <div>
              <p class="eyebrow">Mantenimiento continuo</p>
              <h1>Bitacora ID</h1>
              <p>Accesos de clientes, reportes de mantenimiento y seguimiento operativo.</p>
            </div>
            <div class="crm-head__actions">
              <button class="crm-button crm-button--ghost" type="button" data-print-report>Exportar PDF</button>
              <a class="crm-button" href="cliente.php">Portal cliente</a>
            </div>
          </div>

          <section class="crm-report" aria-label="Reporte de mantenimiento">
            <div class="crm-report__meta">
              <strong>Reporte de mantenimiento</strong>
              <span>Generado: <?php echo h(date('Y-m-d H:i')); ?></span>
            </div>

            <div class="crm-kpis crm-kpis--secondary">
              <article class="crm-card crm-kpi"><span class="crm-kpi__icon">R</span><div><span>Reportes totales</span><strong><?php echo $maintenanceMetrics['total']; ?></strong></div></article>
              <article class="crm-card crm-kpi"><span class="crm-kpi__icon">A</span><div><span>Abiertos</span><strong><?php echo $maintenanceMetrics['open']; ?></strong></div></article>
              <article class="crm-card crm-kpi"><span class="crm-kpi__icon">U</span><div><span>Urgentes</span><strong><?php echo $maintenanceMetrics['urgent']; ?></strong></div></article>
              <article class="crm-card crm-kpi"><span class="crm-kpi__icon">%</span><div><span>Con respuesta</span><strong><?php echo $maintenanceMetrics['response_rate']; ?>%</strong></div></article>
            </div>

            <div class="crm-report-grid">
              <article class="crm-card crm-chart-card">
                <h2>Estatus de reportes</h2>
                <div class="crm-chart-bars">
                  <?php foreach ($requestStatusTotals as $status => $total): ?>
                    <?php $percent = $total > 0 ? max(6, round(($total / $maxStatusTotal) * 100)) : 0; ?>
                    <div class="crm-chart-row">
                      <div class="crm-chart-row__label"><span><?php echo h($status); ?></span><strong><?php echo (int) $total; ?></strong></div>
                      <div class="crm-chart-row__track"><span style="width: <?php echo $percent; ?>%"></span></div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </article>

              <article class="crm-card crm-chart-card">
                <h2>Prioridad</h2>
                <div class="crm-chart-bars">
                  <?php foreach ($requestPriorityTotals as $priority => $total): ?>
                    <?php $percent = $total > 0 ? max(6, round(($total / $maxPriorityTotal) * 100)) : 0; ?>
                    <div class="crm-chart-row">
                      <div class="crm-chart-row__label"><span><?php echo h($priority); ?></span><strong><?php echo (int) $total; ?></strong></div>
                      <div class="crm-chart-row__track"><span style="width: <?php echo $percent; ?>%"></span></div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </article>

              <article class="crm-card crm-chart-card">
                <h2>Ultimos 6 meses</h2>
                <div class="crm-month-chart">
                  <?php foreach ($requestMonthlyTotals as $month => $total): ?>
                    <?php $height = $total > 0 ? max(12, round(($total / $maxMonthlyTotal) * 100)) : 4; ?>
                    <div class="crm-month-chart__bar">
                      <span style="height: <?php echo $height; ?>%"></span>
                      <strong><?php echo (int) $total; ?></strong>
                      <small><?php echo h(date('M', strtotime($month . '-01'))); ?></small>
                    </div>
                  <?php endforeach; ?>
                </div>
              </article>
            </div>
          </section>

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
              <div class="crm-list crm-list--requests">
                <?php foreach ($clientRequests as $request): ?>
                  <?php $requestStatus = trim((string) ($request['status'] ?? 'Recibida')) ?: 'Recibida'; ?>
                  <?php $requestPriority = trim((string) ($request['priority'] ?? 'Media')) ?: 'Media'; ?>
                  <form class="crm-list__item crm-request-card" method="post">
                    <input type="hidden" name="token" value="<?php echo h($token); ?>">
                    <input type="hidden" name="action" value="update_client_request">
                    <input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">
                    <div class="crm-request-card__head">
                      <span class="crm-pill <?php echo h(crm_pill_class($requestStatus)); ?>"><?php echo h($requestStatus); ?></span>
                      <small><?php echo h($request['company_name']); ?> - <?php echo h($request['created_at']); ?></small>
                    </div>
                    <strong><?php echo h($request['title']); ?></strong>
                    <p><?php echo h($request['message']); ?></p>
                    <?php if (!empty($request['admin_response'])): ?><div class="crm-response"><strong>Respuesta actual</strong><p><?php echo h($request['admin_response']); ?></p></div><?php endif; ?>
                    <div class="crm-form-grid crm-form-grid--request">
                      <label class="crm-field">Estatus
                        <select name="status"><?php foreach ($requestStatuses as $status): ?><option <?php echo $status === $requestStatus ? 'selected' : ''; ?>><?php echo h($status); ?></option><?php endforeach; ?></select>
                      </label>
                      <label class="crm-field">Prioridad
                        <select name="priority"><?php foreach ($requestPriorities as $priority): ?><option <?php echo $priority === $requestPriority ? 'selected' : ''; ?>><?php echo h($priority); ?></option><?php endforeach; ?></select>
                      </label>
                      <label class="crm-field crm-field--wide">Respuesta para el cliente<textarea name="admin_response" rows="3"><?php echo h($request['admin_response'] ?? ''); ?></textarea></label>
                    </div>
                    <button class="crm-button crm-button--ghost" type="submit">Guardar seguimiento</button>
                  </form>
                <?php endforeach; ?>
                <?php if (!$clientRequests): ?><p>No hay solicitudes de mantenimiento registradas.</p><?php endif; ?>
              </div>
            </article>
          </div>
        <?php endif; ?>
      </section>
    </main>
  </div>
  <script>
    (() => {
      document.querySelectorAll('[data-password-toggle]').forEach((toggle) => {
        const targetId = toggle.getAttribute('aria-controls');
        const input = targetId ? document.getElementById(targetId) : null;
        if (!input) return;

        toggle.addEventListener('click', () => {
          const showing = input.type === 'text';
          input.type = showing ? 'password' : 'text';
          toggle.setAttribute('aria-label', showing ? 'Mostrar password' : 'Ocultar password');
          toggle.classList.toggle('is-active', !showing);
        });
      });

      const printButton = document.querySelector('[data-print-report]');
      if (printButton) {
        printButton.addEventListener('click', () => {
          const originalTitle = document.title;
          document.title = 'Reporte Bitacora ID - ID Industrial';
          window.print();
          window.setTimeout(() => {
            document.title = originalTitle;
          }, 500);
        });
      }
    })();
  </script>
</body>
</html>
