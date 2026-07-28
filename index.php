<?php
$siteUrl = 'https://idindustrial.com.mx/sistema/';
$publicOrigin = 'https://idindustrial.com.mx';
$assetUrlBase = 'https://idindustrial.com.mx/sistema/';
$phone = '+52 442 598 6318';
$whatsapp = '524425986318';
$contactEmail = 'contacto@idindustrial.com.mx';

$title = 'ID Industrial | Ingeniería industrial, cableado, HVAC y seguridad en Querétaro';
$description = 'Soluciones de ingeniería industrial en Querétaro: cableado estructurado, detección de incendios, sistemas HVAC, fibra óptica, control de accesos e infraestructura crítica.';
$keywords = 'ID Industrial, cableado estructurado Querétaro, detección de incendios industrial, sistemas HVAC Querétaro, fibra óptica industrial, control de accesos Querétaro';
$requestPath = strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/';
$canonicalUrl = rtrim($publicOrigin, '/') . ($requestPath === '/' ? '/' : $requestPath);
$heroMobileImage = 'assets/img/hero-mobile.webp';
$heroDesktopImage = 'assets/img/hero-desktop.webp';

$navItems = [
  ['label' => 'Inicio', 'href' => '#inicio'],
  ['label' => 'Quiénes somos', 'href' => '#quienes-somos'],
  ['label' => 'Cableado estructurado', 'href' => '#cableado-estructurado'],
  ['label' => 'Detección de incendios', 'href' => '#deteccion-incendios'],
  ['label' => 'Sistemas HVAC', 'href' => '#sistemas-hvac'],
  ['label' => 'Fibra óptica', 'href' => '#fibra-optica'],
  ['label' => 'Control de Accesos', 'href' => '#control-accesos'],
  ['label' => 'Bitácora ID', 'href' => '#bitacora-id'],
  ['label' => 'Contacto', 'href' => '#contacto'],
];

