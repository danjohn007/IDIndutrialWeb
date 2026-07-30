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
  unset($_SESSION['bitacora_user'], $_SESSION['bitacora_token']);
  header('Location: cliente.php');
  exit;
}

$loginError = '';
if (($_POST['action'] ?? '') === 'client_login') {
  $username = trim((string) ($_POST['bitacora_user'] ?? ''));
  $password = (string) ($_POST['bitacora_password'] ?? '');
  if ($username === '' || strlen($password) < 8) {
    $loginError = 'Ingresa usuario y password validos.';
  } else {
    $portalUser = crm_portal_user_by_username($pdo, $username);
    if ($portalUser && password_verify($password, $portalUser['password_hash'])) {
      session_regenerate_id(true);
      crm_update_portal_last_login($pdo, (int) $portalUser['id']);
      $_SESSION['bitacora_user'] = [
        'id' => (int) $portalUser['id'],
        'opportunity_id' => (int) $portalUser['opportunity_id'],
        'username' => $portalUser['username'],
        'company_name' => $portalUser['company_name'],
        'contact_name' => $portalUser['contact_name'],
        'service' => $portalUser['service'],
      ];
      bitacora_token();
      header('Location: cliente.php');
      exit;
    }
    $loginError = 'Acceso incorrecto o inactivo.';
  }
}

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
        <button class="crm-button" type="submit">Entrar a Bitacora ID</button>
      </form>
    </section>
  </main>
  <script>
    (() => {
      const form = document.querySelector('[data-login-form]');
      const password = document.querySelector('[data-login-password]');
      const toggle = document.querySelector('[data-password-toggle]');
      if (!form || !password || !toggle) return;
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
$notice = null;
if (($_POST['action'] ?? '') === 'create_request') {
  bitacora_check_token();
  $title = trim((string) ($_POST['title'] ?? ''));
  $message = trim((string) ($_POST['message'] ?? ''));
  if ($title === '' || $message === '') {
    $notice = ['type' => 'error', 'text' => 'Completa asunto y descripcion de la solicitud.'];
  } else {
    $stmt = $pdo->prepare('INSERT INTO client_requests (opportunity_id, portal_user_id, title, message, status) VALUES (?, ?, ?, ?, "Recibida")');
    $stmt->execute([(int) $portal['opportunity_id'], (int) $portal['id'], $title, $message]);
    $notice = ['type' => 'success', 'text' => 'Solicitud recibida. El equipo de ID Industrial dara seguimiento.'];
  }
}

$token = bitacora_token();
$projectStmt = $pdo->prepare('SELECT * FROM opportunities WHERE id = ? LIMIT 1');
$projectStmt->execute([(int) $portal['opportunity_id']]);
$project = $projectStmt->fetch();
$logsStmt = $pdo->prepare('SELECT * FROM maintenance_logs WHERE opportunity_id = ? AND visible_to_client = 1 ORDER BY COALESCE(scheduled_date, created_at) DESC, id DESC');
$logsStmt->execute([(int) $portal['opportunity_id']]);
$logs = $logsStmt->fetchAll();
$requestsStmt = $pdo->prepare('SELECT * FROM client_requests WHERE portal_user_id = ? ORDER BY created_at DESC');
$requestsStmt->execute([(int) $portal['id']]);
$requests = $requestsStmt->fetchAll();
?>
<!doctype html>
<html lang="es-MX">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Bitacora ID | <?php echo h($portal['company_name']); ?></title>
  <link rel="stylesheet" href="../assets/css/crm.css">
</head>
<body class="crm-app crm-client-app">
  <main class="crm-client-shell">
    <header class="crm-client-hero">
      <div>
        <img src="../assets/img/logo-idindustrial-small.webp" alt="ID Industrial" width="280" height="74">
        <p class="eyebrow">Bitacora ID</p>
        <h1><?php echo h($portal['company_name']); ?></h1>
        <p><?php echo h($portal['service']); ?> · <?php echo h($project['status'] ?? 'Proyecto entregado'); ?></p>
      </div>
      <a class="crm-button crm-button--ghost" href="cliente.php?logout=1">Cerrar sesion</a>
    </header>

    <?php if ($notice): ?><div class="crm-flash crm-flash--<?php echo h($notice['type']); ?>"><p><?php echo h($notice['text']); ?></p></div><?php endif; ?>

    <section class="crm-kpis">
      <article class="crm-card crm-kpi"><span class="crm-kpi__icon">P</span><div><span>Proyecto</span><strong><?php echo h($project['status'] ?? 'Entregado'); ?></strong></div></article>
      <article class="crm-card crm-kpi"><span class="crm-kpi__icon">M</span><div><span>Registros</span><strong><?php echo count($logs); ?></strong></div></article>
      <article class="crm-card crm-kpi"><span class="crm-kpi__icon">S</span><div><span>Solicitudes</span><strong><?php echo count($requests); ?></strong></div></article>
      <article class="crm-card crm-kpi"><span class="crm-kpi__icon">ID</span><div><span>Usuario</span><strong><?php echo h($portal['username']); ?></strong></div></article>
    </section>

    <section class="crm-grid">
      <article class="crm-card">
        <h2>Bitacora de mantenimiento</h2>
        <div class="crm-list">
          <?php foreach ($logs as $log): ?>
            <div class="crm-list__item">
              <span class="crm-pill crm-pill--success"><?php echo h($log['status']); ?></span>
              <strong><?php echo h($log['title']); ?></strong>
              <p><?php echo h($log['notes']); ?></p>
              <small><?php echo h($log['type']); ?> · <?php echo h($log['scheduled_date'] ?: $log['created_at']); ?></small>
            </div>
          <?php endforeach; ?>
          <?php if (!$logs): ?><p>Aun no hay registros publicados para este proyecto.</p><?php endif; ?>
        </div>
      </article>

      <article class="crm-card">
        <h2>Solicitar mantenimiento</h2>
        <form class="crm-form" method="post">
          <input type="hidden" name="token" value="<?php echo h($token); ?>">
          <input type="hidden" name="action" value="create_request">
          <label class="crm-field">Asunto<input name="title" required></label>
          <label class="crm-field">Descripcion<textarea name="message" rows="5" required></textarea></label>
          <button class="crm-button" type="submit">Enviar solicitud</button>
        </form>

        <div class="crm-list crm-list--compact">
          <?php foreach ($requests as $request): ?>
            <div class="crm-list__item">
              <span class="crm-pill crm-pill--warning"><?php echo h($request['status']); ?></span>
              <strong><?php echo h($request['title']); ?></strong>
              <small><?php echo h($request['created_at']); ?></small>
            </div>
          <?php endforeach; ?>
        </div>
      </article>
    </section>
  </main>
</body>
</html>