<?php
declare(strict_types=1);

require __DIR__ . '/lib/database.php';
if (crm_uses_legacy_php_url('cliente.php')) {
  $legacyQuery = $_GET;
  if (isset($legacyQuery['logout'])) {
    $canonicalUrl = crm_portal_url('logout');
  } elseif (isset($legacyQuery['notification_poll'])) {
    $projectId = (int) ($legacyQuery['project_id'] ?? 0);
    unset($legacyQuery['logout'], $legacyQuery['notification_poll'], $legacyQuery['project_id'], $legacyQuery['view']);
    $canonicalUrl = crm_portal_url('notification_poll', $projectId, $legacyQuery);
  } else {
    $legacyView = (string) ($legacyQuery['view'] ?? 'resumen');
    $projectId = (int) ($legacyQuery['project_id'] ?? 0);
    unset($legacyQuery['view'], $legacyQuery['project_id'], $legacyQuery['logout'], $legacyQuery['notification_poll']);
    $canonicalUrl = crm_portal_url($legacyView, $projectId, $legacyQuery);
  }
  header('Location: ' . $canonicalUrl, true, 301);
  exit;
}

try {
  $pdo = crm_db();
  crm_start_database_session($pdo);
} catch (Throwable $error) {
  crm_log_event('login.bootstrap_failed', [
    'area' => 'client',
    'config_source' => crm_config_source(),
    'error_class' => get_class($error),
    'error_message' => substr($error->getMessage(), 0, 500),
  ]);
  throw $error;
}

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
    'bell' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>',
    'quote' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h9l3 3v15H6V3Z"/><path d="M14 3v4h4"/><path d="M9 11h6"/><path d="M9 15h6"/><path d="M9 19h3"/></svg>',
    'camera' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14.5 5 13 3h-2L9.5 5H5a3 3 0 0 0-3 3v9a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3V8a3 3 0 0 0-3-3h-4.5Z"/><circle cx="12" cy="12.5" r="4"/></svg>',
    'close' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>',
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

function bitacora_file_size(int $bytes): string
{
  if ($bytes >= 1024 * 1024) {
    return number_format($bytes / (1024 * 1024), 1) . ' MB';
  }
  return max(1, (int) ceil($bytes / 1024)) . ' KB';
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
  $allowedViews = ['resumen', 'proyectos', 'bitacora', 'solicitudes', 'cotizaciones', 'notificaciones', 'perfil'];
  $params = ['view' => in_array($view, $allowedViews, true) ? $view : 'resumen'];
  if ($projectId > 0) {
    $params['project_id'] = $projectId;
  }
  return crm_portal_url((string) $params['view'], $projectId);
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
  header('Location: ' . crm_portal_url());
  exit;
}

crm_enforce_session_timeout('bitacora_user', 'bitacora_token', crm_portal_url('resumen', 0, ['expired' => 1]));

