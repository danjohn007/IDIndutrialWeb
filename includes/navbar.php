<?php
$primaryNavItems = [
  ['label' => 'Inicio', 'href' => '#inicio'],
  ['label' => 'Quiénes somos', 'href' => '#quienes-somos'],
  ['label' => 'Cableado estructurado', 'href' => '#cableado-estructurado'],
  ['label' => 'Detección de incendios', 'href' => '#deteccion-incendios'],
];

$moreServiceItems = [
  ['label' => 'Sistemas HVAC', 'href' => '#sistemas-hvac'],
  ['label' => 'Fibra óptica', 'href' => '#fibra-optica'],
  ['label' => 'Control de Accesos', 'href' => '#control-accesos'],
];

$secondaryNavItems = [
  ['label' => 'Bitácora ID', 'href' => '#bitacora-id'],
  ['label' => 'Contacto', 'href' => '#contacto'],
];
?>

<header class="site-header" data-header>
  <nav class="nav container" aria-label="Mapa de navegación principal">
    <a class="brand" href="#inicio" aria-label="ID Industrial inicio">
      <img src="assets/img/logo-idindustrial.png" alt="ID Industrial" width="500" height="132">
    </a>
    <button class="nav-toggle" type="button" aria-label="Abrir menú de navegación" aria-controls="main-menu" aria-expanded="false" data-nav-toggle>
      <span></span>
      <span></span>
      <span></span>
    </button>
    <div class="nav-menu" id="main-menu" data-nav-menu>
      <?php foreach ($primaryNavItems as $item): ?>
        <a href="<?php echo htmlspecialchars($item['href']); ?>"><?php echo htmlspecialchars($item['label']); ?></a>
      <?php endforeach; ?>

      <div class="nav-dropdown" data-dropdown>
        <button class="nav-dropdown__button" type="button" aria-expanded="false" aria-controls="services-menu" data-dropdown-toggle>
          Más servicios
          <span aria-hidden="true"></span>
        </button>
        <div class="nav-dropdown__menu" id="services-menu" data-dropdown-menu>
          <?php foreach ($moreServiceItems as $item): ?>
            <a href="<?php echo htmlspecialchars($item['href']); ?>"><?php echo htmlspecialchars($item['label']); ?></a>
          <?php endforeach; ?>
        </div>
      </div>

      <?php foreach ($secondaryNavItems as $item): ?>
        <a href="<?php echo htmlspecialchars($item['href']); ?>"><?php echo htmlspecialchars($item['label']); ?></a>
      <?php endforeach; ?>
    </div>
    <a class="nav-cta" href="#contacto">Cotizar</a>
  </nav>
</header>