$services = [
  [
    'id' => 'cableado-estructurado',
    'eyebrow' => 'Infraestructura de red',
    'title' => 'Cableado estructurado para plantas, oficinas y sites industriales.',
    'copy' => 'Diseñamos e instalamos nodos, racks, canalizaciones, puntos de voz y datos, etiquetado técnico y pruebas para redes preparadas para operación continua.',
    'image' => 'assets/imagesnew/SLIDES2/CABLEADO%20ESTRUCTURADO/ID%20INDUSTRIAL%20WEB_5.jpg',
    'alt' => 'Instalación de cableado estructurado en Querétaro',
    'width' => 1920,
    'height' => 500,
    'detailHref' => 'industriales/cableado-estructurado-queretaro/',
    'detailLabel' => 'Ver más',
    'bullets' => ['Cableado UTP, fibra y canalización', 'Racks, patch panels y ordenamiento', 'Memoria técnica y pruebas de enlace'],
  ],
  [
    'id' => 'deteccion-incendios',
    'eyebrow' => 'Protección temprana',
    'title' => 'Detección de incendios con integración para áreas críticas.',
    'copy' => 'Implementamos paneles, sensores, sirenas, estaciones manuales y lógica de alerta para reducir tiempos de respuesta y proteger activos estratégicos.',
    'image' => 'assets/imagesnew/SLIDES2/SISTEMAS%20CONTRA%20INCENDIO/ID%20INDUSTRIAL%20WEB_8.jpg',
    'alt' => 'Sistema de detección de incendios industrial',
    'width' => 1920,
    'height' => 500,
    'detailHref' => '#contacto',
    'detailLabel' => 'Cotizar detección',
    'bullets' => ['Paneles y sensores direccionables', 'Alarmamiento y supervisión', 'Diseño orientado a normativas aplicables'],
  ],
  [
    'id' => 'sistemas-hvac',
    'eyebrow' => 'Control ambiental',
    'title' => 'Sistemas HVAC industriales para continuidad operativa.',
    'copy' => 'Integramos climatización, ventilación, chillers y mantenimiento para oficinas, cuartos técnicos, procesos productivos y espacios de precisión.',
    'image' => 'assets/imagesnew/SLIDES2/AIRE%20ACONDICIONADO/ID%20INDUSTRIAL%20WEB_7.jpg',
    'alt' => 'Sistemas HVAC industriales en Querétaro',
    'width' => 1920,
    'height' => 500,
    'detailHref' => 'instalacion-aire-acondicionado-industrial-queretaro/',
    'detailLabel' => 'Ver más',
    'bullets' => ['Instalación y mantenimiento', 'Ventilación y ductería', 'Sistemas de precisión para cuartos técnicos'],
  ],
  [
    'id' => 'cctv-industrial',
    'eyebrow' => 'Videovigilancia industrial',
    'title' => 'CCTV industrial para monitoreo, evidencia y control operativo.',
    'copy' => 'Diseñamos e instalamos cámaras IP, grabadores, almacenamiento, redes y monitoreo para plantas, oficinas, perímetros y naves industriales en Querétaro.',
    'image' => 'assets/imagesnew/SLIDES2/CCTV/PAGINA%20WEB%20ID%20INDUSTRIAL_2.jpg',
    'alt' => 'Centro de monitoreo CCTV industrial',
    'width' => 1920,
    'height' => 800,
    'detailHref' => 'instalacion-camaras-seguridad-industrial-queretaro/',
    'detailLabel' => 'Ver más',
    'bullets' => ['Cámaras IP y grabación 24/7', 'Monitoreo remoto y evidencia', 'Integración con acceso, alarmas y red'],
  ],
  [
    'id' => 'fibra-optica',
    'eyebrow' => 'Alta disponibilidad',
    'title' => 'Fibra óptica para comunicación industrial de alto desempeño.',
    'copy' => 'Tendidos, fusiones, certificación y enlaces de fibra óptica para naves, campus industriales, edificios corporativos y redes críticas.',
    'image' => 'assets/imagesnew/BANNERS/FIBRA%20OPTICA/LANDING%20ID%20INDUSTRIAL_6.jpg',
    'alt' => 'Instaladores de fibra óptica industrial',
    'width' => 1920,
    'height' => 500,
    'detailHref' => '#contacto',
    'detailLabel' => 'Cotizar fibra óptica',
    'bullets' => ['Fusión y certificación', 'Backbone para naves y campus', 'Canalización y protección de enlace'],
  ],
  [
    'id' => 'control-accesos',
    'eyebrow' => 'Seguridad y trazabilidad',
    'title' => 'Control de accesos conectado con operación y vigilancia.',
    'copy' => 'Integramos biométricos, tarjetas, plumas, torniquetes, CCTV y monitoreo para controlar personal, proveedores y perímetros industriales.',
    'image' => 'assets/imagesnew/SLIDES2/CONTROL%20DE%20ACCESOS/PAGINA%20WEB%20ID%20INDUSTRIAL_4.jpg',
    'alt' => 'Control de accesos conectado a CCTV',
    'width' => 1920,
    'height' => 800,
    'detailHref' => 'control-de-acceso-de-personal-queretaro/',
    'detailLabel' => 'Ver más',
    'bullets' => ['Biométricos, tarjetas y plumas', 'Integración con CCTV y nómina', 'Trazabilidad de entradas y salidas'],
  ],
];

$bitacora = [
  [
    'tag' => 'Checklist',
    'title' => 'Antes de intervenir una red industrial',
    'copy' => 'Levantamiento, rutas, densidad de nodos, energía disponible y ventanas de paro definen una ejecución limpia.',
  ],
  [
    'tag' => 'Mantenimiento',
    'title' => 'Señales de alerta en sistemas HVAC',
    'copy' => 'Variaciones térmicas, ruido, humedad y consumo elevado suelen anticipar fallas que afectan continuidad operativa.',
  ],
  [
    'tag' => 'Seguridad',
    'title' => 'Por qué integrar acceso, CCTV y bitácoras',
    'copy' => 'La seguridad industrial mejora cuando cada evento deja evidencia, responsable, hora y punto de control.',
  ],
];