$humanChallengeKey = 'bitacora_login_human_challenge';
$loginError = isset($_GET['expired']) ? 'Sesion cerrada por inactividad. Vuelve a iniciar sesion.' : '';
if (($_POST['action'] ?? '') === 'client_login') {
  $username = trim((string) ($_POST['bitacora_user'] ?? ''));
  $password = (string) ($_POST['bitacora_password'] ?? '');
  $humanAnswer = (string) ($_POST['human_answer'] ?? '');
  $loginIdentifier = $username !== '' ? $username : 'anonimo';
  $lockStatus = crm_login_lock_status($pdo, 'client', $loginIdentifier);
  $loginDiagnostic = crm_login_diagnostic_context($pdo, $loginIdentifier);
  crm_log_event('login.started', array_merge($loginDiagnostic, ['area' => 'client']));

  if (!empty($lockStatus['locked'])) {
    crm_log_event('login.rejected', array_merge($loginDiagnostic, ['area' => 'client', 'reason' => 'locked', 'attempts' => (int) ($lockStatus['attempts'] ?? 0), 'seconds' => (int) ($lockStatus['seconds'] ?? 0)]));
    $loginError = crm_login_lock_message($lockStatus);
  } elseif (!crm_validate_math_challenge($humanChallengeKey, $humanAnswer)) {
    $status = crm_record_login_failure($pdo, 'client', $loginIdentifier);
    crm_refresh_math_challenge($humanChallengeKey);
    $loginError = crm_login_attempt_message('Confirma que eres humano resolviendo la suma.', $status);
    crm_log_event('login.rejected', array_merge($loginDiagnostic, ['area' => 'client', 'reason' => 'captcha', 'attempts' => (int) ($status['attempts'] ?? 0)]));
  } elseif ($username === '' || strlen($password) < 8) {
    $status = crm_record_login_failure($pdo, 'client', $loginIdentifier);
    crm_refresh_math_challenge($humanChallengeKey);
    $loginError = crm_login_attempt_message('Ingresa usuario y password validos.', $status);
    crm_log_event('login.rejected', array_merge($loginDiagnostic, ['area' => 'client', 'reason' => 'invalid_credentials_format', 'attempts' => (int) ($status['attempts'] ?? 0)]));
  } else {
    $portalCandidates = crm_portal_users_by_identifier($pdo, $username);
    $portalUser = null;
    foreach ($portalCandidates as $candidate) {
      if (password_verify($password, (string) $candidate['password_hash'])) {
        $portalUser = $candidate;
        break;
      }
    }
    $passwordVerified = $portalUser !== null;
    crm_log_event('login.credentials_checked', array_merge($loginDiagnostic, [
      'area' => 'client',
      'user_found' => count($portalCandidates) > 0,
      'candidate_count' => count($portalCandidates),
      'password_verified' => $passwordVerified,
      'matched_by_contact_email' => $portalUser ? strtolower((string) $portalUser['username']) !== strtolower($username) : false,
    ]));
    if ($portalUser && $passwordVerified) {
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
      crm_log_event('login.success', array_merge($loginDiagnostic, ['area' => 'client', 'portal_user_id' => (int) $portalUser['id'], 'opportunity_id' => (int) $portalUser['opportunity_id'], 'redirect' => crm_portal_url(), 'session_active' => session_status() === PHP_SESSION_ACTIVE]));
      header('Location: ' . crm_portal_url());
      exit;
    }
    $status = crm_record_login_failure($pdo, 'client', $loginIdentifier);
    crm_refresh_math_challenge($humanChallengeKey);
    $loginError = crm_login_failure_message($status);
    crm_log_event('login.rejected', array_merge($loginDiagnostic, [
      'area' => 'client',
      'reason' => count($portalCandidates) > 0 ? 'password_mismatch' : 'user_not_found',
      'candidate_count' => count($portalCandidates),
      'attempts' => (int) ($status['attempts'] ?? 0),
    ]));
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
  <link rel="stylesheet" href="<?php echo h(crm_public_url('assets/css/crm.css')); ?>">
</head>
<body class="crm-login">
  <main class="crm-login__panel crm-login__panel--client">
    <section class="crm-login__media" aria-label="Bitacora ID mantenimiento industrial">
      <img src="<?php echo h(crm_public_url('assets/img/optimized/home-hero-control-acceso.jpg')); ?>" alt="Mantenimiento industrial ID Industrial" width="1920" height="500">
      <div class="crm-login__media-copy">
        <span>Bitacora ID</span>
        <strong>Mantenimiento, evidencia y seguimiento despues de la entrega.</strong>
      </div>
    </section>

    <section class="crm-login__card" aria-labelledby="client-login-title">
      <div class="crm-login__brand">
        <img src="<?php echo h(crm_public_url('assets/img/logo-idindustrial-small.webp')); ?>" alt="ID Industrial" width="280" height="74">
        <div>
          <strong>Bitacora ID</strong>
          <span>Portal cliente</span>
        </div>
      </div>
      <h1 id="client-login-title">Acceso cliente</h1>
      <p>Consulta tu proyecto entregado, mantenimientos y solicitudes de servicio.</p>
      <?php if ($loginError): ?><p class="crm-alert" role="alert"><?php echo h($loginError); ?></p><?php endif; ?>
      <form method="post" autocomplete="on" data-login-form novalidate>
        <input type="hidden" name="action" value="client_login">
        <label class="crm-field">
          Usuario
          <input name="bitacora_user" autocomplete="username" autocapitalize="none" spellcheck="false" placeholder="Usuario o correo" required data-login-email>
          <span class="crm-field__error" role="alert">Ingresa tu usuario de Bitacora ID.</span>
        </label>
        <label class="crm-field">
          Password
          <span class="crm-password-field">
            <input id="bitacora-password" type="password" name="bitacora_password" autocomplete="current-password" minlength="8" placeholder="Tu contrasena" required data-login-password>
            <button class="crm-password-toggle" type="button" aria-label="Mostrar password" aria-controls="bitacora-password" data-password-toggle>
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5c5 0 8.5 4.2 9.7 6.1a1.7 1.7 0 0 1 0 1.8C20.5 14.8 17 19 12 19s-8.5-4.2-9.7-6.1a1.7 1.7 0 0 1 0-1.8C3.5 9.2 7 5 12 5Zm0 2C7.9 7 4.9 10.4 4 12c.9 1.6 3.9 5 8 5s7.1-3.4 8-5c-.9-1.6-3.9-5-8-5Zm0 2.2a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6Z"/></svg>
            </button>
          </span>
          <span class="crm-field__error" role="alert">La contrasena debe tener al menos 8 caracteres.</span>
        </label>
        <label class="crm-field crm-human-check">
          Verificacion humana
          <span class="crm-human-check__row">
            <span class="crm-human-check__question">
              <small>Resuelve</small>
              <strong><?php echo h((string) $humanChallenge['a']); ?> + <?php echo h((string) $humanChallenge['b']); ?></strong>
            </span>
            <span class="crm-human-check__equals" aria-hidden="true">=</span>
            <input type="text" name="human_answer" inputmode="numeric" pattern="[0-9]{1,2}" maxlength="2" autocomplete="off" enterkeyhint="done" required data-human-answer placeholder="Respuesta" aria-label="Respuesta de la suma">
          </span>
          <span class="crm-field__error" role="alert">Resuelve la suma para continuar.</span>
        </label>
        <button class="crm-button" type="submit" data-login-submit>Entrar a Bitacora ID</button>
      </form>
    </section>
  </main>
  <script>
    (() => {
      const form = document.querySelector('[data-login-form]');
      const password = document.querySelector('[data-login-password]');
      const toggle = document.querySelector('[data-password-toggle]');
      const human = document.querySelector('[data-human-answer]');
      const submit = document.querySelector('[data-login-submit]');
      if (!form || !password || !toggle || !human || !submit) return;
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
          return;
        }
        submit.disabled = true;
        submit.setAttribute('aria-busy', 'true');
        submit.textContent = 'Validando acceso...';
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
  header('Location: ' . crm_portal_url());
  exit;
}
$mustChangePassword = (int) ($portalAccount['password_change_required'] ?? 1) === 1 || empty($portalAccount['password_changed_at']);
$portal['must_change_password'] = $mustChangePassword;
$_SESSION['bitacora_user']['must_change_password'] = $mustChangePassword;
$requestPriorities = ['Baja', 'Media', 'Alta', 'Urgente'];
$requestCategories = ['Mantenimiento correctivo', 'Mantenimiento preventivo', 'Falla de equipo', 'Rendimiento', 'Inspeccion', 'Seguridad', 'Otro'];
$requestImpacts = ['Sin paro', 'Operacion parcial', 'Paro total', 'Riesgo de seguridad'];
$quoteServices = ['Cableado estructurado', 'Deteccion de incendios', 'Sistemas HVAC', 'CCTV industrial', 'Fibra optica', 'Control de accesos', 'Mantenimiento industrial', 'Smart Factories / IoT', 'Otro'];
$quoteUrgencies = ['Normal', 'Prioritaria', 'Urgente'];
$quoteBudgets = ['Por definir', 'Menos de $50,000', '$50,000 a $150,000', '$150,000 a $500,000', 'Mas de $500,000'];
$projectAccesses = bitacora_project_accesses($pdo, $portal);
$selectedProjectId = max(0, (int) ($_POST['project_id'] ?? $_GET['project_id'] ?? $portal['opportunity_id'] ?? 0));
$currentAccess = bitacora_select_project($projectAccesses, $selectedProjectId);
if (!$currentAccess) {
  unset($_SESSION['bitacora_user'], $_SESSION['bitacora_token']);
  header('Location: ' . crm_portal_url());
  exit;
}
$clientViews = [
  'resumen' => ['label' => 'Resumen', 'icon' => 'dashboard'],
  'proyectos' => ['label' => 'Proyectos', 'icon' => 'projects'],
  'bitacora' => ['label' => 'Bitacora', 'icon' => 'logs'],
  'solicitudes' => ['label' => 'Solicitudes', 'icon' => 'requests'],
  'cotizaciones' => ['label' => 'Cotizaciones', 'icon' => 'quote'],
  'notificaciones' => ['label' => 'Notificaciones', 'icon' => 'bell'],
  'perfil' => ['label' => 'Perfil', 'icon' => 'profile'],
];
$activeView = (string) ($_GET['view'] ?? 'resumen');
if (!isset($clientViews[$activeView])) {
  $activeView = 'resumen';
}
$action = (string) ($_POST['action'] ?? '');
if ($action === 'update_client_password') {
  $activeView = 'perfil';
} elseif ($action === 'create_request') {
  $activeView = 'solicitudes';
} elseif (in_array($action, ['create_quote_request', 'respond_quote'], true)) {
  $activeView = 'cotizaciones';
} elseif (in_array($action, ['mark_client_notification_read', 'mark_all_client_notifications_read'], true)) {
  $activeView = 'notificaciones';
}
$notice = null;

if ($action === 'mark_client_notification_read') {
  bitacora_check_token();
  crm_mark_notification_read($pdo, (int) ($_POST['notification_id'] ?? 0), 'client', (int) $currentAccess['id']);
  header('Location: ' . bitacora_client_url('notificaciones', (int) $currentAccess['opportunity_id']));
  exit;
}

if ($action === 'mark_all_client_notifications_read') {
  bitacora_check_token();
  crm_mark_all_notifications_read($pdo, 'client', (int) $currentAccess['id']);
  header('Location: ' . bitacora_client_url('notificaciones', (int) $currentAccess['opportunity_id']));
  exit;
}

if ($action === 'update_client_password') {
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

if ($action === 'create_quote_request') {
  bitacora_check_token();
  $service = trim((string) ($_POST['quote_service'] ?? ''));
  $otherService = trim((string) ($_POST['quote_service_other'] ?? ''));
  $location = trim((string) ($_POST['quote_location'] ?? ''));
  $scope = trim((string) ($_POST['quote_scope'] ?? ''));
  $urgency = trim((string) ($_POST['quote_urgency'] ?? 'Normal'));
  $budget = trim((string) ($_POST['quote_budget'] ?? 'Por definir'));
  $requestedDate = trim((string) ($_POST['quote_requested_date'] ?? ''));
  $relatedProjectId = max(0, (int) ($_POST['related_project_id'] ?? 0));
  $relatedAccess = $relatedProjectId > 0 ? bitacora_select_project($projectAccesses, $relatedProjectId) : null;

  if ($service === 'Otro') {
    $service = $otherService;
  }
  if (!in_array($urgency, $quoteUrgencies, true)) {
    $urgency = 'Normal';
  }
  if (!in_array($budget, $quoteBudgets, true)) {
    $budget = 'Por definir';
  }

  if ($relatedProjectId > 0 && (!$relatedAccess || (int) $relatedAccess['opportunity_id'] !== $relatedProjectId)) {
    $notice = ['type' => 'error', 'text' => 'El proyecto relacionado no pertenece a tu cuenta.'];
  } elseif ($service === '' || $location === '' || $scope === '') {
    $notice = ['type' => 'error', 'text' => 'Completa servicio, ubicacion y alcance de la cotizacion.'];
  } elseif (strlen($service) > 160 || strlen($location) > 190) {
    $notice = ['type' => 'error', 'text' => 'Servicio y ubicacion deben ser mas breves.'];
  } elseif (strlen($scope) < 30 || strlen($scope) > 5000) {
    $notice = ['type' => 'error', 'text' => 'El alcance debe tener entre 30 y 5000 caracteres.'];
  } elseif ($requestedDate !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedDate) || strtotime($requestedDate) === false || date('Y-m-d', (int) strtotime($requestedDate)) !== $requestedDate || $requestedDate < date('Y-m-d'))) {
    $notice = ['type' => 'error', 'text' => 'La fecha requerida debe ser igual o posterior a hoy.'];
  } else {
    try {
      $attachment = crm_store_quote_attachment($_FILES['quote_attachment'] ?? null);
    } catch (RuntimeException $error) {
      $attachment = ['path' => null, 'name' => null, 'mime' => null, 'size' => null];
      $notice = ['type' => 'error', 'text' => $error->getMessage()];
    }

    if (!$notice) {
      $priorityMap = ['Normal' => 'Media', 'Prioritaria' => 'Alta', 'Urgente' => 'Urgente'];
      $priority = $priorityMap[$urgency] ?? 'Media';
      try {
        $pdo->beginTransaction();
        $opportunityStmt = $pdo->prepare('
          INSERT INTO opportunities (
            client_id, company_name, contact_name, contact_email, contact_phone,
            service, source, status, priority, estimated_value, next_action_date, notes
          ) VALUES (?, ?, ?, ?, ?, ?, "Portal cliente", "Nueva solicitud", ?, 0, ?, ?)
        ');
        $opportunityStmt->execute([
          (int) $portal['client_id'],
          (string) $currentAccess['company_name'],
          (string) $currentAccess['contact_name'],
          (string) $currentAccess['contact_email'],
          (string) $currentAccess['contact_phone'],
          $service,
          $priority,
          date('Y-m-d', strtotime('+1 day')),
          $scope,
        ]);
        $newOpportunityId = (int) $pdo->lastInsertId();

        $quoteCode = crm_next_quote_code($pdo, 'SOL');
        $quoteStmt = $pdo->prepare('
          INSERT INTO quotes (
            opportunity_id, related_opportunity_id, requested_by_portal_user_id,
            quote_code, amount, status, probability, request_scope, request_location,
            requested_date, budget_range, client_status, visible_to_client,
            attachment_path, attachment_original_name, attachment_mime, attachment_size
          ) VALUES (?, ?, ?, ?, 0, "Solicitud recibida", 15, ?, ?, ?, ?, "Pendiente", 0, ?, ?, ?, ?)
        ');
        $quoteStmt->execute([
          $newOpportunityId,
          $relatedAccess ? (int) $relatedAccess['opportunity_id'] : null,
          (int) $currentAccess['id'],
          $quoteCode,
          $scope,
          $location,
          $requestedDate ?: null,
          $budget,
          $attachment['path'],
          $attachment['name'],
          $attachment['mime'],
          $attachment['size'],
        ]);
        $quoteId = (int) $pdo->lastInsertId();

        $activity = $pdo->prepare('INSERT INTO activities (opportunity_id, type, summary, due_date) VALUES (?, "Cotizacion", ?, ?)');
        $activity->execute([$newOpportunityId, 'Solicitud de cotizacion recibida desde el portal cliente: ' . $quoteCode, date('Y-m-d', strtotime('+1 day'))]);

        crm_create_notification($pdo, [
          'recipient_type' => 'admin',
          'portal_user_id' => (int) $currentAccess['id'],
          'opportunity_id' => $newOpportunityId,
          'event_type' => 'client_quote_requested',
          'title' => 'Nueva solicitud de cotizacion',
          'message' => $currentAccess['company_name'] . ' solicito cotizacion para ' . $service . ' con prioridad ' . $priority . '.',
          'target_url' => crm_admin_url('quote', $quoteId),
        ]);
        crm_create_notification($pdo, [
          'recipient_type' => 'client',
          'portal_user_id' => (int) $currentAccess['id'],
          'opportunity_id' => $newOpportunityId,
          'event_type' => 'client_quote_requested',
          'title' => 'Solicitud de cotizacion recibida',
          'message' => 'Registramos la solicitud ' . $quoteCode . '. El equipo comercial preparara la propuesta.',
          'target_url' => crm_portal_url('cotizaciones', (int) $currentAccess['opportunity_id'], [], 'quote-' . $quoteId),
        ]);

        $pdo->commit();
        $_SESSION['bitacora_quote_notice'] = ['type' => 'success', 'text' => 'Solicitud ' . $quoteCode . ' registrada. Te notificaremos cuando la propuesta este disponible.'];
        header('Location: ' . crm_portal_url('cotizaciones', (int) $currentAccess['opportunity_id'], [], 'quote-' . $quoteId));
        exit;
      } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
          $pdo->rollBack();
        }
        crm_delete_quote_attachment($attachment['path']);
        crm_log_event('client_quote.create_failed', [
          'portal_user_id' => (int) $currentAccess['id'],
          'client_id' => (int) $portal['client_id'],
          'error_class' => get_class($error),
          'error_message' => substr($error->getMessage(), 0, 500),
        ]);
        $notice = ['type' => 'error', 'text' => 'No se pudo registrar la cotizacion. Intenta nuevamente.'];
      }
    }
  }
}

if ($action === 'respond_quote') {
  bitacora_check_token();
  $quoteId = max(0, (int) ($_POST['quote_id'] ?? 0));
  $clientStatus = trim((string) ($_POST['client_status'] ?? ''));
  $clientComments = trim((string) ($_POST['client_comments'] ?? ''));
  $allowedResponses = ['Aceptada', 'Cambios solicitados', 'Rechazada'];

  $quoteStmt = $pdo->prepare('
    SELECT q.id, q.quote_code, q.opportunity_id, q.visible_to_client, q.client_status, o.client_id, o.service
    FROM quotes q
    JOIN opportunities o ON o.id = q.opportunity_id
    WHERE q.id = ? AND o.client_id = ? AND q.visible_to_client = 1
    LIMIT 1
  ');
  $quoteStmt->execute([$quoteId, (int) $portal['client_id']]);
  $clientQuote = $quoteStmt->fetch();

  if (!$clientQuote || !in_array($clientStatus, $allowedResponses, true)) {
    $notice = ['type' => 'error', 'text' => 'La cotizacion no esta disponible o la respuesta no es valida.'];
  } elseif (in_array((string) ($clientQuote['client_status'] ?? ''), ['Aceptada', 'Rechazada'], true)) {
    $notice = ['type' => 'error', 'text' => 'Esta cotizacion ya tiene una respuesta final registrada.'];
  } elseif (in_array($clientStatus, ['Cambios solicitados', 'Rechazada'], true) && strlen($clientComments) < 10) {
    $notice = ['type' => 'error', 'text' => 'Agrega un comentario de al menos 10 caracteres para enviar esta respuesta.'];
  } elseif (strlen($clientComments) > 2000) {
    $notice = ['type' => 'error', 'text' => 'El comentario no debe exceder 2000 caracteres.'];
  } else {
    try {
      $pdo->beginTransaction();
      $commercialStatus = $clientStatus === 'Aceptada'
        ? 'Aprobada'
        : ($clientStatus === 'Rechazada' ? 'Perdida' : 'En revision cliente');
      $update = $pdo->prepare('UPDATE quotes SET client_status = ?, client_comments = ?, responded_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP, status = ? WHERE id = ?');
      $update->execute([$clientStatus, $clientComments ?: null, $commercialStatus, $quoteId]);

      $opportunityStatus = $clientStatus === 'Aceptada'
        ? 'Proyecto ganado'
        : ($clientStatus === 'Rechazada' ? 'Proyecto perdido' : 'Seguimiento');
      $pdo->prepare('UPDATE opportunities SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$opportunityStatus, (int) $clientQuote['opportunity_id']]);

      $activity = $pdo->prepare('INSERT INTO activities (opportunity_id, type, summary, due_date) VALUES (?, "Respuesta cliente", ?, NULL)');
      $activity->execute([(int) $clientQuote['opportunity_id'], 'Cotizacion ' . $clientQuote['quote_code'] . ': ' . $clientStatus . ($clientComments !== '' ? '. ' . $clientComments : '')]);

      crm_create_notification($pdo, [
        'recipient_type' => 'admin',
        'portal_user_id' => (int) $currentAccess['id'],
        'opportunity_id' => (int) $clientQuote['opportunity_id'],
        'event_type' => 'client_quote_response',
        'title' => 'Respuesta de cliente a cotizacion',
        'message' => $currentAccess['company_name'] . ' marco ' . $clientQuote['quote_code'] . ' como ' . $clientStatus . '.',
        'target_url' => crm_admin_url('quote', $quoteId),
      ]);

      $pdo->commit();
      $_SESSION['bitacora_quote_notice'] = ['type' => 'success', 'text' => 'Respuesta enviada para la cotizacion ' . $clientQuote['quote_code'] . '.'];
      header('Location: ' . crm_portal_url('cotizaciones', (int) $currentAccess['opportunity_id'], [], 'quote-' . $quoteId));
      exit;
    } catch (Throwable $error) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      crm_log_event('client_quote.response_failed', [
        'quote_id' => $quoteId,
        'client_id' => (int) $portal['client_id'],
        'error_class' => get_class($error),
        'error_message' => substr($error->getMessage(), 0, 500),
      ]);
      $notice = ['type' => 'error', 'text' => 'No se pudo guardar tu respuesta. Intenta nuevamente.'];
    }
  }
}
if ($action === 'create_request') {
  bitacora_check_token();
  $postedProjectId = max(0, (int) ($_POST['project_id'] ?? 0));
  $postedAccess = bitacora_select_project($projectAccesses, $postedProjectId);
  if ($postedAccess) {
    $currentAccess = $postedAccess;
  }

  $title = trim((string) ($_POST['title'] ?? ''));
  $category = trim((string) ($_POST['category'] ?? 'Mantenimiento correctivo'));
  $location = trim((string) ($_POST['location'] ?? ''));
  $equipment = trim((string) ($_POST['equipment'] ?? ''));
  $impact = trim((string) ($_POST['impact'] ?? 'Sin paro'));
  $occurredAtInput = trim((string) ($_POST['occurred_at'] ?? ''));
  $occurredTimestamp = $occurredAtInput !== '' ? strtotime($occurredAtInput) : false;
  $occurredAt = $occurredTimestamp ? date('Y-m-d H:i:s', $occurredTimestamp) : null;
  $actionsTaken = trim((string) ($_POST['actions_taken'] ?? ''));
  $message = trim((string) ($_POST['message'] ?? ''));
  $priority = trim((string) ($_POST['priority'] ?? 'Media'));

  if (!in_array($priority, $requestPriorities, true)) {
    $priority = 'Media';
  }
  if (!in_array($category, $requestCategories, true)) {
    $category = 'Otro';
  }
  if (!in_array($impact, $requestImpacts, true)) {
    $impact = 'Sin paro';
  }

  if ($title === '' || $location === '' || $message === '') {
    $notice = ['type' => 'error', 'text' => 'Completa asunto, ubicacion y descripcion tecnica del reporte.'];
  } elseif (strlen($title) > 160 || strlen($location) > 190 || strlen($equipment) > 190) {
    $notice = ['type' => 'error', 'text' => 'Revisa los campos: asunto, ubicacion y equipo deben ser mas breves.'];
  } elseif (strlen($message) < 20 || strlen($message) > 4000) {
    $notice = ['type' => 'error', 'text' => 'La descripcion tecnica debe tener entre 20 y 4000 caracteres.'];
  } elseif (strlen($actionsTaken) > 2000) {
    $notice = ['type' => 'error', 'text' => 'Las acciones realizadas no deben exceder 2000 caracteres.'];
  } elseif ($occurredAtInput !== '' && (!$occurredTimestamp || $occurredTimestamp > time() + 300)) {
    $notice = ['type' => 'error', 'text' => 'La fecha y hora del evento no es valida o esta en el futuro.'];
  } else {
    try {
      $evidence = crm_store_request_evidence($_FILES['evidence'] ?? null);
    } catch (RuntimeException $error) {
      $evidence = ['path' => null, 'name' => null, 'mime' => null, 'size' => null];
      $notice = ['type' => 'error', 'text' => $error->getMessage()];
    }

    if (!$notice) {
      try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('
          INSERT INTO client_requests (
            opportunity_id, portal_user_id, title, message, category, location, equipment,
            impact, occurred_at, actions_taken, evidence_path, evidence_original_name,
            evidence_mime, evidence_size, status, priority, due_date
          ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "Recibida", ?, ?)
        ');
        $stmt->execute([
          (int) $currentAccess['opportunity_id'],
          (int) $currentAccess['id'],
          $title,
          $message,
          $category,
          $location,
          $equipment ?: null,
          $impact,
          $occurredAt,
          $actionsTaken ?: null,
          $evidence['path'],
          $evidence['name'],
          $evidence['mime'],
          $evidence['size'],
          $priority,
          bitacora_request_due_date($priority),
        ]);
        $requestId = (int) $pdo->lastInsertId();

        crm_create_notification($pdo, [
          'recipient_type' => 'admin',
          'opportunity_id' => (int) $currentAccess['opportunity_id'],
          'portal_user_id' => (int) $currentAccess['id'],
          'client_request_id' => $requestId,
          'event_type' => 'report_received',
          'title' => 'Nuevo reporte recibido',
          'message' => ($currentAccess['company_name'] ?? 'Cliente') . ' reporto ' . $category . ' en ' . $location . ' con prioridad ' . $priority . ': ' . $title,
          'target_url' => crm_admin_url('notifications', 0, [], 'request-' . $requestId),
        ]);
        crm_create_notification($pdo, [
          'recipient_type' => 'client',
          'portal_user_id' => (int) $currentAccess['id'],
          'opportunity_id' => (int) $currentAccess['opportunity_id'],
          'client_request_id' => $requestId,
          'event_type' => 'report_received',
          'title' => 'Reporte recibido',
          'message' => 'ID Industrial recibio tu reporte "' . $title . '" y dara seguimiento por proyecto.',
          'target_url' => crm_portal_url('solicitudes', (int) $currentAccess['opportunity_id'], [], 'request-' . $requestId),
        ]);
        $pdo->commit();
        $notice = ['type' => 'success', 'text' => 'Reporte registrado correctamente. El equipo de ID Industrial ya puede consultar el detalle y la evidencia.'];
        $_POST = [];
      } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
          $pdo->rollBack();
        }
        crm_delete_request_evidence($evidence['path']);
        error_log('Client report creation failed: ' . $error->getMessage());
        $notice = ['type' => 'error', 'text' => 'No se pudo registrar el reporte. Intenta nuevamente.'];
      }
    }
  }
}
$projectAccesses = bitacora_project_accesses($pdo, $portal);
$currentAccess = bitacora_select_project($projectAccesses, (int) $currentAccess['opportunity_id']) ?: $currentAccess;
if (!$notice && !empty($_SESSION['bitacora_quote_notice'])) {
  $notice = $_SESSION['bitacora_quote_notice'];
  unset($_SESSION['bitacora_quote_notice']);
}
$token = bitacora_token();
$projects = $projectAccesses;
$project = $currentAccess;
$activeProject = $currentAccess;
$activeOpportunityId = (int) $currentAccess['opportunity_id'];
$activePortalUserId = (int) $currentAccess['id'];

