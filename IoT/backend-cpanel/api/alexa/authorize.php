<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/lib/alexa.php';

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');

session_name('idind_alexa_oauth');
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => true,
    'cookie_samesite' => 'Lax',
    'use_strict_mode' => true,
]);

function alexaHtml(string $valor): string
{
    return htmlspecialchars($valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function alexaRedirect(string $uri, array $parametros): void
{
    $separador = strpos($uri, '?') === false ? '?' : '&';
    header('Location: ' . $uri . $separador . http_build_query($parametros, '', '&', PHP_QUERY_RFC3986), true, 302);
    exit;
}

$entrada = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST' ? $_POST : $_GET;
$clientId = trim((string) ($entrada['client_id'] ?? ''));
$redirectUri = trim((string) ($entrada['redirect_uri'] ?? ''));
$responseType = trim((string) ($entrada['response_type'] ?? ''));
$scope = trim((string) ($entrada['scope'] ?? 'smart_home'));
$state = trim((string) ($entrada['state'] ?? ''));
$codeChallenge = trim((string) ($entrada['code_challenge'] ?? ''));
$codeChallengeMethod = strtoupper(trim((string) ($entrada['code_challenge_method'] ?? '')));
$error = '';

try {
    idindAlexaRequerirMigracion($pdo);
    $alexa = idindAlexaRequerirConfig($configLocal);
    if (!hash_equals($alexa['client_id'], $clientId)) {
        throw new IdindAlexaException('Cliente OAuth no reconocido');
    }
    if (!idindAlexaRedirectPermitido($alexa, $redirectUri)) {
        throw new IdindAlexaException('La URL de retorno no esta autorizada');
    }
    if ($responseType !== 'code') {
        throw new IdindAlexaException('Alexa envio un tipo OAuth no compatible. Verifica que Account Linking use Auth Code Grant.');
    }
    if ($state === '') {
        throw new IdindAlexaException('Alexa no envio el parametro de seguridad state. Guarda nuevamente Account Linking e intenta otra vez.');
    }
    if (strlen($state) > 8192) {
        throw new IdindAlexaException('El parametro de seguridad state enviado por Alexa es demasiado grande.');
    }
    if (strlen($scope) > 1024) {
        throw new IdindAlexaException('El alcance OAuth enviado por Alexa es demasiado grande.');
    }
    if ($codeChallenge !== '' && ($codeChallengeMethod !== 'S256' || !preg_match('/^[A-Za-z0-9_-]{43,128}$/', $codeChallenge))) {
        throw new IdindAlexaException('PKCE no valido');
    }

    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
        $csrf = (string) ($_POST['csrf'] ?? '');
        if (empty($_SESSION['alexa_csrf']) || !hash_equals((string) $_SESSION['alexa_csrf'], $csrf)) {
            throw new IdindAlexaException('La sesion de vinculacion vencio. Inicia nuevamente desde Alexa.');
        }
        if ((string) ($_POST['decision'] ?? '') === 'cancelar') {
            alexaRedirect($redirectUri, ['error' => 'access_denied', 'state' => $state]);
        }

        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $stmt = $pdo->prepare(
            "SELECT id, cliente_id, nombre, email, password_hash, rol, estado, bloqueado_hasta
             FROM usuarios WHERE email = :email LIMIT 1"
        );
        $stmt->execute(['email' => $email]);
        $usuario = $stmt->fetch();
        $bloqueado = $usuario && $usuario['bloqueado_hasta']
            && strtotime((string) $usuario['bloqueado_hasta'] . ' UTC') > time();
        if (
            !$usuario
            || !password_verify($password, (string) $usuario['password_hash'])
            || (string) $usuario['estado'] !== 'ACTIVO'
            || !in_array((string) $usuario['rol'], ['ADMIN', 'OPERADOR'], true)
            || $bloqueado
        ) {
            usleep(250000);
            $error = 'Correo, password o permisos incorrectos.';
        } else {
            $codigo = idindAlexaTokenAleatorio(48);
            $pdo->prepare(
                'DELETE FROM alexa_oauth_codes
                 WHERE usuario_id = :usuario_id AND (usado_en IS NOT NULL OR expira_en < UTC_TIMESTAMP())'
            )->execute(['usuario_id' => (int) $usuario['id']]);
            $pdo->prepare(
                'INSERT INTO alexa_oauth_codes (
                   usuario_id, client_id, code_hash, redirect_uri, scope,
                   code_challenge, code_challenge_method, expira_en
                 ) VALUES (
                   :usuario_id, :client_id, :code_hash, :redirect_uri, :scope,
                   :challenge, :challenge_method, UTC_TIMESTAMP() + INTERVAL 5 MINUTE
                 )'
            )->execute([
                'usuario_id' => (int) $usuario['id'],
                'client_id' => $clientId,
                'code_hash' => hash('sha256', $codigo),
                'redirect_uri' => $redirectUri,
                'scope' => $scope === '' ? 'smart_home' : $scope,
                'challenge' => $codeChallenge === '' ? null : $codeChallenge,
                'challenge_method' => $codeChallenge === '' ? null : 'S256',
            ]);
            unset($_SESSION['alexa_csrf']);
            alexaRedirect($redirectUri, ['code' => $codigo, 'state' => $state]);
        }
    }
} catch (Throwable $exception) {
    $error = $exception->getMessage();
}