$serviceOverview = [
  [
    'title' => 'Cableado estructurado',
    'copy' => 'Redes industriales, voz y datos, racks, servidores, sites y fibra óptica para operación estable.',
    'href' => 'industriales/cableado-estructurado-queretaro/',
    'image' => 'assets/imagesnew/BANNERS/CABLEADO%20ESTRUCTURADO/LANDING%20ID%20INDUSTRIAL_10.jpg',
    'alt' => 'Cableado estructurado industrial en Querétaro',
    'width' => 1920,
    'height' => 500,
    'badge' => 'Página técnica',
    'featured' => true,
  ],
  [
    'title' => 'Detección de incendios',
    'copy' => 'Paneles, sensores, estaciones manuales y alarmamiento para áreas críticas y procesos productivos.',
    'href' => '#deteccion-incendios',
    'image' => 'assets/imagesnew/SLIDES2/SISTEMAS%20CONTRA%20INCENDIO/ID%20INDUSTRIAL%20WEB_8.jpg',
    'alt' => 'Panel de detección de incendios industrial',
    'width' => 1920,
    'height' => 500,
    'badge' => 'Resumen',
    'featured' => false,
  ],
  [
    'title' => 'Sistemas HVAC',
    'copy' => 'Climatización, ventilación, chillers y soporte para cuartos técnicos, oficinas y producción.',
    'href' => 'instalacion-aire-acondicionado-industrial-queretaro/',
    'image' => 'assets/imagesnew/SLIDES2/AIRE%20ACONDICIONADO/ID%20INDUSTRIAL%20WEB_7.jpg',
    'alt' => 'Sistemas HVAC industriales',
    'width' => 1920,
    'height' => 500,
    'badge' => 'Resumen',
    'featured' => false,
  ],
  [
    'title' => 'CCTV industrial',
    'copy' => 'Instalación de cámaras de seguridad, videovigilancia y monitoreo para plantas, oficinas y naves industriales.',
    'href' => 'instalacion-camaras-seguridad-industrial-queretaro/',
    'image' => 'assets/imagesnew/BANNERS%20VER2/CCTV/PAGINA%20WEB%20ID%20INDUSTRIAL_11.jpg',
    'alt' => 'Sistema de CCTV industrial en Querétaro',
    'width' => 1920,
    'height' => 500,
    'badge' => 'Página técnica',
    'featured' => true,
  ],
  [
    'title' => 'Fibra óptica',
    'copy' => 'Backbone, fusiones, certificación e interconexión de edificios para redes de alto desempeño.',
    'href' => '#fibra-optica',
    'image' => 'assets/imagesnew/BANNERS/FIBRA%20OPTICA/LANDING%20ID%20INDUSTRIAL_6.jpg',
    'alt' => 'Instalación de fibra óptica en Querétaro',
    'width' => 1920,
    'height' => 500,
    'badge' => 'Resumen',
    'featured' => false,
  ],
  [
    'title' => 'Control de Accesos',
    'copy' => 'Biométricos, tarjetas, plumas, CCTV y trazabilidad para personal, proveedores y perímetros.',
    'href' => 'control-de-acceso-de-personal-queretaro/',
    'image' => 'assets/imagesnew/BANNERS%20VER2/CONTROL%20DE%20ACCESO/PAGINA%20WEB%20ID%20INDUSTRIAL_13.jpg',
    'alt' => 'Control de accesos biométrico industrial',
    'width' => 1920,
    'height' => 500,
    'badge' => 'Página técnica',
    'featured' => true,
  ],
];

