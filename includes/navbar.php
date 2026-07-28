<header class="site-header" data-header>
  <nav class="nav container" aria-label="Mapa de navegación principal">
    <a class="brand" href="#inicio" aria-label="ID Industrial inicio">
      <img src="assets/img/logo-idindustrial.png" alt="ID Industrial">
    </a>
    <button class="nav-toggle" type="button" aria-controls="main-menu" aria-expanded="false" data-nav-toggle>
      <span></span>
      <span></span>
      <span></span>
    </button>
    <div class="nav-menu" id="main-menu" data-nav-menu>
      <?php foreach ($navItems as $item): ?>
        <a href="<?php echo htmlspecialchars($item['href']); ?>"><?php echo htmlspecialchars($item['label']); ?></a>
      <?php endforeach; ?>
    </div>
    <a class="nav-cta" href="#contacto">Cotizar</a>
  </nav>
</header>
