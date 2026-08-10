<?php
$assetBase = $basePath ?? '';
$currentPath = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '', '/');
$currentSection = $currentSection ?? '';
$clientPortalHref = $assetBase . 'crm/portal';

function idindustrial_is_active_nav(string $href, string $currentPath, string $currentSection): bool
{
  $hrefPath = trim(parse_url($href, PHP_URL_PATH) ?? '', '/');
  $hrefPath = trim(str_replace('../', '', $hrefPath), '/');
  $hrefPath = preg_replace('#^sistema/#', '', $hrefPath);
  $normalizedCurrentPath = preg_replace('#^sistema/#', '', $currentPath);
  $hrefFragment = parse_url($href, PHP_URL_FRAGMENT) ?? '';

  if ($hrefPath !== '' && substr($normalizedCurrentPath, -strlen($hrefPath)) === $hrefPath) {
    return true;
  }

  return $hrefFragment !== '' && $hrefFragment === $currentSection;
}

$primaryNavItems = [
  ['label' => 'Inicio', 'href' => $assetBase . '#inicio'],
  ['label' => 'Quiénes somos', 'href' => $assetBase . '#quienes-somos'],
  ['label' => 'Cableado estructurado', 'href' => $assetBase . 'industriales/cableado-estructurado-queretaro/'],
  ['label' => 'Detección de incendios', 'href' => $assetBase . '#deteccion-incendios'],
];

$moreServiceItems = [
  ['label' => 'Sistemas HVAC', 'href' => $assetBase . 'instalacion-aire-acondicionado-industrial-queretaro/'],
  ['label' => 'CCTV industrial', 'href' => $assetBase . 'instalacion-camaras-seguridad-industrial-queretaro/'],
  ['label' => 'Fibra óptica', 'href' => $assetBase . '#fibra-optica'],
  ['label' => 'Control de Accesos', 'href' => $assetBase . 'control-de-acceso-de-personal-queretaro/'],
];

$secondaryNavItems = [
  ['label' => 'Bitácora ID', 'href' => $assetBase . '#bitacora-id'],
];
?>

<header class="site-header" data-header>
  <nav class="nav container" aria-label="Mapa de navegación principal">
    <a class="brand" href="<?php echo htmlspecialchars($assetBase); ?>#inicio" aria-label="ID Industrial inicio">
      <img src="<?php echo htmlspecialchars($assetBase); ?>assets/img/logo-idindustrial-small.webp" alt="ID Industrial" width="280" height="74" fetchpriority="high" decoding="async">
    </a>
    <button class="nav-toggle" type="button" aria-label="Abrir menú de navegación" aria-controls="main-menu" aria-expanded="false" data-nav-toggle>
      <span></span>
      <span></span>
      <span></span>
    </button>
    <div class="nav-menu" id="main-menu" data-nav-menu>
      <?php foreach ($primaryNavItems as $item): ?>
        <?php $isActive = idindustrial_is_active_nav($item['href'], $currentPath, $currentSection); ?>
        <a href="<?php echo htmlspecialchars($item['href']); ?>" <?php echo $isActive ? 'class="is-active" aria-current="page"' : ''; ?>><?php echo htmlspecialchars($item['label']); ?></a>
      <?php endforeach; ?>

      <div class="nav-dropdown" data-dropdown>
        <button class="nav-dropdown__button" type="button" aria-expanded="false" aria-controls="services-menu" data-dropdown-toggle>
          Más servicios
          <span aria-hidden="true"></span>
        </button>
        <div class="nav-dropdown__menu" id="services-menu" data-dropdown-menu>
          <?php foreach ($moreServiceItems as $item): ?>
            <?php $isActive = idindustrial_is_active_nav($item['href'], $currentPath, $currentSection); ?>
            <a href="<?php echo htmlspecialchars($item['href']); ?>" <?php echo $isActive ? 'class="is-active" aria-current="page"' : ''; ?>><?php echo htmlspecialchars($item['label']); ?></a>
          <?php endforeach; ?>
        </div>
      </div>

      <?php foreach ($secondaryNavItems as $item): ?>
        <?php $isActive = idindustrial_is_active_nav($item['href'], $currentPath, $currentSection); ?>
        <a href="<?php echo htmlspecialchars($item['href']); ?>" <?php echo $isActive ? 'class="is-active" aria-current="page"' : ''; ?>><?php echo htmlspecialchars($item['label']); ?></a>
      <?php endforeach; ?>

      <a class="nav-client-entry" href="<?php echo htmlspecialchars($clientPortalHref); ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 11V8a7 7 0 0 1 14 0v3"/><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M12 15v2"/></svg>
        <span><small>Portal Bitácora ID</small><strong>Iniciar sesión</strong></span>
      </a>

      <div class="nav-menu__contact">
        <span>Atención directa</span>
        <strong><?php echo htmlspecialchars($phone ?? '+52 442 598 6318'); ?></strong>
      </div>
    </div>
    <a class="nav-cta nav-client-login" href="<?php echo htmlspecialchars($clientPortalHref); ?>" aria-label="Iniciar sesión en el portal de clientes">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 11V8a7 7 0 0 1 14 0v3"/><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M12 15v2"/></svg>
      <span>Acceso clientes</span>
    </a>
  </nav>
</header>
