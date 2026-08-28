<?php
declare(strict_types=1);

require __DIR__ . '/lib/database.php';
if (crm_uses_legacy_php_url('index.php')) {
  $legacyQuery = $_GET;
  if (isset($legacyQuery['logout'])) {
    $canonicalUrl = crm_admin_url('logout');
  } elseif (isset($legacyQuery['notification_poll'])) {
    $canonicalUrl = crm_admin_url('notification_poll');
  } else {
    $legacyView = (string) ($legacyQuery['view'] ?? 'dashboard');
    $legacyId = (int) ($legacyQuery['id'] ?? 0);
    $isNotificationView = $legacyView === 'bitacora' && isset($legacyQuery['notifications']);
    unset($legacyQuery['view'], $legacyQuery['id'], $legacyQuery['notifications'], $legacyQuery['logout'], $legacyQuery['notification_poll']);
    $canonicalUrl = crm_admin_url($isNotificationView ? 'notifications' : $legacyView, $legacyId, $legacyQuery);
  }
  header('Location: ' . $canonicalUrl, true, 301);
  exit;
}

try {
  $pdo = crm_db();
  crm_start_database_session($pdo);
} catch (Throwable $error) {
  crm_log_event('login.bootstrap_failed', [
    'area' => 'admin',
    'config_source' => crm_config_source(),
    'error_class' => get_class($error),
    'error_message' => substr($error->getMessage(), 0, 500),
  ]);
  throw $error;
}
$statuses = [
  'Nueva solicitud',
  'Contacto realizado',
  'Levantamiento programado',
  'Ingenieria en desarrollo',
  'Cotizacion enviada',
  'Seguimiento',
  'Negociacion',
  'Proyecto ganado',
  'Proyecto iniciado',
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

function crm_month_name(string $month): string
{
  $months = [
    '01' => 'Enero',
    '02' => 'Febrero',
    '03' => 'Marzo',
    '04' => 'Abril',
    '05' => 'Mayo',
    '06' => 'Junio',
    '07' => 'Julio',
    '08' => 'Agosto',
    '09' => 'Septiembre',
    '10' => 'Octubre',
    '11' => 'Noviembre',
    '12' => 'Diciembre',
  ];
  $key = str_pad((string) (int) $month, 2, '0', STR_PAD_LEFT);
  return $months[$key] ?? $month;
}

function crm_chart_color(int $index): string
{
  $colors = ['#1b5f7a', '#d4a321', '#1f7a54', '#a64235', '#5c6f82', '#8a6900', '#2f7464', '#c0784d'];
  return $colors[$index % count($colors)];
}

function crm_chart_total(array $items): int
{
  $total = 0;
  foreach ($items as $item) {
    $total += (int) (is_array($item) ? ($item['total'] ?? 0) : $item);
  }
  return $total;
}

function crm_chart_percent(int $value, int $total): int
{
  return $total > 0 ? (int) round(($value / $total) * 100) : 0;
}

function crm_conic_gradient(array $items): string
{
  $total = crm_chart_total($items);
  if ($total <= 0) {
    return 'conic-gradient(#e9e2d5 0deg 360deg)';
  }

  $start = 0.0;
  $segments = [];
  $index = 0;
  foreach ($items as $item) {
    $value = (int) (is_array($item) ? ($item['total'] ?? 0) : $item);
    if ($value <= 0) {
      $index++;
      continue;
    }
    $end = $start + (($value / $total) * 360);
    $segments[] = crm_chart_color($index) . ' ' . round($start, 2) . 'deg ' . round($end, 2) . 'deg';
    $start = $end;
    $index++;
  }
  return 'conic-gradient(' . implode(', ', $segments) . ')';
}
function crm_icon(string $name): string
{
  $icons = [
    'leads' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
    'clients' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/><path d="M9 9h1"/><path d="M9 13h1"/><path d="M9 17h1"/></svg>',
    'prospects' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/><path d="M11 8v6"/><path d="M8 11h6"/></svg>',
    'tasks' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>',
    'money' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="3"/><path d="M6 12h.01"/><path d="M18 12h.01"/></svg>',
    'portal' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="14" rx="2"/><path d="M8 22h8"/><path d="M12 18v4"/><path d="M8 9h8"/><path d="M8 13h5"/></svg>',
    'reports' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8"/><path d="M8 17h5"/></svg>',
    'open' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7h6l2 2h10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M3 7V5a2 2 0 0 1 2-2h4l2 2h4"/></svg>',
    'urgent' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>',
    'response' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/><path d="m8 11 2 2 5-5"/></svg>',
    'scheduled' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 2v4"/><path d="M16 2v4"/><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 10h18"/><path d="m9 16 2 2 4-4"/></svg>',
    'calendar' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 2v4"/><path d="M16 2v4"/><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 10h18"/><path d="M7 14h.01"/><path d="M12 14h.01"/><path d="M17 14h.01"/><path d="M7 18h.01"/><path d="M12 18h.01"/></svg>',
    'overdue' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/><path d="M12 18h.01"/></svg>',
    'bell' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>',
    'iot' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2v6"/><path d="m8.5 8.5-3-1.5"/><path d="m15.5 8.5 3-1.5"/><circle cx="12" cy="13" r="4"/><path d="M12 17v5"/><path d="M8 22h8"/><path d="M4 13H2"/><path d="M22 13h-2"/><path d="M6.3 9.3 5 8"/><path d="M17.7 9.3 19 8"/></svg>',
  ];
  return $icons[$name] ?? $icons['reports'];
}

function crm_has_text(string $value, string $needle): bool
{
  return strpos($value, $needle) !== false;
}

function crm_request_due_date(string $priority, string $baseDate = 'now'): string
{
  $daysByPriority = [
    'Urgente' => 1,
    'Alta' => 2,
    'Media' => 5,
    'Baja' => 10,
  ];
  $days = $daysByPriority[$priority] ?? 5;
  return date('Y-m-d', strtotime($baseDate . ' +' . $days . ' days'));
}

function crm_request_is_final(string $status): bool
{
  return in_array($status, ['Resuelta', 'Cerrada'], true);
}

function crm_request_next_step(string $status): string
{
  $steps = [
    'Recibida' => 'Revisar alcance y confirmar prioridad.',
    'En revision' => 'Definir responsable y fecha objetivo.',
    'Programada' => 'Ejecutar visita o mantenimiento programado.',
    'En proceso' => 'Documentar avance y respuesta para cliente.',
    'Resuelta' => 'Validar conformidad del cliente.',
    'Cerrada' => 'Seguimiento cerrado.',
  ];
  return $steps[$status] ?? 'Actualizar seguimiento operativo.';
}

function crm_request_log_type(string $status): string
{
  if ($status === 'Programada') {
    return 'Programacion';
  }
  if ($status === 'En proceso') {
    return 'Servicio';
  }
  if (crm_request_is_final($status)) {
    return 'Cierre';
  }
  return 'Seguimiento';
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

function crm_calendar_state(string $recordType, string $status, string $eventDate): string
{
  $normalizedStatus = strtolower(trim($status));
  $completedStatuses = $recordType === 'quote'
    ? ['enviada', 'aprobada']
    : ['cotizacion enviada', 'proyecto entregado'];
  if (in_array($normalizedStatus, $completedStatuses, true)) {
    return 'completed';
  }

  if ($eventDate !== '' && $eventDate < date('Y-m-d')) {
    return 'pending';
  }

  $pendingStatuses = $recordType === 'quote'
    ? ['solicitud recibida', 'en elaboracion']
    : ['nueva solicitud'];
  if (in_array($normalizedStatus, $pendingStatuses, true)) {
    return 'pending';
  }

  return 'followup';
}

function crm_calendar_state_label(string $state): string
{
  $labels = [
    'pending' => 'Pendiente o vencido',
    'followup' => 'En seguimiento',
    'completed' => 'Enviado o completado',
  ];
  return $labels[$state] ?? 'En seguimiento';
}

function crm_calendar_date_label(string $date): string
{
  $timestamp = strtotime($date);
  if ($timestamp === false) {
    return $date;
  }
  return date('d/m/Y', $timestamp);
}
function crm_opportunity_age_label(string $date): string
{
  $timestamp = strtotime($date) ?: time();
  $days = max(0, (int) floor((time() - $timestamp) / 86400));
  if ($days === 0) {
    return 'Hoy';
  }
  return 'Hace ' . $days . ' dia' . ($days === 1 ? '' : 's');
}

function crm_report_priority_rank(string $priority): int
{
  $rank = ['Urgente' => 0, 'Alta' => 1, 'Media' => 2, 'Baja' => 3];
  return $rank[$priority] ?? 4;
}
function crm_require_login(): void
{
  if (empty($_SESSION['crm_user'])) {
    header('Location: ' . crm_admin_url());
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
  crm_expire_iot_session_cookie();
  session_destroy();
  header('Location: ' . crm_admin_url());
  exit;
}

crm_enforce_session_timeout('crm_user', 'crm_token', crm_admin_url('dashboard', 0, ['expired' => 1]));

$humanChallengeKey = 'crm_admin_human_challenge';
$loginError = isset($_GET['expired']) ? 'La sesion se cerro por inactividad. Vuelve a entrar.' : '';
if (($_POST['action'] ?? '') === 'login') {
  $email = strtolower(trim((string) ($_POST['crm_email'] ?? $_POST['email'] ?? '')));
  $password = (string) ($_POST['crm_password'] ?? $_POST['password'] ?? '');
  $humanAnswer = (string) ($_POST['human_answer'] ?? '');
  $loginIdentifier = $email !== '' ? $email : 'anonimo';
  $lockStatus = crm_login_lock_status($pdo, 'admin', $loginIdentifier);
  $loginDiagnostic = crm_login_diagnostic_context($pdo, $loginIdentifier);
  crm_log_event('login.started', array_merge($loginDiagnostic, ['area' => 'admin']));

  if (!empty($lockStatus['locked'])) {
    crm_log_event('login.rejected', array_merge($loginDiagnostic, ['area' => 'admin', 'reason' => 'locked', 'attempts' => (int) ($lockStatus['attempts'] ?? 0), 'seconds' => (int) ($lockStatus['seconds'] ?? 0)]));
    $loginError = crm_login_lock_message($lockStatus);
  } elseif (!crm_validate_math_challenge($humanChallengeKey, $humanAnswer)) {
    $status = crm_record_login_failure($pdo, 'admin', $loginIdentifier);
    crm_refresh_math_challenge($humanChallengeKey);
    $loginError = crm_login_attempt_message('Confirma que eres humano resolviendo la suma.', $status);
    crm_log_event('login.rejected', array_merge($loginDiagnostic, ['area' => 'admin', 'reason' => 'captcha', 'attempts' => (int) ($status['attempts'] ?? 0)]));
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $status = crm_record_login_failure($pdo, 'admin', $loginIdentifier);
    crm_refresh_math_challenge($humanChallengeKey);
    $loginError = crm_login_attempt_message('Ingresa un correo valido.', $status);
    crm_log_event('login.rejected', array_merge($loginDiagnostic, ['area' => 'admin', 'reason' => 'invalid_email', 'attempts' => (int) ($status['attempts'] ?? 0)]));
  } elseif (strlen($password) < 8) {
    $status = crm_record_login_failure($pdo, 'admin', $loginIdentifier);
    crm_refresh_math_challenge($humanChallengeKey);
    $loginError = crm_login_attempt_message('La contrasena debe tener al menos 8 caracteres.', $status);
    crm_log_event('login.rejected', array_merge($loginDiagnostic, ['area' => 'admin', 'reason' => 'password_too_short', 'attempts' => (int) ($status['attempts'] ?? 0)]));
  } else {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE LOWER(email) = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    $passwordVerified = $user ? password_verify($password, (string) $user['password_hash']) : false;
    crm_log_event('login.credentials_checked', array_merge($loginDiagnostic, ['area' => 'admin', 'user_found' => (bool) $user, 'password_verified' => $passwordVerified]));
    if ($user && $passwordVerified) {
      crm_record_login_success($pdo, 'admin', $email);
      crm_refresh_math_challenge($humanChallengeKey);
      session_regenerate_id(true);
      $_SESSION['crm_user'] = ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email'], 'role' => $user['role']];
      $_SESSION['crm_user_last_activity'] = time();
      crm_token();
      crm_log_event('login.success', array_merge($loginDiagnostic, ['area' => 'admin', 'user_id' => (int) $user['id'], 'role' => (string) $user['role'], 'redirect' => crm_admin_url(), 'session_active' => session_status() === PHP_SESSION_ACTIVE]));
      header('Location: ' . crm_admin_url());
      exit;
    }
    $status = crm_record_login_failure($pdo, 'admin', $loginIdentifier);
    crm_refresh_math_challenge($humanChallengeKey);
    $loginError = crm_login_failure_message($status);
    crm_log_event('login.rejected', array_merge($loginDiagnostic, ['area' => 'admin', 'reason' => $user ? 'password_mismatch' : 'user_not_found', 'attempts' => (int) ($status['attempts'] ?? 0)]));
  }
}
$humanChallenge = crm_math_challenge($humanChallengeKey);
if (empty($_SESSION['crm_user'])):
?>
<!doctype html>
<html lang="es-MX">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ID Industrial CRM | Acceso</title>
  <link rel="stylesheet" href="<?php echo h(crm_public_url('assets/css/crm.css')); ?>">
</head>
<body class="crm-login">
  <main class="crm-login__panel">
    <section class="crm-login__media" aria-label="Infraestructura industrial ID Industrial">
      <img src="<?php echo h(crm_public_url('assets/img/optimized/home-industrial.jpg')); ?>" alt="Infraestructura industrial ID Industrial" width="1920" height="500">
      <div class="crm-login__media-copy">
        <span>Pipeline industrial</span>
        <strong>Leads, cotizaciones y seguimiento tecnico en un solo lugar.</strong>
      </div>
    </section>

    <section class="crm-login__card" aria-labelledby="login-title">
      <div class="crm-login__brand">
        <img src="<?php echo h(crm_public_url('assets/img/logo-idindustrial-small.webp')); ?>" alt="ID Industrial" width="280" height="74">
        <div>
          <strong>ID CRM</strong>
          <span>Gestion comercial</span>
        </div>
      </div>
      <h1 id="login-title">Acceso administrador</h1>
      <p>Seguimiento de leads, cotizaciones y clientes industriales.</p>
      <?php if ($loginError): ?><p class="crm-alert"><?php echo h($loginError); ?></p><?php endif; ?>
      <form method="post" autocomplete="on" data-login-form novalidate>
        <input type="hidden" name="action" value="login">
        <label class="crm-field">
          Correo
          <input type="email" name="crm_email" inputmode="email" autocomplete="email" autocapitalize="none" spellcheck="false" required data-login-email>
          <span class="crm-field__error" data-error-email>Ingresa un correo valido.</span>
        </label>
        <label class="crm-field">
          Password
          <span class="crm-password-field">
            <input id="crm-password" type="password" name="crm_password" autocomplete="current-password" minlength="8" required data-login-password>
            <button class="crm-password-toggle" type="button" aria-label="Mostrar password" aria-controls="crm-password" data-password-toggle>
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5c5 0 8.5 4.2 9.7 6.1a1.7 1.7 0 0 1 0 1.8C20.5 14.8 17 19 12 19s-8.5-4.2-9.7-6.1a1.7 1.7 0 0 1 0-1.8C3.5 9.2 7 5 12 5Zm0 2C7.9 7 4.9 10.4 4 12c.9 1.6 3.9 5 8 5s7.1-3.4 8-5c-.9-1.6-3.9-5-8-5Zm0 2.2a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6Z"/></svg>
            </button>
          </span>
          <span class="crm-field__error" data-error-password>La contrasena debe tener al menos 8 caracteres.</span>
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
          <span class="crm-field__error">Resuelve la suma para continuar.</span>
        </label>
        <button class="crm-button" type="submit">Entrar al CRM</button>
      </form>
    </section>
  </main>
  <script>
    (() => {
      const menuToggle = document.querySelector('[data-menu-toggle]');
      const menuClose = document.querySelector('[data-menu-close]');
      const sidebar = document.getElementById('crm-sidebar');
      const setMenu = (open) => {
        document.body.classList.toggle('crm-menu-open', open);
        if (menuToggle) {
          menuToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
          menuToggle.setAttribute('aria-label', open ? 'Cerrar menu' : 'Abrir menu');
        }
      };
      if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', () => setMenu(!document.body.classList.contains('crm-menu-open')));
      }
      if (menuClose) {
        menuClose.addEventListener('click', () => setMenu(false));
      }
      document.querySelectorAll('.crm-nav a').forEach((link) => {
        link.addEventListener('click', () => setMenu(false));
      });
      window.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') setMenu(false);
      });
      const form = document.querySelector('[data-login-form]');
      const password = document.querySelector('[data-login-password]');
      const toggle = document.querySelector('[data-password-toggle]');
      const email = document.querySelector('[data-login-email]');
      const human = document.querySelector('[data-human-answer]');
      if (!form || !password || !toggle || !email || !human) return;

      toggle.addEventListener('click', () => {
        const showing = password.type === 'text';
        password.type = showing ? 'password' : 'text';
        toggle.setAttribute('aria-label', showing ? 'Mostrar password' : 'Ocultar password');
        toggle.classList.toggle('is-active', !showing);
      });

      form.addEventListener('submit', (event) => {
        form.classList.add('was-validated');
        if (!email.validity.valid || !password.validity.valid || !human.validity.valid) {
          event.preventDefault();
          const invalid = form.querySelector(':invalid');
          if (invalid) invalid.focus();
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

if (isset($_GET['notification_poll'])) {
  $latestNotification = crm_recent_notifications($pdo, 'admin', null, 1)[0] ?? null;
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store');
  echo json_encode([
    'unread' => crm_unread_notification_count($pdo, 'admin'),
    'latest_id' => (int) ($latestNotification['id'] ?? 0),
    'latest_title' => (string) ($latestNotification['title'] ?? ''),
    'latest_url' => crm_clean_internal_url($latestNotification['target_url'] ?? crm_admin_url('notifications', 0, [], 'reportes-recibidos')),
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

if (($_POST['action'] ?? '') === 'mark_notification_read') {
  crm_check_token();
  crm_mark_notification_read($pdo, (int) ($_POST['notification_id'] ?? 0), 'admin');
  $returnTo = crm_clean_internal_url((string) ($_POST['return_to'] ?? crm_admin_url('bitacora', 0, [], 'reportes-recibidos')));
  if (!str_starts_with($returnTo, crm_web_base_path())) {
    $returnTo = crm_admin_url('bitacora', 0, [], 'reportes-recibidos');
  }
  header('Location: ' . $returnTo);
  exit;
}

if (($_POST['action'] ?? '') === 'mark_all_notifications_read') {
  crm_check_token();
  crm_mark_all_notifications_read($pdo, 'admin');
  header('Location: ' . crm_admin_url('bitacora', 0, [], 'reportes-recibidos'));
  exit;
}

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

  header('Location: ' . crm_admin_url('profile'));
  exit;
}

if (($_POST['action'] ?? '') === 'update_settings') {
  crm_check_token();
  $quoteRequestAdminEmail = strtolower(trim((string) ($_POST['quote_request_admin_email'] ?? '')));
  $quoteRequestSecondaryEmail = strtolower(trim((string) ($_POST['quote_request_secondary_email'] ?? '')));

  if (!filter_var($quoteRequestAdminEmail, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['crm_flash'] = [
      'type' => 'error',
      'title' => 'Correo no valido',
      'text' => 'Ingresa un correo valido para recibir solicitudes de cotizacion.',
    ];
  } elseif ($quoteRequestSecondaryEmail !== '' && !filter_var($quoteRequestSecondaryEmail, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['crm_flash'] = [
      'type' => 'error',
      'title' => 'Correo secundario no valido',
      'text' => 'Ingresa un correo secundario valido o deja el campo vacio.',
    ];
  } else {
    try {
      crm_set_setting($pdo, 'quote_request_admin_email', $quoteRequestAdminEmail);
      crm_set_setting($pdo, 'quote_request_secondary_email', $quoteRequestSecondaryEmail);
      $_SESSION['crm_flash'] = [
        'type' => 'success',
        'title' => 'Configuracion guardada',
        'text' => 'Las solicitudes de cotizacion web llegaran a ' . $quoteRequestAdminEmail . ($quoteRequestSecondaryEmail !== '' ? ' con copia a ' . $quoteRequestSecondaryEmail : '') . '.',
      ];
    } catch (Throwable $error) {
      crm_log_event('admin_settings.update_failed', [
        'area' => 'admin',
        'error_class' => get_class($error),
        'error_message' => substr($error->getMessage(), 0, 500),
      ]);
      $_SESSION['crm_flash'] = [
        'type' => 'error',
        'title' => 'No se pudo guardar',
        'text' => 'La configuracion no se pudo actualizar en la base de datos.',
      ];
    }
  }

  header('Location: ' . crm_admin_url('settings'));
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
  if (in_array(trim($_POST['status'] ?? 'Nueva solicitud'), ['Proyecto iniciado', 'Proyecto entregado'], true)) {
    $clientStmt = $pdo->prepare('UPDATE clients SET lifecycle_stage = ?, segment = CASE WHEN segment = ? THEN ? ELSE segment END, converted_at = COALESCE(converted_at, CURRENT_TIMESTAMP) WHERE id = ?');
    $clientStmt->execute(['Cliente', 'Prospecto', 'Industrial', $clientId]);
  }
  header('Location: ' . crm_admin_url('opportunities'));
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

  if (in_array($newStatus, ['Proyecto iniciado', 'Proyecto entregado'], true)) {
    $clientStmt = $pdo->prepare('UPDATE clients SET lifecycle_stage = ?, segment = CASE WHEN segment = ? THEN ? ELSE segment END, converted_at = COALESCE(converted_at, CURRENT_TIMESTAMP) WHERE id = (SELECT client_id FROM opportunities WHERE id = ?)');
    $clientStmt->execute(['Cliente', 'Prospecto', 'Industrial', $opportunityId]);
  }

  if ($newStatus === 'Proyecto entregado') {
    try {
      $portal = crm_enable_client_portal($pdo, $opportunityId);
      $emailSent = $portal['created'] && !empty($portal['password'])
        ? crm_send_portal_credentials($portal['opportunity'], $portal['username'], $portal['password'])
        : false;
      $_SESSION['crm_flash'] = $portal['created']
        ? [
          'type' => 'success',
          'title' => 'Proyecto entregado y Bitacora ID activada',
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
    } catch (Throwable $error) {
      error_log('CRM portal activation failed: ' . $error->getMessage());
      $_SESSION['crm_flash'] = [
        'type' => 'error',
        'title' => 'No se pudo activar Bitacora ID',
        'text' => 'MySQL rechazo el guardado del usuario cliente: ' . $error->getMessage(),
      ];
    }
  }

  $redirect = ($_POST['return_to'] ?? '') === 'opportunity' ? crm_admin_url('opportunity', $opportunityId) : crm_admin_url('opportunities');
  header('Location: ' . $redirect);
  exit;
}

if (($_POST['action'] ?? '') === 'toggle_client_public') {
  crm_check_token();
  $clientId = max(0, (int) ($_POST['client_id'] ?? 0));
  $makePublic = ($_POST['make_public'] ?? '') === '1';
  $clientStmt = $pdo->prepare('SELECT id, name FROM clients WHERE id = ? LIMIT 1');
  $clientStmt->execute([$clientId]);
  $client = $clientStmt->fetch();

  if (!$client) {
    $_SESSION['crm_flash'] = [
      'type' => 'error',
      'title' => 'Cliente no encontrado',
      'text' => 'No fue posible localizar el registro que intentas actualizar.',
    ];
  } else {
    $update = $pdo->prepare('UPDATE clients SET is_public = ? WHERE id = ?');
    $update->execute([$makePublic ? 1 : 0, $clientId]);
    $_SESSION['crm_flash'] = [
      'type' => 'success',
      'title' => $makePublic ? 'Cliente visible en el sitio' : 'Cliente oculto del sitio',
      'text' => $makePublic
        ? $client['name'] . ' ahora aparece en la seccion de referencias publicas.'
        : $client['name'] . ' ya no aparece en la seccion de referencias publicas.',
    ];
  }

  header('Location: ' . crm_admin_url('clients'));
  exit;
}

if (($_POST['action'] ?? '') === 'convert_client') {
  crm_check_token();
  $clientId = max(0, (int) ($_POST['client_id'] ?? 0));
  $opportunityId = max(0, (int) ($_POST['opportunity_id'] ?? 0));
  $clientStmt = $pdo->prepare('SELECT id, name, lifecycle_stage FROM clients WHERE id = ? LIMIT 1');
  $clientStmt->execute([$clientId]);
  $client = $clientStmt->fetch();

  if (!$client) {
    $_SESSION['crm_flash'] = [
      'type' => 'error',
      'title' => 'Prospecto no encontrado',
      'text' => 'No fue posible localizar el registro que intentas convertir.',
    ];
  } elseif ((string) $client['lifecycle_stage'] === 'Cliente') {
    $_SESSION['crm_flash'] = [
      'type' => 'info',
      'title' => 'Cliente ya convertido',
      'text' => $client['name'] . ' ya forma parte de la cartera de clientes.',
    ];
  } else {
    $update = $pdo->prepare('UPDATE clients SET lifecycle_stage = ?, segment = CASE WHEN segment = ? THEN ? ELSE segment END, converted_at = COALESCE(converted_at, CURRENT_TIMESTAMP) WHERE id = ?');
    $update->execute(['Cliente', 'Prospecto', 'Industrial', $clientId]);

    if ($opportunityId <= 0) {
      $latestOpportunityStmt = $pdo->prepare('SELECT id FROM opportunities WHERE client_id = ? AND status = "Proyecto entregado" ORDER BY updated_at DESC, id DESC LIMIT 1');
      $latestOpportunityStmt->execute([$clientId]);
      $opportunityId = (int) $latestOpportunityStmt->fetchColumn();
    }

    $portalFlash = null;
    if ($opportunityId > 0) {
      $belongsStmt = $pdo->prepare('SELECT COUNT(*) FROM opportunities WHERE id = ? AND client_id = ? AND status = "Proyecto entregado"');
      $belongsStmt->execute([$opportunityId, $clientId]);
      if ((int) $belongsStmt->fetchColumn() > 0) {
        $activity = $pdo->prepare('INSERT INTO activities (opportunity_id, type, summary, due_date) VALUES (?, "Conversion", "Prospecto convertido a cliente.", NULL)');
        $activity->execute([$opportunityId]);
        try {
          $portalAccess = crm_enable_client_portal($pdo, $opportunityId);
          $emailSent = $portalAccess['created'] && !empty($portalAccess['password'])
            ? crm_send_portal_credentials($portalAccess['opportunity'], $portalAccess['username'], $portalAccess['password'])
            : false;
          $portalFlash = $portalAccess['created']
            ? [
              'type' => 'success',
              'title' => 'Cliente y Bitacora ID activados',
              'text' => $emailSent ? 'El cliente fue convertido y recibio su acceso por correo.' : 'El cliente fue convertido. Comparte estas credenciales; la contrasena se muestra una sola vez.',
              'username' => $portalAccess['username'],
              'password' => $portalAccess['password'],
            ]
            : [
              'type' => 'success',
              'title' => 'Cliente convertido',
              'text' => 'El cliente fue convertido y ya tenia Bitacora ID activa.',
              'username' => $portalAccess['username'],
              'password' => null,
            ];
        } catch (Throwable $error) {
          crm_log_event('portal.conversion_activation_failed', [
            'client_id' => $clientId,
            'opportunity_id' => $opportunityId,
            'error_class' => get_class($error),
            'error_message' => substr($error->getMessage(), 0, 500),
          ]);
          $portalFlash = [
            'type' => 'error',
            'title' => 'Cliente convertido; acceso pendiente',
            'text' => 'La conversion se guardo, pero Bitacora ID no pudo activarse. Intenta nuevamente desde la ficha del cliente.',
          ];
        }
      }
    }

    $_SESSION['crm_flash'] = $portalFlash ?? [
      'type' => 'success',
      'title' => 'Cliente convertido',
      'text' => $client['name'] . ' ahora es cliente. Marca un proyecto como entregado para habilitar Bitacora ID.',
    ];
  }

  $redirect = ($_POST['return_to'] ?? '') === 'opportunity' && $opportunityId > 0
    ? crm_admin_url('opportunity', $opportunityId)
    : crm_admin_url('clients');
  header('Location: ' . $redirect);
  exit;
}
if (($_POST['action'] ?? '') === 'activate_portal_access') {
  crm_check_token();
  $opportunityId = max(0, (int) ($_POST['opportunity_id'] ?? 0));
  $returnTo = (string) ($_POST['return_to'] ?? 'bitacora');
  $opportunityStmt = $pdo->prepare('
    SELECT o.id, o.client_id, o.company_name, o.contact_email, o.status, c.lifecycle_stage
    FROM opportunities o
    LEFT JOIN clients c ON c.id = o.client_id
    WHERE o.id = ?
    LIMIT 1
  ');
  $opportunityStmt->execute([$opportunityId]);
  $portalOpportunity = $opportunityStmt->fetch();

  if (!$portalOpportunity) {
    $_SESSION['crm_flash'] = ['type' => 'error', 'title' => 'Proyecto no encontrado', 'text' => 'No fue posible localizar el proyecto para activar Bitacora ID.'];
  } elseif ((string) ($portalOpportunity['lifecycle_stage'] ?? '') !== 'Cliente') {
    $_SESSION['crm_flash'] = ['type' => 'error', 'title' => 'Cliente requerido', 'text' => 'Convierte primero el prospecto en cliente para habilitar su acceso.'];
  } elseif ((string) ($portalOpportunity['status'] ?? '') !== 'Proyecto entregado') {
    $_SESSION['crm_flash'] = ['type' => 'error', 'title' => 'Entrega requerida', 'text' => 'Bitacora ID solo se activa despues de marcar el proyecto como entregado.'];
  } else {
    try {
      $portalAccess = crm_enable_client_portal($pdo, $opportunityId);
      $emailSent = $portalAccess['created'] && !empty($portalAccess['password'])
        ? crm_send_portal_credentials($portalAccess['opportunity'], $portalAccess['username'], $portalAccess['password'])
        : false;
      $_SESSION['crm_flash'] = $portalAccess['created']
        ? [
          'type' => 'success',
          'title' => 'Bitacora ID activada',
          'text' => $emailSent ? 'El acceso se guardo y fue enviado al correo del cliente.' : 'El acceso se guardo. Comparte estas credenciales; la contrasena se muestra una sola vez.',
          'username' => $portalAccess['username'],
          'password' => $portalAccess['password'],
        ]
        : [
          'type' => 'info',
          'title' => 'Bitacora ID ya estaba activa',
          'text' => 'El proyecto ya tiene un usuario activo en el portal cliente.',
          'username' => $portalAccess['username'],
          'password' => null,
        ];
    } catch (Throwable $error) {
      crm_log_event('portal.manual_activation_failed', [
        'opportunity_id' => $opportunityId,
        'error_class' => get_class($error),
        'error_message' => substr($error->getMessage(), 0, 500),
      ]);
      $_SESSION['crm_flash'] = ['type' => 'error', 'title' => 'No se pudo activar Bitacora ID', 'text' => 'La activacion no pudo guardarse. Revisa error_log para consultar el detalle tecnico.'];
    }
  }

  $redirect = $returnTo === 'opportunity'
    ? crm_admin_url('opportunity', $opportunityId)
    : ($returnTo === 'clients' ? crm_admin_url('clients') : crm_admin_url('bitacora'));
  header('Location: ' . $redirect);
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
  header('Location: ' . crm_admin_url('bitacora'));
  exit;
}
if (($_POST['action'] ?? '') === 'sync_portal_access') {
  crm_check_token();
  $pendingStmt = $pdo->query('
    SELECT o.id
    FROM opportunities o
    JOIN clients c ON c.id = o.client_id
    LEFT JOIN client_portal_users cpu ON cpu.opportunity_id = o.id
    WHERE c.lifecycle_stage = "Cliente"
      AND o.status = "Proyecto entregado"
      AND cpu.id IS NULL
    ORDER BY o.updated_at DESC, o.created_at DESC
  ');
  $created = [];
  $syncErrors = [];
  foreach ($pendingStmt->fetchAll() as $row) {
    try {
      $portal = crm_enable_client_portal($pdo, (int) $row['id']);
      $created[] = $portal;
      if (!empty($portal['password'])) {
        crm_send_portal_credentials($portal['opportunity'], $portal['username'], $portal['password']);
      }
    } catch (Throwable $error) {
      error_log('CRM portal sync failed for opportunity ' . (int) $row['id'] . ': ' . $error->getMessage());
      $syncErrors[] = 'Oportunidad ' . (int) $row['id'] . ': ' . $error->getMessage();
    }
  }

  $credentials = [];
  foreach ($created as $portal) {
    if (!empty($portal['username']) && !empty($portal['password'])) {
      $credentials[] = [
        'company' => $portal['opportunity']['company_name'] ?? 'Cliente',
        'service' => $portal['opportunity']['service'] ?? 'Proyecto',
        'username' => $portal['username'],
        'password' => $portal['password'],
      ];
    }
  }

  $lastPortal = count($created) === 1 ? $created[0] : null;
  $_SESSION['crm_flash'] = [
    'type' => $syncErrors ? 'error' : 'success',
    'title' => $syncErrors ? 'Bitacoras con errores' : 'Bitacoras pendientes activadas',
    'text' => count($created) . ' acceso(s) persistidos en client_portal_users.' . ($syncErrors ? ' Errores: ' . implode(' | ', $syncErrors) : ''),
    'username' => $lastPortal['username'] ?? null,
    'password' => $lastPortal['password'] ?? null,
    'credentials' => $credentials,
  ];
  header('Location: ' . crm_admin_url('bitacora'));
  exit;
}

if (($_POST['action'] ?? '') === 'update_client_request') {
  crm_check_token();
  $requestId = (int) ($_POST['request_id'] ?? 0);
  $status = trim((string) ($_POST['status'] ?? 'Recibida'));
  $priority = trim((string) ($_POST['priority'] ?? 'Media'));
  $dueDate = trim((string) ($_POST['due_date'] ?? ''));
  $scheduledDate = trim((string) ($_POST['scheduled_date'] ?? ''));
  $assignedTo = trim((string) ($_POST['assigned_to'] ?? ''));
  $adminResponse = trim((string) ($_POST['admin_response'] ?? ''));
  $internalNotes = trim((string) ($_POST['internal_notes'] ?? ''));
  if (!in_array($status, $requestStatuses, true)) {
    $status = 'Recibida';
  }
  if (!in_array($priority, $requestPriorities, true)) {
    $priority = 'Media';
  }
  if ($dueDate === '') {
    $dueDate = crm_request_due_date($priority);
  }
  if ($scheduledDate === '') {
    $scheduledDate = null;
  }

  $requestStmt = $pdo->prepare('SELECT cr.*, o.company_name FROM client_requests cr JOIN opportunities o ON o.id = cr.opportunity_id WHERE cr.id = ? LIMIT 1');
  $requestStmt->execute([$requestId]);
  $request = $requestStmt->fetch();
  if ($request) {
    $wasFinal = crm_request_is_final(trim((string) ($request['status'] ?? '')));
    $isFinal = crm_request_is_final($status);
    $resolvedAt = $isFinal ? (($wasFinal && !empty($request['resolved_at'])) ? $request['resolved_at'] : date('Y-m-d H:i:s')) : null;

    $previousStatus = trim((string) ($request['status'] ?? 'Recibida')) ?: 'Recibida';
    $previousPriority = trim((string) ($request['priority'] ?? 'Media')) ?: 'Media';
    $previousDueDate = trim((string) ($request['due_date'] ?? ''));
    $previousScheduledDate = trim((string) ($request['scheduled_date'] ?? ''));
    $previousAssignedTo = trim((string) ($request['assigned_to'] ?? ''));
    $previousAdminResponse = trim((string) ($request['admin_response'] ?? ''));

    $update = $pdo->prepare('UPDATE client_requests SET status = ?, priority = ?, due_date = ?, scheduled_date = ?, assigned_to = ?, admin_response = ?, internal_notes = ?, resolved_at = ?, last_admin_update_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
    $update->execute([
      $status,
      $priority,
      $dueDate,
      $scheduledDate,
      $assignedTo !== '' ? $assignedTo : null,
      $adminResponse !== '' ? $adminResponse : null,
      $internalNotes !== '' ? $internalNotes : null,
      $resolvedAt,
      $requestId,
    ]);

    $notes = $adminResponse !== '' ? $adminResponse : crm_request_next_step($status);
    if ($scheduledDate) {
      $notes .= ' Servicio programado: ' . $scheduledDate . '.';
    }
    if ($assignedTo !== '') {
      $notes .= ' Responsable: ' . $assignedTo . '.';
    }
    $log = $pdo->prepare('INSERT INTO maintenance_logs (opportunity_id, portal_user_id, type, title, status, scheduled_date, notes, visible_to_client) VALUES (?, ?, ?, ?, ?, ?, ?, 1)');
    $log->execute([(int) $request['opportunity_id'], (int) $request['portal_user_id'], crm_request_log_type($status), 'Seguimiento: ' . $request['title'], $status, $scheduledDate ?: date('Y-m-d'), $notes]);

    $clientNotificationChanged = $status !== $previousStatus
      || $priority !== $previousPriority
      || $dueDate !== $previousDueDate
      || (string) ($scheduledDate ?? '') !== $previousScheduledDate
      || $assignedTo !== $previousAssignedTo
      || ($adminResponse !== '' && $adminResponse !== $previousAdminResponse);

    if ($clientNotificationChanged) {
      $eventType = 'report_updated';
      $notificationTitle = 'Seguimiento actualizado';
      $notificationMessage = 'ID Industrial actualizo tu reporte "' . $request['title'] . '".';
      if (crm_request_is_final($status)) {
        $eventType = 'report_resolved';
        $notificationTitle = 'Reporte resuelto';
        $notificationMessage = 'Tu reporte "' . $request['title'] . '" fue marcado como ' . $status . '.';
      } elseif ($status === 'Programada' || ((string) ($scheduledDate ?? '') !== '' && (string) ($scheduledDate ?? '') !== $previousScheduledDate)) {
        $eventType = 'report_scheduled';
        $notificationTitle = 'Atencion programada';
        $notificationMessage = 'ID Industrial programo atencion para tu reporte "' . $request['title'] . '".';
      } elseif ($adminResponse !== '' && $adminResponse !== $previousAdminResponse) {
        $notificationTitle = 'Nueva respuesta de ID Industrial';
        $notificationMessage = 'Hay una nueva respuesta para tu reporte "' . $request['title'] . '".';
      }

      crm_create_notification($pdo, [
        'recipient_type' => 'client',
        'portal_user_id' => (int) $request['portal_user_id'],
        'opportunity_id' => (int) $request['opportunity_id'],
        'client_request_id' => $requestId,
        'event_type' => $eventType,
        'title' => $notificationTitle,
        'message' => $notificationMessage,
        'target_url' => crm_portal_url('solicitudes', (int) $request['opportunity_id'], [], 'request-' . $requestId),
      ]);
    }

    $_SESSION['crm_flash'] = [
      'type' => 'success',
      'title' => 'Seguimiento actualizado',
      'text' => 'La solicitud quedo persistida con SLA, responsable y bitacora visible para el cliente.',
    ];
  }
  header('Location: ' . crm_admin_url('bitacora'));
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
  header('Location: ' . crm_admin_url('quote', $quoteId));
  exit;
}
if (($_POST['action'] ?? '') === 'update_quote') {
  crm_check_token();
  $quoteId = max(0, (int) ($_POST['quote_id'] ?? 0));
  $status = trim((string) ($_POST['status'] ?? 'En elaboracion')) ?: 'En elaboracion';
  if (!in_array($status, $quoteStatuses, true)) {
    $status = 'En elaboracion';
  }
  $amount = max(0, (float) ($_POST['amount'] ?? 0));
  $probability = max(0, min(100, (int) ($_POST['probability'] ?? 40)));
  $validUntil = trim((string) ($_POST['valid_until'] ?? '')) ?: null;
  $terms = trim((string) ($_POST['terms'] ?? ''));
  $visibleToClient = isset($_POST['visible_to_client']) ? 1 : 0;

  $beforeStmt = $pdo->prepare('
    SELECT q.*, o.client_id, o.company_name, o.service
    FROM quotes q
    JOIN opportunities o ON o.id = q.opportunity_id
    WHERE q.id = ?
    LIMIT 1
  ');
  $beforeStmt->execute([$quoteId]);
  $before = $beforeStmt->fetch();

  if (!$before) {
    $_SESSION['crm_flash'] = ['type' => 'error', 'title' => 'Cotizacion no encontrada', 'text' => 'El registro solicitado ya no existe.'];
    header('Location: ' . crm_admin_url('quotes'));
    exit;
  }

  try {
    $proposal = crm_store_quote_proposal($_FILES['proposal_file'] ?? null);
  } catch (RuntimeException $error) {
    $_SESSION['crm_flash'] = ['type' => 'error', 'title' => 'PDF no valido', 'text' => $error->getMessage()];
    header('Location: ' . crm_admin_url('quote', $quoteId));
    exit;
  }

  $proposalPath = $proposal['path'] ?: ($before['proposal_path'] ?? null);
  $proposalName = $proposal['name'] ?: ($before['proposal_original_name'] ?? null);
  $proposalMime = $proposal['mime'] ?: ($before['proposal_mime'] ?? null);
  $proposalSize = $proposal['size'] ?: ($before['proposal_size'] ?? null);

  if ($validUntil !== null && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $validUntil) || strtotime($validUntil) === false || date('Y-m-d', (int) strtotime($validUntil)) !== $validUntil)) {
    crm_delete_quote_attachment($proposal['path']);
    $_SESSION['crm_flash'] = ['type' => 'error', 'title' => 'Vigencia no valida', 'text' => 'Selecciona una fecha de vigencia valida.'];
  } elseif ($visibleToClient && $amount <= 0) {
    crm_delete_quote_attachment($proposal['path']);
    $_SESSION['crm_flash'] = ['type' => 'error', 'title' => 'Monto requerido', 'text' => 'Define un monto mayor a cero antes de publicar la propuesta.'];
  } elseif ($visibleToClient && !$validUntil) {
    crm_delete_quote_attachment($proposal['path']);
    $_SESSION['crm_flash'] = ['type' => 'error', 'title' => 'Vigencia requerida', 'text' => 'Indica la vigencia de la propuesta antes de publicarla.'];
  } elseif ($visibleToClient && strlen($terms) < 20) {
    crm_delete_quote_attachment($proposal['path']);
    $_SESSION['crm_flash'] = ['type' => 'error', 'title' => 'Condiciones requeridas', 'text' => 'Agrega al menos 20 caracteres de condiciones comerciales.'];
  } elseif ($visibleToClient && !$proposalPath) {
    $_SESSION['crm_flash'] = ['type' => 'error', 'title' => 'PDF requerido', 'text' => 'Adjunta el PDF formal antes de publicar la propuesta al cliente.'];
  } else {
    try {
      $pdo->beginTransaction();
      $proposalChanged = $amount !== (float) $before['amount']
        || $validUntil !== ($before['valid_until'] ?: null)
        || $terms !== trim((string) ($before['terms'] ?? ''))
        || !empty($proposal['path']);
      $effectiveStatus = $visibleToClient && !in_array($status, ['Aprobada', 'Perdida'], true) ? 'Enviada' : $status;
      $clientStatus = $proposalChanged && (string) ($before['client_status'] ?? '') === 'Cambios solicitados'
        ? 'Pendiente'
        : (string) ($before['client_status'] ?? 'Pendiente');

      $stmt = $pdo->prepare('
        UPDATE quotes SET
          amount = ?, status = ?, probability = ?, valid_until = ?, terms = ?,
          visible_to_client = ?, published_at = CASE WHEN ? = 1 THEN COALESCE(published_at, CURRENT_TIMESTAMP) ELSE published_at END,
          sent_at = CASE WHEN ? = 1 THEN COALESCE(sent_at, ?) ELSE sent_at END,
          client_status = ?, proposal_path = ?, proposal_original_name = ?,
          proposal_mime = ?, proposal_size = ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
      ');
      $stmt->execute([
        $amount,
        $effectiveStatus,
        $probability,
        $validUntil,
        $terms ?: null,
        $visibleToClient,
        $visibleToClient,
        $visibleToClient,
        date('Y-m-d'),
        $clientStatus,
        $proposalPath,
        $proposalName,
        $proposalMime,
        $proposalSize,
        $quoteId,
      ]);

      if ($effectiveStatus === 'Aprobada') {
        $pdo->prepare('UPDATE opportunities SET status = "Proyecto ganado", updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([(int) $before['opportunity_id']]);
      } elseif ($effectiveStatus === 'Perdida') {
        $pdo->prepare('UPDATE opportunities SET status = "Proyecto perdido", updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([(int) $before['opportunity_id']]);
      } elseif ($visibleToClient) {
        $pdo->prepare('UPDATE opportunities SET status = "Cotizacion enviada", updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([(int) $before['opportunity_id']]);
      }

      $activity = $pdo->prepare('INSERT INTO activities (opportunity_id, type, summary, due_date) VALUES (?, "Cotizacion", ?, ?)');
      $activity->execute([(int) $before['opportunity_id'], $visibleToClient ? 'Propuesta publicada al cliente: ' . $before['quote_code'] : 'Cotizacion actualizada: ' . $before['quote_code'], $validUntil]);

      $shouldNotify = $visibleToClient && ((int) ($before['visible_to_client'] ?? 0) === 0 || $proposalChanged);
      if ($shouldNotify) {
        $recipientStmt = $pdo->prepare('
          SELECT cpu.id, cpu.opportunity_id
          FROM client_portal_users cpu
          WHERE cpu.is_active = 1 AND (cpu.opportunity_id = ? OR cpu.id = ? OR cpu.client_id = ?)
          ORDER BY CASE WHEN cpu.opportunity_id = ? THEN 0 WHEN cpu.id = ? THEN 1 ELSE 2 END, cpu.updated_at DESC, cpu.id DESC
          LIMIT 1
        ');
        $requestedBy = (int) ($before['requested_by_portal_user_id'] ?? 0);
        $recipientStmt->execute([(int) $before['opportunity_id'], $requestedBy, (int) $before['client_id'], (int) $before['opportunity_id'], $requestedBy]);
        $recipient = $recipientStmt->fetch();
        if ($recipient) {
          crm_create_notification($pdo, [
            'recipient_type' => 'client',
            'portal_user_id' => (int) $recipient['id'],
            'opportunity_id' => (int) $before['opportunity_id'],
            'event_type' => 'quote_published',
            'title' => 'Cotizacion disponible',
            'message' => 'La propuesta ' . $before['quote_code'] . ' para ' . $before['service'] . ' ya esta disponible.',
            'target_url' => crm_portal_url('cotizaciones', (int) $before['opportunity_id'], [], 'quote-' . $quoteId),
          ]);
        } else {
          crm_log_event('admin_quote.notification_recipient_missing', [
            'quote_id' => $quoteId,
            'opportunity_id' => (int) $before['opportunity_id'],
            'client_id' => (int) $before['client_id'],
          ]);
        }
      }

      $pdo->commit();
      if (!empty($proposal['path']) && !empty($before['proposal_path'])) {
        crm_delete_quote_attachment((string) $before['proposal_path']);
      }
      $_SESSION['crm_flash'] = [
        'type' => 'success',
        'title' => $visibleToClient ? 'Propuesta publicada' : 'Cotizacion actualizada',
        'text' => $visibleToClient ? 'El cliente ya puede consultar el monto, las condiciones y el PDF.' : 'Los cambios quedaron guardados como borrador interno.',
      ];
    } catch (Throwable $error) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      crm_delete_quote_attachment($proposal['path']);
      crm_log_event('admin_quote.update_failed', [
        'quote_id' => $quoteId,
        'error_class' => get_class($error),
        'error_message' => substr($error->getMessage(), 0, 500),
      ]);
      $_SESSION['crm_flash'] = ['type' => 'error', 'title' => 'No se pudo guardar', 'text' => 'MySQL rechazo la actualizacion de la cotizacion.'];
    }
  }

  $redirect = ($_POST['return_to'] ?? '') === 'quote' ? crm_admin_url('quote', $quoteId) : crm_admin_url('quotes');
  header('Location: ' . $redirect);
  exit;
}
$view = $_GET['view'] ?? 'dashboard';
$flash = $_SESSION['crm_flash'] ?? null;
unset($_SESSION['crm_flash']);
$token = crm_token();
$quoteRequestAdminEmail = crm_quote_request_admin_email($pdo, 'tecnologia@idindustrial.com.mx');
$quoteRequestSecondaryEmail = crm_quote_request_secondary_email($pdo);
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
$opportunityFilter = (string) ($_GET['filter'] ?? 'all');
$opportunityAllowedFilters = ['all', 'new', 'today', 'quote', 'started', 'delivered'];
if (!in_array($opportunityFilter, $opportunityAllowedFilters, true)) {
  $opportunityFilter = 'all';
}
$opportunitySearch = trim((string) ($_GET['q'] ?? ''));
$opportunityWhere = [];
$opportunityParams = [];
if ($opportunityFilter === 'new') {
  $opportunityWhere[] = 'o.status = ?';
  $opportunityParams[] = 'Nueva solicitud';
} elseif ($opportunityFilter === 'today') {
  $opportunityWhere[] = '(o.next_action_date IS NOT NULL AND o.next_action_date <= ? AND o.status NOT IN ("Proyecto perdido", "Proyecto entregado"))';
  $opportunityParams[] = date('Y-m-d');
} elseif ($opportunityFilter === 'quote') {
  $opportunityWhere[] = '(o.status IN ("Cotizacion enviada", "Ingenieria en desarrollo", "Seguimiento") OR EXISTS (SELECT 1 FROM quotes qf WHERE qf.opportunity_id = o.id AND qf.status NOT IN ("Aprobada", "Perdida")))';
} elseif ($opportunityFilter === 'started') {
  $opportunityWhere[] = 'o.status = ?';
  $opportunityParams[] = 'Proyecto iniciado';
} elseif ($opportunityFilter === 'delivered') {
  $opportunityWhere[] = 'o.status = ?';
  $opportunityParams[] = 'Proyecto entregado';
}
if ($opportunitySearch !== '') {
  $opportunityWhere[] = '(o.company_name LIKE ? OR o.contact_name LIKE ? OR o.contact_email LIKE ? OR o.contact_phone LIKE ? OR o.service LIKE ?)';
  $searchTerm = '%' . $opportunitySearch . '%';
  array_push($opportunityParams, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
}
$opportunitySql = '
  SELECT o.*, cpu.username AS portal_username, cpu.is_active AS portal_active
  FROM opportunities o
  LEFT JOIN client_portal_users cpu ON cpu.opportunity_id = o.id
';
if ($opportunityWhere) {
  $opportunitySql .= ' WHERE ' . implode(' AND ', $opportunityWhere);
}
$opportunitySql .= '
  ORDER BY CASE WHEN o.source = "Formulario web" AND o.status = "Nueva solicitud" THEN 0 ELSE 1 END, o.updated_at DESC, o.created_at DESC
';
$opportunityStmt = $pdo->prepare($opportunitySql);
$opportunityStmt->execute($opportunityParams);
$opportunities = $opportunityStmt->fetchAll();
$selectedOpportunity = null;
$selectedOpportunityQuotes = [];
$selectedOpportunityActivities = [];
$selectedOpportunityAttachments = [];
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
    $attachmentStmt = $pdo->prepare('SELECT id, original_name, mime, size, created_at FROM opportunity_attachments WHERE opportunity_id = ? ORDER BY created_at ASC, id ASC');
    $attachmentStmt->execute([(int) $selectedOpportunity['id']]);
    $selectedOpportunityAttachments = $attachmentStmt->fetchAll();
  }
}
$quotes = $pdo->query('
  SELECT q.*, o.company_name, o.service, o.source, o.status AS opportunity_status
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
$clientRecords = $pdo->query('
  SELECT c.*,
    COALESCE(SUM(CASE WHEN o.status IN ("Proyecto iniciado", "Proyecto entregado") THEN 1 ELSE 0 END), 0) AS started_projects,
    COALESCE(SUM(CASE WHEN o.status = "Proyecto entregado" THEN 1 ELSE 0 END), 0) AS delivered_projects,
    MAX(o.updated_at) AS last_project_update,
    (SELECT COUNT(*) FROM client_portal_users cpu2 WHERE cpu2.client_id = c.id AND cpu2.is_active = 1) AS portal_access_count,
    (SELECT o2.id FROM opportunities o2 WHERE o2.client_id = c.id AND o2.status = "Proyecto entregado" ORDER BY o2.updated_at DESC, o2.id DESC LIMIT 1) AS access_opportunity_id
  FROM clients c
  LEFT JOIN opportunities o ON o.client_id = c.id
  GROUP BY c.id
  ORDER BY c.created_at DESC, c.name
')->fetchAll();
$clients = array_values(array_filter($clientRecords, static fn(array $client): bool => (string) ($client['lifecycle_stage'] ?? 'Prospecto') === 'Cliente'));
$prospects = array_values(array_filter($clientRecords, static fn(array $client): bool => (string) ($client['lifecycle_stage'] ?? 'Prospecto') === 'Prospecto'));
$portalUsers = $pdo->query('
  SELECT cpu.*, o.company_name, o.contact_name, o.contact_email, o.service, o.status AS opportunity_status
  FROM client_portal_users cpu
  JOIN opportunities o ON o.id = cpu.opportunity_id
  ORDER BY cpu.updated_at DESC, cpu.created_at DESC
')->fetchAll();
$missingPortalOpportunities = $pdo->query('
  SELECT o.id, o.company_name, o.contact_name, o.contact_email, o.service, o.status
  FROM opportunities o
  JOIN clients c ON c.id = o.client_id
  LEFT JOIN client_portal_users cpu ON cpu.opportunity_id = o.id
  WHERE c.lifecycle_stage = "Cliente"
    AND o.status = "Proyecto entregado"
    AND cpu.id IS NULL
  ORDER BY o.updated_at DESC, o.created_at DESC
')->fetchAll();
$missingPortalCount = count($missingPortalOpportunities);
$clientRequests = $pdo->query('
  SELECT cr.*, o.company_name, o.service, cpu.username
  FROM client_requests cr
  JOIN opportunities o ON o.id = cr.opportunity_id
  JOIN client_portal_users cpu ON cpu.id = cr.portal_user_id
  ORDER BY cr.created_at DESC
')->fetchAll();
usort($clientRequests, static function (array $a, array $b): int {
  $priorityA = trim((string) ($a['priority'] ?? 'Media')) ?: 'Media';
  $priorityB = trim((string) ($b['priority'] ?? 'Media')) ?: 'Media';
  $rankCompare = crm_report_priority_rank($priorityA) <=> crm_report_priority_rank($priorityB);
  if ($rankCompare !== 0) {
    return $rankCompare;
  }
  $statusA = trim((string) ($a['status'] ?? 'Recibida')) ?: 'Recibida';
  $statusB = trim((string) ($b['status'] ?? 'Recibida')) ?: 'Recibida';
  $dueA = trim((string) ($a['due_date'] ?? '')) ?: crm_request_due_date($priorityA, (string) ($a['created_at'] ?? 'now'));
  $dueB = trim((string) ($b['due_date'] ?? '')) ?: crm_request_due_date($priorityB, (string) ($b['created_at'] ?? 'now'));
  $overdueA = !crm_request_is_final($statusA) && $dueA !== '' && $dueA < date('Y-m-d');
  $overdueB = !crm_request_is_final($statusB) && $dueB !== '' && $dueB < date('Y-m-d');
  if ($overdueA !== $overdueB) {
    return $overdueA ? -1 : 1;
  }
  $dueCompare = strcmp($dueA ?: '9999-12-31', $dueB ?: '9999-12-31');
  if ($dueCompare !== 0) {
    return $dueCompare;
  }
  return (strtotime((string) ($b['created_at'] ?? 'now')) ?: 0) <=> (strtotime((string) ($a['created_at'] ?? 'now')) ?: 0);
});
if ($view === 'bitacora' && isset($_GET['notifications'])) {
  crm_mark_all_notifications_read($pdo, 'admin');
}
$adminUnreadNotifications = crm_unread_notification_count($pdo, 'admin');
$adminNotifications = crm_recent_notifications($pdo, 'admin', null, 12);
$requestStatusTotals = array_fill_keys($requestStatuses, 0);
$requestPriorityTotals = array_fill_keys($requestPriorities, 0);
$requestMonthlyTotals = [];
$maintenanceMetrics = [
  'total' => count($clientRequests),
  'open' => 0,
  'urgent' => 0,
  'new' => 0,
  'resolved' => 0,
  'answered' => 0,
  'unanswered' => 0,
  'scheduled' => 0,
  'overdue' => 0,
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
  if ($requestStatus === 'Recibida') {
    $maintenanceMetrics['new']++;
  }
  if (!crm_request_is_final($requestStatus) && trim((string) ($request['admin_response'] ?? '')) === '') {
    $maintenanceMetrics['unanswered']++;
  }

  if (!crm_request_is_final($requestStatus)) {
    $maintenanceMetrics['open']++;
    $dueDate = trim((string) ($request['due_date'] ?? ''));
    if ($dueDate !== '' && $dueDate < date('Y-m-d')) {
      $maintenanceMetrics['overdue']++;
    }
  } else {
    $maintenanceMetrics['resolved']++;
  }
  if ($requestStatus === 'Programada' || trim((string) ($request['scheduled_date'] ?? '')) !== '') {
    $maintenanceMetrics['scheduled']++;
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
$maxStatusTotal = max(array_merge([1], array_values($requestStatusTotals)));
$maxPriorityTotal = max(array_merge([1], array_values($requestPriorityTotals)));
$maxMonthlyTotal = max(array_merge([1], array_values($requestMonthlyTotals)));
$pendingStmt = $pdo->prepare('SELECT COUNT(*) FROM activities WHERE completed_at IS NULL AND (due_date IS NULL OR due_date <= ?)');
$pendingStmt->execute([date('Y-m-d', strtotime('+2 days'))]);
$counts['pending'] = (int) $pendingStmt->fetchColumn();
$statusRows = $pdo->query('SELECT status, COUNT(*) AS total FROM opportunities GROUP BY status ORDER BY total DESC')->fetchAll();
$monthlyStart = date('Y-m-01', strtotime('-5 months'));
$monthlySql = crm_driver($pdo) === 'mysql'
  ? "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_key, COUNT(*) AS total FROM opportunities WHERE created_at >= ? GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY month_key"
  : "SELECT strftime('%Y-%m', created_at) AS month_key, COUNT(*) AS total FROM opportunities WHERE created_at >= ? GROUP BY strftime('%Y-%m', created_at) ORDER BY month_key";
$monthlyStmt = $pdo->prepare($monthlySql);
$monthlyStmt->execute([$monthlyStart]);
$monthlyTotals = [];
foreach ($monthlyStmt->fetchAll() as $row) {
  $monthlyTotals[(string) $row['month_key']] = (int) $row['total'];
}
$monthlyRows = [];
for ($monthsAgo = 5; $monthsAgo >= 0; $monthsAgo--) {
  $timestamp = strtotime('first day of -' . $monthsAgo . ' months');
  $monthKey = date('Y-m', $timestamp);
  $monthlyRows[] = [
    'month' => $monthKey,
    'label' => substr(crm_month_name(date('m', $timestamp)), 0, 3) . ' ' . date('Y', $timestamp),
    'total' => $monthlyTotals[$monthKey] ?? 0,
  ];
}
$calendarEvents = [];
$calendarAgenda = [];
$calendarInitialDate = date('Y-m-d');
$calendarSummary = ['total' => 0, 'pending' => 0, 'followup' => 0, 'completed' => 0];

if ($view === 'calendar') {
  $calendarPalette = [
    'pending' => ['background' => '#a63c32', 'border' => '#7f2821', 'text' => '#ffffff'],
    'followup' => ['background' => '#d9a514', 'border' => '#9d7400', 'text' => '#17130a'],
    'completed' => ['background' => '#247451', 'border' => '#18583c', 'text' => '#ffffff'],
  ];

  $addCalendarEvent = static function (
    string $recordType,
    int $recordId,
    string $recordLabel,
    string $reference,
    string $company,
    string $service,
    string $status,
    string $date,
    string $dateMeaning,
    string $eventKind,
    string $url,
    ?string $forcedState = null
  ) use (&$calendarEvents, &$calendarSummary, $calendarPalette): void {
    $date = substr(trim($date), 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
      || !checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
      return;
    }

    $state = $forcedState ?: crm_calendar_state($recordType, $status, $date);
    $colors = $calendarPalette[$state] ?? $calendarPalette['followup'];
    $referenceLabel = trim($reference) !== '' ? trim($reference) : $company;
    $title = $recordLabel . ' · ' . $referenceLabel;
    if ($company !== '' && $company !== $referenceLabel) {
      $title .= ' · ' . $company;
    }

    $calendarEvents[] = [
      'id' => $recordType . '-' . $recordId . '-' . $eventKind . '-' . $date,
      'title' => $title,
      'start' => $date,
      'allDay' => true,
      'url' => $url,
      'backgroundColor' => $colors['background'],
      'borderColor' => $colors['border'],
      'textColor' => $colors['text'],
      'classNames' => ['crm-calendar-event', 'crm-calendar-event--' . $state, 'crm-calendar-event--' . $recordType],
      'extendedProps' => [
        'recordType' => $recordType,
        'recordLabel' => $recordLabel,
        'reference' => $referenceLabel,
        'company' => $company,
        'service' => $service,
        'status' => $status,
        'state' => $state,
        'stateLabel' => crm_calendar_state_label($state),
        'dateLabel' => crm_calendar_date_label($date),
        'dateMeaning' => $dateMeaning,
        'url' => $url,
      ],
    ];
    $calendarSummary['total']++;
    $calendarSummary[$state]++;
  };

  $calendarQuoteRows = $pdo->query('
    SELECT q.id, q.quote_code, q.status, q.requested_date, q.valid_until, q.sent_at,
      o.company_name, o.service
    FROM quotes q
    JOIN opportunities o ON o.id = q.opportunity_id
    WHERE q.status <> "Perdida"
    ORDER BY q.updated_at DESC, q.created_at DESC
  ')->fetchAll();

  foreach ($calendarQuoteRows as $calendarQuote) {
    $quoteUrl = crm_admin_url('quote', (int) $calendarQuote['id']);
    $quoteCode = trim((string) ($calendarQuote['quote_code'] ?? '')) ?: 'Cotizacion #' . (int) $calendarQuote['id'];
    $company = trim((string) ($calendarQuote['company_name'] ?? '')) ?: 'Empresa por definir';
    $service = trim((string) ($calendarQuote['service'] ?? '')) ?: 'Servicio por definir';
    $status = trim((string) ($calendarQuote['status'] ?? '')) ?: 'Solicitud recibida';
    $addCalendarEvent('quote', (int) $calendarQuote['id'], 'Cotizacion', $quoteCode, $company, $service, $status, (string) ($calendarQuote['requested_date'] ?? ''), 'Fecha requerida por el cliente', 'requested', $quoteUrl);
    $addCalendarEvent('quote', (int) $calendarQuote['id'], 'Cotizacion', $quoteCode, $company, $service, $status, (string) ($calendarQuote['valid_until'] ?? ''), 'Fin de vigencia de la propuesta', 'validity', $quoteUrl);
    $addCalendarEvent('quote', (int) $calendarQuote['id'], 'Cotizacion', $quoteCode, $company, $service, $status, (string) ($calendarQuote['sent_at'] ?? ''), 'Fecha de envio al cliente', 'sent', $quoteUrl, 'completed');
  }

  $calendarProjectRows = $pdo->query('
    SELECT id, company_name, service, status, next_action_date, desired_execution_date, updated_at
    FROM opportunities
    WHERE status <> "Proyecto perdido"
    ORDER BY updated_at DESC, created_at DESC
  ')->fetchAll();

  foreach ($calendarProjectRows as $calendarProject) {
    $projectId = (int) $calendarProject['id'];
    $projectUrl = crm_admin_url('opportunity', $projectId);
    $company = trim((string) ($calendarProject['company_name'] ?? '')) ?: 'Empresa por definir';
    $service = trim((string) ($calendarProject['service'] ?? '')) ?: 'Servicio por definir';
    $status = trim((string) ($calendarProject['status'] ?? '')) ?: 'Nueva solicitud';
    $recordLabel = strpos($status, 'Proyecto ') === 0 ? 'Proyecto' : 'Oportunidad';
    $addCalendarEvent('project', $projectId, $recordLabel, $service, $company, $service, $status, (string) ($calendarProject['next_action_date'] ?? ''), 'Proximo seguimiento comercial u operativo', 'followup', $projectUrl);
    $addCalendarEvent('project', $projectId, $recordLabel, $service, $company, $service, $status, (string) ($calendarProject['desired_execution_date'] ?? ''), 'Ejecucion o entrega propuesta', 'delivery', $projectUrl);
    if ($status === 'Proyecto entregado' && empty($calendarProject['desired_execution_date'])) {
      $addCalendarEvent('project', $projectId, 'Proyecto', $service, $company, $service, $status, (string) ($calendarProject['updated_at'] ?? ''), 'Ultima actualizacion del proyecto entregado', 'completed', $projectUrl, 'completed');
    }
  }

  usort($calendarEvents, static fn(array $a, array $b): int => strcmp((string) $a['start'], (string) $b['start']));
  foreach ($calendarEvents as $calendarEvent) {
    if ((string) $calendarEvent['start'] >= date('Y-m-d')) {
      $calendarInitialDate = (string) $calendarEvent['start'];
      break;
    }
  }
  if ($calendarEvents && $calendarInitialDate === date('Y-m-d') && (string) end($calendarEvents)['start'] < date('Y-m-d')) {
    $calendarInitialDate = (string) end($calendarEvents)['start'];
  }
  reset($calendarEvents);

  $calendarAgenda = $calendarEvents;
  usort($calendarAgenda, static function (array $a, array $b): int {
    $today = date('Y-m-d');
    $aState = (string) ($a['extendedProps']['state'] ?? 'followup');
    $bState = (string) ($b['extendedProps']['state'] ?? 'followup');
    $aPast = (string) $a['start'] < $today;
    $bPast = (string) $b['start'] < $today;
    $aRank = $aPast && $aState === 'pending' ? 0 : ($aPast ? 2 : 1);
    $bRank = $bPast && $bState === 'pending' ? 0 : ($bPast ? 2 : 1);
    if ($aRank !== $bRank) {
      return $aRank <=> $bRank;
    }
    return $aRank === 1
      ? strcmp((string) $a['start'], (string) $b['start'])
      : strcmp((string) $b['start'], (string) $a['start']);
  });
  $calendarAgenda = array_slice($calendarAgenda, 0, 12);
}
$services = ['Cableado estructurado', 'CCTV industrial', 'Control de accesos', 'HVAC industrial', 'Deteccion de incendios', 'Fibra optica', 'Subestaciones electricas', 'Mantenimiento', 'Smart Factories / IoT', 'Otro'];
?>
<!doctype html>
<html lang="es-MX">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>ID Industrial CRM</title>
  <link rel="stylesheet" href="<?php echo h(crm_public_url('assets/css/crm.css')); ?>">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js" defer></script>
  <script src="<?php echo h(crm_public_url('assets/js/crm-workspace.js')); ?>" defer></script>
  <?php if ($view === 'calendar'): ?>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.20/index.global.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.20/locales-all.global.min.js" defer></script>
    <script src="<?php echo h(crm_public_url('assets/js/crm-calendar.js')); ?>" defer></script>
  <?php endif; ?>
</head>
<body class="crm-app" data-notification-poll="<?php echo h(crm_admin_url('notification_poll')); ?>">
  <a class="crm-skip-link" href="#crm-main-content">Ir al contenido principal</a>
  <div class="crm-shell">
    <aside class="crm-sidebar" id="crm-sidebar">
      <div class="crm-brand">
        <img src="<?php echo h(crm_public_url('assets/img/logo-idindustrial-small.webp')); ?>" alt="ID Industrial" width="280" height="74">
        <div>
          <strong>ID CRM</strong>
          <span>Gestion comercial</span>
        </div>
      </div>
      <nav class="crm-nav" aria-label="Navegacion principal del CRM">
        <span class="crm-nav__label">Resumen</span>
        <a class="<?php echo $view === 'dashboard' ? 'is-active' : ''; ?>" href="<?php echo h(crm_admin_url()); ?>"><span class="crm-nav__item-icon"><?php echo crm_icon('reports'); ?></span><span>Dashboard</span></a>
        <span class="crm-nav__label">Ventas</span>
        <a class="<?php echo in_array($view, ['opportunities', 'opportunity'], true) ? 'is-active' : ''; ?>" href="<?php echo h(crm_admin_url('opportunities')); ?>"><span class="crm-nav__item-icon"><?php echo crm_icon('leads'); ?></span><span>Oportunidades</span></a>
        <a class="<?php echo in_array($view, ['quotes', 'quote'], true) ? 'is-active' : ''; ?>" href="<?php echo h(crm_admin_url('quotes')); ?>"><span class="crm-nav__item-icon"><?php echo crm_icon('money'); ?></span><span>Cotizaciones</span></a>
        <a class="<?php echo $view === 'calendar' ? 'is-active' : ''; ?>" href="<?php echo h(crm_admin_url('calendar')); ?>"><span class="crm-nav__item-icon"><?php echo crm_icon('calendar'); ?></span><span>Calendario</span></a>
        <span class="crm-nav__label">Cartera</span>
        <a class="<?php echo $view === 'prospects' ? 'is-active' : ''; ?>" href="<?php echo h(crm_admin_url('prospects')); ?>"><span class="crm-nav__item-icon"><?php echo crm_icon('prospects'); ?></span><span>Prospectos</span></a>
        <a class="<?php echo $view === 'clients' ? 'is-active' : ''; ?>" href="<?php echo h(crm_admin_url('clients')); ?>"><span class="crm-nav__item-icon"><?php echo crm_icon('clients'); ?></span><span>Clientes</span></a>
        <span class="crm-nav__label">Operacion</span>
        <a class="<?php echo $view === 'bitacora' && !isset($_GET['notifications']) ? 'is-active' : ''; ?>" href="<?php echo h(crm_admin_url('bitacora')); ?>"><span class="crm-nav__item-icon"><?php echo crm_icon('portal'); ?></span><span>Bitacora ID</span></a>
        <a class="<?php echo $view === 'bitacora' && isset($_GET['notifications']) ? 'is-active' : ''; ?>" href="<?php echo h(crm_admin_url('notifications', 0, [], 'reportes-recibidos')); ?>"><span class="crm-nav__item-icon"><?php echo crm_icon('bell'); ?></span><span>Notificaciones</span><em data-notification-count <?php echo $adminUnreadNotifications > 0 ? '' : 'hidden'; ?>><?php echo $adminUnreadNotifications; ?></em></a>
        <span class="crm-nav__label">Administracion</span>
        <a class="<?php echo $view === 'profile' ? 'is-active' : ''; ?>" href="<?php echo h(crm_admin_url('profile')); ?>"><span class="crm-nav__item-icon"><?php echo crm_icon('clients'); ?></span><span>Perfil</span></a>
        <a class="<?php echo $view === 'settings' ? 'is-active' : ''; ?>" href="<?php echo h(crm_admin_url('settings')); ?>"><span class="crm-nav__item-icon"><?php echo crm_icon('tasks'); ?></span><span>Configuracion</span></a>
        <span class="crm-nav__label">Sistemas</span>
        <a class="crm-nav__iot" href="<?php echo h(crm_build_path(crm_web_base_path(), 'iot')); ?>"><span class="crm-nav__item-icon"><?php echo crm_icon('iot'); ?></span><span>Monitoreo IoT</span></a>
        <a href="<?php echo h(crm_public_url()); ?>"><span class="crm-nav__item-icon"><?php echo crm_icon('open'); ?></span><span>Vista publica</span></a>
      </nav>
      <div class="crm-sidebar__footer">
        <strong><?php echo h($_SESSION['crm_user']['name']); ?></strong><br>
        <small><?php echo h($_SESSION['crm_user']['role']); ?></small><br><br>
        <a href="<?php echo h(crm_admin_url('logout')); ?>">Cerrar sesion</a>
      </div>
    </aside>

        <button class="crm-menu-overlay" type="button" aria-label="Cerrar menu" data-menu-close></button>

    <main class="crm-main" id="crm-main-content" tabindex="-1">
      <header class="crm-topbar">
        <button class="crm-menu-toggle" type="button" aria-label="Abrir menu" aria-controls="crm-sidebar" aria-expanded="false" data-menu-toggle>
          <span></span><span></span><span></span>
        </button>
        <div>
          <small>ID Industrial</small>
          <strong>CRM para servicios industriales</strong>
        </div>
        <div class="crm-topbar__actions">
          <span>Hola, <?php echo h($_SESSION['crm_user']['name']); ?></span>
        </div>
      </header>

      <section class="crm-content">
        <?php if ($flash && !in_array($view, ['profile', 'settings'], true)): ?>
          <div class="crm-flash crm-flash--<?php echo h($flash['type'] ?? 'info'); ?>">
            <div>
              <strong><?php echo h($flash['title'] ?? 'Aviso'); ?></strong>
              <p><?php echo h($flash['text'] ?? ''); ?></p>
              <?php if (!empty($flash['username'])): ?><code>Usuario: <?php echo h($flash['username']); ?></code><?php endif; ?>
              <?php if (!empty($flash['password'])): ?><code>Password: <?php echo h($flash['password']); ?></code><?php endif; ?>
              <?php if (!empty($flash['credentials']) && is_array($flash['credentials'])): ?>
                <div class="crm-credentials-list">
                  <?php foreach ($flash['credentials'] as $credential): ?>
                    <code><?php echo h(($credential['company'] ?? 'Cliente') . ' - ' . ($credential['service'] ?? 'Proyecto')); ?> | Usuario: <?php echo h($credential['username'] ?? ''); ?> | Password: <?php echo h($credential['password'] ?? ''); ?></code>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
            <?php if (!empty($flash['username'])): ?><a class="crm-button crm-button--ghost" href="<?php echo h(crm_portal_url()); ?>">Ver portal</a><?php endif; ?>
          </div>
        <?php endif; ?>
        <?php if ($view === 'dashboard'): ?>
          <div class="crm-head crm-dashboard-head">
            <div>
              <p class="eyebrow">Resumen general</p>
              <h1>Dashboard comercial</h1>
              <p>Seguimiento de solicitudes, levantamientos, ingenieria y cotizaciones.</p>
            </div>
            <a class="crm-button" href="<?php echo h(crm_admin_url('opportunities')); ?>">Ver oportunidades</a>
          </div>

          <div class="crm-kpis crm-dashboard-kpis">
            <article class="crm-card crm-kpi"><span class="crm-kpi__icon"><?php echo crm_icon('leads'); ?></span><div><span>Leads / oportunidades</span><strong><?php echo $counts['leads']; ?></strong></div></article>
            <article class="crm-card crm-kpi"><span class="crm-kpi__icon"><?php echo crm_icon('clients'); ?></span><div><span>Clientes</span><strong><?php echo $counts['clients']; ?></strong></div></article>
            <article class="crm-card crm-kpi"><span class="crm-kpi__icon"><?php echo crm_icon('prospects'); ?></span><div><span>Prospectos</span><strong><?php echo $counts['prospects']; ?></strong></div></article>
            <article class="crm-card crm-kpi"><span class="crm-kpi__icon"><?php echo crm_icon('tasks'); ?></span><div><span>Tareas proximas</span><strong><?php echo $counts['pending']; ?></strong></div></article>
            <article class="crm-card crm-kpi"><span class="crm-kpi__icon"><?php echo crm_icon('money'); ?></span><div><span>Cotizado activo</span><strong><?php echo crm_money($quoteTotal); ?></strong></div></article>
            <article class="crm-card crm-kpi"><span class="crm-kpi__icon"><?php echo crm_icon('portal'); ?></span><div><span>Bitacoras activas</span><strong><?php echo $counts['portal']; ?></strong></div></article>
          </div>

          <div class="crm-grid crm-dashboard-grid">
            <article class="crm-card crm-dashboard-card crm-chart-card">
              <div class="crm-chart-card__head">
                <div><h2>Nuevas oportunidades</h2><p>Tendencia mensual de registros comerciales.</p></div>
                <span class="crm-chart-total"><?php echo array_sum(array_column($monthlyRows, 'total')); ?><small>periodo</small></span>
              </div>
              <div class="crm-chart-stage">
                <canvas id="crm-opportunity-trend" role="img" aria-label="Grafica de oportunidades creadas por mes"></canvas>
              </div>
              <details class="crm-chart-data">
                <summary>Consultar datos de la grafica</summary>
                <table><thead><tr><th>Mes</th><th>Oportunidades</th></tr></thead><tbody><?php foreach ($monthlyRows as $row): ?><tr><td><?php echo h((string) $row['label']); ?></td><td><?php echo (int) $row['total']; ?></td></tr><?php endforeach; ?></tbody></table>
              </details>
            </article>
            <article class="crm-card crm-dashboard-card crm-chart-card">
              <?php $pipelineTotal = crm_chart_total($statusRows); ?>
              <div class="crm-chart-card__head">
                <div><h2>Pipeline comercial</h2><p>Volumen actual por etapa, de mayor a menor.</p></div>
                <span class="crm-chart-total"><?php echo $pipelineTotal; ?><small>oportunidades</small></span>
              </div>
              <div class="crm-chart-stage crm-chart-stage--pipeline">
                <canvas id="crm-pipeline-distribution" role="img" aria-label="Grafica de oportunidades por etapa comercial"></canvas>
              </div>
              <details class="crm-chart-data">
                <summary>Consultar datos de la grafica</summary>
                <table><thead><tr><th>Etapa</th><th>Total</th><th>Participacion</th></tr></thead><tbody><?php foreach ($statusRows as $row): ?><tr><td><?php echo h($row['status']); ?></td><td><?php echo (int) $row['total']; ?></td><td><?php echo crm_chart_percent((int) $row['total'], $pipelineTotal); ?>%</td></tr><?php endforeach; ?></tbody></table>
              </details>
            </article>
          </div>
          <script type="application/json" id="crm-dashboard-chart-data"><?php echo json_encode([
            'monthly' => array_map(static fn(array $row): array => ['label' => (string) $row['label'], 'value' => (int) $row['total']], $monthlyRows),
            'pipeline' => array_map(static fn(array $row): array => ['label' => (string) $row['status'], 'value' => (int) $row['total']], $statusRows),
          ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?></script>
        <?php elseif ($view === 'calendar'): ?>
          <div class="crm-head crm-calendar-head">
            <div>
              <p class="eyebrow">Planeacion comercial y operativa</p>
              <h1>Calendario de seguimiento</h1>
              <p>Consulta en un solo lugar los compromisos, fechas requeridas, vigencias, envios y entregas propuestas.</p>
            </div>
            <div class="crm-head__actions">
              <a class="crm-button crm-button--ghost" href="<?php echo h(crm_admin_url('opportunities')); ?>">Ver oportunidades</a>
              <a class="crm-button" href="<?php echo h(crm_admin_url('quotes')); ?>">Ver cotizaciones</a>
            </div>
          </div>

          <section class="crm-calendar-summary" aria-label="Resumen del calendario">
            <article class="crm-card crm-calendar-stat crm-calendar-stat--total"><span><?php echo crm_icon('calendar'); ?></span><div><small>Fechas visibles</small><strong><?php echo (int) $calendarSummary['total']; ?></strong><p>Hitos registrados</p></div></article>
            <article class="crm-card crm-calendar-stat crm-calendar-stat--pending"><span><?php echo crm_icon('overdue'); ?></span><div><small>Pendientes / vencidas</small><strong><?php echo (int) $calendarSummary['pending']; ?></strong><p>Requieren atencion</p></div></article>
            <article class="crm-card crm-calendar-stat crm-calendar-stat--followup"><span><?php echo crm_icon('scheduled'); ?></span><div><small>En seguimiento</small><strong><?php echo (int) $calendarSummary['followup']; ?></strong><p>Compromisos activos</p></div></article>
            <article class="crm-card crm-calendar-stat crm-calendar-stat--completed"><span><?php echo crm_icon('tasks'); ?></span><div><small>Enviadas / completadas</small><strong><?php echo (int) $calendarSummary['completed']; ?></strong><p>Hitos cumplidos</p></div></article>
          </section>

          <section class="crm-card crm-calendar-controls" aria-label="Filtros y significado de colores">
            <div class="crm-calendar-filters">
              <span>Mostrar</span>
              <div role="group" aria-label="Filtrar eventos del calendario">
                <button class="is-active" type="button" data-calendar-filter="all" aria-pressed="true">Todo</button>
                <button type="button" data-calendar-filter="quote" aria-pressed="false">Cotizaciones</button>
                <button type="button" data-calendar-filter="project" aria-pressed="false">Proyectos</button>
              </div>
            </div>
            <div class="crm-calendar-legend" aria-label="Estados del calendario">
              <span><i class="crm-calendar-dot crm-calendar-dot--pending"></i><strong>Rojo</strong> Pendiente o vencido</span>
              <span><i class="crm-calendar-dot crm-calendar-dot--followup"></i><strong>Amarillo</strong> En seguimiento</span>
              <span><i class="crm-calendar-dot crm-calendar-dot--completed"></i><strong>Verde</strong> Enviado o completado</span>
            </div>
          </section>

          <div class="crm-calendar-layout">
            <section class="crm-card crm-calendar-panel" aria-labelledby="crm-calendar-title">
              <div class="crm-calendar-panel__head">
                <div><p class="eyebrow">Vista mensual</p><h2 id="crm-calendar-title">Agenda de fechas</h2></div>
                <output data-calendar-visible-count aria-live="polite"><?php echo (int) $calendarSummary['total']; ?> fechas</output>
              </div>
              <div class="crm-calendar-root" id="crm-calendar" data-calendar-root aria-label="Calendario mensual de cotizaciones y proyectos">
                <p class="crm-calendar-loading" data-calendar-loading>Cargando vista del calendario...</p>
              </div>
              <noscript><p class="crm-calendar-notice">Activa JavaScript para navegar por meses y filtrar. La agenda lateral conserva enlaces directos a cada registro.</p></noscript>
            </section>

            <aside class="crm-card crm-calendar-agenda" aria-labelledby="crm-calendar-agenda-title">
              <header><div><p class="eyebrow">Prioridades</p><h2 id="crm-calendar-agenda-title">Agenda inmediata</h2></div><span>12 max.</span></header>
              <p>Primero aparecen los vencidos recientes; despues, las proximas fechas.</p>
              <div class="crm-calendar-agenda__list">
                <?php foreach ($calendarAgenda as $agendaEvent): ?>
                  <?php $agendaProps = $agendaEvent['extendedProps']; ?>
                  <a class="crm-calendar-agenda__item crm-calendar-agenda__item--<?php echo h($agendaProps['state']); ?>" href="<?php echo h($agendaProps['url']); ?>" data-calendar-event="<?php echo h($agendaEvent['id']); ?>">
                    <span class="crm-calendar-agenda__date"><strong><?php echo h(date('d', strtotime((string) $agendaEvent['start']))); ?></strong><small><?php echo h(strtoupper(substr(crm_month_name(date('m', strtotime((string) $agendaEvent['start']))), 0, 3))); ?></small></span>
                    <span class="crm-calendar-agenda__body"><small><?php echo h($agendaProps['recordLabel'] . ' · ' . $agendaProps['dateMeaning']); ?></small><strong><?php echo h($agendaProps['reference']); ?></strong><em><?php echo h($agendaProps['company']); ?></em><b><?php echo h($agendaProps['stateLabel']); ?></b></span>
                  </a>
                <?php endforeach; ?>
                <?php if (!$calendarAgenda): ?>
                  <div class="crm-calendar-empty"><span><?php echo crm_icon('calendar'); ?></span><strong>No hay fechas registradas</strong><p>Agrega una siguiente accion, fecha requerida, vigencia o entrega propuesta desde su registro.</p></div>
                <?php endif; ?>
              </div>
            </aside>
          </div>

          <dialog class="crm-calendar-dialog" id="crm-calendar-detail" aria-labelledby="crm-calendar-detail-title">
            <div class="crm-modal__surface">
              <header class="crm-modal__header">
                <div><p class="eyebrow" data-calendar-detail-kind>Detalle de fecha</p><h2 id="crm-calendar-detail-title" data-calendar-detail-title>Evento del calendario</h2></div>
                <button class="crm-modal__close" type="button" aria-label="Cerrar detalle" data-calendar-detail-close><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
              </header>
              <div class="crm-calendar-detail__status" data-calendar-detail-state>En seguimiento</div>
              <dl class="crm-calendar-detail__grid">
                <div><dt>Fecha</dt><dd data-calendar-detail-date>&mdash;</dd></div>
                <div><dt>Que representa</dt><dd data-calendar-detail-meaning>&mdash;</dd></div>
                <div><dt>Empresa</dt><dd data-calendar-detail-company>&mdash;</dd></div>
                <div><dt>Servicio</dt><dd data-calendar-detail-service>&mdash;</dd></div>
                <div><dt>Estatus del registro</dt><dd data-calendar-detail-status>&mdash;</dd></div>
              </dl>
              <footer class="crm-modal__footer"><p>Abre el registro para actualizar la fecha o cambiar su estatus.</p><a class="crm-button" href="<?php echo h(crm_admin_url()); ?>" data-calendar-detail-url>Abrir registro</a></footer>
            </div>
          </dialog>

          <script type="application/json" id="crm-calendar-data"><?php echo json_encode([
            'events' => $calendarEvents,
            'initialDate' => $calendarInitialDate,
          ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
        <?php elseif ($view === 'opportunities'): ?>
          <div class="crm-head">
            <div>
              <p class="eyebrow">Pipeline</p>
              <h1>Oportunidades</h1>
              <p>Gestiona el seguimiento comercial. Cada oportunidad conserva su contacto, servicio, prioridad y siguiente accion.</p>
            </div>
            <button class="crm-button" type="button" data-modal-open="crm-modal-opportunity">Nueva oportunidad</button>
          </div>

          <dialog class="crm-modal" id="crm-modal-opportunity" aria-labelledby="crm-modal-opportunity-title" aria-describedby="crm-modal-opportunity-description">
            <div class="crm-modal__surface">
              <header class="crm-modal__header">
                <div><p class="eyebrow">Nuevo registro</p><h2 id="crm-modal-opportunity-title">Crear oportunidad</h2><p id="crm-modal-opportunity-description">Registra primero el contacto y despues define el siguiente paso comercial.</p></div>
                <button class="crm-modal__close" type="button" aria-label="Cerrar formulario de oportunidad" data-modal-close>
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
              </header>
              <form class="crm-form crm-modal__form" method="post" data-modal-form>
                <input type="hidden" name="token" value="<?php echo h($token); ?>">
                <input type="hidden" name="action" value="create_opportunity">
                <input type="hidden" name="source" value="Captura manual">
                <fieldset class="crm-form-section">
                  <legend>Empresa y contacto</legend>
                  <div class="crm-form-grid">
                    <label class="crm-field" for="opportunity-company"><span>Empresa <b aria-hidden="true">*</b></span><input id="opportunity-company" name="company_name" autocomplete="organization" required><small>Razon social o nombre comercial con el que identificaras el proyecto.</small></label>
                    <label class="crm-field" for="opportunity-contact"><span>Contacto <b aria-hidden="true">*</b></span><input id="opportunity-contact" name="contact_name" autocomplete="name" required><small>Persona responsable de evaluar o autorizar el servicio.</small></label>
                    <label class="crm-field" for="opportunity-phone"><span>Telefono</span><input id="opportunity-phone" type="tel" name="contact_phone" autocomplete="tel"><small>Incluye lada; se usara para seguimiento comercial.</small></label>
                    <label class="crm-field" for="opportunity-email"><span>Correo</span><input id="opportunity-email" type="email" name="contact_email" autocomplete="email"><small>Correo donde se enviaran avances y propuestas.</small></label>
                  </div>
                </fieldset>
                <fieldset class="crm-form-section">
                  <legend>Seguimiento comercial</legend>
                  <div class="crm-form-grid">
                    <label class="crm-field" for="opportunity-service"><span>Servicio</span><select id="opportunity-service" name="service"><?php foreach ($services as $service): ?><option><?php echo h($service); ?></option><?php endforeach; ?></select><small>Linea de servicio principal solicitada por el prospecto.</small></label>
                    <label class="crm-field" for="opportunity-status"><span>Estatus</span><select id="opportunity-status" name="status"><?php foreach ($statuses as $status): ?><option><?php echo h($status); ?></option><?php endforeach; ?></select><small>Etapa actual del proceso; inicia en Nueva solicitud si aun no hubo contacto.</small></label>
                    <label class="crm-field" for="opportunity-priority"><span>Prioridad</span><select id="opportunity-priority" name="priority"><option>Alta</option><option selected>Media</option><option>Baja</option></select><small>Urgencia interna para ordenar el trabajo del equipo.</small></label>
                    <label class="crm-field" for="opportunity-value"><span>Valor estimado</span><input id="opportunity-value" type="number" name="estimated_value" min="0" step="1000" inputmode="decimal"><small>Monto preliminar esperado; puede actualizarse despues del levantamiento.</small></label>
                    <label class="crm-field" for="opportunity-next-action"><span>Siguiente accion</span><input id="opportunity-next-action" type="date" name="next_action_date" value="<?php echo h(date('Y-m-d', strtotime('+1 day'))); ?>"><small>Fecha compromiso para llamar, visitar, cotizar o dar seguimiento.</small></label>
                    <label class="crm-field crm-field--wide" for="opportunity-notes"><span>Notas</span><textarea id="opportunity-notes" name="notes" rows="4" placeholder="Necesidad, alcance inicial, restricciones y acuerdos."></textarea><small>Resume el contexto necesario para que cualquier integrante pueda continuar el seguimiento.</small></label>
                  </div>
                </fieldset>
                <footer class="crm-modal__footer">
                  <button class="crm-button crm-button--ghost" type="button" data-modal-close>Cancelar</button>
                  <button class="crm-button" type="submit">Guardar oportunidad</button>
                </footer>
              </form>
            </div>
          </dialog>
          <article class="crm-card crm-opportunity-tools">
            <div class="crm-filter-tabs" aria-label="Filtros rapidos de oportunidades">
              <?php $quickFilters = ['all' => 'Todas', 'new' => 'Nuevas', 'today' => 'Hoy / vencidas', 'quote' => 'Cotizacion', 'started' => 'Proyecto iniciado', 'delivered' => 'Proyecto entregado']; ?>
              <?php foreach ($quickFilters as $filterKey => $filterLabel): ?>
                <a class="<?php echo $opportunityFilter === $filterKey ? 'is-active' : ''; ?>" href="<?php echo h(crm_admin_url('opportunities', 0, ['filter' => $filterKey] + ($opportunitySearch !== '' ? ['q' => $opportunitySearch] : []))); ?>"><?php echo h($filterLabel); ?></a>
              <?php endforeach; ?>
            </div>
            <form class="crm-search-form" method="get" action="<?php echo h(crm_admin_url('opportunities')); ?>">
              <input type="hidden" name="filter" value="<?php echo h($opportunityFilter); ?>">
              <label class="crm-field">Buscar por empresa, contacto, correo, telefono o servicio
                <input name="q" value="<?php echo h($opportunitySearch); ?>" placeholder="Ej. HVAC, compras@empresa.com, 442...">
              </label>
              <button class="crm-button" type="submit">Buscar</button>
              <?php if ($opportunitySearch !== ''): ?><a class="crm-button crm-button--ghost" href="<?php echo h(crm_admin_url('opportunities', 0, ['filter' => $opportunityFilter])); ?>">Limpiar</a><?php endif; ?>
            </form>
          </article>

          <article class="crm-card">
            <h2>Seguimiento activo</h2>
            <div class="crm-table-wrap">
              <table class="crm-table crm-table--compact crm-table--opportunities">
                <thead><tr><th>Empresa</th><th>Contacto</th><th>Servicio</th><th>Siguiente accion</th><th>Prioridad</th><th>Estatus</th><th>Ver</th></tr></thead>
                <tbody>
                  <?php foreach ($opportunities as $opportunity): ?>
                    <?php $nextAction = trim((string) ($opportunity['next_action_date'] ?? '')); ?>
                    <tr>
                      <td><strong><?php echo h($opportunity['company_name']); ?></strong><br><small><?php echo h($opportunity['source']); ?> - <?php echo h(crm_opportunity_age_label((string) $opportunity['created_at'])); ?></small></td>
                      <td><?php echo h($opportunity['contact_name']); ?><br><small><?php echo h($opportunity['contact_phone'] ?: $opportunity['contact_email']); ?></small></td>
                      <td><?php echo h($opportunity['service']); ?></td>
                      <td><strong><?php echo h($nextAction ?: 'Sin fecha'); ?></strong><br><small><?php echo $nextAction !== '' && $nextAction <= date('Y-m-d') ? 'Atender hoy' : 'Programada'; ?></small></td>
                      <td><span class="crm-pill <?php echo h(crm_pill_class((string) $opportunity['priority'])); ?>"><?php echo h($opportunity['priority']); ?></span></td>
                      <td><span class="crm-pill <?php echo h(crm_pill_class((string) $opportunity['status'])); ?>"><?php echo h($opportunity['status']); ?></span></td>
                      <td><a class="crm-button crm-button--ghost" href="<?php echo h(crm_admin_url('opportunity', (int) $opportunity['id'])); ?>">Ver</a></td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if (!$opportunities): ?>
                    <tr><td colspan="7">No hay oportunidades con este filtro.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </article>
        <?php elseif ($view === 'opportunity'): ?>
          <?php if (!$selectedOpportunity): ?>
            <div class="crm-head"><div><p class="eyebrow">Pipeline</p><h1>Oportunidad no encontrada</h1><p>El registro solicitado no existe o fue eliminado.</p></div><a class="crm-button crm-button--ghost" href="<?php echo h(crm_admin_url('opportunities')); ?>">Volver</a></div>
          <?php else: ?>
            <div class="crm-head">
              <div>
                <p class="eyebrow">Detalle de oportunidad</p>
                <h1><?php echo h($selectedOpportunity['company_name']); ?></h1>
                <p><?php echo h($selectedOpportunity['service']); ?> - <?php echo h($selectedOpportunity['contact_name']); ?></p>
              </div>
              <a class="crm-button crm-button--ghost" href="<?php echo h(crm_admin_url('opportunities')); ?>">Volver</a>
            </div>

            <div class="crm-kpis crm-kpis--secondary">
              <article class="crm-card"><h2>Estatus</h2><p><span class="crm-pill <?php echo h(crm_pill_class((string) $selectedOpportunity['status'])); ?>"><?php echo h($selectedOpportunity['status']); ?></span></p></article>
              <article class="crm-card"><h2>Valor</h2><p><?php echo crm_money($selectedOpportunity['estimated_value']); ?></p></article>
              <article class="crm-card"><h2>Siguiente accion</h2><p><?php echo h($selectedOpportunity['next_action_date'] ?: 'Sin fecha'); ?></p></article>
              <article class="crm-card"><h2>Bitacora ID</h2><p><?php if (!empty($selectedOpportunity['portal_username']) && (int) ($selectedOpportunity['portal_active'] ?? 0) === 1): ?><span class="crm-pill crm-pill--success">Activa</span><?php elseif (($selectedOpportunity['lifecycle_stage'] ?? '') === 'Cliente'): ?><span class="crm-pill crm-pill--warning">Pendiente de activar</span><?php else: ?><span class="crm-pill crm-pill--neutral">Cliente requerido</span><?php endif; ?></p></article>
            </div>

            <div class="crm-grid">
              <article class="crm-card">
                <div class="crm-section-head">
                  <h2>Datos del contacto</h2>
                  <span class="crm-pill <?php echo ($selectedOpportunity['lifecycle_stage'] ?? 'Prospecto') === 'Cliente' ? 'crm-pill--success' : 'crm-pill--warning'; ?>"><?php echo h($selectedOpportunity['lifecycle_stage'] ?: 'Prospecto'); ?></span>
                </div>
                <div class="crm-list crm-list--compact">
                  <div class="crm-list__item"><strong>Empresa</strong><span><?php echo h($selectedOpportunity['company_name']); ?></span></div>
                  <div class="crm-list__item"><strong>Contacto</strong><span><?php echo h($selectedOpportunity['contact_name']); ?></span></div>
                  <div class="crm-list__item"><strong>Correo</strong><span><?php echo h($selectedOpportunity['contact_email'] ?: 'Sin correo'); ?></span></div>
                  <div class="crm-list__item"><strong>Teléfono WhatsApp</strong><span><?php echo h($selectedOpportunity['contact_phone'] ?: 'Sin teléfono'); ?></span></div>
                  <div class="crm-list__item"><strong>Tipo de solicitud</strong><span><?php echo h($selectedOpportunity['request_type'] ?: 'Sin especificar'); ?></span></div>
                  <div class="crm-list__item"><strong>Locación del proyecto</strong><span><?php echo h($selectedOpportunity['project_location'] ?: 'Sin especificar'); ?></span></div>
                  <div class="crm-list__item"><strong>Fecha deseada</strong><span><?php echo h($selectedOpportunity['desired_execution_date'] ?: 'Sin fecha'); ?></span></div>
                  <div class="crm-list__item"><strong>Origen</strong><span><?php echo h($selectedOpportunity['source']); ?></span></div>
                </div>
                <div class="crm-section-head">
                  <h2>Archivos del proyecto</h2>
                  <span class="crm-pill crm-pill--neutral"><?php echo count($selectedOpportunityAttachments); ?></span>
                </div>
                <div class="crm-list crm-list--compact">
                  <?php foreach ($selectedOpportunityAttachments as $attachment): ?>
                    <div class="crm-list__item">
                      <strong><?php echo h($attachment['original_name']); ?></strong>
                      <small><?php echo h($attachment['mime']); ?> · <?php echo number_format(((int) $attachment['size']) / 1048576, 2); ?> MB</small>
                      <a class="crm-button crm-button--ghost" href="<?php echo h(crm_build_path(crm_web_base_path(), 'archivos-oportunidad/' . (int) $attachment['id'])); ?>">Descargar</a>
                    </div>
                  <?php endforeach; ?>
                  <?php if (!$selectedOpportunityAttachments): ?><p>No hay archivos adjuntos.</p><?php endif; ?>
                </div>
                <?php if (($selectedOpportunity['lifecycle_stage'] ?? 'Prospecto') !== 'Cliente' && (int) ($selectedOpportunity['client_id'] ?? 0) > 0): ?>
                  <form class="crm-inline-form crm-conversion-form" method="post">
                    <input type="hidden" name="token" value="<?php echo h($token); ?>">
                    <input type="hidden" name="action" value="convert_client">
                    <input type="hidden" name="client_id" value="<?php echo (int) $selectedOpportunity['client_id']; ?>">
                    <input type="hidden" name="opportunity_id" value="<?php echo (int) $selectedOpportunity['id']; ?>">
                    <input type="hidden" name="return_to" value="opportunity">
                    <button class="crm-button" type="submit"><?php echo ($selectedOpportunity['status'] ?? '') === 'Proyecto entregado' ? 'Convertir y activar Bitacora' : 'Convertir a cliente'; ?></button>
                  </form>
                <?php endif; ?>
                <?php if (($selectedOpportunity['lifecycle_stage'] ?? '') === 'Cliente' && (empty($selectedOpportunity['portal_username']) || (int) ($selectedOpportunity['portal_active'] ?? 0) !== 1)): ?>
                  <?php if (($selectedOpportunity['status'] ?? '') === 'Proyecto entregado'): ?>
                    <form class="crm-inline-form crm-conversion-form" method="post">
                      <input type="hidden" name="token" value="<?php echo h($token); ?>">
                      <input type="hidden" name="action" value="activate_portal_access">
                      <input type="hidden" name="opportunity_id" value="<?php echo (int) $selectedOpportunity['id']; ?>">
                      <input type="hidden" name="return_to" value="opportunity">
                      <button class="crm-button" type="submit">Activar Bitacora ID</button>
                    </form>
                  <?php else: ?>
                    <span class="crm-pill crm-pill--neutral">Bitacora disponible al entregar</span>
                  <?php endif; ?>
                <?php endif; ?>
              </article>

              <article class="crm-card crm-form-card">
                <h2>Actualizar seguimiento</h2>
                <form class="crm-form" method="post">
                  <input type="hidden" name="token" value="<?php echo h($token); ?>">
                  <input type="hidden" name="action" value="update_opportunity">
                  <input type="hidden" name="return_to" value="opportunity">
                  <input type="hidden" name="opportunity_id" value="<?php echo (int) $selectedOpportunity['id']; ?>">
                  <div class="crm-form-grid">
                    <label class="crm-field"><span>Estatus</span><select name="status"><?php foreach ($statuses as $status): ?><option <?php echo $status === $selectedOpportunity['status'] ? 'selected' : ''; ?>><?php echo h($status); ?></option><?php endforeach; ?></select><small>Etapa comercial real. Proyecto entregado convierte al cliente y habilita Bitacora ID.</small></label>
                    <label class="crm-field"><span>Prioridad</span><select name="priority"><option <?php echo $selectedOpportunity['priority'] === 'Alta' ? 'selected' : ''; ?>>Alta</option><option <?php echo $selectedOpportunity['priority'] === 'Media' ? 'selected' : ''; ?>>Media</option><option <?php echo $selectedOpportunity['priority'] === 'Baja' ? 'selected' : ''; ?>>Baja</option></select><small>Orden interno de atencion, no la probabilidad de venta.</small></label>
                    <label class="crm-field"><span>Valor estimado</span><input type="number" name="estimated_value" value="<?php echo h($selectedOpportunity['estimated_value']); ?>" min="0" step="1000"><small>Ingreso potencial de la oportunidad; actualizalo conforme avance la ingenieria.</small></label>
                    <label class="crm-field"><span>Siguiente accion</span><input type="date" name="next_action_date" value="<?php echo h($selectedOpportunity['next_action_date']); ?>"><small>Fecha del proximo compromiso comercial, no la fecha de ejecucion del proyecto.</small></label>
                    <label class="crm-field crm-field--wide"><span>Notas</span><textarea name="notes" rows="4"><?php echo h($selectedOpportunity['notes']); ?></textarea><small>Registra acuerdos, alcance, bloqueos y contexto para el siguiente contacto.</small></label>
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
                      <a class="crm-button crm-button--ghost" href="<?php echo h(crm_admin_url('quote', (int) $quote['id'])); ?>">Ver cotizacion</a>
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
          <div class="crm-head">
            <div><p class="eyebrow">Propuestas</p><h1>Cotizaciones</h1><p>Consulta solicitudes y propuestas sin mezclar la captura con el historial.</p></div>
            <button class="crm-button" type="button" data-modal-open="crm-modal-quote" <?php echo !$quoteableOpportunities ? 'disabled aria-describedby="quote-create-disabled"' : ''; ?>>Nueva cotizacion</button>
          </div>
          <?php if (!$quoteableOpportunities): ?><p class="crm-context-note" id="quote-create-disabled">Primero crea una oportunidad activa para poder vincular la cotizacion.</p><?php endif; ?>

          <dialog class="crm-modal" id="crm-modal-quote" aria-labelledby="crm-modal-quote-title" aria-describedby="crm-modal-quote-description">
            <div class="crm-modal__surface">
              <header class="crm-modal__header">
                <div><p class="eyebrow">Nueva propuesta</p><h2 id="crm-modal-quote-title">Crear cotizacion</h2><p id="crm-modal-quote-description">Esta alta crea el folio inicial. El PDF y las condiciones se agregan desde el detalle antes de publicar al cliente.</p></div>
                <button class="crm-modal__close" type="button" aria-label="Cerrar formulario de cotizacion" data-modal-close>
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
              </header>
              <form class="crm-form crm-modal__form" method="post" data-modal-form>
                <input type="hidden" name="token" value="<?php echo h($token); ?>">
                <input type="hidden" name="action" value="create_quote">
                <fieldset class="crm-form-section">
                  <legend>Referencia comercial</legend>
                  <div class="crm-form-grid crm-form-grid--quotes">
                    <label class="crm-field crm-field--wide" for="quote-opportunity"><span>Oportunidad <b aria-hidden="true">*</b></span>
                      <select id="quote-opportunity" name="opportunity_id" required>
                        <?php foreach ($quoteableOpportunities as $opportunity): ?>
                          <option value="<?php echo (int) $opportunity['id']; ?>"><?php echo h($opportunity['company_name'] . ' - ' . $opportunity['service']); ?></option>
                        <?php endforeach; ?>
                      </select>
                      <small>Relaciona la propuesta con la empresa, contacto y servicio correctos.</small>
                    </label>
                  </div>
                </fieldset>
                <fieldset class="crm-form-section">
                  <legend>Condiciones iniciales</legend>
                  <div class="crm-form-grid crm-form-grid--quotes">
                    <label class="crm-field" for="quote-amount"><span>Monto <b aria-hidden="true">*</b></span><input id="quote-amount" type="number" name="amount" min="0" step="1000" inputmode="decimal" required><small>Importe total antes de impuestos, salvo que las condiciones indiquen lo contrario.</small></label>
                    <label class="crm-field" for="quote-status"><span>Estatus</span><select id="quote-status" name="status"><?php foreach ($quoteStatuses as $status): ?><option <?php echo $status === 'En elaboracion' ? 'selected' : ''; ?>><?php echo h($status); ?></option><?php endforeach; ?></select><small>Usa En elaboracion mientras falten alcance, costos o autorizaciones internas.</small></label>
                    <label class="crm-field" for="quote-probability"><span>Probabilidad</span><input id="quote-probability" type="number" name="probability" min="0" max="100" value="40" inputmode="numeric"><small>Estimacion comercial de cierre, entre 0 y 100%; no modifica el monto.</small></label>
                    <label class="crm-field" for="quote-valid-until"><span>Vigencia</span><input id="quote-valid-until" type="date" name="valid_until"><small>Ultimo dia en que precio, alcance y tiempos ofrecidos siguen vigentes.</small></label>
                  </div>
                </fieldset>
                <aside class="crm-form-guidance" aria-label="Siguiente paso"><strong>Despues de crearla</strong><p>Abre el folio, agrega condiciones comerciales y adjunta el PDF. La cotizacion no sera visible para el cliente hasta activar Publicar en portal.</p></aside>
                <footer class="crm-modal__footer">
                  <button class="crm-button crm-button--ghost" type="button" data-modal-close>Cancelar</button>
                  <button class="crm-button" type="submit">Crear cotizacion</button>
                </footer>
              </form>
            </div>
          </dialog>
          <article class="crm-card">
            <div class="crm-table-wrap">
              <table class="crm-table crm-table--compact">
                <thead><tr><th>Folio</th><th>Empresa</th><th>Servicio</th><th>Origen</th><th>Estatus</th><th>Cliente</th><th>Ver</th></tr></thead>
                <tbody>
                  <?php foreach ($quotes as $quote): ?>
                    <?php $quoteStatus = trim((string) ($quote['status'] ?? '')); ?>
                    <?php if ($quoteStatus === '') { $quoteStatus = 'En elaboracion'; } ?>
                    <tr>
                      <td><strong><?php echo h($quote['quote_code']); ?></strong></td>
                      <td><?php echo h($quote['company_name']); ?></td>
                      <td><?php echo h($quote['service']); ?></td>
                      <td><?php echo h($quote['source'] ?? 'CRM'); ?></td>
                      <td><span class="crm-pill crm-pill--neutral"><?php echo h($quoteStatus); ?></span></td>
                      <td><span class="crm-pill <?php echo (int) ($quote['visible_to_client'] ?? 0) === 1 ? 'crm-pill--success' : 'crm-pill--neutral'; ?>"><?php echo h((int) ($quote['visible_to_client'] ?? 0) === 1 ? ($quote['client_status'] ?? 'Pendiente') : 'Borrador'); ?></span></td>
                      <td>
                        <a class="crm-button crm-button--ghost" href="<?php echo h(crm_admin_url('quote', (int) $quote['id'])); ?>">Ver</a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if (!$quotes): ?>
                    <tr><td colspan="7">Aun no hay cotizaciones. Crea la primera desde una oportunidad activa.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </article>
        <?php elseif ($view === 'quote'): ?>
          <?php if (!$selectedQuote): ?>
            <div class="crm-head"><div><p class="eyebrow">Propuestas</p><h1>Cotizacion no encontrada</h1><p>El registro solicitado no existe o fue eliminado.</p></div><a class="crm-button crm-button--ghost" href="<?php echo h(crm_admin_url('quotes')); ?>">Volver</a></div>
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
              <a class="crm-button crm-button--ghost" href="<?php echo h(crm_admin_url('quotes')); ?>">Volver</a>
            </div>

            <div class="crm-kpis crm-kpis--secondary">
              <article class="crm-card"><h2>Monto</h2><p><?php echo crm_money($selectedQuote['amount']); ?></p></article>
              <article class="crm-card"><h2>Estatus</h2><p><span class="crm-pill <?php echo h(crm_pill_class($quoteStatus)); ?>"><?php echo h($quoteStatus); ?></span></p></article>
              <article class="crm-card"><h2>Respuesta cliente</h2><p><span class="crm-pill <?php echo ($selectedQuote['client_status'] ?? 'Pendiente') === 'Aceptada' ? 'crm-pill--success' : (($selectedQuote['client_status'] ?? '') === 'Rechazada' ? 'crm-pill--danger' : 'crm-pill--neutral'); ?>"><?php echo h($selectedQuote['client_status'] ?? 'Pendiente'); ?></span></p></article>
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
                  <div class="crm-list__item"><strong>Alcance solicitado</strong><span><?php echo nl2br(h($selectedQuote['request_scope'] ?: $selectedQuote['opportunity_notes'] ?: 'Sin notas')); ?></span></div>
                  <div class="crm-list__item"><strong>Ubicacion</strong><span><?php echo h($selectedQuote['request_location'] ?: 'Sin especificar'); ?></span></div>
                  <div class="crm-list__item"><strong>Fecha requerida</strong><span><?php echo h($selectedQuote['requested_date'] ?: 'Por definir'); ?></span></div>
                  <div class="crm-list__item"><strong>Presupuesto cliente</strong><span><?php echo h($selectedQuote['budget_range'] ?: 'Por definir'); ?></span></div>
                  <?php if (!empty($selectedQuote['client_comments'])): ?><div class="crm-list__item"><strong>Comentarios del cliente</strong><span><?php echo nl2br(h($selectedQuote['client_comments'])); ?></span></div><?php endif; ?>
                </div>
                <div class="crm-client-actions-row">
                  <?php if (!empty($selectedQuote['attachment_path'])): ?><a class="crm-button crm-button--ghost" href="<?php echo h(crm_quote_attachment_url((int) $selectedQuote['id'])); ?>" target="_blank" rel="noopener">Ver anexo cliente</a><?php endif; ?>
                  <?php if (!empty($selectedQuote['proposal_path'])): ?><a class="crm-button crm-button--ghost" href="<?php echo h(crm_quote_attachment_url((int) $selectedQuote['id'], 'proposal')); ?>" target="_blank" rel="noopener">Ver PDF actual</a><?php endif; ?>
                </div>
              </article>
            </div>

            <article class="crm-card crm-form-card">
              <h2>Actualizar cotizacion</h2>
              <form class="crm-form" method="post" enctype="multipart/form-data">
                <input type="hidden" name="token" value="<?php echo h($token); ?>">
                <input type="hidden" name="action" value="update_quote">
                <input type="hidden" name="return_to" value="quote">
                <input type="hidden" name="quote_id" value="<?php echo (int) $selectedQuote['id']; ?>">
                <div class="crm-form-grid crm-form-grid--quotes">
                  <label class="crm-field"><span>Monto</span><input type="number" name="amount" value="<?php echo h($selectedQuote['amount']); ?>" min="0" step="1000"><small>Importe comercial de la propuesta; confirma en condiciones si incluye impuestos.</small></label>
                  <label class="crm-field"><span>Estatus</span><select name="status"><?php foreach ($quoteStatuses as $status): ?><option <?php echo $status === $quoteStatus ? 'selected' : ''; ?>><?php echo h($status); ?></option><?php endforeach; ?></select><small>Refleja el avance interno; publicar al cliente cambia el flujo a Enviada.</small></label>
                  <label class="crm-field"><span>Probabilidad</span><input type="number" name="probability" min="0" max="100" value="<?php echo $quoteProbability; ?>"><small>Estimacion de cierre entre 0 y 100%, independiente del importe.</small></label>
                  <label class="crm-field"><span>Vigencia</span><input type="date" name="valid_until" value="<?php echo h($quoteValidUntil); ?>"><small>Ultimo dia en que la propuesta conserva precio, alcance y tiempos.</small></label>
                  <label class="crm-field crm-field--wide"><span>Condiciones comerciales</span><textarea name="terms" rows="5" maxlength="5000" placeholder="Alcance incluido, exclusiones, forma de pago y tiempos de entrega."><?php echo h($selectedQuote['terms'] ?? ''); ?></textarea><small>Detalla inclusiones, exclusiones, anticipos, entregables y plazo estimado.</small></label>
                  <label class="crm-field crm-field--wide">PDF de propuesta<input type="file" name="proposal_file" accept="application/pdf"><small><?php echo !empty($selectedQuote['proposal_original_name']) ? h('Actual: ' . $selectedQuote['proposal_original_name']) : 'PDF obligatorio para publicar. Maximo 12 MB.'; ?></small></label>
                  <label class="crm-check crm-field--wide"><input type="checkbox" name="visible_to_client" value="1" <?php echo (int) ($selectedQuote['visible_to_client'] ?? 0) === 1 ? 'checked' : ''; ?>><span><strong>Publicar en portal cliente</strong><small>El cliente recibira una notificacion y podra aceptar, pedir cambios o rechazar.</small></span></label>
                </div>
                <button class="crm-button" type="submit"><?php echo (int) ($selectedQuote['visible_to_client'] ?? 0) === 1 ? 'Actualizar propuesta' : 'Guardar cotizacion'; ?></button>
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
          <?php endif; ?>
        <?php elseif ($view === 'prospects'): ?>
          <div class="crm-head">
            <div><p class="eyebrow">Cartera potencial</p><h1>Prospectos</h1><p>Contactos que aun estan en evaluacion comercial y no deben mezclarse con clientes activos.</p></div>
            <a class="crm-button" href="<?php echo h(crm_admin_url('opportunities')); ?>">Crear oportunidad</a>
          </div>
          <div class="crm-kpis crm-kpis--secondary crm-portfolio-kpis">
            <article class="crm-card"><h2>Prospectos activos</h2><p><?php echo count($prospects); ?></p><small>Registros pendientes de conversion comercial.</small></article>
            <article class="crm-card"><h2>Criterio de conversion</h2><p>Relacion confirmada</p><small>Convierte cuando exista proyecto ganado, iniciado o una validacion comercial equivalente.</small></article>
          </div>
          <article class="crm-card">
            <div class="crm-section-head"><div><h2>Directorio de prospectos</h2><p>Revisa contacto y contexto antes de convertir. La conversion mueve el registro a Clientes.</p></div></div>
            <?php if ($prospects): ?>
              <div class="crm-table-wrap">
                <table class="crm-table crm-table--compact">
                  <thead><tr><th>Prospecto</th><th>Contacto</th><th>Ciudad</th><th>Alta</th><th>Contexto</th><th>Accion</th></tr></thead>
                  <tbody>
                    <?php foreach ($prospects as $prospect): ?>
                      <tr>
                        <td><strong><?php echo h($prospect['name']); ?></strong><br><small><?php echo h($prospect['segment'] ?: 'Prospecto'); ?></small></td>
                        <td><?php echo h($prospect['contact_name'] ?: 'Sin contacto'); ?><br><small><?php echo h($prospect['contact_email'] ?: $prospect['contact_phone'] ?: 'Sin datos de contacto'); ?></small></td>
                        <td><?php echo h($prospect['city'] ?: 'Sin ciudad'); ?></td>
                        <td><?php echo h(substr((string) $prospect['created_at'], 0, 10)); ?></td>
                        <td><?php echo h($prospect['notes'] ?: 'Sin notas'); ?></td>
                        <td>
                          <form class="crm-inline-form crm-conversion-form" method="post" data-confirm-message="El prospecto pasara a la cartera de clientes. Verifica que la relacion comercial ya este confirmada.">
                            <input type="hidden" name="token" value="<?php echo h($token); ?>">
                            <input type="hidden" name="action" value="convert_client">
                            <input type="hidden" name="client_id" value="<?php echo (int) $prospect['id']; ?>">
                            <button class="crm-button" type="submit">Convertir a cliente</button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <div class="crm-empty-state"><strong>No hay prospectos pendientes</strong><p>Los nuevos contactos apareceran aqui al registrar una oportunidad o recibir una solicitud web.</p><a class="crm-button crm-button--ghost" href="<?php echo h(crm_admin_url('opportunities')); ?>">Ir a oportunidades</a></div>
            <?php endif; ?>
          </article>
        <?php elseif ($view === 'clients'): ?>
          <div class="crm-head">
            <div><p class="eyebrow">Cartera activa</p><h1>Clientes</h1><p>Empresas convertidas con su avance de proyectos y estado de acceso a Bitacora ID.</p></div>
            <a class="crm-button crm-button--ghost" href="<?php echo h(crm_admin_url('prospects')); ?>">Ver prospectos</a>
          </div>
          <div class="crm-kpis crm-kpis--secondary crm-portfolio-kpis">
            <article class="crm-card"><h2>Clientes activos</h2><p><?php echo count($clients); ?></p><small>Empresas ya convertidas a cartera.</small></article>
            <article class="crm-card"><h2>Bitacoras activas</h2><p><?php echo $counts['portal']; ?></p><small>Accesos vigentes al portal de mantenimiento.</small></article>
            <article class="crm-card"><h2>Proyectos entregados</h2><p><?php echo $counts['delivered']; ?></p><small>Proyectos elegibles para continuidad operativa.</small></article>
          </div>
          <article class="crm-card">
            <div class="crm-section-head"><div><h2>Directorio de clientes</h2><p>Consulta responsables, proyectos y habilitacion del portal sin registros de prospeccion.</p></div></div>
            <?php if ($clients): ?>
              <div class="crm-table-wrap">
                <table class="crm-table crm-table--compact">
                  <thead><tr><th>Cliente</th><th>Contacto</th><th>Ciudad</th><th>Proyectos</th><th>Ultima actividad</th><th>Bitacora ID</th><th>Sitio publico</th></tr></thead>
                  <tbody>
                    <?php foreach ($clients as $client): ?>
                      <tr>
                        <td><strong><?php echo h($client['name']); ?></strong><br><small><?php echo h($client['segment'] ?: 'Industrial'); ?></small></td>
                        <td><?php echo h($client['contact_name'] ?: 'Sin contacto'); ?><br><small><?php echo h($client['contact_email'] ?: $client['contact_phone'] ?: 'Sin datos de contacto'); ?></small></td>
                        <td><?php echo h($client['city'] ?: 'Sin ciudad'); ?></td>
                        <td><strong><?php echo (int) $client['started_projects']; ?></strong> iniciados<br><small><?php echo (int) $client['delivered_projects']; ?> entregados</small></td>
                        <td><?php echo h($client['last_project_update'] ?: $client['converted_at'] ?: $client['created_at']); ?></td>
                        <td>
                          <?php if ((int) ($client['portal_access_count'] ?? 0) > 0): ?>
                            <span class="crm-pill crm-pill--success">Activa</span>
                          <?php elseif ((int) ($client['access_opportunity_id'] ?? 0) > 0): ?>
                            <form class="crm-inline-form crm-conversion-form" method="post">
                              <input type="hidden" name="token" value="<?php echo h($token); ?>">
                              <input type="hidden" name="action" value="activate_portal_access">
                              <input type="hidden" name="opportunity_id" value="<?php echo (int) $client['access_opportunity_id']; ?>">
                              <input type="hidden" name="return_to" value="clients">
                              <button class="crm-button" type="submit">Activar Bitacora</button>
                            </form>
                          <?php else: ?>
                            <span class="crm-pill crm-pill--neutral">Requiere proyecto entregado</span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <form class="crm-inline-form" method="post">
                            <input type="hidden" name="token" value="<?php echo h($token); ?>">
                            <input type="hidden" name="action" value="toggle_client_public">
                            <input type="hidden" name="client_id" value="<?php echo (int) $client['id']; ?>">
                            <?php if ($client['is_public']): ?>
                              <input type="hidden" name="make_public" value="0">
                              <span class="crm-pill crm-pill--success">Visible</span>
                              <button class="crm-button crm-button--ghost" type="submit">Ocultar del sitio</button>
                            <?php else: ?>
                              <input type="hidden" name="make_public" value="1">
                              <span class="crm-pill crm-pill--neutral">Oculto</span>
                              <button class="crm-button crm-button--ghost" type="submit">Mostrar en el sitio</button>
                            <?php endif; ?>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <div class="crm-empty-state"><strong>Aun no hay clientes convertidos</strong><p>Convierte un prospecto cuando la relacion comercial este confirmada.</p><a class="crm-button crm-button--ghost" href="<?php echo h(crm_admin_url('prospects')); ?>">Revisar prospectos</a></div>
            <?php endif; ?>
          </article>
        <?php elseif ($view === 'profile'): ?>
          <div class="crm-head">
            <div>
              <p class="eyebrow">Cuenta</p>
              <h1>Perfil administrador</h1>
              <p>Actualiza el password de acceso al CRM.</p>
            </div>
          </div>

          <article class="crm-card crm-account-security">
            <div class="crm-section-head">
              <div><h2>Seguridad de cuenta</h2><p>Cambia tus credenciales de acceso al CRM.</p></div>
              <span class="crm-pill crm-pill--success">Cuenta activa</span>
            </div>
            <?php if ($flash): ?>
              <div class="crm-account-feedback crm-account-feedback--<?php echo h($flash['type'] ?? 'info'); ?>" role="status">
                <strong><?php echo h($flash['title'] ?? 'Aviso'); ?></strong>
                <span><?php echo h($flash['text'] ?? ''); ?></span>
              </div>
            <?php endif; ?>
            <div class="crm-profile-grid crm-profile-grid--security">
              <aside class="crm-profile-summary crm-profile-summary--security">
                <span><?php echo crm_icon('clients'); ?></span>
                <div><small>Administrador</small><strong><?php echo h($_SESSION['crm_user']['name']); ?></strong><code><?php echo h($_SESSION['crm_user']['email']); ?></code></div>
              </aside>
              <div class="crm-password-panel">
                <div class="crm-password-panel__head"><h3>Cambiar password</h3><p>Usa una clave distinta a la actual y confirma el nuevo acceso.</p></div>
                <form class="crm-form crm-password-form" method="post" autocomplete="on" data-password-change-form>
                  <input type="hidden" name="token" value="<?php echo h($token); ?>">
                  <input type="hidden" name="action" value="change_password">
                  <label class="crm-field">Password actual<span class="crm-password-field"><input id="profile-current-password" type="password" name="current_password" autocomplete="current-password" required><button class="crm-password-toggle" type="button" aria-label="Mostrar password actual" aria-controls="profile-current-password" data-password-toggle><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5c5 0 8.5 4.2 9.7 6.1a1.7 1.7 0 0 1 0 1.8C20.5 14.8 17 19 12 19s-8.5-4.2-9.7-6.1a1.7 1.7 0 0 1 0-1.8C3.5 9.2 7 5 12 5Zm0 2C7.9 7 4.9 10.4 4 12c.9 1.6 3.9 5 8 5s7.1-3.4 8-5c-.9-1.6-3.9-5-8-5Zm0 2.2a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6Z"/></svg></button></span></label>
                  <div class="crm-password-pair">
                    <label class="crm-field">Nuevo password<span class="crm-password-field"><input id="profile-new-password" type="password" name="new_password" autocomplete="new-password" minlength="10" required data-password-new><button class="crm-password-toggle" type="button" aria-label="Mostrar nuevo password" aria-controls="profile-new-password" data-password-toggle><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5c5 0 8.5 4.2 9.7 6.1a1.7 1.7 0 0 1 0 1.8C20.5 14.8 17 19 12 19s-8.5-4.2-9.7-6.1a1.7 1.7 0 0 1 0-1.8C3.5 9.2 7 5 12 5Zm0 2C7.9 7 4.9 10.4 4 12c.9 1.6 3.9 5 8 5s7.1-3.4 8-5c-.9-1.6-3.9-5-8-5Zm0 2.2a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6Z"/></svg></button></span></label>
                    <label class="crm-field">Confirmar password<span class="crm-password-field"><input id="profile-confirm-password" type="password" name="confirm_password" autocomplete="new-password" minlength="10" required data-password-confirm><button class="crm-password-toggle" type="button" aria-label="Mostrar confirmacion de password" aria-controls="profile-confirm-password" data-password-toggle><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5c5 0 8.5 4.2 9.7 6.1a1.7 1.7 0 0 1 0 1.8C20.5 14.8 17 19 12 19s-8.5-4.2-9.7-6.1a1.7 1.7 0 0 1 0-1.8C3.5 9.2 7 5 12 5Zm0 2C7.9 7 4.9 10.4 4 12c.9 1.6 3.9 5 8 5s7.1-3.4 8-5c-.9-1.6-3.9-5-8-5Zm0 2.2a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6Z"/></svg></button></span></label>
                  </div>
                  <div class="crm-password-actions"><small>Minimo 10 caracteres.</small><button class="crm-button" type="submit">Actualizar password</button></div>
                </form>
              </div>
            </div>
          </article>
        <?php elseif ($view === 'settings'): ?>
          <div class="crm-head crm-settings-head">
            <div>
              <p class="eyebrow">Configuracion</p>
              <h1>Canales y notificaciones</h1>
              <p>Define quién recibe las nuevas solicitudes comerciales y revisa cómo entra cada contacto al CRM.</p>
            </div>
            <span class="crm-pill crm-pill--success">Recepción activa</span>
          </div>

          <section class="crm-settings-overview" aria-label="Resumen de configuración">
            <article class="crm-card crm-settings-stat"><span><?php echo crm_icon('reports'); ?></span><div><small>Canal</small><strong>Formulario web</strong><p>Crea oportunidades automáticamente.</p></div></article>
            <article class="crm-card crm-settings-stat"><span><?php echo crm_icon('clients'); ?></span><div><small>Destino principal</small><strong><?php echo h($quoteRequestAdminEmail); ?></strong><p>Recibe el aviso y el enlace directo.</p></div></article>
            <article class="crm-card crm-settings-stat"><span><?php echo crm_icon('bell'); ?></span><div><small>Copia de respaldo</small><strong><?php echo $quoteRequestSecondaryEmail !== '' ? h($quoteRequestSecondaryEmail) : 'Desactivada'; ?></strong><p><?php echo $quoteRequestSecondaryEmail !== '' ? 'También recibe cada solicitud.' : 'Puedes activarla cuando la necesites.'; ?></p></div></article>
          </section>

          <article class="crm-card crm-settings-card crm-settings-card--channel">
            <?php if ($flash): ?>
              <div class="crm-account-feedback crm-account-feedback--<?php echo h($flash['type'] ?? 'info'); ?>" role="status">
                <strong><?php echo h($flash['title'] ?? 'Aviso'); ?></strong>
                <span><?php echo h($flash['text'] ?? ''); ?></span>
              </div>
            <?php endif; ?>
            <div class="crm-settings-card__body">
              <form class="crm-form crm-settings-form" method="post" autocomplete="on" data-submit-lock data-submit-label="Guardando...">
                <div class="crm-settings-form__head"><span><?php echo crm_icon('reports'); ?></span><div><p class="eyebrow">Entrega de solicitudes</p><h2>Correos de recepción</h2><p>Estos destinatarios se usan únicamente para nuevas solicitudes enviadas desde el sitio web.</p></div></div>
                <input type="hidden" name="token" value="<?php echo h($token); ?>">
                <input type="hidden" name="action" value="update_settings">
                <label class="crm-field">Correo principal <span class="crm-field__required">Obligatorio</span><input type="email" name="quote_request_admin_email" value="<?php echo h($quoteRequestAdminEmail); ?>" placeholder="cotizaciones@idindustrial.com.mx" autocomplete="email" required><small>Responsable de revisar y asignar la nueva oportunidad.</small></label>
                <label class="crm-field">Correo de copia <span class="crm-field__optional">Opcional</span><input type="email" name="quote_request_secondary_email" value="<?php echo h($quoteRequestSecondaryEmail); ?>" placeholder="ventas@idindustrial.com.mx" autocomplete="email"><small>Recibe el mismo aviso. Déjalo vacío para desactivar la copia.</small></label>
                <div class="crm-settings-actions"><small>Los cambios se aplican a las próximas solicitudes; no modifican registros anteriores.</small><button class="crm-button" type="submit">Guardar cambios</button></div>
              </form>
              <aside class="crm-settings-flow" aria-labelledby="settings-flow-title">
                <div><p class="eyebrow">Flujo automático</p><h2 id="settings-flow-title">Qué ocurre al recibir una solicitud</h2></div>
                <ol>
                  <li><span>1</span><div><strong>El cliente envía el formulario</strong><p>Se validan sus datos de contacto y el servicio requerido.</p></div></li>
                  <li><span>2</span><div><strong>El CRM crea la oportunidad</strong><p>El registro queda disponible en Oportunidades y Cotizaciones.</p></div></li>
                  <li><span>3</span><div><strong>Se envía el aviso</strong><p>El correo incluye los datos del cliente y un acceso directo al seguimiento.</p></div></li>
                </ol>
                <div class="crm-settings-note"><strong>Recomendación</strong><p>Usa un buzón del equipo comercial como destino principal para evitar que las solicitudes dependan de una sola persona.</p></div>
              </aside>
            </div>
          </article>        <?php else: ?>
          <div class="crm-head crm-report-head">
            <div>
              <p class="eyebrow">Mantenimiento continuo</p>
              <h1>Bitacora ID</h1>
              <p>Accesos de clientes, reportes de mantenimiento y seguimiento operativo.</p>
            </div>
            <div class="crm-head__actions">
              <button class="crm-button crm-button--ghost" type="button" data-print-report>Exportar PDF</button>
              <?php if ($missingPortalCount > 0): ?>
                <form method="post" class="crm-inline-form">
                  <input type="hidden" name="token" value="<?php echo h($token); ?>">
                  <input type="hidden" name="action" value="sync_portal_access">
                  <button class="crm-button crm-button--ghost" type="submit">Activar todos (<?php echo $missingPortalCount; ?>)</button>
                </form>
              <?php endif; ?>
              <a class="crm-button" href="<?php echo h(crm_portal_url()); ?>">Portal cliente</a>
            </div>
          </div>

          <section class="crm-report" aria-label="Reporte de mantenimiento">
            <header class="crm-print-header crm-print-only">
              <div class="crm-print-brand">
                <img src="<?php echo h(crm_public_url('assets/img/logo-idindustrial-small.webp')); ?>" alt="ID Industrial" width="280" height="74">
                <div><strong>Bitacora ID</strong><span>Reporte operativo de mantenimiento</span></div>
              </div>
              <div class="crm-print-date"><span>Fecha de corte</span><strong><?php echo h(date('d/m/Y')); ?></strong><small><?php echo h(date('H:i')); ?> h</small></div>
            </header>

            <div class="crm-report__meta">
              <strong>Resumen operativo</strong>
              <span>Generado: <?php echo h(date('Y-m-d H:i')); ?></span>
            </div>

            <div class="crm-kpis crm-kpis--secondary crm-report-kpis">
              <article class="crm-card crm-kpi"><span class="crm-kpi__icon"><?php echo crm_icon('reports'); ?></span><div><span>Reportes nuevos</span><strong><?php echo $maintenanceMetrics['new']; ?></strong></div></article>
              <article class="crm-card crm-kpi"><span class="crm-kpi__icon"><?php echo crm_icon('overdue'); ?></span><div><span>Vencidos</span><strong><?php echo $maintenanceMetrics['overdue']; ?></strong></div></article>
              <article class="crm-card crm-kpi"><span class="crm-kpi__icon"><?php echo crm_icon('urgent'); ?></span><div><span>Urgentes</span><strong><?php echo $maintenanceMetrics['urgent']; ?></strong></div></article>
              <article class="crm-card crm-kpi"><span class="crm-kpi__icon"><?php echo crm_icon('response'); ?></span><div><span>Sin respuesta</span><strong><?php echo $maintenanceMetrics['unanswered']; ?></strong></div></article>
              <article class="crm-card crm-kpi"><span class="crm-kpi__icon"><?php echo crm_icon('open'); ?></span><div><span>Abiertos</span><strong><?php echo $maintenanceMetrics['open']; ?></strong></div></article>
              <article class="crm-card crm-kpi"><span class="crm-kpi__icon"><?php echo crm_icon('scheduled'); ?></span><div><span>Programados</span><strong><?php echo $maintenanceMetrics['scheduled']; ?></strong></div></article>
            </div>

            <div class="crm-report-grid">
              <article class="crm-card crm-chart-card">
                <?php $requestStatusTotal = crm_chart_total($requestStatusTotals); ?>
                <div class="crm-chart-card__head">
                  <div><h2>Estatus de reportes</h2><p>Avance operativo de solicitudes.</p></div>
                  <span class="crm-chart-total"><?php echo $requestStatusTotal; ?><small>reportes</small></span>
                </div>
                <div class="crm-distribution crm-distribution--compact">
                  <?php $statusIndex = 0; foreach ($requestStatusTotals as $status => $total): ?>
                    <?php $statusPercent = crm_chart_percent((int) $total, $requestStatusTotal); ?>
                    <div class="crm-distribution__row">
                      <div class="crm-distribution__label"><span><i style="background: <?php echo h(crm_chart_color($statusIndex)); ?>"></i><?php echo h($status); ?></span><strong><?php echo (int) $total; ?><small><?php echo $statusPercent; ?>%</small></strong></div>
                      <div class="crm-distribution__track" role="img" aria-label="<?php echo h($status); ?>: <?php echo (int) $total; ?>, <?php echo $statusPercent; ?> por ciento"><span style="width: <?php echo $statusPercent; ?>%; background: <?php echo h(crm_chart_color($statusIndex)); ?>"></span></div>
                    </div>
                  <?php $statusIndex++; endforeach; ?>
                </div>
              </article>

              <article class="crm-card crm-chart-card">
                <?php $requestPriorityTotal = crm_chart_total($requestPriorityTotals); ?>
                <div class="crm-chart-card__head">
                  <div><h2>Prioridad</h2><p>Concentracion por nivel de atencion.</p></div>
                  <span class="crm-chart-total"><?php echo $requestPriorityTotal; ?><small>solicitudes</small></span>
                </div>
                <div class="crm-distribution crm-distribution--compact">
                  <?php $priorityIndex = 0; foreach ($requestPriorityTotals as $priority => $total): ?>
                    <?php $priorityPercent = crm_chart_percent((int) $total, $requestPriorityTotal); ?>
                    <div class="crm-distribution__row">
                      <div class="crm-distribution__label"><span><i style="background: <?php echo h(crm_chart_color($priorityIndex)); ?>"></i><?php echo h($priority); ?></span><strong><?php echo (int) $total; ?><small><?php echo $priorityPercent; ?>%</small></strong></div>
                      <div class="crm-distribution__track" role="img" aria-label="<?php echo h($priority); ?>: <?php echo (int) $total; ?>, <?php echo $priorityPercent; ?> por ciento"><span style="width: <?php echo $priorityPercent; ?>%; background: <?php echo h(crm_chart_color($priorityIndex)); ?>"></span></div>
                    </div>
                  <?php $priorityIndex++; endforeach; ?>
                </div>
              </article>

              <article class="crm-card crm-chart-card crm-chart-card--trend">
                <div class="crm-chart-card__head">
                  <div><h2>Ultimos 6 meses</h2><p>Volumen de reportes recibidos.</p></div>
                  <span class="crm-chart-total"><?php echo array_sum($requestMonthlyTotals); ?><small>periodo</small></span>
                </div>
                <div class="crm-month-chart">
                  <?php foreach ($requestMonthlyTotals as $month => $total): ?>
                    <?php $height = $total > 0 ? max(12, round(($total / $maxMonthlyTotal) * 100)) : 4; ?>
                    <div class="crm-month-chart__bar" aria-label="<?php echo h(crm_month_name(date('m', strtotime($month . '-01')))); ?>: <?php echo (int) $total; ?> reportes">
                      <span style="height: <?php echo $height; ?>%"></span>
                      <strong><?php echo (int) $total; ?></strong>
                      <small><?php echo h(crm_month_name(date('m', strtotime($month . '-01')))); ?></small>
                    </div>
                  <?php endforeach; ?>
                </div>
              </article>
            </div>

            <section class="crm-print-details crm-print-only" aria-label="Detalle del reporte">
              <div class="crm-print-performance">
                <div><span>Total de reportes</span><strong><?php echo $maintenanceMetrics['total']; ?></strong></div>
                <div><span>Tasa de respuesta</span><strong><?php echo $maintenanceMetrics['response_rate']; ?>%</strong></div>
                <div><span>Resueltos</span><strong><?php echo $maintenanceMetrics['resolved']; ?></strong></div>
              </div>

              <section class="crm-print-section">
                <div class="crm-print-section__head"><div><span>Operacion</span><h2>Solicitudes activas</h2></div><strong><?php echo $maintenanceMetrics['open']; ?> abiertas</strong></div>
                <table class="crm-print-table">
                  <thead><tr><th>Cliente / reporte</th><th>Prioridad</th><th>Estatus</th><th>Fecha objetivo</th><th>Responsable</th></tr></thead>
                  <tbody>
                    <?php foreach ($clientRequests as $request): ?>
                      <?php
                        $printStatus = trim((string) ($request['status'] ?? 'Recibida')) ?: 'Recibida';
                        if (crm_request_is_final($printStatus)) { continue; }
                        $printPriority = trim((string) ($request['priority'] ?? 'Media')) ?: 'Media';
                        $printDueDate = trim((string) ($request['due_date'] ?? '')) ?: crm_request_due_date($printPriority, (string) ($request['created_at'] ?? 'now'));
                      ?>
                      <tr>
                        <td><strong><?php echo h($request['company_name']); ?></strong><span><?php echo h($request['title']); ?> - <?php echo h($request['category'] ?? 'Mantenimiento correctivo'); ?><?php echo !empty($request['evidence_path']) ? ' - Evidencia adjunta' : ''; ?></span></td>
                        <td><?php echo h($printPriority); ?></td>
                        <td><?php echo h($printStatus); ?></td>
                        <td><?php echo h($printDueDate); ?></td>
                        <td><?php echo h(trim((string) ($request['assigned_to'] ?? '')) ?: 'Sin asignar'); ?></td>
                      </tr>
                    <?php endforeach; ?>
                    <?php if ($maintenanceMetrics['open'] === 0): ?><tr><td colspan="5">No hay solicitudes activas al momento del corte.</td></tr><?php endif; ?>
                  </tbody>
                </table>
              </section>

              <section class="crm-print-section crm-print-request-section">
                <div class="crm-print-section__head"><div><span>Detalle tecnico</span><h2>Fichas de solicitudes activas</h2></div><strong><?php echo $maintenanceMetrics['open']; ?> fichas</strong></div>
                <div class="crm-print-request-list">
                  <?php foreach ($clientRequests as $request): ?>
                    <?php
                      $detailStatus = trim((string) ($request['status'] ?? 'Recibida')) ?: 'Recibida';
                      if (crm_request_is_final($detailStatus)) { continue; }
                      $detailPriority = trim((string) ($request['priority'] ?? 'Media')) ?: 'Media';
                      $detailDueDate = trim((string) ($request['due_date'] ?? '')) ?: crm_request_due_date($detailPriority, (string) ($request['created_at'] ?? 'now'));
                    ?>
                    <article class="crm-print-request">
                      <header><div><span>Folio ID-<?php echo str_pad((string) $request['id'], 5, '0', STR_PAD_LEFT); ?></span><h3><?php echo h($request['title']); ?></h3><p><?php echo h($request['company_name']); ?> - <?php echo h($request['service']); ?></p></div><strong><?php echo h($detailStatus); ?></strong></header>
                      <div class="crm-print-request__meta">
                        <span><strong>Categoria</strong><?php echo h($request['category'] ?? 'Mantenimiento correctivo'); ?></span>
                        <span><strong>Prioridad</strong><?php echo h($detailPriority); ?></span>
                        <span><strong>Ubicacion</strong><?php echo h($request['location'] ?: 'Sin especificar'); ?></span>
                        <span><strong>Equipo</strong><?php echo h($request['equipment'] ?: 'No especificado'); ?></span>
                        <span><strong>Impacto</strong><?php echo h($request['impact'] ?? 'Sin paro'); ?></span>
                        <span><strong>Incidente</strong><?php echo h($request['occurred_at'] ?: $request['created_at']); ?></span>
                        <span><strong>Fecha objetivo</strong><?php echo h($detailDueDate); ?></span>
                        <span><strong>Responsable</strong><?php echo h(trim((string) ($request['assigned_to'] ?? '')) ?: 'Sin asignar'); ?></span>
                      </div>
                      <div class="crm-print-request__content"><strong>Descripcion reportada</strong><p><?php echo nl2br(h($request['message'])); ?></p></div>
                      <?php if (!empty($request['actions_taken'])): ?><div class="crm-print-request__content"><strong>Acciones realizadas por el cliente</strong><p><?php echo nl2br(h($request['actions_taken'])); ?></p></div><?php endif; ?>
                      <?php if (!empty($request['admin_response'])): ?><div class="crm-print-request__content"><strong>Respuesta de ID Industrial</strong><p><?php echo nl2br(h($request['admin_response'])); ?></p></div><?php endif; ?>
                      <?php if (!empty($request['evidence_path'])): ?>
                        <div class="crm-print-evidence">
                          <img src="<?php echo h(crm_evidence_url((int) $request['id'])); ?>" alt="Evidencia del folio ID-<?php echo str_pad((string) $request['id'], 5, '0', STR_PAD_LEFT); ?>">
                          <span><strong>Evidencia fotografica</strong><?php echo h($request['evidence_original_name'] ?: 'Fotografia adjunta'); ?></span>
                        </div>
                      <?php endif; ?>
                    </article>
                  <?php endforeach; ?>
                  <?php if ($maintenanceMetrics['open'] === 0): ?><p class="crm-print-empty">No hay solicitudes activas para detallar.</p><?php endif; ?>
                </div>
              </section>

              <section class="crm-print-section">
                <div class="crm-print-section__head"><div><span>Cobertura</span><h2>Accesos de clientes</h2></div><strong><?php echo count($portalUsers); ?> cuentas</strong></div>
                <table class="crm-print-table">
                  <thead><tr><th>Cliente</th><th>Servicio</th><th>Estatus</th><th>Ultimo acceso</th></tr></thead>
                  <tbody>
                    <?php foreach ($portalUsers as $portalUser): ?>
                      <tr>
                        <td><strong><?php echo h($portalUser['company_name']); ?></strong><span><?php echo h($portalUser['contact_name']); ?></span></td>
                        <td><?php echo h($portalUser['service']); ?></td>
                        <td><?php echo (int) $portalUser['is_active'] ? 'Activo' : 'Inactivo'; ?></td>
                        <td><?php echo h($portalUser['last_login_at'] ?: 'Sin acceso'); ?></td>
                      </tr>
                    <?php endforeach; ?>
                    <?php if (!$portalUsers): ?><tr><td colspan="4">No hay accesos de clientes registrados.</td></tr><?php endif; ?>
                  </tbody>
                </table>
              </section>

              <footer class="crm-print-footer"><span>ID Industrial - Bitacora ID</span><span>Documento de seguimiento operativo</span></footer>
            </section>
          </section>
          <div class="crm-stack crm-bitacora-stack">
            <article class="crm-card crm-notification-panel" id="reportes-recibidos">
              <div class="crm-section-head">
                <div><h2>Reportes recibidos</h2><p>Notificaciones internas generadas cuando un cliente levanta o actualiza un reporte.</p></div>
                <?php if ($adminUnreadNotifications > 0): ?>
                  <form method="post" class="crm-inline-form">
                    <input type="hidden" name="token" value="<?php echo h($token); ?>">
                    <input type="hidden" name="action" value="mark_all_notifications_read">
                    <button class="crm-button crm-button--ghost" type="submit">Marcar todo leido</button>
                  </form>
                <?php endif; ?>
              </div>
              <div class="crm-list crm-notification-list">
                <?php foreach ($adminNotifications as $notification): ?>
                  <?php $notificationIsUnread = (int) ($notification['is_read'] ?? 0) === 0; ?>
                  <div class="crm-list__item crm-notification-item <?php echo $notificationIsUnread ? 'is-unread' : ''; ?>">
                    <div class="crm-request-card__head">
                      <span class="crm-pill <?php echo $notificationIsUnread ? 'crm-pill--warning' : 'crm-pill--neutral'; ?>"><?php echo $notificationIsUnread ? 'Nuevo' : 'Leido'; ?></span>
                      <small><?php echo h($notification['created_at']); ?></small>
                    </div>
                    <strong><?php echo h($notification['title']); ?></strong>
                    <p><?php echo h($notification['message']); ?></p>
                    <div class="crm-request-meta">
                      <span><strong>Cliente</strong><?php echo h($notification['company_name'] ?: 'Sin cliente'); ?></span>
                      <span><strong>Servicio</strong><?php echo h($notification['service'] ?: 'Sin servicio'); ?></span>
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
                          <?php if (!empty($notification['request_actions_taken'])): ?><section><strong>Acciones realizadas por el cliente</strong><p><?php echo nl2br(h($notification['request_actions_taken'])); ?></p></section><?php endif; ?>
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
                      <?php if (!empty($notification['target_url'])): ?><a class="crm-button" href="<?php echo h(crm_clean_internal_url($notification['target_url'], 'admin')); ?>"><?php echo ($notification['event_type'] ?? '') === 'web_lead_received' ? 'Abrir oportunidad' : 'Atender reporte'; ?></a><?php endif; ?>
                      <?php if ($notificationIsUnread): ?>
                        <form method="post">
                          <input type="hidden" name="token" value="<?php echo h($token); ?>">
                          <input type="hidden" name="action" value="mark_notification_read">
                          <input type="hidden" name="notification_id" value="<?php echo (int) $notification['id']; ?>">
                          <input type="hidden" name="return_to" value="<?php echo h(crm_admin_url('bitacora', 0, [], 'reportes-recibidos')); ?>">
                          <button class="crm-button crm-button--ghost" type="submit">Marcar leida</button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
                <?php if (!$adminNotifications): ?><p>No hay notificaciones de reportes recibidos.</p><?php endif; ?>
              </div>
            </article>

            <?php if ($missingPortalOpportunities): ?>
              <article class="crm-card">
                <div class="crm-section-head"><div><h2>Accesos pendientes</h2><p>Clientes con proyecto entregado que aun no tienen usuario de Bitacora ID.</p></div><span class="crm-pill crm-pill--warning"><?php echo $missingPortalCount; ?> pendiente(s)</span></div>
                <div class="crm-table-wrap">
                  <table class="crm-table">
                    <thead><tr><th>Cliente</th><th>Servicio</th><th>Contacto</th><th>Estatus</th><th>Accion</th></tr></thead>
                    <tbody>
                      <?php foreach ($missingPortalOpportunities as $pendingPortal): ?>
                        <tr>
                          <td><strong><?php echo h($pendingPortal['company_name']); ?></strong></td>
                          <td><?php echo h($pendingPortal['service']); ?></td>
                          <td><?php echo h($pendingPortal['contact_name']); ?><br><small><?php echo h($pendingPortal['contact_email'] ?: 'Sin correo; comparte el acceso manualmente'); ?></small></td>
                          <td><span class="crm-pill crm-pill--warning"><?php echo h($pendingPortal['status']); ?></span></td>
                          <td>
                            <form method="post">
                              <input type="hidden" name="token" value="<?php echo h($token); ?>">
                              <input type="hidden" name="action" value="activate_portal_access">
                              <input type="hidden" name="opportunity_id" value="<?php echo (int) $pendingPortal['id']; ?>">
                              <input type="hidden" name="return_to" value="bitacora">
                              <button class="crm-button" type="submit">Activar acceso</button>
                            </form>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </article>
            <?php endif; ?>

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
                    <?php if (!$portalUsers): ?><tr><td colspan="6">No hay accesos activos. Activa uno desde la lista de pendientes.</td></tr><?php endif; ?>
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
                  <?php $requestDueDate = trim((string) ($request['due_date'] ?? '')) ?: crm_request_due_date($requestPriority, (string) ($request['created_at'] ?? 'now')); ?>
                  <?php $requestScheduledDate = trim((string) ($request['scheduled_date'] ?? '')); ?>
                  <?php $requestResolvedAt = trim((string) ($request['resolved_at'] ?? '')); ?>
                  <?php $requestAssignedTo = trim((string) ($request['assigned_to'] ?? '')); ?>
                  <?php $requestIsOverdue = !crm_request_is_final($requestStatus) && $requestDueDate !== '' && $requestDueDate < date('Y-m-d'); ?>
                  <form id="request-<?php echo (int) $request['id']; ?>" class="crm-list__item crm-request-card <?php echo $requestIsOverdue ? 'crm-request-card--overdue' : ''; ?>" method="post">
                    <input type="hidden" name="token" value="<?php echo h($token); ?>">
                    <input type="hidden" name="action" value="update_client_request">
                    <input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">
                    <div class="crm-request-card__head">
                      <span class="crm-pill <?php echo h(crm_pill_class($requestStatus)); ?>"><?php echo h($requestStatus); ?></span>
                      <small><?php echo h($request['company_name']); ?> - <?php echo h($request['created_at']); ?></small>
                    </div>
                    <div class="crm-request-title"><span><?php echo h($request['category'] ?? 'Mantenimiento correctivo'); ?></span><strong><?php echo h($request['title']); ?></strong></div>
                    <div class="crm-request-meta">
                      <span><strong>Prioridad</strong><?php echo h($requestPriority); ?></span>
                      <span><strong>Ubicacion</strong><?php echo h($request['location'] ?: 'Sin especificar'); ?></span>
                      <span><strong>Impacto</strong><?php echo h($request['impact'] ?? 'Sin paro'); ?></span>
                      <span><strong>Incidente</strong><?php echo h($request['occurred_at'] ?: $request['created_at']); ?></span>
                    </div>
                    <div class="crm-report-details__body crm-report-details__body--static">
                      <div class="crm-report-detail-grid">
                        <span><strong>Equipo afectado</strong><?php echo h($request['equipment'] ?: 'No especificado'); ?></span>
                        <span><strong>Fecha objetivo</strong><?php echo h($requestDueDate); ?><?php echo $requestIsOverdue ? ' - vencida' : ''; ?></span>
                        <span><strong>Fecha programada</strong><?php echo h($requestScheduledDate ?: 'Sin fecha'); ?></span><span><strong>Fecha real de resolucion</strong><?php echo h($requestResolvedAt ?: 'Pendiente'); ?></span>
                        <span><strong>Responsable</strong><?php echo h($requestAssignedTo ?: 'Sin asignar'); ?></span>
                      </div>
                      <section><strong>Descripcion enviada por el cliente</strong><p><?php echo nl2br(h($request['message'])); ?></p></section>
                      <?php if (!empty($request['actions_taken'])): ?><section><strong>Acciones realizadas</strong><p><?php echo nl2br(h($request['actions_taken'])); ?></p></section><?php endif; ?>
                      <?php if (!empty($request['evidence_path'])): ?>
                        <a class="crm-evidence-card" href="<?php echo h(crm_evidence_url((int) $request['id'])); ?>" target="_blank" rel="noopener">
                          <img src="<?php echo h(crm_evidence_url((int) $request['id'])); ?>" alt="Evidencia del reporte <?php echo h($request['title']); ?>" loading="lazy">
                          <span><strong>Fotografia de evidencia</strong><small><?php echo h($request['evidence_original_name'] ?: 'Abrir evidencia'); ?></small></span>
                        </a>
                      <?php endif; ?>
                    </div>
                    <?php if ($requestIsOverdue || $requestPriority === 'Urgente'): ?>
                      <p class="crm-request-alert"><strong>Atencion prioritaria:</strong> <?php echo $requestIsOverdue ? 'Reporte vencido. ' : ''; ?><?php echo $requestPriority === 'Urgente' ? 'Prioridad urgente.' : ''; ?></p>
                    <?php endif; ?>
                    <p class="crm-request-next"><strong>Siguiente accion:</strong> <?php echo h(crm_request_next_step($requestStatus)); ?></p>
                    <?php if (!empty($request['admin_response'])): ?><div class="crm-response"><strong>Respuesta actual</strong><p><?php echo h($request['admin_response']); ?></p></div><?php endif; ?>
                    <div class="crm-form-grid crm-form-grid--request">
                      <label class="crm-field"><span>Estatus</span>
                        <select name="status"><?php foreach ($requestStatuses as $status): ?><option <?php echo $status === $requestStatus ? 'selected' : ''; ?>><?php echo h($status); ?></option><?php endforeach; ?></select>
                        <small>Indica la etapa operativa. Resuelta o Cerrada registra automaticamente la fecha real de resolucion.</small>
                      </label>
                      <label class="crm-field"><span>Prioridad</span>
                        <select name="priority"><?php foreach ($requestPriorities as $priority): ?><option <?php echo $priority === $requestPriority ? 'selected' : ''; ?>><?php echo h($priority); ?></option><?php endforeach; ?></select>
                        <small>Define la urgencia y el SLA sugerido para la fecha objetivo.</small>
                      </label>
                      <label class="crm-field"><span>Fecha objetivo</span><input type="date" name="due_date" value="<?php echo h($requestDueDate); ?>"><small>Limite interno para completar la atencion; sirve para detectar vencimientos.</small></label>
                      <label class="crm-field"><span>Fecha programada</span><input type="date" name="scheduled_date" value="<?php echo h($requestScheduledDate); ?>"><small>Dia acordado para visita, mantenimiento o intervencion tecnica.</small></label>
                      <div class="crm-field crm-readonly-field"><span>Fecha real de resolucion</span><output><?php echo h($requestResolvedAt ?: 'Se asignara al resolver'); ?></output><small>La genera el sistema al cambiar el estatus a Resuelta o Cerrada; no se captura manualmente.</small></div>
                      <label class="crm-field"><span>Responsable</span><input name="assigned_to" value="<?php echo h($requestAssignedTo); ?>" placeholder="Tecnico o area responsable"><small>Persona o equipo que ejecutara y documentara la atencion.</small></label>
                      <label class="crm-field crm-field--wide"><span>Respuesta para el cliente</span><textarea name="admin_response" rows="3"><?php echo h($request['admin_response'] ?? ''); ?></textarea><small>Mensaje visible en el portal; explica avance, acuerdos o solucion sin notas internas.</small></label>
                      <label class="crm-field crm-field--wide"><span>Notas internas</span><textarea name="internal_notes" rows="3"><?php echo h($request['internal_notes'] ?? ''); ?></textarea><small>Contexto exclusivo del equipo: diagnostico, riesgos, refacciones o pendientes.</small></label>
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
      const adminMenuToggle = document.querySelector('[data-menu-toggle]');
      const adminMenuClose = document.querySelector('[data-menu-close]');
      const adminSidebar = document.getElementById('crm-sidebar');
      const setAdminMenu = (open) => {
        document.body.classList.toggle('crm-menu-open', open);
        if (adminMenuToggle) {
          adminMenuToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
          adminMenuToggle.setAttribute('aria-label', open ? 'Cerrar menu' : 'Abrir menu');
        }
      };
      if (adminMenuToggle && adminSidebar) {
        adminMenuToggle.addEventListener('click', () => setAdminMenu(!document.body.classList.contains('crm-menu-open')));
      }
      if (adminMenuClose) {
        adminMenuClose.addEventListener('click', () => setAdminMenu(false));
      }
      document.querySelectorAll('.crm-nav a').forEach((link) => {
        link.addEventListener('click', () => setAdminMenu(false));
      });
      window.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') setAdminMenu(false);
      });
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