$formStatus = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $projectPhone = trim($_POST['phone'] ?? '');
  $serviceInterest = trim($_POST['service'] ?? '');
  $message = trim($_POST['message'] ?? '');
  $honeypot = trim($_POST['company_site'] ?? '');

  if ($honeypot !== '') {
    $formStatus = ['type' => 'ok', 'text' => 'Gracias. Recibimos tu solicitud.'];
  } elseif ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $message === '') {
    $formStatus = ['type' => 'error', 'text' => 'Por favor completa nombre, correo válido y mensaje.'];
  } else {
    $subject = 'Nueva solicitud desde idindustrial.com.mx';
    $body = "Nombre: {$name}\nCorreo: {$email}\nTeléfono: {$projectPhone}\nServicio de interés: {$serviceInterest}\n\nMensaje:\n{$message}";
    $headers = "From: {$contactEmail}\r\nReply-To: {$email}\r\nContent-Type: text/plain; charset=UTF-8";
    $sent = @mail($contactEmail, $subject, $body, $headers);
    $formStatus = $sent
      ? ['type' => 'ok', 'text' => 'Gracias. Tu solicitud fue enviada correctamente.']
      : ['type' => 'error', 'text' => 'No se pudo enviar desde el servidor. Escríbenos por WhatsApp y te atendemos.'];
  }
}

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/navbar.php';
?>