if ($activeView === 'notificaciones') {
  crm_mark_all_notifications_read($pdo, 'client', $activePortalUserId);
}

if (isset($_GET['notification_poll'])) {
  $latestNotification = crm_recent_notifications($pdo, 'client', $activePortalUserId, 1)[0] ?? null;
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store');
  echo json_encode([
    'unread' => crm_unread_notification_count($pdo, 'client', $activePortalUserId),
    'latest_id' => (int) ($latestNotification['id'] ?? 0),
    'latest_title' => (string) ($latestNotification['title'] ?? ''),
    'latest_url' => crm_clean_internal_url($latestNotification['target_url'] ?? bitacora_client_url('notificaciones', $activeOpportunityId), 'client'),
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

$logsStmt = $pdo->prepare('SELECT * FROM maintenance_logs WHERE opportunity_id = ? AND visible_to_client = 1 ORDER BY COALESCE(scheduled_date, created_at) DESC, id DESC');
$logsStmt->execute([$activeOpportunityId]);
$logs = $logsStmt->fetchAll();
$requestsStmt = $pdo->prepare('SELECT * FROM client_requests WHERE opportunity_id = ? AND portal_user_id = ? ORDER BY created_at DESC');
$requestsStmt->execute([$activeOpportunityId, $activePortalUserId]);
$requests = $requestsStmt->fetchAll();
$clientQuotesStmt = $pdo->prepare('
  SELECT q.*, o.company_name, o.service, o.source, o.status AS opportunity_status,
    ro.service AS related_service
  FROM quotes q
  JOIN opportunities o ON o.id = q.opportunity_id
  LEFT JOIN opportunities ro ON ro.id = q.related_opportunity_id
  WHERE o.client_id = ?
    AND (q.requested_by_portal_user_id IS NOT NULL OR q.visible_to_client = 1)
  ORDER BY q.created_at DESC, q.id DESC
');
$clientQuotesStmt->execute([(int) $portal['client_id']]);
$clientQuotes = $clientQuotesStmt->fetchAll();
$clientUnreadNotifications = crm_unread_notification_count($pdo, 'client', $activePortalUserId);
$clientNotifications = crm_recent_notifications($pdo, 'client', $activePortalUserId, 20);
$openRequests = array_values(array_filter($requests, static function (array $request): bool {
  return !in_array(trim((string) ($request['status'] ?? 'Recibida')), ['Resuelta', 'Cerrada'], true);
}));
$urgentOpenRequests = array_values(array_filter($openRequests, static function (array $request): bool {
  return trim((string) ($request['priority'] ?? '')) === 'Urgente';
}));
$upcomingLogs = array_values(array_filter($logs, static function (array $log): bool {
  return !empty($log['scheduled_date']) && substr((string) $log['scheduled_date'], 0, 10) >= date('Y-m-d');
}));
usort($upcomingLogs, static function (array $left, array $right): int {
  return strcmp((string) $left['scheduled_date'], (string) $right['scheduled_date']);
});
$nextMaintenanceLog = $upcomingLogs[0] ?? null;
$nextScheduledRequest = null;
foreach ($openRequests as $request) {
  $scheduledDate = trim((string) ($request['scheduled_date'] ?? ''));
  if ($scheduledDate === '' || substr($scheduledDate, 0, 10) < date('Y-m-d')) {
    continue;
  }
  if ($nextScheduledRequest === null || strcmp($scheduledDate, (string) $nextScheduledRequest['scheduled_date']) < 0) {
    $nextScheduledRequest = $request;
  }
}
$nextServiceDate = (string) ($nextScheduledRequest['scheduled_date'] ?? $nextMaintenanceLog['scheduled_date'] ?? '');
$latestLog = $logs[0] ?? null;
$latestRequest = $requests[0] ?? null;
$latestClientQuote = $clientQuotes[0] ?? null;
?>
<!doctype html>
<html lang="es-MX">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Bitacora ID | <?php echo h($project['company_name']); ?></title>
  <link rel="stylesheet" href="<?php echo h(crm_public_url('assets/css/crm.css')); ?>">
  <script src="<?php echo h(crm_public_url('assets/js/crm-workspace.js')); ?>" defer></script>
</head>
<body class="crm-app crm-client-app crm-client-portal" data-notification-poll="<?php echo h(crm_portal_url('notification_poll', $activeOpportunityId)); ?>">
  <a class="crm-skip-link" href="#client-main">Saltar al contenido</a>
  <div class="crm-client-layout">
    <aside class="crm-client-sidebar" id="cliente-sidebar">
      <div class="crm-client-brand">
        <img src="<?php echo h(crm_public_url('assets/img/logo-idindustrial-small.webp')); ?>" alt="ID Industrial" width="280" height="74">
        <div><strong>Bitacora ID</strong><span>Portal cliente</span></div>
      </div>
      <nav class="crm-client-nav" aria-label="Navegación del portal cliente">
        <?php foreach (['Panel' => ['resumen', 'proyectos'], 'Mantenimiento' => ['bitacora', 'solicitudes', 'cotizaciones'], 'Cuenta' => ['notificaciones', 'perfil']] as $groupLabel => $groupViews): ?>
          <p class="crm-client-nav__label"><?php echo h($groupLabel); ?></p>
          <?php foreach ($groupViews as $viewKey): ?>
            <?php $viewItem = $clientViews[$viewKey]; ?>
            <a href="<?php echo h(bitacora_client_url($viewKey, $activeOpportunityId)); ?>" class="<?php echo $activeView === $viewKey ? 'is-active' : ''; ?>" <?php echo $activeView === $viewKey ? 'aria-current="page"' : ''; ?>>
              <span><?php echo bitacora_icon($viewItem['icon']); ?></span><?php echo h($viewItem['label']); ?><?php if ($viewKey === 'notificaciones'): ?><em data-notification-count <?php echo $clientUnreadNotifications > 0 ? '' : 'hidden'; ?>><?php echo $clientUnreadNotifications; ?></em><?php endif; ?>
            </a>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </nav>
      <div class="crm-client-sidebar__footer">
        <span>Usuario</span>
        <strong><?php echo h($portal['username']); ?></strong>
        <a href="<?php echo h(crm_portal_url('logout')); ?>"><span><?php echo bitacora_icon('logout'); ?></span>Cerrar sesion</a>
      </div>
    </aside>
    <button class="crm-client-overlay" type="button" aria-label="Cerrar menu" data-client-menu-close></button>

    <main class="crm-client-main" id="client-main">
      <header class="crm-client-topbar">
        <button class="crm-client-menu" type="button" aria-label="Abrir menú" aria-controls="cliente-sidebar" aria-expanded="false" data-client-menu-toggle><?php echo bitacora_icon('menu'); ?></button>
        <div class="crm-client-topbar__context"><small>Portal cliente / <?php echo h($clientViews[$activeView]['label']); ?></small><strong><?php echo h($project['service']); ?></strong></div>
        <div class="crm-topbar__actions crm-client-topbar__actions">
          <a class="crm-client-notification-link" href="<?php echo h(bitacora_client_url('notificaciones', $activeOpportunityId)); ?>" aria-label="Abrir notificaciones<?php echo $clientUnreadNotifications > 0 ? ', ' . $clientUnreadNotifications . ' sin leer' : ''; ?>">
            <?php echo bitacora_icon('bell'); ?><span data-notification-count <?php echo $clientUnreadNotifications > 0 ? '' : 'hidden'; ?>><?php echo $clientUnreadNotifications; ?></span>
          </a>
          <a class="crm-button crm-button--ghost" href="<?php echo h(crm_portal_url('logout')); ?>">Cerrar sesión</a>
        </div>
      </header>

      <?php if ($notice && $activeView !== 'perfil'): ?><div class="crm-flash crm-flash--<?php echo h($notice['type']); ?>"><p><?php echo h($notice['text']); ?></p></div><?php endif; ?>
      <?php if ($mustChangePassword && $activeView !== 'perfil'): ?>
        <section class="crm-security-callout" aria-live="polite">
          <span><?php echo bitacora_icon('lock'); ?></span>
          <div><strong>Actualiza tu password por seguridad</strong><p>Es tu primer acceso o tu password fue regenerado. Cambialo desde Perfil antes de compartir este acceso con tu equipo.</p></div>
          <a class="crm-button" href="<?php echo h(bitacora_client_url('perfil', $activeOpportunityId)); ?>">Cambiar password</a>
        </section>
      <?php endif; ?>

      <?php if ($activeView === 'resumen'): ?>
        <section class="crm-client-module crm-client-module--resumen">
          <section class="crm-client-hero crm-client-hero--panel crm-client-hero--compact">
            <div class="crm-client-hero__content">
              <p class="eyebrow">Proyecto seleccionado</p>
              <div class="crm-client-hero__title">
                <div><h1><?php echo h($project['service']); ?></h1><p><?php echo h($project['company_name']); ?> · <?php echo count($projects); ?> proyecto(s) con acceso</p></div>
                <span class="crm-pill <?php echo h(bitacora_pill_class((string) ($project['opportunity_status'] ?? 'Proyecto entregado'))); ?>"><?php echo h($project['opportunity_status'] ?? 'Proyecto entregado'); ?></span>
              </div>
              <div class="crm-client-hero__actions">
                <a class="crm-button" href="<?php echo h(crm_portal_url('solicitudes', $activeOpportunityId, ['new' => 1])); ?>"><?php echo bitacora_icon('requests'); ?> Reportar un incidente</a>
                <a class="crm-button crm-button--ghost" href="<?php echo h(crm_portal_url('cotizaciones', $activeOpportunityId, ['new' => 1])); ?>"><?php echo bitacora_icon('quote'); ?> Solicitar cotización</a>
              </div>
            </div>
            <aside class="crm-client-hero__next">
              <small>Próxima atención</small>
              <strong><?php echo h($nextServiceDate !== '' ? $nextServiceDate : 'Por programar'); ?></strong>
              <p><?php echo $nextScheduledRequest ? h($nextScheduledRequest['title']) : ($nextMaintenanceLog ? h($nextMaintenanceLog['title']) : 'No hay una visita o solicitud programada.'); ?></p>
              <a href="<?php echo h(bitacora_client_url('bitacora', $activeOpportunityId)); ?>">Consultar bitácora</a>
            </aside>
          </section>

          <section class="crm-kpis crm-client-kpis crm-client-kpis--operational" aria-label="Resumen de mantenimiento">
            <article class="crm-card crm-kpi"><span class="crm-kpi__icon"><?php echo bitacora_icon('requests'); ?></span><div><span>Solicitudes abiertas</span><strong><?php echo count($openRequests); ?></strong><small><?php echo count($urgentOpenRequests); ?> urgente(s)</small></div></article>
            <article class="crm-card crm-kpi"><span class="crm-kpi__icon"><?php echo bitacora_icon('logs'); ?></span><div><span>Registros publicados</span><strong><?php echo count($logs); ?></strong><small>Del proyecto actual</small></div></article>
            <article class="crm-card crm-kpi"><span class="crm-kpi__icon"><?php echo bitacora_icon('quote'); ?></span><div><span>Cotizaciones</span><strong><?php echo count($clientQuotes); ?></strong><small>De toda la empresa</small></div></article>
            <article class="crm-card crm-kpi"><span class="crm-kpi__icon"><?php echo bitacora_icon('bell'); ?></span><div><span>Sin leer</span><strong><?php echo $clientUnreadNotifications; ?></strong><small>Actualizaciones nuevas</small></div></article>
          </section>

          <section class="crm-client-overview-grid">
            <article class="crm-card crm-client-project-card">
              <div class="crm-section-head"><div><p class="eyebrow">Contexto operativo</p><h2>Proyecto actual</h2><p>Todos los reportes y registros de esta vista corresponden a este servicio.</p></div><a class="crm-button crm-button--ghost" href="<?php echo h(bitacora_client_url('proyectos', $activeOpportunityId)); ?>">Cambiar proyecto</a></div>
              <div class="crm-client-project-detail">
                <span><small>Servicio</small><strong><?php echo h($project['service']); ?></strong></span>
                <span><small>Empresa</small><strong><?php echo h($project['company_name']); ?></strong></span>
                <span><small>Contacto</small><strong><?php echo h($project['contact_name'] ?: 'No especificado'); ?></strong></span>
                <span><small>Siguiente acción</small><strong><?php echo h($project['next_action_date'] ?: 'Por definir'); ?></strong></span>
              </div>
              <div class="crm-client-project-card__footer"><a href="<?php echo h(bitacora_client_url('bitacora', $activeOpportunityId)); ?>">Ver historial de mantenimiento</a><a href="<?php echo h(bitacora_client_url('solicitudes', $activeOpportunityId)); ?>">Dar seguimiento a solicitudes</a></div>
            </article>

            <article class="crm-card crm-client-activity">
              <div class="crm-section-head"><div><p class="eyebrow">Seguimiento</p><h2>Actividad reciente</h2><p>Últimos movimientos visibles para tu empresa.</p></div></div>
              <div class="crm-client-activity__list">
                <?php if ($latestRequest): ?><a href="<?php echo h(crm_portal_url('solicitudes', $activeOpportunityId, [], 'request-' . (int) $latestRequest['id'])); ?>"><span><?php echo bitacora_icon('requests'); ?></span><div><small>Solicitud · <?php echo h($latestRequest['status'] ?: 'Recibida'); ?></small><strong><?php echo h($latestRequest['title']); ?></strong><p><?php echo h($latestRequest['updated_at'] ?: $latestRequest['created_at']); ?></p></div></a><?php endif; ?>
                <?php if ($latestLog): ?><a href="<?php echo h(bitacora_client_url('bitacora', $activeOpportunityId)); ?>"><span><?php echo bitacora_icon('logs'); ?></span><div><small>Bitácora · <?php echo h($latestLog['status']); ?></small><strong><?php echo h($latestLog['title']); ?></strong><p><?php echo h($latestLog['scheduled_date'] ?: $latestLog['created_at']); ?></p></div></a><?php endif; ?>
                <?php if ($latestClientQuote): ?><a href="<?php echo h(crm_portal_url('cotizaciones', $activeOpportunityId, [], 'quote-' . (int) $latestClientQuote['id'])); ?>"><span><?php echo bitacora_icon('quote'); ?></span><div><small>Cotización · <?php echo h($latestClientQuote['status']); ?></small><strong><?php echo h($latestClientQuote['quote_code']); ?> — <?php echo h($latestClientQuote['service']); ?></strong><p><?php echo h($latestClientQuote['created_at']); ?></p></div></a><?php endif; ?>
                <?php if (!$latestRequest && !$latestLog && !$latestClientQuote): ?><div class="crm-empty-state"><strong>Aún no hay actividad</strong><p>Cuando exista un registro o solicitud aparecerá aquí.</p></div><?php endif; ?>
              </div>
            </article>
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
              <a class="crm-button crm-button--ghost" href="<?php echo h(bitacora_client_url('cotizaciones', $activeOpportunityId)); ?>">Cotizar ampliacion</a>
            </div>
          </section>
        </section>
      <?php elseif ($activeView === 'cotizaciones'): ?>
        <section class="crm-client-module crm-client-module--cotizaciones">
          <div class="crm-module-head crm-module-head--actions"><div><p class="eyebrow">Cotizaciones</p><h1>Solicitudes comerciales</h1><p>Solicita ampliaciones, nuevos servicios o trabajos relacionados con tus proyectos.</p></div><button class="crm-button" type="button" data-modal-open="client-quote-modal">Solicitar cotización</button></div>

          <section class="crm-client-workspace crm-client-workspace--quotes crm-client-workspace--history">
            <dialog id="client-quote-modal" class="crm-modal crm-client-modal" aria-labelledby="client-quote-modal-title" <?php echo (($action === '' && isset($_GET['new'])) || ($action === 'create_quote_request' && $notice && ($notice['type'] ?? '') === 'error')) ? 'data-open-on-load' : ''; ?>>
              <div class="crm-modal__surface">
                <header class="crm-modal__header"><div><p class="eyebrow">Solicitud comercial</p><h2 id="client-quote-modal-title">Solicitar cotización</h2><p>Comparte el alcance inicial. El equipo comercial validará los datos antes de publicar una propuesta.</p></div><button class="crm-modal__close" type="button" aria-label="Cerrar formulario" data-modal-close><?php echo bitacora_icon('close'); ?></button></header>
              <form class="crm-form crm-request-intake crm-modal__form" method="post" enctype="multipart/form-data" data-modal-form>
                <input type="hidden" name="token" value="<?php echo h($token); ?>">
                <input type="hidden" name="action" value="create_quote_request">
                <fieldset class="crm-request-form-section">
                  <legend>Servicio requerido</legend>
                  <div class="crm-request-form-grid">
                    <label class="crm-field">Proyecto relacionado
                      <select name="related_project_id">
                        <option value="0">Nuevo servicio / sin relacion</option>
                        <?php foreach ($projects as $projectOption): ?>
                          <option value="<?php echo (int) $projectOption['opportunity_id']; ?>" <?php echo (int) ($_POST['related_project_id'] ?? $activeOpportunityId) === (int) $projectOption['opportunity_id'] ? 'selected' : ''; ?>><?php echo h($projectOption['service']); ?></option>
                        <?php endforeach; ?>
                      </select>
                      <small>Elige un proyecto si la cotización es una ampliación; usa “sin relación” para un servicio nuevo.</small>
                    </label>
                    <label class="crm-field">Servicio<select name="quote_service" required><?php foreach ($quoteServices as $serviceOption): ?><option <?php echo (($_POST['quote_service'] ?? '') === $serviceOption) ? 'selected' : ''; ?>><?php echo h($serviceOption); ?></option><?php endforeach; ?></select></label>
                    <label class="crm-field">Otro servicio<input name="quote_service_other" maxlength="160" value="<?php echo h($_POST['quote_service_other'] ?? ''); ?>" placeholder="Especifica si elegiste Otro"></label>
                    <label class="crm-field">Ubicacion<input name="quote_location" maxlength="190" value="<?php echo h($_POST['quote_location'] ?? ''); ?>" placeholder="Planta, nave, edificio o area" required></label>
                    <label class="crm-field">Prioridad<select name="quote_urgency"><?php foreach ($quoteUrgencies as $urgencyOption): ?><option <?php echo (($_POST['quote_urgency'] ?? 'Normal') === $urgencyOption) ? 'selected' : ''; ?>><?php echo h($urgencyOption); ?></option><?php endforeach; ?></select></label>
                    <label class="crm-field">Presupuesto estimado<select name="quote_budget"><?php foreach ($quoteBudgets as $budgetOption): ?><option <?php echo (($_POST['quote_budget'] ?? 'Por definir') === $budgetOption) ? 'selected' : ''; ?>><?php echo h($budgetOption); ?></option><?php endforeach; ?></select><small>Es una referencia para proponer el alcance adecuado; no compromete la compra.</small></label>
                    <label class="crm-field">Fecha requerida<input type="date" name="quote_requested_date" min="<?php echo h(date('Y-m-d')); ?>" value="<?php echo h($_POST['quote_requested_date'] ?? ''); ?>"><small>Fecha ideal para ejecutar o entregar el servicio, no la vigencia de la propuesta.</small></label>
                  </div>
                </fieldset>
                <fieldset class="crm-request-form-section">
                  <legend>Alcance</legend>
                  <label class="crm-field">Descripcion del requerimiento<textarea name="quote_scope" rows="7" minlength="30" maxlength="5000" placeholder="Describe cantidades, capacidades, medidas, areas involucradas y resultado esperado." required><?php echo h($_POST['quote_scope'] ?? ''); ?></textarea></label>
                </fieldset>
                <fieldset class="crm-request-form-section">
                  <legend>Archivo tecnico</legend>
                  <label class="crm-evidence-upload">
                    <span class="crm-evidence-upload__icon"><?php echo bitacora_icon('quote'); ?></span>
                    <span><strong>Adjuntar plano, fotografia o PDF</strong><small>PDF, JPG, PNG o WEBP. Maximo 8 MB. Archivo opcional.</small></span>
                    <input type="file" name="quote_attachment" accept="application/pdf,image/jpeg,image/png,image/webp">
                  </label>
                </fieldset>
                <footer class="crm-modal__footer"><p>La solicitud quedará asociada a <strong><?php echo h($project['company_name']); ?></strong>.</p><div><button class="crm-button crm-button--ghost" type="button" data-modal-close>Cancelar</button><button class="crm-button" type="submit">Enviar solicitud</button></div></footer>
              </form>
              </div>
            </dialog>

            <section class="crm-quote-list" aria-label="Historial de cotizaciones">
              <div class="crm-section-head"><div><h2>Historial</h2><p>Solicitudes enviadas y propuestas publicadas para tu empresa.</p></div><span class="crm-pill crm-pill--neutral"><?php echo count($clientQuotes); ?> registro(s)</span></div>
              <?php foreach ($clientQuotes as $clientQuote): ?>
                <?php
                  $isPublished = (int) ($clientQuote['visible_to_client'] ?? 0) === 1;
                  $clientQuoteStatus = trim((string) ($clientQuote['client_status'] ?? 'Pendiente')) ?: 'Pendiente';
                ?>
                <article id="quote-<?php echo (int) $clientQuote['id']; ?>" class="crm-card crm-quote-card">
                  <div class="crm-quote-card__head">
                    <div><span class="crm-pill <?php echo h(bitacora_pill_class((string) $clientQuote['status'])); ?>"><?php echo h($clientQuote['status']); ?></span><h3><?php echo h($clientQuote['quote_code']); ?> - <?php echo h($clientQuote['service']); ?></h3></div>
                    <strong><?php echo $isPublished ? '$' . number_format((float) $clientQuote['amount'], 2) : 'En preparacion'; ?></strong>
                  </div>
                  <div class="crm-request-meta">
                    <span><strong>Cliente</strong><?php echo h($clientQuoteStatus); ?></span>
                    <span><strong>Ubicacion</strong><?php echo h($clientQuote['request_location'] ?: 'Sin especificar'); ?></span>
                    <span><strong>Fecha requerida</strong><?php echo h($clientQuote['requested_date'] ?: 'Por definir'); ?></span>
                    <span><strong>Vigencia</strong><?php echo h($isPublished ? ($clientQuote['valid_until'] ?: 'Sin vigencia') : 'Pendiente'); ?></span>
                  </div>
                  <details class="crm-report-details">
                    <summary>Ver detalle</summary>
                    <div class="crm-report-details__body">
                      <p><strong>Alcance solicitado</strong><br><?php echo nl2br(h($clientQuote['request_scope'] ?: 'Sin alcance registrado.')); ?></p>
                      <p><strong>Presupuesto indicado</strong><br><?php echo h($clientQuote['budget_range'] ?: 'Por definir'); ?></p>
                      <?php if (!empty($clientQuote['related_service'])): ?><p><strong>Proyecto relacionado</strong><br><?php echo h($clientQuote['related_service']); ?></p><?php endif; ?>
                      <?php if ($isPublished && !empty($clientQuote['terms'])): ?><p><strong>Condiciones de la propuesta</strong><br><?php echo nl2br(h($clientQuote['terms'])); ?></p><?php endif; ?>
                      <div class="crm-client-actions-row">
                        <?php if (!empty($clientQuote['attachment_path'])): ?><a class="crm-button crm-button--ghost" href="<?php echo h(crm_quote_attachment_url((int) $clientQuote['id'])); ?>" target="_blank" rel="noopener">Ver archivo tecnico</a><?php endif; ?>
                        <?php if ($isPublished && !empty($clientQuote['proposal_path'])): ?><a class="crm-button" href="<?php echo h(crm_quote_attachment_url((int) $clientQuote['id'], 'proposal')); ?>" target="_blank" rel="noopener">Abrir propuesta PDF</a><?php endif; ?>
                      </div>
                    </div>
                  </details>
                  <?php if ($isPublished && !in_array($clientQuoteStatus, ['Aceptada', 'Rechazada'], true)): ?>
                    <form class="crm-quote-response" method="post">
                      <input type="hidden" name="token" value="<?php echo h($token); ?>">
                      <input type="hidden" name="action" value="respond_quote">
                      <input type="hidden" name="quote_id" value="<?php echo (int) $clientQuote['id']; ?>">
                      <label class="crm-field">Respuesta<select name="client_status" required><option value="Aceptada">Aceptar propuesta</option><option value="Cambios solicitados">Solicitar cambios</option><option value="Rechazada">Rechazar propuesta</option></select></label>
                      <label class="crm-field crm-field--wide">Comentarios<textarea name="client_comments" rows="3" maxlength="2000" placeholder="Obligatorio para solicitar cambios o rechazar."></textarea></label>
                      <button class="crm-button" type="submit">Enviar respuesta</button>
                    </form>
                  <?php elseif ($isPublished): ?>
                    <div class="crm-quote-response-summary"><span class="crm-pill <?php echo $clientQuoteStatus === 'Aceptada' ? 'crm-pill--success' : 'crm-pill--danger'; ?>"><?php echo h($clientQuoteStatus); ?></span><?php if (!empty($clientQuote['client_comments'])): ?><p><?php echo nl2br(h($clientQuote['client_comments'])); ?></p><?php endif; ?></div>
                  <?php endif; ?>
                </article>
              <?php endforeach; ?>
              <?php if (!$clientQuotes): ?><div class="crm-card"><p>Aun no hay solicitudes de cotizacion para esta empresa.</p></div><?php endif; ?>
            </section>
          </section>
        </section>
      <?php elseif ($activeView === 'bitacora'): ?>
        <section class="crm-client-module crm-client-module--bitacora">
          <div class="crm-module-head crm-module-head--actions">
            <div><p class="eyebrow">Mantenimiento</p><h1>Bitácora del proyecto</h1><p>Consulta servicios programados, trabajos realizados y notas técnicas publicadas para <?php echo h($project['service']); ?>.</p></div>
            <div class="crm-head__actions"><a class="crm-button crm-button--ghost" href="<?php echo h(bitacora_client_url('proyectos', $activeOpportunityId)); ?>">Cambiar proyecto</a><a class="crm-button" href="<?php echo h(crm_portal_url('solicitudes', $activeOpportunityId, ['new' => 1])); ?>">Reportar incidente</a></div>
          </div>

          <section class="crm-maintenance-overview" aria-label="Resumen de bitácora">
            <article><small>Proyecto</small><strong><?php echo h($project['service']); ?></strong><span><?php echo h($project['opportunity_status'] ?? 'Activo'); ?></span></article>
            <article><small>Registros publicados</small><strong><?php echo count($logs); ?></strong><span>Visibles para tu cuenta</span></article>
            <article><small>Próxima atención</small><strong><?php echo h($nextServiceDate !== '' ? $nextServiceDate : 'Por programar'); ?></strong><span><?php echo $nextServiceDate !== '' ? 'Fecha confirmada o prevista' : 'Sin visita pendiente'; ?></span></article>
          </section>

          <article class="crm-card crm-maintenance-log">
            <div class="crm-section-head"><div><h2>Historial técnico</h2><p>Los registros más recientes aparecen primero. Las fechas programadas indican cuándo está previsto el servicio.</p></div><span class="crm-pill crm-pill--neutral"><?php echo count($logs); ?> registro(s)</span></div>
            <div class="crm-maintenance-timeline">
              <?php foreach ($logs as $log): ?>
                <article class="crm-maintenance-entry">
                  <span class="crm-maintenance-entry__marker"><?php echo bitacora_icon('logs'); ?></span>
                  <div class="crm-maintenance-entry__body">
                    <div class="crm-maintenance-entry__head"><div><small><?php echo h($log['type']); ?></small><h3><?php echo h($log['title']); ?></h3></div><span class="crm-pill <?php echo h(bitacora_pill_class((string) $log['status'])); ?>"><?php echo h($log['status']); ?></span></div>
                    <p><?php echo nl2br(h($log['notes'] ?: 'Sin notas técnicas adicionales.')); ?></p>
                    <div class="crm-maintenance-entry__meta">
                      <span><strong>Fecha de servicio</strong><?php echo h($log['scheduled_date'] ?: 'No programada'); ?></span>
                      <span><strong>Publicado</strong><?php echo h($log['created_at']); ?></span>
                    </div>
                  </div>
                </article>
              <?php endforeach; ?>
              <?php if (!$logs): ?><div class="crm-empty-state crm-empty-state--maintenance"><span><?php echo bitacora_icon('logs'); ?></span><strong>Aún no hay registros publicados</strong><p>Cuando ID Industrial publique una visita o servicio aparecerá aquí con su fecha y notas técnicas.</p><a class="crm-button crm-button--ghost" href="<?php echo h(crm_portal_url('solicitudes', $activeOpportunityId, ['new' => 1])); ?>">Crear una solicitud</a></div><?php endif; ?>
            </div>
          </article>
        </section>
      <?php elseif ($activeView === 'solicitudes'): ?>
        <section class="crm-client-module crm-client-module--solicitudes">
          <div class="crm-module-head crm-module-head--actions"><div><p class="eyebrow">Solicitudes</p><h1>Reportes de mantenimiento</h1><p>Levanta y consulta reportes ligados solamente a <?php echo h($project['service']); ?>.</p></div><div class="crm-head__actions"><a class="crm-button crm-button--ghost" href="<?php echo h(bitacora_client_url('proyectos', $activeOpportunityId)); ?>">Cambiar proyecto</a><button class="crm-button" type="button" data-modal-open="client-request-modal">Nueva solicitud</button></div></div>
          <section class="crm-client-workspace crm-client-workspace--requests crm-client-workspace--history">
            <dialog id="client-request-modal" class="crm-modal crm-client-modal" aria-labelledby="client-request-modal-title" <?php echo (($action === '' && isset($_GET['new'])) || ($action === 'create_request' && $notice && ($notice['type'] ?? '') === 'error')) ? 'data-open-on-load' : ''; ?>>
              <div class="crm-modal__surface">
                <header class="crm-modal__header"><div><p class="eyebrow">Mantenimiento · <?php echo h($project['service']); ?></p><h2 id="client-request-modal-title">Nueva solicitud</h2><p>Describe el incidente con suficiente detalle para que el equipo pueda evaluarlo y programarlo.</p></div><button class="crm-modal__close" type="button" aria-label="Cerrar formulario" data-modal-close><?php echo bitacora_icon('close'); ?></button></header>
              <form class="crm-form crm-request-intake crm-modal__form" method="post" enctype="multipart/form-data" data-request-form data-modal-form>
                <input type="hidden" name="token" value="<?php echo h($token); ?>">
                <input type="hidden" name="action" value="create_request">
                <input type="hidden" name="project_id" value="<?php echo (int) $activeOpportunityId; ?>">
                <fieldset class="crm-request-form-section">
                  <legend>Identificacion del reporte</legend>
                  <div class="crm-request-form-grid">
                    <label class="crm-field crm-field--wide">Asunto<input name="title" maxlength="160" value="<?php echo h($_POST['title'] ?? ''); ?>" placeholder="Ej. Falla en unidad manejadora de aire" required><small>Resume el problema en una frase clara; será el título del seguimiento.</small></label>
                    <label class="crm-field">Categoria<select name="category" required><?php foreach ($requestCategories as $category): ?><option <?php echo (($_POST['category'] ?? '') === $category) ? 'selected' : ''; ?>><?php echo h($category); ?></option><?php endforeach; ?></select></label>
                    <label class="crm-field">Prioridad<select name="priority" required><?php foreach ($requestPriorities as $priority): ?><option <?php echo (($_POST['priority'] ?? 'Media') === $priority) ? 'selected' : ''; ?>><?php echo h($priority); ?></option><?php endforeach; ?></select><small>Urgente: paro total o riesgo. Alta: afecta la operación. Media/Baja: puede programarse.</small></label>
                    <label class="crm-field">Ubicacion exacta<input name="location" maxlength="190" value="<?php echo h($_POST['location'] ?? ''); ?>" placeholder="Area, nivel o linea" required></label>
                    <label class="crm-field">Equipo afectado<input name="equipment" maxlength="190" value="<?php echo h($_POST['equipment'] ?? ''); ?>" placeholder="Nombre, modelo o identificador"></label>
                    <label class="crm-field">Impacto operativo<select name="impact" required><?php foreach ($requestImpacts as $impact): ?><option <?php echo (($_POST['impact'] ?? 'Sin paro') === $impact) ? 'selected' : ''; ?>><?php echo h($impact); ?></option><?php endforeach; ?></select></label>
                    <label class="crm-field">Fecha del incidente<input type="datetime-local" name="occurred_at" max="<?php echo h(date('Y-m-d\TH:i')); ?>" value="<?php echo h($_POST['occurred_at'] ?? date('Y-m-d\TH:i')); ?>"><small>Momento real en que comenzó o se detectó la falla; no es la fecha programada de atención.</small></label>
                  </div>
                </fieldset>
                <fieldset class="crm-request-form-section">
                  <legend>Descripcion tecnica</legend>
                  <label class="crm-field">Que esta ocurriendo<textarea name="message" rows="6" minlength="20" maxlength="4000" placeholder="Describe sintomas, frecuencia, ruidos, alarmas o cambios observados." required><?php echo h($_POST['message'] ?? ''); ?></textarea></label>
                  <label class="crm-field">Acciones realizadas<textarea name="actions_taken" rows="3" maxlength="2000" placeholder="Indica si se detuvo el equipo, reinicio el sistema o se aislo el area."><?php echo h($_POST['actions_taken'] ?? ''); ?></textarea></label>
                </fieldset>
                <fieldset class="crm-request-form-section">
                  <legend>Evidencia</legend>
                  <label class="crm-evidence-upload">
                    <span class="crm-evidence-upload__icon"><?php echo bitacora_icon('camera'); ?></span>
                    <span><strong>Adjuntar fotografia</strong><small id="request-evidence-help">JPG, PNG o WEBP. Maximo 5 MB. Archivo opcional.</small></span>
                    <input id="request-evidence" type="file" name="evidence" accept="image/jpeg,image/png,image/webp" capture="environment" aria-describedby="request-evidence-help" data-evidence-input>
                  </label>
                  <p class="crm-field-error" hidden data-evidence-error role="alert"></p>
                  <div class="crm-evidence-preview" hidden data-evidence-preview><img alt="Vista previa de evidencia"><div><strong data-evidence-name></strong><small data-evidence-size></small></div><button type="button" data-evidence-remove>Quitar</button></div>
                </fieldset>
                <footer class="crm-modal__footer"><p>El reporte quedará ligado a <strong><?php echo h($project['service']); ?></strong>.</p><div><button class="crm-button crm-button--ghost" type="button" data-modal-close>Cancelar</button><button class="crm-button" type="submit">Enviar reporte</button></div></footer>
              </form>
              </div>
            </dialog>
            <article class="crm-card crm-client-history-card">
              <div class="crm-section-head"><div><h2>Seguimiento</h2><p>Estado y respuesta de ID Industrial.</p></div></div>
              <div class="crm-list crm-list--compact">
                <?php foreach ($requests as $request): ?>
                  <?php $requestStatus = trim((string) ($request['status'] ?? 'Recibida')) ?: 'Recibida'; ?>
                  <div id="request-<?php echo (int) $request['id']; ?>" class="crm-list__item crm-request-card">
                    <div class="crm-request-card__head"><span class="crm-pill <?php echo h(bitacora_pill_class($requestStatus)); ?>"><?php echo h($requestStatus); ?></span><small>Folio ID-<?php echo str_pad((string) $request['id'], 5, '0', STR_PAD_LEFT); ?> - <?php echo h($request['updated_at'] ?: $request['created_at']); ?></small></div>
                    <div class="crm-request-title"><span><?php echo h($request['category'] ?? 'Mantenimiento correctivo'); ?></span><strong><?php echo h($request['title']); ?></strong></div>
                    <div class="crm-request-meta crm-request-meta--client">
                      <span><strong>Prioridad</strong><?php echo h($request['priority'] ?? 'Media'); ?></span>
                      <span><strong>Ubicacion</strong><?php echo h($request['location'] ?: 'Sin especificar'); ?></span>
                      <span><strong>Impacto</strong><?php echo h($request['impact'] ?? 'Sin paro'); ?></span>
                      <span><strong>Incidente</strong><?php echo h($request['occurred_at'] ?: $request['created_at']); ?></span>
                    </div>
                    <details class="crm-report-details">
                      <summary>Ver detalle completo</summary>
                      <div class="crm-report-details__body">
                        <div class="crm-report-detail-grid">
                          <span><strong>Equipo afectado</strong><?php echo h($request['equipment'] ?: 'No especificado'); ?></span>
                          <span><strong>Fecha objetivo</strong><?php echo h($request['due_date'] ?: 'Por confirmar'); ?></span>
                          <span><strong>Fecha programada</strong><?php echo h($request['scheduled_date'] ?: 'Por confirmar'); ?></span>
                          <span><strong>Responsable</strong><?php echo h($request['assigned_to'] ?: 'Por asignar'); ?></span>
                        </div>
                        <section><strong>Descripcion del reporte</strong><p><?php echo nl2br(h($request['message'])); ?></p></section>
                        <?php if (!empty($request['actions_taken'])): ?><section><strong>Acciones realizadas</strong><p><?php echo nl2br(h($request['actions_taken'])); ?></p></section><?php endif; ?>
                        <?php if (!empty($request['evidence_path'])): ?>
                          <a class="crm-evidence-card" href="<?php echo h(crm_evidence_url((int) $request['id'])); ?>" target="_blank" rel="noopener">
                            <img src="<?php echo h(crm_evidence_url((int) $request['id'])); ?>" alt="Evidencia del reporte <?php echo h($request['title']); ?>" loading="lazy">
                            <span><strong>Fotografia de evidencia</strong><small><?php echo h($request['evidence_original_name'] ?: 'Evidencia adjunta'); ?> - <?php echo h(bitacora_file_size((int) ($request['evidence_size'] ?? 0))); ?></small></span>
                          </a>
                        <?php endif; ?>
                      </div>
                    </details>
                    <p class="crm-request-next"><strong>Seguimiento:</strong> <?php echo h(bitacora_request_next_step($requestStatus)); ?><?php if (!empty($request['resolved_at'])): ?> Resuelta: <?php echo h($request['resolved_at']); ?>.<?php endif; ?></p>
                    <?php if (!empty($request['admin_response'])): ?><div class="crm-response"><strong>Respuesta ID Industrial</strong><p><?php echo h($request['admin_response']); ?></p></div><?php endif; ?>
                  </div>
                <?php endforeach; ?>
                <?php if (!$requests): ?><p>Aun no has enviado solicitudes de mantenimiento para este proyecto.</p><?php endif; ?>
              </div>
            </article>
          </section>
        </section>
      <?php elseif ($activeView === 'notificaciones'): ?>
        <section class="crm-client-module crm-client-module--notificaciones">
          <div class="crm-module-head"><p class="eyebrow">Notificaciones</p><h1>Atencion de reportes</h1><p>Consulta las actualizaciones de ID Industrial para este proyecto.</p></div>
          <article class="crm-card crm-notification-panel">
            <div class="crm-section-head">
              <div><h2>Actualizaciones</h2><p>Seguimiento recibido para <?php echo h($project['service']); ?>.</p></div>
              <?php if ($clientUnreadNotifications > 0): ?>
                <form method="post" class="crm-inline-form">
                  <input type="hidden" name="token" value="<?php echo h($token); ?>">
                  <input type="hidden" name="action" value="mark_all_client_notifications_read">
                  <button class="crm-button crm-button--ghost" type="submit">Marcar todo leido</button>
                </form>
              <?php endif; ?>
            </div>
            <div class="crm-list crm-notification-list">
              <?php foreach ($clientNotifications as $notification): ?>
                <?php $notificationIsUnread = (int) ($notification['is_read'] ?? 0) === 0; ?>
                <div class="crm-list__item crm-notification-item <?php echo $notificationIsUnread ? 'is-unread' : ''; ?>">
                  <div class="crm-request-card__head">
                    <span class="crm-pill <?php echo $notificationIsUnread ? 'crm-pill--warning' : 'crm-pill--neutral'; ?>"><?php echo $notificationIsUnread ? 'Nuevo' : 'Leido'; ?></span>
                    <small><?php echo h($notification['created_at']); ?></small>
                  </div>
                  <strong><?php echo h($notification['title']); ?></strong>
                  <p><?php echo h($notification['message']); ?></p>
                  <div class="crm-request-meta crm-request-meta--client">
                    <span><strong>Servicio</strong><?php echo h($notification['service'] ?: $project['service']); ?></span>
                    <span><strong>Estatus</strong><?php echo h($notification['request_status'] ?: 'Recibida'); ?></span>
                    <span><strong>Prioridad</strong><?php echo h($notification['request_priority'] ?: 'Media'); ?></span>
                  </div>
                  <?php if (!empty($notification['client_request_id'])): ?>
                    <details class="crm-report-details crm-report-details--notification">
                      <summary>Ver detalles del reporte <span>Folio ID-<?php echo str_pad((string) $notification['client_request_id'], 5, '0', STR_PAD_LEFT); ?></span></summary>
                      <div class="crm-report-details__body">
                        <div class="crm-report-detail-grid">
                          <span><strong>Categoria</strong><?php echo h($notification['request_category'] ?: 'Mantenimiento correctivo'); ?></span>
                          <span><strong>Ubicacion</strong><?php echo h($notification['request_location'] ?: 'Sin especificar'); ?></span>
                          <span><strong>Equipo</strong><?php echo h($notification['request_equipment'] ?: 'No especificado'); ?></span>
                          <span><strong>Impacto</strong><?php echo h($notification['request_impact'] ?: 'Sin paro'); ?></span>
                          <span><strong>Fecha del incidente</strong><?php echo h($notification['request_occurred_at'] ?: $notification['created_at']); ?></span>
                        </div>
                        <section><strong><?php echo h($notification['request_title'] ?: 'Descripcion del reporte'); ?></strong><p><?php echo nl2br(h($notification['request_message'] ?: $notification['message'])); ?></p></section>
                        <?php if (!empty($notification['request_actions_taken'])): ?><section><strong>Acciones realizadas</strong><p><?php echo nl2br(h($notification['request_actions_taken'])); ?></p></section><?php endif; ?>
                        <?php if (!empty($notification['request_evidence_path'])): ?>
                          <a class="crm-evidence-card" href="<?php echo h(crm_evidence_url((int) $notification['client_request_id'])); ?>" target="_blank" rel="noopener">
                            <img src="<?php echo h(crm_evidence_url((int) $notification['client_request_id'])); ?>" alt="Evidencia del reporte" loading="lazy">
                            <span><strong>Fotografia de evidencia</strong><small><?php echo h($notification['request_evidence_name'] ?: 'Abrir evidencia'); ?></small></span>
                          </a>
                        <?php endif; ?>
                      </div>
                    </details>
                  <?php endif; ?>
                  <div class="crm-notification-actions">
                    <?php if (!empty($notification['target_url'])): ?><a class="crm-button" href="<?php echo h(crm_clean_internal_url($notification['target_url'], 'client')); ?>">Ver reporte</a><?php endif; ?>
                    <?php if ($notificationIsUnread): ?>
                      <form method="post">
                        <input type="hidden" name="token" value="<?php echo h($token); ?>">
                        <input type="hidden" name="action" value="mark_client_notification_read">
                        <input type="hidden" name="notification_id" value="<?php echo (int) $notification['id']; ?>">
                        <button class="crm-button crm-button--ghost" type="submit">Marcar leida</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
              <?php if (!$clientNotifications): ?><p>Aun no hay notificaciones para este proyecto.</p><?php endif; ?>
            </div>
          </article>
        </section>
      <?php else: ?>
        <section class="crm-client-module crm-client-module--perfil">
          <div class="crm-module-head"><p class="eyebrow">Perfil</p><h1>Perfil de acceso</h1><p>Administra tu password y revisa los datos asociados al portal.</p></div>
          <section class="crm-card crm-client-profile crm-account-security">
            <div class="crm-section-head">
              <div><h2>Seguridad de cuenta</h2><p>Administra las credenciales de acceso al portal.</p></div>
              <span class="crm-pill <?php echo $mustChangePassword ? 'crm-pill--warning' : 'crm-pill--success'; ?>"><?php echo $mustChangePassword ? 'Cambio requerido' : 'Protegido'; ?></span>
            </div>
            <?php if ($notice): ?>
              <div class="crm-account-feedback crm-account-feedback--<?php echo h($notice['type']); ?>" role="status">
                <strong><?php echo $notice['type'] === 'success' ? 'Password actualizado' : 'Revisa los datos'; ?></strong>
                <span><?php echo h($notice['text']); ?></span>
              </div>
            <?php endif; ?>
            <div class="crm-profile-grid crm-profile-grid--security">
              <aside class="crm-profile-summary crm-profile-summary--security">
                <span><?php echo bitacora_icon('profile'); ?></span>
                <div><small>Cuenta del cliente</small><strong><?php echo h($project['contact_name'] ?: $project['company_name']); ?></strong><p><?php echo h($project['company_name']); ?></p><code><?php echo h($portal['username']); ?></code></div>
              </aside>
              <div class="crm-password-panel">
                <div class="crm-password-panel__head"><h3>Cambiar password</h3><p>Usa una clave distinta a la actual y confirma el nuevo acceso.</p></div>
                <form class="crm-form crm-password-form" method="post" autocomplete="on" data-password-change-form>
                  <input type="hidden" name="token" value="<?php echo h($token); ?>"><input type="hidden" name="action" value="update_client_password">
                  <label class="crm-field">Password actual<span class="crm-password-field"><input id="current-password" type="password" name="current_password" autocomplete="current-password" required><button class="crm-password-toggle" type="button" aria-label="Mostrar password actual" aria-controls="current-password" data-password-toggle><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5c5 0 8.5 4.2 9.7 6.1a1.7 1.7 0 0 1 0 1.8C20.5 14.8 17 19 12 19s-8.5-4.2-9.7-6.1a1.7 1.7 0 0 1 0-1.8C3.5 9.2 7 5 12 5Zm0 2C7.9 7 4.9 10.4 4 12c.9 1.6 3.9 5 8 5s7.1-3.4 8-5c-.9-1.6-3.9-5-8-5Zm0 2.2a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6Z"/></svg></button></span></label>
                  <div class="crm-password-pair">
                    <label class="crm-field">Nuevo password<span class="crm-password-field"><input id="new-password" type="password" name="new_password" autocomplete="new-password" minlength="10" required data-password-new><button class="crm-password-toggle" type="button" aria-label="Mostrar nuevo password" aria-controls="new-password" data-password-toggle><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5c5 0 8.5 4.2 9.7 6.1a1.7 1.7 0 0 1 0 1.8C20.5 14.8 17 19 12 19s-8.5-4.2-9.7-6.1a1.7 1.7 0 0 1 0-1.8C3.5 9.2 7 5 12 5Zm0 2C7.9 7 4.9 10.4 4 12c.9 1.6 3.9 5 8 5s7.1-3.4 8-5c-.9-1.6-3.9-5-8-5Zm0 2.2a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6Z"/></svg></button></span></label>
                    <label class="crm-field">Confirmar password<span class="crm-password-field"><input id="confirm-password" type="password" name="confirm_password" autocomplete="new-password" minlength="10" required data-password-confirm><button class="crm-password-toggle" type="button" aria-label="Mostrar confirmacion" aria-controls="confirm-password" data-password-toggle><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5c5 0 8.5 4.2 9.7 6.1a1.7 1.7 0 0 1 0 1.8C20.5 14.8 17 19 12 19s-8.5-4.2-9.7-6.1a1.7 1.7 0 0 1 0-1.8C3.5 9.2 7 5 12 5Zm0 2C7.9 7 4.9 10.4 4 12c.9 1.6 3.9 5 8 5s7.1-3.4 8-5c-.9-1.6-3.9-5-8-5Zm0 2.2a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6Z"/></svg></button></span></label>
                  </div>
                  <div class="crm-password-actions"><small>Minimo 10 caracteres.</small><button class="crm-button" type="submit">Actualizar password</button></div>
                </form>
              </div>
            </div>
          </section>
        </section>
      <?php endif; ?>
    </main>
  </div>
  <script>
    (() => {
      const legacyViews = ['resumen', 'proyectos', 'bitacora', 'solicitudes', 'cotizaciones', 'notificaciones', 'perfil'];
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
      document.querySelectorAll('[data-password-change-form]').forEach((form) => {
        const newPassword = form.querySelector('[data-password-new]');
        const confirmation = form.querySelector('[data-password-confirm]');
        if (!newPassword || !confirmation) return;
        const validateMatch = () => {
          confirmation.setCustomValidity(newPassword.value === confirmation.value ? '' : 'La confirmacion no coincide.');
        };
        newPassword.addEventListener('input', validateMatch);
        confirmation.addEventListener('input', validateMatch);
        form.addEventListener('submit', (event) => {
          validateMatch();
          form.classList.add('was-validated');
          if (!form.checkValidity()) {
            event.preventDefault();
            form.querySelector(':invalid')?.focus();
          }
        });
      });
      const requestForm = document.querySelector('[data-request-form]');
      const evidenceInput = document.querySelector('[data-evidence-input]');
      const evidencePreview = document.querySelector('[data-evidence-preview]');
      const evidenceImage = evidencePreview?.querySelector('img');
      const evidenceName = evidencePreview?.querySelector('[data-evidence-name]');
      const evidenceSize = evidencePreview?.querySelector('[data-evidence-size]');
      const evidenceRemove = evidencePreview?.querySelector('[data-evidence-remove]');
      const evidenceError = document.querySelector('[data-evidence-error]');
      let evidenceObjectUrl = '';

      const setEvidenceError = (message = '') => {
        if (!evidenceError) return;
        evidenceError.textContent = message;
        evidenceError.hidden = message === '';
      };

      const clearEvidencePreview = (clearInput = false) => {
        if (evidenceObjectUrl) {
          URL.revokeObjectURL(evidenceObjectUrl);
          evidenceObjectUrl = '';
        }
        if (clearInput && evidenceInput) evidenceInput.value = '';
        if (evidenceInput) evidenceInput.setCustomValidity('');
        if (evidenceImage) evidenceImage.removeAttribute('src');
        if (evidencePreview) evidencePreview.hidden = true;
      };

      if (evidenceInput && evidencePreview && evidenceImage) {
        evidenceInput.addEventListener('change', () => {
          setEvidenceError();
          clearEvidencePreview(false);
          const file = evidenceInput.files?.[0];
          if (!file) return;

          const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
          if (!allowedTypes.includes(file.type)) {
            evidenceInput.setCustomValidity('Selecciona una fotografia JPG, PNG o WEBP.');
            setEvidenceError(evidenceInput.validationMessage);
            clearEvidencePreview(true);
            return;
          }
          if (file.size > 5 * 1024 * 1024) {
            evidenceInput.setCustomValidity('La fotografia debe pesar menos de 5 MB.');
            setEvidenceError(evidenceInput.validationMessage);
            clearEvidencePreview(true);
            return;
          }

          evidenceObjectUrl = URL.createObjectURL(file);
          evidenceImage.src = evidenceObjectUrl;
          if (evidenceName) evidenceName.textContent = file.name;
          if (evidenceSize) evidenceSize.textContent = Math.max(1, Math.ceil(file.size / 1024)) + ' KB';
          evidencePreview.hidden = false;
        });
        evidenceRemove?.addEventListener('click', () => {
          clearEvidencePreview(true);
          setEvidenceError();
        });
      }

      if (requestForm) {
        requestForm.addEventListener('submit', (event) => {
          requestForm.classList.add('was-validated');
          if (!requestForm.checkValidity()) {
            event.preventDefault();
            requestForm.querySelector(':invalid')?.focus();
            return;
          }
          const submitButton = requestForm.querySelector('button[type="submit"]');
          if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = 'Enviando reporte...';
          }
        });
      }

      const notificationPollUrl = document.body.dataset.notificationPoll;
      const notificationCounters = Array.from(document.querySelectorAll('[data-notification-count]'));
      let notificationCount = notificationCounters.reduce((max, counter) => Math.max(max, Number.parseInt(counter.textContent || '0', 10) || 0), 0);
      const renderNotificationCount = (count) => {
        notificationCount = Math.max(0, Number(count) || 0);
        notificationCounters.forEach((counter) => {
          counter.textContent = notificationCount > 99 ? '99+' : String(notificationCount);
          counter.toggleAttribute('hidden', notificationCount === 0);
        });
      };
      const showLiveNotification = (data) => {
        document.querySelector('[data-live-notification]')?.remove();
        const notice = document.createElement('a');
        notice.className = 'crm-live-notification';
        notice.href = data.latest_url || '#';
        notice.dataset.liveNotification = 'true';
        notice.setAttribute('role', 'status');
        notice.setAttribute('aria-live', 'polite');
        notice.innerHTML = '<span>Nueva actualizacion</span><strong></strong><small>Abrir notificaciones</small>';
        notice.querySelector('strong').textContent = data.latest_title || 'Tienes una notificacion nueva';
        document.body.appendChild(notice);
        window.setTimeout(() => notice.remove(), 9000);
      };
      const pollNotifications = async () => {
        if (!notificationPollUrl || document.visibilityState === 'hidden') return;
        try {
          const response = await fetch(notificationPollUrl, { headers: { Accept: 'application/json' }, cache: 'no-store' });
          if (!response.ok) return;
          const data = await response.json();
          const nextCount = Math.max(0, Number(data.unread) || 0);
          if (nextCount > notificationCount) showLiveNotification(data);
          renderNotificationCount(nextCount);
        } catch (error) {
          // El siguiente intervalo vuelve a intentar sin interrumpir el trabajo.
        }
      };
      if (notificationPollUrl) {
        window.setInterval(pollNotifications, 8000);
        document.addEventListener('visibilitychange', () => {
          if (document.visibilityState === 'visible') pollNotifications();
        });
      }
    })();
  </script>
</body>
</html>