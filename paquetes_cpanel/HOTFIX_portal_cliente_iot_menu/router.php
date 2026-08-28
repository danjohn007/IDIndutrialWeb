<?php
declare(strict_types=1);

$documentRoot = realpath(__DIR__) ?: __DIR__;
$requestPath = rawurldecode((string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? '/'));
$relativePath = trim(str_replace('\\', '/', $requestPath), '/');

$requestedFile = realpath($documentRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
$routeManagedPath = preg_match('#^(iot(?:/|$)|crm/iot/?$|crm/portal/iot/?$)#i', $relativePath) === 1;
if ($relativePath !== '' && !$routeManagedPath && $requestedFile && str_starts_with($requestedFile, $documentRoot . DIRECTORY_SEPARATOR) && (is_file($requestedFile) || is_dir($requestedFile))) {
  return false;
}

$dispatch = static function (string $script, array $routeQuery = []) use ($documentRoot): never {
  $_GET = $routeQuery + $_GET;
  $_REQUEST = $_GET + $_POST;
  $_SERVER['SCRIPT_NAME'] = '/' . ltrim(str_replace('\\', '/', $script), '/');
  $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
  require $documentRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $script);
  exit;
};

if ($relativePath === '') {
  $dispatch('index.php');
}

$routes = [
  '#^crm/portal/notificaciones/estado/?$#' => ['crm/cliente.php', ['notification_poll' => '1']],
  '#^crm/portal/salir/?$#' => ['crm/cliente.php', ['logout' => '1']],
  '#^crm/portal/proyectos/?$#' => ['crm/cliente.php', ['view' => 'proyectos']],
  '#^crm/portal/bitacora/?$#' => ['crm/cliente.php', ['view' => 'bitacora']],
  '#^crm/portal/solicitudes/?$#' => ['crm/cliente.php', ['view' => 'solicitudes']],
  '#^crm/portal/cotizaciones/?$#' => ['crm/cliente.php', ['view' => 'cotizaciones']],
  '#^crm/portal/notificaciones/?$#' => ['crm/cliente.php', ['view' => 'notificaciones']],
  '#^crm/portal/perfil/?$#' => ['crm/cliente.php', ['view' => 'perfil']],
  '#^crm/portal/iot/?$#' => ['crm/iot-sso.php', []],
  '#^crm/portal/?$#' => ['crm/cliente.php', ['view' => 'resumen']],
  '#^crm/notificaciones/estado/?$#' => ['crm/index.php', ['notification_poll' => '1']],
  '#^crm/salir/?$#' => ['crm/index.php', ['logout' => '1']],
  '#^crm/oportunidades/?$#' => ['crm/index.php', ['view' => 'opportunities']],
  '#^crm/cotizaciones/?$#' => ['crm/index.php', ['view' => 'quotes']],
  '#^crm/clientes/?$#' => ['crm/index.php', ['view' => 'clients']],
  '#^crm/notificaciones/?$#' => ['crm/index.php', ['view' => 'bitacora', 'notifications' => '1']],
  '#^crm/bitacora/?$#' => ['crm/index.php', ['view' => 'bitacora']],
  '#^crm/perfil/?$#' => ['crm/index.php', ['view' => 'profile']],
  '#^crm/configuracion/?$#' => ['crm/index.php', ['view' => 'settings']],
  '#^crm/iot/?$#' => ['crm/iot-sso.php', []],
  '#^crm/?$#' => ['crm/index.php', ['view' => 'dashboard']],
];

if (preg_match('#^crm/evidencias/([0-9]+)/?$#', $relativePath, $match)) {
  $dispatch('crm/evidence.php', ['id' => $match[1]]);
}
if (preg_match('#^crm/oportunidades/([0-9]+)/?$#', $relativePath, $match)) {
  $dispatch('crm/index.php', ['view' => 'opportunity', 'id' => $match[1]]);
}
if (preg_match('#^crm/cotizaciones/([0-9]+)/?$#', $relativePath, $match)) {
  $dispatch('crm/index.php', ['view' => 'quote', 'id' => $match[1]]);
}

foreach ($routes as $pattern => [$script, $routeQuery]) {
  if (preg_match($pattern, $relativePath)) {
    $dispatch($script, $routeQuery);
  }
}


// Modulo IoT: panel web en /iot/ y API en /iot/api/.
if ($relativePath === 'iot/login.html') {
  header('Location: /crm/', true, 302);
  exit;
}
if ($relativePath === 'iot' || $relativePath === 'iot/') {
  $iotIndex = $documentRoot . '/IoT/web/index.html';
  if (is_file($iotIndex)) {
    header('Content-Type: text/html; charset=UTF-8');
    readfile($iotIndex);
    exit;
  }
}
if (preg_match('#^iot/api/(.+)$#', $relativePath, $match)) {
  $iotApiScript = 'IoT/api/' . $match[1];
  $iotApiFile = realpath($documentRoot . '/' . $iotApiScript);
  $iotApiBase = $documentRoot . DIRECTORY_SEPARATOR . 'IoT' . DIRECTORY_SEPARATOR . 'api';
  if ($iotApiFile && str_starts_with($iotApiFile, $iotApiBase) && is_file($iotApiFile)) {
    $dispatch('IoT/api/' . $match[1]);
  }
}
if (preg_match('#^iot/(.+)$#', $relativePath, $match)) {
  $iotWebPath = 'IoT/web/' . $match[1];
  $iotWebFile = realpath($documentRoot . '/' . $iotWebPath);
  $iotWebBase = $documentRoot . DIRECTORY_SEPARATOR . 'IoT' . DIRECTORY_SEPARATOR . 'web';
  if ($iotWebFile && str_starts_with($iotWebFile, $iotWebBase) && is_file($iotWebFile)) {
    $iotExt = strtolower(pathinfo($iotWebFile, PATHINFO_EXTENSION));
    $iotMimes = [
      'css' => 'text/css; charset=UTF-8',
      'js' => 'application/javascript; charset=UTF-8',
      'html' => 'text/html; charset=UTF-8',
      'svg' => 'image/svg+xml',
      'png' => 'image/png',
      'jpg' => 'image/jpeg',
      'jpeg' => 'image/jpeg',
      'webp' => 'image/webp',
    ];
    if (isset($iotMimes[$iotExt])) {
      header('Content-Type: ' . $iotMimes[$iotExt]);
    }
    readfile($iotWebFile);
    exit;
  }
}
http_response_code(404);
header('Content-Type: text/plain; charset=UTF-8');
echo 'Ruta no encontrada.';