if (empty($_SESSION['alexa_csrf'])) {
    $_SESSION['alexa_csrf'] = idindAlexaTokenAleatorio(32);
}
$campos = [
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'response_type' => $responseType,
    'scope' => $scope,
    'state' => $state,
    'code_challenge' => $codeChallenge,
    'code_challenge_method' => $codeChallengeMethod,
];
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Vincular Alexa | ID Industrial</title>
  <style>
    :root{color-scheme:dark;--bg:#12161b;--surface:#1e252b;--field:#151b20;--line:#34434d;--text:#e0e0e0;--muted:#9fb1bd;--yellow:#ffb000;--red:#ff453a}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font:16px/1.5 system-ui,-apple-system,Segoe UI,sans-serif;min-height:100vh;display:grid;place-items:center;padding:24px}.panel{width:min(100%,520px);background:var(--surface);border:1px solid var(--line);border-radius:8px;padding:32px}.brand{color:var(--yellow);font-size:14px;font-weight:800}.title{font-size:32px;margin:8px 0}.copy{color:var(--muted);margin:0 0 24px}.notice{border-left:3px solid var(--yellow);background:var(--field);padding:12px 14px;margin-bottom:20px}.error{color:var(--red);margin-bottom:16px}.field{display:grid;gap:7px;margin:14px 0}.field label{font-weight:700}.field input{width:100%;background:var(--field);border:1px solid var(--line);border-radius:6px;color:var(--text);font:inherit;padding:14px}.actions{display:grid;grid-template-columns:1fr 2fr;gap:12px;margin-top:24px}button{border:1px solid var(--line);border-radius:6px;font:inherit;font-weight:800;padding:14px;cursor:pointer}.cancel{background:transparent;color:var(--text)}.allow{background:var(--yellow);border-color:var(--yellow);color:#111}@media(max-width:480px){.panel{padding:22px}.actions{grid-template-columns:1fr}.title{font-size:27px}}
  </style>
</head>
<body>
  <main class="panel">
    <div class="brand">ID INDUSTRIAL + AMAZON ALEXA</div>
    <h1 class="title">Vincular cuenta</h1>
    <p class="copy">Autoriza a Alexa para descubrir y controlar exclusivamente tus canales Shelly de automatizacion.</p>
    <div class="notice">Las sirenas, balizas y equipos clasificados como Seguridad nunca se comparten con Alexa.</div>
    <?php if ($error !== ''): ?><div class="error"><?= alexaHtml($error) ?></div><?php endif; ?>
    <?php if ($redirectUri !== '' && isset($alexa)): ?>
    <form method="post" autocomplete="on">
      <?php foreach ($campos as $nombre => $valor): ?><input type="hidden" name="<?= alexaHtml($nombre) ?>" value="<?= alexaHtml((string) $valor) ?>"><?php endforeach; ?>
      <input type="hidden" name="csrf" value="<?= alexaHtml((string) $_SESSION['alexa_csrf']) ?>">
      <div class="field"><label for="email">Correo de ID Industrial</label><input id="email" name="email" type="email" maxlength="160" required autocomplete="username"></div>
      <div class="field"><label for="password">Password</label><input id="password" name="password" type="password" maxlength="200" required autocomplete="current-password"></div>
      <div class="actions"><button class="cancel" type="submit" name="decision" value="cancelar">Cancelar</button><button class="allow" type="submit" name="decision" value="autorizar">Vincular Alexa</button></div>
    </form>
    <?php endif; ?>
  </main>
</body>
</html>