<main id="inicio">
  <section class="hero section-dark" aria-labelledby="hero-title">
    <div class="hero__media" aria-hidden="true">
      <picture>
        <source srcset="<?php echo htmlspecialchars($heroDesktopImage); ?>" media="(min-width: 900px)">
        <img src="<?php echo htmlspecialchars($heroMobileImage); ?>" alt="" width="820" height="342" fetchpriority="high" decoding="async">
      </picture>
    </div>
    <div class="hero__overlay" aria-hidden="true"></div>
    <div class="container hero__grid">
      <div class="hero__copy reveal">
        <p class="eyebrow">Ingeniería industrial en Querétaro y Bajío</p>
        <h1 id="hero-title"><span>ID</span><span>Industrial</span></h1>
        <p class="hero__lead">Integramos infraestructura crítica para plantas, naves y edificios corporativos: redes, seguridad, detección de incendios, HVAC y fibra óptica.</p>
        <div class="hero__actions">
          <a class="button button--primary" href="#contacto">Cotizar proyecto</a>
          <a class="button button--ghost" href="#quienes-somos">Ver capacidades</a>
        </div>
      </div>
      <div class="hero__panel reveal reveal--delay">
        <span class="status-dot"></span>
        <p>Operación técnica llave en mano</p>
        <strong>Diagnóstico, ejecución, documentación y soporte.</strong>
      </div>
    </div>
  </section>

  <section class="metrics section-light" aria-label="Indicadores de ID Industrial">
    <div class="container metrics__grid">
      <div class="metric reveal">
        <span data-count="20">0</span>
        <p>Años de experiencia técnica</p>
      </div>
      <div class="metric reveal">
        <span data-count="5">0</span>
        <p>Especialidades integradas</p>
      </div>
      <div class="metric reveal">
        <span data-count="24">0</span>
        <p>Enfoque en continuidad operativa</p>
      </div>
      <div class="metric reveal">
        <span data-count="100">0</span>
        <p>Proyectos documentados y trazables</p>
      </div>
    </div>
  </section>

  <section id="servicios" class="services-overview section-dark section-pad">
    <div class="container">
      <div class="section-head reveal">
        <p class="eyebrow">Servicios</p>
        <h2>Soluciones industriales para infraestructura, seguridad y continuidad operativa.</h2>
        <p>Explora primero el alcance dentro de esta página. En los servicios con landing técnica podrás entrar a una página exclusiva con información SEO, preguntas frecuentes, normativas y criterios de compra.</p>
      </div>

      <div class="services-overview__grid">
        <?php foreach ($serviceOverview as $item): ?>
          <article class="service-card <?php echo !empty($item['featured']) ? 'service-card--featured' : ''; ?> reveal">
            <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['alt']); ?>" width="<?php echo (int) $item['width']; ?>" height="<?php echo (int) $item['height']; ?>" loading="lazy" decoding="async" fetchpriority="low">
            <em><?php echo htmlspecialchars($item['badge']); ?></em>
            <span><?php echo htmlspecialchars($item['title']); ?></span>
            <p><?php echo htmlspecialchars($item['copy']); ?></p>
            <a class="service-card__more" href="<?php echo htmlspecialchars($item['href']); ?>" aria-label="Ver más sobre <?php echo htmlspecialchars($item['title']); ?>">Ver más</a>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section id="quienes-somos" class="about section-dark section-pad">
    <div class="container split">
      <div class="split__content reveal">
        <p class="eyebrow">Quiénes somos</p>
        <h2>Somos el equipo que conecta infraestructura, seguridad y operación.</h2>
        <p>ID Industrial desarrolla soluciones técnicas para entornos donde una falla cuesta producción, seguridad o confianza. Trabajamos con cuadrillas capacitadas, enfoque preventivo y documentación clara para que cada instalación pueda mantenerse, escalarse y auditarse.</p>
        <div class="check-grid">
          <span>Levantamiento en sitio</span>
          <span>Ingeniería y suministro</span>
          <span>Instalación profesional</span>
          <span>Soporte y mantenimiento</span>
        </div>
      </div>
      <figure class="image-lockup reveal reveal--delay">
        <img src="assets/imagesnew/SLIDES2/INDUSTRIAL/ID%20INDUSTRIAL%20WEB_1.jpg" alt="Personal capacitado de ID Industrial" width="1920" height="800" loading="lazy" decoding="async">
        <figcaption>Cuadrillas técnicas para ejecución industrial con orden, seguridad y trazabilidad.</figcaption>
      </figure>
    </div>
  </section>

  <section class="process section-light section-pad" aria-labelledby="process-title">
    <div class="container">
      <div class="section-head reveal">
        <p class="eyebrow">Proceso de mejora</p>
        <h2 id="process-title">Una página más clara para clientes que comparan proveedores técnicos.</h2>
      </div>
      <div class="process__grid">
        <article class="process-step reveal">
          <span>01</span>
          <h3>Mensaje directo</h3>
          <p>Se prioriza qué hace ID Industrial, dónde opera y qué problemas resuelve.</p>
        </article>
        <article class="process-step reveal">
          <span>02</span>
          <h3>Navegación por servicios</h3>
          <p>Cada solución clave tiene su propio bloque, imagen, beneficios y llamada a contacto.</p>
        </article>
        <article class="process-step reveal">
          <span>03</span>
          <h3>Base técnica SEO/GEO</h3>
          <p>Metadatos, datos estructurados, contenido semántico y archivos de rastreo listos.</p>
        </article>
      </div>
    </div>
  </section>

  <section class="lamp-section" aria-labelledby="lamp-title">
    <div class="lamp-scene reveal">
      <div class="lamp-beam lamp-beam--left" aria-hidden="true"></div>
      <div class="lamp-beam lamp-beam--right" aria-hidden="true"></div>
      <div class="lamp-haze lamp-haze--wide" aria-hidden="true"></div>
      <div class="lamp-haze lamp-haze--soft" aria-hidden="true"></div>
      <div class="lamp-core" aria-hidden="true"></div>
      <div class="lamp-line" aria-hidden="true"></div>
      <div class="lamp-mask" aria-hidden="true"></div>
      <div class="lamp-content">
        <p class="eyebrow">Infraestructura crítica conectada</p>
        <h2 id="lamp-title">Sistemas industriales que trabajan como una sola operación.</h2>
        <p>Redes, fibra, HVAC, incendio y accesos con una arquitectura pensada para continuidad, trazabilidad y crecimiento.</p>
        <a class="button button--primary" href="#contacto">Evaluar proyecto</a>
      </div>
    </div>
  </section>

  <?php foreach ($services as $index => $service): ?>
    <section id="<?php echo htmlspecialchars($service['id']); ?>" class="service section-dark section-pad <?php echo $index % 2 ? 'service--reverse' : ''; ?>">
      <div class="container split">
        <div class="split__content reveal">
          <p class="eyebrow"><?php echo htmlspecialchars($service['eyebrow']); ?></p>
          <h2><?php echo htmlspecialchars($service['title']); ?></h2>
          <p><?php echo htmlspecialchars($service['copy']); ?></p>
          <ul class="service-list">
            <?php foreach ($service['bullets'] as $bullet): ?>
              <li><?php echo htmlspecialchars($bullet); ?></li>
            <?php endforeach; ?>
          </ul>
          <div class="service-actions">
            <a class="text-link" href="<?php echo htmlspecialchars($service['detailHref']); ?>"><?php echo htmlspecialchars($service['detailLabel']); ?></a>
            <a class="text-link text-link--muted" href="#contacto">Solicitar evaluación técnica</a>
          </div>
        </div>
        <figure class="service__image reveal reveal--delay">
          <img src="<?php echo htmlspecialchars($service['image']); ?>" alt="<?php echo htmlspecialchars($service['alt']); ?>" width="<?php echo (int) $service['width']; ?>" height="<?php echo (int) $service['height']; ?>" loading="lazy" decoding="async">
        </figure>
      </div>
    </section>
  <?php endforeach; ?>

  <section class="integration section-light section-pad" aria-labelledby="integration-title">
    <div class="container integration__grid">
      <div class="section-head reveal">
        <p class="eyebrow">Integración industrial</p>
        <h2 id="integration-title">Un solo criterio técnico para sistemas que normalmente se instalan por separado.</h2>
        <p>Cuando redes, HVAC, seguridad, control de accesos y detección trabajan con una misma lógica de operación, el mantenimiento es más claro y las decisiones se toman con mejor información.</p>
      </div>
      <div class="integration__visual reveal reveal--delay">
        <img src="assets/imagesnew/SLIDES2/SERVIDORES/ID%20INDUSTRIAL%20WEB_2.jpg" alt="Centro de monitoreo inteligente industrial" width="1920" height="800" loading="lazy" decoding="async">
      </div>
    </div>
  </section>

  <section id="bitacora-id" class="journal section-dark section-pad">
    <div class="container">
      <div class="section-head reveal">
        <p class="eyebrow">Bitácora ID</p>
        <h2>Notas prácticas para mantener infraestructura crítica bajo control.</h2>
      </div>
      <div class="journal__grid">
        <?php foreach ($bitacora as $entry): ?>
          <article class="journal-card reveal">
            <span><?php echo htmlspecialchars($entry['tag']); ?></span>
            <h3><?php echo htmlspecialchars($entry['title']); ?></h3>
            <p><?php echo htmlspecialchars($entry['copy']); ?></p>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section id="contacto" class="contact section-light section-pad">
    <div class="container contact__grid">
      <div class="contact__copy reveal">
        <p class="eyebrow">Contacto</p>
        <h2>Cuéntanos qué sistema necesitas mejorar o instalar.</h2>
        <p>Respondemos con una ruta de atención clara: diagnóstico, alcance técnico, tiempos y próximos pasos.</p>
        <div class="contact-proof">
          <div>
            <strong>24/7</strong>
            <span>Atención a operación crítica</span>
          </div>
          <div>
            <strong>QRO</strong>
            <span>Cobertura en polos industriales</span>
          </div>
        </div>
        <div class="contact-methods">
          <a href="tel:+524425986318" aria-label="Llamar a ID Industrial">
            <span class="contact-methods__icon" aria-hidden="true">
              <svg viewBox="0 0 24 24"><path d="M6.62 10.78a15.3 15.3 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1.02-.24 11.4 11.4 0 0 0 3.56.56 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1 11.4 11.4 0 0 0 .56 3.56 1 1 0 0 1-.24 1.02l-2.2 2.2Z"/></svg>
            </span>
            <span class="contact-methods__label">Teléfono</span>
            <strong><?php echo htmlspecialchars($phone); ?></strong>
          </a>
          <a href="https://wa.me/<?php echo htmlspecialchars($whatsapp); ?>?text=Hola%20ID%20Industrial,%20quiero%20cotizar%20un%20proyecto" target="_blank" rel="noopener" aria-label="Contactar por WhatsApp">
            <span class="contact-methods__icon contact-methods__icon--whatsapp" aria-hidden="true">
              <svg viewBox="0 0 32 32"><path d="M16.04 3.2A12.72 12.72 0 0 0 5.2 22.6L4 29l6.56-1.72A12.72 12.72 0 1 0 16.04 3.2Zm0 22.84a10.1 10.1 0 0 1-5.14-1.4l-.36-.22-3.9 1.02 1.04-3.78-.24-.4A10.08 10.08 0 1 1 16.04 26.04Zm5.52-7.54c-.3-.16-1.8-.9-2.08-1-.28-.1-.48-.16-.68.16-.2.3-.78 1-.96 1.2-.18.2-.36.22-.66.08-.3-.16-1.28-.48-2.44-1.52-.9-.8-1.5-1.78-1.68-2.08-.18-.3-.02-.46.14-.62.14-.14.3-.36.46-.54.16-.18.2-.3.3-.5.1-.2.06-.38-.02-.54-.08-.16-.68-1.64-.94-2.24-.24-.58-.5-.5-.68-.5h-.58c-.2 0-.52.08-.8.38-.28.3-1.06 1.04-1.06 2.54s1.1 2.94 1.24 3.14c.16.2 2.16 3.3 5.24 4.62.74.32 1.3.5 1.74.64.74.24 1.4.2 1.94.12.6-.1 1.8-.74 2.06-1.46.26-.72.26-1.34.18-1.46-.08-.14-.28-.22-.58-.38Z"/></svg>
            </span>
            <span class="contact-methods__label">WhatsApp</span>
            <strong>Atención directa</strong>
          </a>
          <a href="mailto:<?php echo htmlspecialchars($contactEmail); ?>" aria-label="Enviar correo a ID Industrial">
            <span class="contact-methods__icon" aria-hidden="true">
              <svg viewBox="0 0 24 24"><path d="M4 5h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Zm0 3.2V17h16V8.2l-7.42 5.16a1 1 0 0 1-1.16 0L4 8.2Zm1.1-1.2 6.9 4.8L18.9 7H5.1Z"/></svg>
            </span>
            <span class="contact-methods__label">Correo</span>
            <strong><?php echo htmlspecialchars($contactEmail); ?></strong>
          </a>
        </div>
      </div>

      <form class="contact-form reveal reveal--delay" action="#contacto" method="post">
        <div class="form-head">
          <span>Solicitud técnica</span>
          <h3>Agenda una evaluación</h3>
          <p>Déjanos los datos clave y te contactamos para aterrizar alcance, prioridad y visita.</p>
        </div>
        <?php if ($formStatus): ?>
          <p class="form-status form-status--<?php echo htmlspecialchars($formStatus['type']); ?>"><?php echo htmlspecialchars($formStatus['text']); ?></p>
        <?php endif; ?>
        <div class="form-row">
          <label>
            <span class="field-pill">Nombre</span>
            <input type="text" name="name" autocomplete="name" placeholder="Nombre y empresa" required>
          </label>
          <label>
            <span class="field-pill">Correo</span>
            <input type="email" name="email" autocomplete="email" placeholder="correo@empresa.com" required>
          </label>
        </div>
        <div class="form-row">
          <label>
            <span class="field-pill">Teléfono</span>
            <input type="tel" name="phone" autocomplete="tel" placeholder="+52 442 000 0000">
          </label>
          <label>
            <span class="field-pill">Servicio</span>
            <select name="service">
              <option value="">Seleccionar</option>
              <option value="Cableado estructurado">Cableado estructurado</option>
              <option value="Detección de incendios">Detección de incendios</option>
              <option value="Sistemas HVAC">Sistemas HVAC</option>
              <option value="CCTV industrial">CCTV industrial</option>
              <option value="Fibra óptica">Fibra óptica</option>
              <option value="Control de Accesos">Control de Accesos</option>
            </select>
          </label>
        </div>
        <label class="honeypot">
          Sitio
          <input type="text" name="company_site" tabindex="-1" autocomplete="off">
        </label>
        <label>
          <span class="field-pill">Mensaje</span>
          <textarea name="message" rows="5" placeholder="Cuéntanos ubicación, tipo de instalación y prioridad del proyecto." required></textarea>
        </label>
        <button class="button button--primary" type="submit">Enviar solicitud</button>
      </form>
    </div>
  </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
