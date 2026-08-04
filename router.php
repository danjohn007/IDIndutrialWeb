<?php
declare(strict_types=1);

$documentRoot = realpath(__DIR__) ?: __DIR__;
$requestPath = rawurldecode((string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? '/'));
$relativePath = trim(str_replace('\\', '/', $requestPath), '/');

$requestedFile = realpath($documentRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
if ($relativePath !== '' && $requestedFile && str_starts_with($requestedFile, $documentRoot . DIRECTORY_SEPARATOR) && (is_file($requestedFile) || is_dir($requestedFile))) {
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
  '#^crm/portal/notificaciones/?$#' => ['crm/cliente.php', ['view' => 'notificaciones']],
  '#^crm/portal/perfil/?$#' => ['crm/cliente.php', ['view' => 'perfil']],
  '#^crm/portal/?$#' => ['crm/cliente.php', ['view' => 'resumen']],
  '#^crm/notificaciones/estado/?$#' => ['crm/index.php', ['notification_poll' => '1']],
  '#^crm/salir/?$#' => ['crm/index.php', ['logout' => '1']],
  '#^crm/oportunidades/?$#' => ['crm/index.php', ['view' => 'opportunities']],
  '#^crm/cotizaciones/?$#' => ['crm/index.php', ['view' => 'quotes']],
  '#^crm/clientes/?$#' => ['crm/index.php', ['view' => 'clients']],
  '#^crm/notificaciones/?$#' => ['crm/index.php', ['view' => 'bitacora', 'notifications' => '1']],
  '#^crm/bitacora/?$#' => ['crm/index.php', ['view' => 'bitacora']],
  '#^crm/perfil/?$#' => ['crm/index.php', ['view' => 'profile']],
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

http_response_code(404);
header('Content-Type: text/plain; charset=UTF-8');
echo 'Ruta no encontrada.';