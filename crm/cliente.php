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

function h($value): string
{
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function bitacora_icon(string $name): string
{
  $icons = [
    'dashboard' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="8" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="15" width="7" height="6" rx="1.5"/></svg>',
    'projects' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7.5A2.5 2.5 0 0 1 6.5 5H10l2 2h5.5A2.5 2.5 0 0 1 20 9.5v7A2.5 2.5 0 0 1 17.5 19h-11A2.5 2.5 0 0 1 4 16.5v-9Z"/><path d="M4 10h16"/></svg>',
    'logs' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 4h8l3 3v13H5V4h3Z"/><path d="M15 4v4h4"/><path d="M8 12h8"/><path d="M8 16h6"/></svg>',
    'requests' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 5h14v10H8l-3 3V5Z"/><path d="M9 9h6"/><path d="M9 12h4"/></svg>',
    'profile' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4.5 20a7.5 7.5 0 0 1 15 0"/></svg>',
    'lock' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>',
    'logout' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 5H5v14h5"/><path d="M14 8l4 4-4 4"/><path d="M9 12h9"/></svg>',
    'menu' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h16"/></svg>',
    'check' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>',
  ];
  return $icons[$name] ?? $icons['dashboard'];
}

function bitacora_pill_class(string $value): string
{
  $key = strtolower($value);
  if (strpos($key, 'resuelta') !== false || strpos($key, 'cerrada') !== false || strpos($key, 'activo') !== false) {
    return 'crm-pill--success';
  }
  if (strpos($key, 'urgente') !== false || strpos($key, 'cancelada') !== false) {
    return 'crm-pill--danger';
  }
  if (strpos($key, 'revision') !== false || strpos($key, 'programada') !== false || strpos($key, 'proceso') !== false || strpos($key, 'recibida') !== false) {
    return 'crm-pill--warning';
  }
  return 'crm-pill--neutral';
}

function bitacora_request_due_date(string $priority): string
{
  $daysByPriority = [
    'Urgente' => 1,
    'Alta' => 2,
    'Media' => 5,
    'Baja' => 10,
  ];
  $days = $daysByPriority[$priority] ?? 5;
  return date('Y-m-d', strtotime('+' . $days . ' days'));
}
function bitacora_request_next_step(string $status): string
{
  $steps = [
    'Recibida' => 'Solicitud recibida por ID Industrial.',
    'En revision' => 'El equipo esta revisando alcance y prioridad.',
    'Programada' => 'Servicio programado para atencion.',
    'En proceso' => 'Servicio en atencion por el equipo tecnico.',
    'Resuelta' => 'Solicitud resuelta, pendiente de validacion final.',
    'Cerrada' => 'Solicitud cerrada.',
  ];
  return $steps[$status] ?? 'Seguimiento en actualizacion.';
}
function bitacora_project_accesses(PDO $pdo, array $portal): array
{
  $clientId = (int) ($portal['client_id'] ?? 0);
  if ($clientId > 0) {
    $stmt = $pdo->prepare('
      SELECT cpu.*, o.company_name, o.contact_name, o.contact_email, o.contact_phone, o.service, o.status AS opportunity_status, o.next_action_date, o.notes AS opportunity_notes, o.id AS opportunity_id, o.updated_at AS opportunity_updated_at, (SELECT COUNT(*) FROM maintenance_logs ml WHERE ml.opportunity_id = o.id AND ml.visible_to_client = 1) AS log_count, (SELECT COUNT(*) FROM client_requests cr WHERE cr.opportunity_id = o.id AND cr.portal_user_id = cpu.id) AS request_count
      FROM client_portal_users cpu
      JOIN opportunities o ON o.id = cpu.opportunity_id
      WHERE cpu.client_id = ? AND cpu.is_active = 1
      ORDER BY o.updated_at DESC, o.created_at DESC
    ');
    $stmt->execute([$clientId]);
    $projects = $stmt->fetchAll();
    if ($projects) {
      return $projects;
    }
  }

  $stmt = $pdo->prepare('
    SELECT cpu.*, o.company_name, o.contact_name, o.contact_email, o.contact_phone, o.service, o.status AS opportunity_status, o.next_action_date, o.notes AS opportunity_notes, o.id AS opportunity_id, o.updated_at AS opportunity_updated_at, (SELECT COUNT(*) FROM maintenance_logs ml WHERE ml.opportunity_id = o.id AND ml.visible_to_client = 1) AS log_count, (SELECT COUNT(*) FROM client_requests cr WHERE cr.opportunity_id = o.id AND cr.portal_user_id = cpu.id) AS request_count
    FROM client_portal_users cpu
    JOIN opportunities o ON o.id = cpu.opportunity_id
    WHERE cpu.id = ? AND cpu.is_active = 1
    LIMIT 1
  ');
  $stmt->execute([(int) ($portal['id'] ?? 0)]);
  $project = $stmt->fetch();
  return $project ? [$project] : [];
}

function bitacora_select_project(array $projects, int $projectId): ?array
{
  foreach ($projects as $project) {
    if ((int) $project['opportunity_id'] === $projectId) {
      return $project;
    }
  }
  return $projects[0] ?? null;
}
function bitacora_client_url(string $view, int $projectId = 0): string
{
  $allowedViews = ['resumen', 'proyectos', 'bitacora', 'solicitudes', 'perfil'];
  $params = ['view' => in_array($view, $allowedViews, true) ? $view : 'resumen'];
  if ($projectId > 0) {
    $params['project_id'] = $projectId;
  }
  return 'cliente.php?' . http_build_query($params);
}
function bitacora_token(): string
{
  if (empty($_SESSION['bitacora_token'])) {
    $_SESSION['bitacora_token'] = bin2hex(random_bytes(16));
  }
  return $_SESSION['bitacora_token'];
}

function bitacora_check_token(): void
{
  if (!hash_equals($_SESSION['bitacora_token'] ?? '', $_POST['token'] ?? '')) {
    http_response_code(403);
    exit('Token invalido.');
  }
}

if (isset($_GET['logout'])) {
  unset($_SESSION['bitacora_user'], $_SESSION['bitacora_token'], $_SESSION['bitacora_user_last_activity']);
  header('Location: cliente.php');
  exit;
}

crm_enforce_session_timeout('bitacora_user', 'bitacora_token', 'cliente.php?expired=1');

$humanChallengeKey = 'bitacora_login_human_challenge';
$loginError = isset($_GET['expired']) ? 'Sesion cerrada por inactividad. Vuelve a iniciar sesion.' : '';
if (($_POST['action'] ?? '') === 'client_login') {
  $username = trim((string) ($_POST['bitacora_user'] ?? ''));
  $password = (string) ($_POST['bitacora_password'] ?? '');
  $humanAnswer = (string) ($_POST['human_answer'] ?? '');
  $loginIdentifier = $username !== '' ? $username : 'anonimo';
  $lockStatus = crm_login_lock_status($pdo, 'client', $loginIdentifier);

  if (!empty($lockStatus['locked'])) {
    $loginError = crm_login_lock_message($lockStatus);
  } elseif (!crm_validate_math_challenge($humanChallengeKey, $humanAnswer)) {
    $status = crm_record_login_failure($pdo, 'client', $loginIdentifier);
    crm_refresh_math_challenge($humanChallengeKey);
    $loginError = crm_login_attempt_message('Confirma que eres humano resolviendo la suma.', $status);
  } elseif ($username === '' || strlen($password) < 8) {
    $status = crm_record_login_failure($pdo, 'client', $loginIdentifier);
    crm_refresh_math_challenge($humanChallengeKey);
    $loginError = crm_login_attempt_message('Ingresa usuario y password validos.', $status);
  } else {
    $portalUser = crm_portal_user_by_username($pdo, $username);
    if ($portalUser && password_verify($password, $portalUser['password_hash'])) {
      crm_record_login_success($pdo, 'client', $username);
      crm_refresh_math_challenge($humanChallengeKey);
      session_regenerate_id(true);
      $mustChangePassword = (int) ($portalUser['password_change_required'] ?? 1) === 1 || empty($portalUser['password_changed_at']);
      crm_update_portal_last_login($pdo, (int) $portalUser['id']);
      $_SESSION['bitacora_user'] = [
        'id' => (int) $portalUser['id'],
        'opportunity_id' => (int) $portalUser['opportunity_id'],
        'client_id' => (int) ($portalUser['client_id'] ?? 0),
        'username' => $portalUser['username'],
        'company_name' => $portalUser['company_name'],
        'contact_name' => $portalUser['contact_name'],
        'service' => $portalUser['service'],
        'must_change_password' => $mustChangePassword,
      ];
      $_SESSION['bitacora_user_last_activity'] = time();
      bitacora_token();
      header('Location: cliente.php');
      exit;
    }
    $status = crm_record_login_failure($pdo, 'client', $loginIdentifier);
    crm_refresh_math_challenge($humanChallengeKey);
    $loginError = crm_login_failure_message($status);
  }
}
$humanChallenge = crm_math_challenge($humanChallengeKey);
if (empty($_SESSION['bitacora_user'])):
?>
<!doctype html>
<html lang="es-MX">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Bitacora ID | Acceso cliente</title>
  <link rel="stylesheet" href="../assets/css/crm.css">
</head>
<body class="crm-login">
  <main class="crm-login__panel crm-login__panel--client">
    <section class="crm-login__media" aria-label="Bitacora ID mantenimiento industrial">
      <img src="../assets/img/optimized/home-hero-control-acceso.jpg" alt="Mantenimiento industrial ID Industrial" width="1920" height="500">
      <div class="crm-login__media-copy">
        <span>Bitacora ID</span>
        <strong>Mantenimiento, evidencia y seguimiento despues de la entrega.</strong>
      </div>
    </section>

    <section class="crm-login__card" aria-labelledby="client-login-title">
      <div class="crm-login__brand">
        <img src="../assets/img/logo-idindustrial-small.webp" alt="ID Industrial" width="280" height="74">
        <div>
          <strong>Bitacora ID</strong>
          <span>Portal cliente</span>
        </div>
      </div>
      <h1 id="client-login-title">Acceso cliente</h1>
      <p>Consulta tu proyecto entregado, mantenimientos y solicitudes de servicio.</p>
      <?php if ($loginError): ?><p class="crm-alert"><?php echo h($loginError); ?></p><?php endif; ?>
      <form method="post" autocomplete="off" data-login-form novalidate>
        <input type="hidden" name="action" value="client_login">
        <label class="crm-field">
          Usuario
          <input name="bitacora_user" autocomplete="off" autocapitalize="none" spellcheck="false" required data-login-email>
          <span class="crm-field__error">Ingresa tu usuario de Bitacora ID.</span>
        </label>
        <label class="crm-field">
          Password
          <span class="crm-password-field">
            <input id="bitacora-password" type="password" name="bitacora_password" autocomplete="new-password" minlength="8" required data-login-password>
            <button class="crm-password-toggle" type="button" aria-label="Mostrar password" aria-controls="bitacora-password" data-password-toggle>
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5c5 0 8.5 4.2 9.7 6.1a1.7 1.7 0 0 1 0 1.8C20.5 14.8 17 19 12 19s-8.5-4.2-9.7-6.1a1.7 1.7 0 0 1 0-1.8C3.5 9.2 7 5 12 5Zm0 2C7.9 7 4.9 10.4 4 12c.9 1.6 3.9 5 8 5s7.1-3.4 8-5c-.9-1.6-3.9-5-8-5Zm0 2.2a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6Z"/></svg>
            </button>
          </span>
          <span class="crm-field__error">La contrasena debe tener al menos 8 caracteres.</span>
        </label>
        <label class="crm-field crm-human-check">
          Verificacion humana
          <span class="crm-human-check__row">
            <span class="crm-human-check__question"><?php echo h((string) $humanChallenge['a']); ?> + <?php echo h((string) $humanChallenge['b']); ?></span>
            <input type="number" name="human_answer" inputmode="numeric" min="0" max="18" autocomplete="off" required data-human-answer placeholder="Resultado" aria-label="Resultado de la suma">
          </span>
          <span class="crm-field__error">Resuelve la suma para continuar.</span>
        </label>
        <button class="crm-button" type="submit">Entrar a Bitacora ID</button>
      </form>
    </section>
  </main>
  <script>
    (() => {
      const form = document.querySelector('[data-login-form]');
      const password = document.querySelector('[data-login-password]');
      const toggle = document.querySelector('[data-password-toggle]');
      const human = document.querySelector('[data-human-answer]');
      if (!form || !password || !toggle || !human) return;
      toggle.addEventListener('click', () => {
        const showing = password.type === 'text';
        password.type = showing ? 'password' : 'text';
        toggle.setAttribute('aria-label', showing ? 'Mostrar password' : 'Ocultar password');
        toggle.classList.toggle('is-active', !showing);
      });
      form.addEventListener('submit', (event) => {
        form.classList.add('was-validated');
        if (!form.checkValidity()) {
          event.preventDefault();
          form.querySelector(':invalid')?.focus();
        }
      });
    })();
  </script>
</body>
</html>
<?php
exit;
endif;

$portal = $_SESSION['bitacora_user'];
if (empty($portal['client_id'])) {
  $refreshPortalStmt = $pdo->prepare('SELECT client_id FROM client_portal_users WHERE id = ? LIMIT 1');
  $refreshPortalStmt->execute([(int) ($portal['id'] ?? 0)]);
  $refreshClientId = (int) ($refreshPortalStmt->fetchColumn() ?: 0);
  if ($refreshClientId > 0) {
    $portal['client_id'] = $refreshClientId;
    $_SESSION['bitacora_user']['client_id'] = $refreshClientId;
  }
}
$accountStmt = $pdo->prepare('SELECT id, username, password_hash, password_change_required, password_changed_at, last_login_at FROM client_portal_users WHERE id = ? AND is_active = 1 LIMIT 1');
$accountStmt->execute([(int) ($portal['id'] ?? 0)]);
$portalAccount = $accountStmt->fetch();
if (!$portalAccount) {
  unset($_SESSION['bitacora_user'], $_SESSION['bitacora_token']);
  header('Location: cliente.php');
  exit;
}
$mustChangePassword = (int) ($portalAccount['password_change_required'] ?? 1) === 1 || empty($portalAccount['password_changed_at']);
$portal['must_change_password'] = $mustChangePassword;
$_SESSION['bitacora_user']['must_change_password'] = $mustChangePassword;
$requestPriorities = ['Baja', 'Media', 'Alta', 'Urgente'];
$projectAccesses = bitacora_project_accesses($pdo, $portal);
$selectedProjectId = max(0, (int) ($_POST['project_id'] ?? $_GET['project_id'] ?? $portal['opportunity_id'] ?? 0));
$currentAccess = bitacora_select_project($projectAccesses, $selectedProjectId);
if (!$currentAccess) {
  unset($_SESSION['bitacora_user'], $_SESSION['bitacora_token']);
  header('Location: cliente.php');
  exit;
}
$clientViews = [
  'resumen' => ['label' => 'Resumen', 'icon' => 'dashboard'],
  'proyectos' => ['label' => 'Proyectos', 'icon' => 'projects'],
  'bitacora' => ['label' => 'Bitacora', 'icon' => 'logs'],
  'solicitudes' => ['label' => 'Solicitudes', 'icon' => 'requests'],
  'perfil' => ['label' => 'Perfil', 'icon' => 'profile'],
];
$activeView = (string) ($_GET['view'] ?? 'resumen');
if (!isset($clientViews[$activeView])) {
  $activeView = 'resumen';
}
if (($_POST['action'] ?? '') === 'update_client_password') {
  $activeView = 'perfil';
} elseif (($_POST['action'] ?? '') === 'create_request') {
  $activeView = 'solicitudes';
}
$notice = null;
if (($_POST['action'] ?? '') === 'update_client_password') {
  bitacora_check_token();
  $currentPassword = (string) ($_POST['current_password'] ?? '');
  $newPassword = (string) ($_POST['new_password'] ?? '');
  $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

  if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
    $notice = ['type' => 'error', 'text' => 'Completa todos los campos de password.'];
  } elseif (!password_verify($currentPassword, (string) $portalAccount['password_hash'])) {
    $notice = ['type' => 'error', 'text' => 'El password actual no coincide.'];
  } elseif (strlen($newPassword) < 10) {
    $notice = ['type' => 'error', 'text' => 'El nuevo password debe tener al menos 10 caracteres.'];
  } elseif ($newPassword !== $confirmPassword) {
    $notice = ['type' => 'error', 'text' => 'La confirmacion no coincide con el nuevo password.'];
  } elseif (password_verify($newPassword, (string) $portalAccount['password_hash'])) {
    $notice = ['type' => 'error', 'text' => 'El nuevo password debe ser diferente al actual.'];
  } else {
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $updatePassword = $pdo->prepare('UPDATE client_portal_users SET password_hash = ?, password_change_required = 0, password_changed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
    $updatePassword->execute([$hash, (int) $portalAccount['id']]);
    $portalAccount['password_hash'] = $hash;
    $portalAccount['password_change_required'] = 0;
    $portalAccount['password_changed_at'] = date('Y-m-d H:i:s');
    $portal['must_change_password'] = false;
    $_SESSION['bitacora_user']['must_change_password'] = false;
    $mustChangePassword = false;
    $notice = ['type' => 'success', 'text' => 'Password actualizado correctamente.'];
  }
}

if (($_POST['action'] ?? '') === 'create_request') {
  bitacora_check_token();
  $postedProjectId = max(0, (int) ($_POST['project_id'] ?? 0));
  $postedAccess = bitacora_select_project($projectAccesses, $postedProjectId);
  if ($postedAccess) {
    $currentAccess = $postedAccess;
  }
  $title = trim((string) ($_POST['title'] ?? ''));
  $message = trim((string) ($_POST['message'] ?? ''));
  $priority = trim((string) ($_POST['priority'] ?? 'Media'));
  if (!in_array($priority, $requestPriorities, true)) {
    $priority = 'Media';
  }
  if ($title === '' || $message === '') {
    $notice = ['type' => 'error', 'text' => 'Completa asunto y descripcion de la solicitud.'];
  } else {
    $stmt = $pdo->prepare('INSERT INTO client_requests (opportunity_id, portal_user_id, title, message, status, priority, due_date) VALUES (?, ?, ?, ?, "Recibida", ?, ?)');
    $stmt->execute([(int) $currentAccess['opportunity_id'], (int) $currentAccess['id'], $title, $message, $priority, bitacora_request_due_date($priority)]);
    $notice = ['type' => 'success', 'text' => 'Solicitud recibida. El equipo de ID Industrial dara seguimiento por proyecto.'];
  }
}

$projectAccesses = bitacora_project_accesses($pdo, $portal);
$currentAccess = bitacora_select_project($projectAccesses, (int) $currentAccess['opportunity_id']) ?: $currentAccess;
$token = bitacora_token();
$projects = $projectAccesses;
$project = $currentAccess;
$activeProject = $currentAccess;
$activeOpportunityId = (int) $currentAccess['opportunity_id'];
$activePortalUserId = (int) $currentAccess['id'];
$logsStmt = $pdo->prepare('SELECT * FROM maintenance_logs WHERE opportunity_id = ? AND visible_to_client = 1 ORDER BY COALESCE(scheduled_date, created_at) DESC, id DESC');
$logsStmt->execute([$activeOpportunityId]);
$logs = $logsStmt->fetchAll();
$requestsStmt = $pdo->prepare('SELECT * FROM client_requests WHERE opportunity_id = ? AND portal_user_id = ? ORDER BY created_at DESC');
$requestsStmt->execute([$activeOpportunityId, $activePortalUserId]);
$requests = $requestsStmt->fetchAll();
?>
<!doctype html>
<html lang="es-MX">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Bitacora ID | <?php echo h($project['company_name']); ?></title>
  <link rel="stylesheet" href="../assets/css/crm.css">
</head>
<body class="crm-app crm-client-app crm-client-portal">
  <div class="crm-client-layout">
    <aside class="crm-client-sidebar" id="cliente-sidebar">
      <div class="crm-client-brand">
        <img src="../assets/img/logo-idindustrial-small.webp" alt="ID Industrial" width="280" height="74">
        <div><strong>Bitacora ID</strong><span>Portal cliente</span></div>
      </div>
      <nav class="crm-client-nav" aria-label="Navegacion del portal cliente">
        <?php foreach ($clientViews as $viewKey => $viewItem): ?>
          <a href="<?php echo h(bitacora_client_url($viewKey, $activeOpportunityId)); ?>" class="<?php echo $activeView === $viewKey ? 'is-active' : ''; ?>">
            <span><?php echo bitacora_icon($viewItem['icon']); ?></span><?php echo h($viewItem['label']); ?>
          </a>
        <?php endforeach; ?>
      </nav>
      <div class="crm-client-sidebar__footer">
        <span>Usuario</span>
        <strong><?php echo h($portal['username']); ?></strong>
        <a href="cliente.php?logout=1"><span><?php echo bitacora_icon('logout'); ?></span>Cerrar sesion</a>
      </div>
    </aside>
    <button class="crm-client-overlay" type="button" aria-label="Cerrar menu" data-client-menu-close></button>

    <main class="crm-client-main">
      <header class="crm-client-topbar">
        <button class="crm-client-menu" type="button" aria-label="Abrir menu" aria-controls="cliente-sidebar" aria-expanded="false" data-client-menu-toggle><?php echo bitacora_icon('menu'); ?></button>
        <div><small>ID Industrial</small><strong>Bitacora ID</strong></div>
        <a class="crm-button crm-button--ghost" href="cliente.php?logout=1">Cerrar sesion</a>
      </header>

      <?php if ($notice): ?><div class="crm-flash crm-flash--<?php echo h($notice['type']); ?>"><p><?php echo h($notice['text']); ?></p></div><?php endif; ?>
      <?php if ($mustChangePassword && $activeView !== 'perfil'): ?>
        <section class="crm-security-callout" aria-live="polite">
          <span><?php echo bitacora_icon('lock'); ?></span>
          <div><strong>Actualiza tu password por seguridad</strong><p>Es tu primer acceso o tu password fue regenerado. Cambialo desde Perfil antes de compartir este acceso con tu equipo.</p></div>
          <a class="crm-button" href="<?php echo h(bitacora_client_url('perfil', $activeOpportunityId)); ?>">Cambiar password</a>
        </section>
      <?php endif; ?>

      <?php if ($activeView === 'resumen'): ?>
        <section class="crm-client-module crm-client-module--resumen">
          <section class="crm-client-hero crm-client-hero--panel">
            <div>
              <p class="eyebrow">Bitacora ID</p>
              <h1><?php echo h($project['company_name']); ?></h1>
              <p><?php echo h($project['service']); ?> - <?php echo h($project['opportunity_status'] ?? 'Proyecto entregado'); ?> - <?php echo count($projects); ?> proyecto(s)</p>
            </div>
            <span class="crm-client-user-pill"><?php echo h($portal['username']); ?></span>
          </section>

          <section class="crm-kpis crm-client-kpis" aria-label="Resumen del proyecto">
            <article class="crm-card crm-kpi"><span class="crm-kpi__icon"><?php echo bitacora_icon('check'); ?></span><div><span>Proyecto activo</span><strong><?php echo h($project['opportunity_status'] ?? 'Entregado'); ?></strong></div></article>
            <article class="crm-card crm-kpi"><span class="crm-kpi__icon"><?php echo bitacora_icon('projects'); ?></span><div><span>Proyectos</span><strong><?php echo count($projects); ?></strong></div></article>
            <article class="crm-card crm-kpi"><span class="crm-kpi__icon"><?php echo bitacora_icon('logs'); ?></span><div><span>Registros</span><strong><?php echo count($logs); ?></strong></div></article>
            <article class="crm-card crm-kpi"><span class="crm-kpi__icon"><?php echo bitacora_icon('requests'); ?></span><div><span>Solicitudes</span><strong><?php echo count($requests); ?></strong></div></article>
          </section>

          <section class="crm-card crm-client-summary-card">
            <div class="crm-section-head"><div><h2>Proyecto seleccionado</h2><p>Consulta la bitacora o levanta reportes por proyecto, de forma independiente.</p></div><span class="crm-pill <?php echo h(bitacora_pill_class((string) ($project['opportunity_status'] ?? 'Proyecto entregado'))); ?>"><?php echo h($project['opportunity_status'] ?? 'Proyecto entregado'); ?></span></div>
            <div class="crm-client-actions-row">
              <a class="crm-button" href="<?php echo h(bitacora_client_url('proyectos', $activeOpportunityId)); ?>">Ver proyectos</a>
              <a class="crm-button crm-button--ghost" href="<?php echo h(bitacora_client_url('bitacora', $activeOpportunityId)); ?>">Ver bitacora</a>
              <a class="crm-button crm-button--ghost" href="<?php echo h(bitacora_client_url('solicitudes', $activeOpportunityId)); ?>">Crear solicitud</a>
            </div>
          </section>
        </section>
      <?php elseif ($activeView === 'proyectos'): ?>
        <section class="crm-client-module crm-client-module--proyectos">
          <div class="crm-module-head"><p class="eyebrow">Proyectos</p><h1>Proyectos contratados</h1><p>Selecciona el proyecto que quieres consultar. Cada proyecto tiene su propia bitacora y sus propias solicitudes.</p></div>
          <section class="crm-card crm-client-projects">
            <div class="crm-section-head"><div><h2>Listado de proyectos</h2><p>El proyecto activo se usara para bitacora y mantenimiento.</p></div><code><?php echo h($activeProject['username']); ?></code></div>
            <div class="crm-project-list">
              <?php foreach ($projects as $projectOption): ?>
                <?php $isActiveProject = (int) $projectOption['opportunity_id'] === $activeOpportunityId; ?>
                <a class="crm-project-tile <?php echo $isActiveProject ? 'is-active' : ''; ?>" href="<?php echo h(bitacora_client_url('proyectos', (int) $projectOption['opportunity_id'])); ?>">
                  <span class="crm-pill <?php echo h(bitacora_pill_class((string) ($projectOption['opportunity_status'] ?? 'Proyecto entregado'))); ?>"><?php echo h($projectOption['opportunity_status'] ?? 'Proyecto entregado'); ?></span>
                  <strong><?php echo h($projectOption['service']); ?></strong>
                  <small><?php echo (int) $projectOption['log_count']; ?> registros - <?php echo (int) $projectOption['request_count']; ?> solicitudes</small>
                </a>
              <?php endforeach; ?>
            </div>
            <div class="crm-client-actions-row">
              <a class="crm-button" href="<?php echo h(bitacora_client_url('bitacora', $activeOpportunityId)); ?>">Abrir bitacora</a>
              <a class="crm-button crm-button--ghost" href="<?php echo h(bitacora_client_url('solicitudes', $activeOpportunityId)); ?>">Solicitar mantenimiento</a>
            </div>
          </section>
        </section>
      <?php elseif ($activeView === 'bitacora'): ?>
        <section class="crm-client-module crm-client-module--bitacora">
          <div class="crm-module-head"><p class="eyebrow">Bitacora</p><h1>Bitacora de mantenimiento</h1><p>Historial visible para <?php echo h($project['service']); ?>.</p></div>
          <article class="crm-card">
            <div class="crm-section-head"><div><h2><?php echo h($project['service']); ?></h2><p>Registros publicados por ID Industrial para este proyecto.</p></div><a class="crm-button crm-button--ghost" href="<?php echo h(bitacora_client_url('proyectos', $activeOpportunityId)); ?>">Cambiar proyecto</a></div>
            <div class="crm-list">
              <?php foreach ($logs as $log): ?>
                <div class="crm-list__item"><span class="crm-pill crm-pill--success"><?php echo h($log['status']); ?></span><strong><?php echo h($log['title']); ?></strong><p><?php echo h($log['notes']); ?></p><small><?php echo h($log['type']); ?> - <?php echo h($log['scheduled_date'] ?: $log['created_at']); ?></small></div>
              <?php endforeach; ?>
              <?php if (!$logs): ?><p>Aun no hay registros publicados para este proyecto.</p><?php endif; ?>
            </div>
          </article>
        </section>
      <?php elseif ($activeView === 'solicitudes'): ?>
        <section class="crm-client-module crm-client-module--solicitudes">
          <div class="crm-module-head"><p class="eyebrow">Solicitudes</p><h1>Reportes de mantenimiento</h1><p>Levanta y consulta reportes ligados solamente a <?php echo h($project['service']); ?>.</p></div>
          <section class="crm-client-workspace crm-client-workspace--requests">
            <article class="crm-card">
              <div class="crm-section-head"><div><h2>Nueva solicitud</h2><p>Describe el requerimiento para que el equipo lo pueda programar.</p></div><a class="crm-button crm-button--ghost" href="<?php echo h(bitacora_client_url('proyectos', $activeOpportunityId)); ?>">Cambiar proyecto</a></div>
              <form class="crm-form" method="post">
                <input type="hidden" name="token" value="<?php echo h($token); ?>">
                <input type="hidden" name="action" value="create_request">
                <input type="hidden" name="project_id" value="<?php echo (int) $activeOpportunityId; ?>">
                <label class="crm-field">Asunto<input name="title" required></label>
                <label class="crm-field">Prioridad<select name="priority"><?php foreach ($requestPriorities as $priority): ?><option><?php echo h($priority); ?></option><?php endforeach; ?></select></label>
                <label class="crm-field">Descripcion<textarea name="message" rows="5" required></textarea></label>
                <button class="crm-button" type="submit">Enviar solicitud</button>
              </form>
            </article>
            <article class="crm-card">
              <div class="crm-section-head"><div><h2>Seguimiento</h2><p>Estado y respuesta de ID Industrial.</p></div></div>
              <div class="crm-list crm-list--compact">
                <?php foreach ($requests as $request): ?>
                  <?php $requestStatus = trim((string) ($request['status'] ?? 'Recibida')) ?: 'Recibida'; ?>
                  <div class="crm-list__item crm-request-card">
                    <div class="crm-request-card__head"><span class="crm-pill <?php echo h(bitacora_pill_class($requestStatus)); ?>"><?php echo h($requestStatus); ?></span><small><?php echo h($request['updated_at'] ?: $request['created_at']); ?></small></div>
                    <strong><?php echo h($request['title']); ?></strong><p><?php echo h($request['message']); ?></p>
                    <div class="crm-request-meta crm-request-meta--client"><span><strong>Prioridad</strong><?php echo h($request['priority'] ?? 'Media'); ?></span><span><strong>Objetivo</strong><?php echo h($request['due_date'] ?? 'Por confirmar'); ?></span><span><strong>Programada</strong><?php echo h($request['scheduled_date'] ?: 'Por confirmar'); ?></span><span><strong>Responsable</strong><?php echo h($request['assigned_to'] ?: 'Por asignar'); ?></span></div>
                    <p class="crm-request-next"><strong>Seguimiento:</strong> <?php echo h(bitacora_request_next_step($requestStatus)); ?><?php if (!empty($request['resolved_at'])): ?> Resuelta: <?php echo h($request['resolved_at']); ?>.<?php endif; ?></p>
                    <?php if (!empty($request['admin_response'])): ?><div class="crm-response"><strong>Respuesta ID Industrial</strong><p><?php echo h($request['admin_response']); ?></p></div><?php endif; ?>
                  </div>
                <?php endforeach; ?>
                <?php if (!$requests): ?><p>Aun no has enviado solicitudes de mantenimiento para este proyecto.</p><?php endif; ?>
              </div>
            </article>
          </section>
        </section>
      <?php else: ?>
        <section class="crm-client-module crm-client-module--perfil">
          <div class="crm-module-head"><p class="eyebrow">Perfil</p><h1>Perfil de acceso</h1><p>Administra tu password y revisa los datos asociados al portal.</p></div>
          <section class="crm-card crm-client-profile">
            <div class="crm-section-head"><div><h2>Seguridad de cuenta</h2><p>Actualiza el password de acceso al portal cliente.</p></div><span class="crm-pill <?php echo $mustChangePassword ? 'crm-pill--warning' : 'crm-pill--success'; ?>"><?php echo $mustChangePassword ? 'Cambio requerido' : 'Protegido'; ?></span></div>
            <div class="crm-profile-grid">
              <div class="crm-profile-summary"><span><?php echo bitacora_icon('profile'); ?></span><div><strong><?php echo h($project['contact_name'] ?: $project['company_name']); ?></strong><p><?php echo h($project['company_name']); ?></p><code><?php echo h($portal['username']); ?></code></div></div>
              <form class="crm-form crm-password-form" method="post" autocomplete="off" data-password-change-form>
                <input type="hidden" name="token" value="<?php echo h($token); ?>"><input type="hidden" name="action" value="update_client_password">
                <label class="crm-field">Password actual<span class="crm-password-field"><input id="current-password" type="password" name="current_password" autocomplete="current-password" required><button class="crm-password-toggle" type="button" aria-label="Mostrar password actual" aria-controls="current-password" data-password-toggle><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5c5 0 8.5 4.2 9.7 6.1a1.7 1.7 0 0 1 0 1.8C20.5 14.8 17 19 12 19s-8.5-4.2-9.7-6.1a1.7 1.7 0 0 1 0-1.8C3.5 9.2 7 5 12 5Zm0 2C7.9 7 4.9 10.4 4 12c.9 1.6 3.9 5 8 5s7.1-3.4 8-5c-.9-1.6-3.9-5-8-5Zm0 2.2a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6Z"/></svg></button></span></label>
                <label class="crm-field">Nuevo password<span class="crm-password-field"><input id="new-password" type="password" name="new_password" autocomplete="new-password" minlength="10" required><button class="crm-password-toggle" type="button" aria-label="Mostrar nuevo password" aria-controls="new-password" data-password-toggle><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5c5 0 8.5 4.2 9.7 6.1a1.7 1.7 0 0 1 0 1.8C20.5 14.8 17 19 12 19s-8.5-4.2-9.7-6.1a1.7 1.7 0 0 1 0-1.8C3.5 9.2 7 5 12 5Zm0 2C7.9 7 4.9 10.4 4 12c.9 1.6 3.9 5 8 5s7.1-3.4 8-5c-.9-1.6-3.9-5-8-5Zm0 2.2a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6Z"/></svg></button></span><small>Minimo 10 caracteres.</small></label>
                <label class="crm-field">Confirmar password<span class="crm-password-field"><input id="confirm-password" type="password" name="confirm_password" autocomplete="new-password" minlength="10" required><button class="crm-password-toggle" type="button" aria-label="Mostrar confirmacion" aria-controls="confirm-password" data-password-toggle><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5c5 0 8.5 4.2 9.7 6.1a1.7 1.7 0 0 1 0 1.8C20.5 14.8 17 19 12 19s-8.5-4.2-9.7-6.1a1.7 1.7 0 0 1 0-1.8C3.5 9.2 7 5 12 5Zm0 2C7.9 7 4.9 10.4 4 12c.9 1.6 3.9 5 8 5s7.1-3.4 8-5c-.9-1.6-3.9-5-8-5Zm0 2.2a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6Z"/></svg></button></span></label>
                <button class="crm-button" type="submit">Actualizar password</button>
              </form>
            </div>
          </section>
        </section>
      <?php endif; ?>
    </main>
  </div>
  <script>
    (() => {
      const legacyViews = ['resumen', 'proyectos', 'bitacora', 'solicitudes', 'perfil'];
      const params = new URLSearchParams(window.location.search);
      const hashView = window.location.hash ? window.location.hash.slice(1) : '';
      if (hashView && legacyViews.includes(hashView) && !params.has('view')) {
        params.set('view', hashView);
        window.location.replace(window.location.pathname + '?' + params.toString());
        return;
      }
      const toggle = document.querySelector('[data-client-menu-toggle]');
      const close = document.querySelector('[data-client-menu-close]');
      const setMenu = (open) => {
        document.body.classList.toggle('crm-client-menu-open', open);
        if (toggle) toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      };
      if (toggle) toggle.addEventListener('click', () => setMenu(!document.body.classList.contains('crm-client-menu-open')));
      if (close) close.addEventListener('click', () => setMenu(false));
      document.querySelectorAll('.crm-client-nav a').forEach((link) => link.addEventListener('click', () => setMenu(false)));
      window.addEventListener('keydown', (event) => { if (event.key === 'Escape') setMenu(false); });
      document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        const input = document.getElementById(button.getAttribute('aria-controls'));
        if (!input) return;
        button.addEventListener('click', () => {
          const showing = input.type === 'text';
          input.type = showing ? 'password' : 'text';
          button.classList.toggle('is-active', !showing);
          button.setAttribute('aria-label', showing ? 'Mostrar password' : 'Ocultar password');
        });
      });
    })();
  </script>
</body>
</html>