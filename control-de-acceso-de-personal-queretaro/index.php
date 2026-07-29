<?php
$basePath = '../';
$siteUrl = 'https://idindustrial.com.mx/sistema/';
$assetUrlBase = 'https://idindustrial.com.mx/sistema/';
$canonicalUrl = 'https://idindustrial.com.mx/sistema/control-de-acceso-de-personal-queretaro/';
$phone = '+52 442 598 6318';
$whatsapp = '524425986318';
$contactEmail = 'contacto@idindustrial.com.mx';
$heroMobileImage = 'assets/img/optimized/service-control-acceso.jpg';
$heroDesktopImage = 'assets/img/optimized/service-control-acceso.jpg';

$title = 'Control de acceso de personal en Querétaro | ID Industrial';
$description = 'Control de acceso para empresas en Querétaro: biometría, tarjetas, visitantes, horarios, CCTV e integración con asistencia y nómina.';
$keywords = 'control de acceso Querétaro, control de acceso de personal, biometría industrial, control de asistencia, acceso con CCTV';

$faqItems = [
  [
    'q' => '¿Qué zonas conviene controlar en una planta o edificio?',
    'a' => 'Accesos principales, almacenes, sites, laboratorios, cuartos técnicos, andenes, estacionamientos y áreas donde se requiere registro por usuario, horario o perfil.',
  ],
  [
    'q' => '¿Se puede conectar control de acceso con nómina?',
    'a' => 'Sí. El sistema puede registrar asistencia, turnos, incidencias y permisos para alimentar procesos internos de RH o exportar reportes operativos.',
  ],
  [
    'q' => '¿Qué diferencia hay entre tarjeta, huella y reconocimiento facial?',
    'a' => 'Cada tecnología tiene ventajas según flujo de personas, higiene, velocidad, nivel de control y condiciones del sitio. La selección se define durante el levantamiento.',
  ],
  [
    'q' => '¿Se puede integrar con CCTV?',
    'a' => 'Sí. La integración permite revisar video asociado a eventos de acceso, intentos fallidos, horarios especiales o entradas a zonas restringidas.',
  ],
];

include __DIR__ . '/../includes/head.php';
include __DIR__ . '/../includes/navbar.php';
?>

<main class="service-page" id="inicio">
  <section class="service-hero section-dark">
    <div class="service-hero__media" aria-hidden="true">
      <picture>
        <source srcset="<?php echo htmlspecialchars($basePath . $heroDesktopImage); ?>" media="(min-width: 900px)">
        <img src="<?php echo htmlspecialchars($basePath . $heroMobileImage); ?>" alt="" width="1920" height="800" fetchpriority="high" decoding="async">
      </picture>
    </div>
    <div class="service-hero__overlay" aria-hidden="true"></div>
    <div class="container service-hero__content reveal">
      <p class="eyebrow">Control de acceso Querétaro</p>
      <h1><span>Control de acceso</span><span>de personal para</span><span>empresas industriales</span></h1>
      <p>Implementamos accesos por perfil, horario y zona para mejorar trazabilidad, asistencia, visitas y seguridad operativa en plantas, corporativos y edificios técnicos.</p>
      <div class="hero__actions">
        <a class="button button--primary" href="<?php echo htmlspecialchars($basePath); ?>?servicio=accesos#cotizacion">Solicitar evaluación</a>
        <a class="button button--ghost" href="#alcance-accesos">Ver alcance</a>
      </div>
    </div>
  </section>

  <section id="alcance-accesos" class="detail-section section-light section-pad">
    <div class="container detail-grid">
      <div class="detail-copy reveal">
        <p class="eyebrow">Trazabilidad y operación</p>
        <h2>Accesos claros para saber quién entra, cuándo y a qué zona.</h2>
        <p>El objetivo no es llenar la planta de barreras, sino ordenar entradas, salidas, visitantes y zonas críticas con reglas fáciles de administrar y evidencia disponible cuando se necesita.</p>
      </div>
      <div class="detail-panel reveal reveal--delay">
        <h3>Objetivos del sistema</h3>
        <ul>
          <li>Restringir áreas por perfil u horario</li>
          <li>Registrar asistencia, entradas y salidas</li>
          <li>Administrar visitantes y proveedores</li>
          <li>Asociar eventos con CCTV cuando aplica</li>
          <li>Generar reportes para RH y seguridad</li>
        </ul>
      </div>
    </div>
  </section>

  <section class="detail-section section-dark section-pad">
    <div class="container split">
      <div class="split__content reveal">
        <p class="eyebrow">Tecnología por necesidad</p>
        <h2>Biometría, tarjetas, torniquetes y barreras conectadas.</h2>
        <p>Seleccionamos lectores y mecanismos según flujo, condiciones del sitio, tipo de usuario y nivel de control requerido. La arquitectura puede crecer por etapas sin perder administración centralizada.</p>
        <ul class="service-list">
          <li>Lectores faciales, huella, palma o RFID</li>
          <li>Puertas, chapas, torniquetes y barreras vehiculares</li>
          <li>Perfiles por horario, zona y tipo de usuario</li>
          <li>Integración con CCTV, red y reportes</li>
        </ul>
      </div>
      <figure class="service__image reveal reveal--delay">
        <img src="<?php echo htmlspecialchars($basePath); ?>assets/img/optimized/service-control-acceso.jpg" alt="Control de acceso biométrico para empresa industrial" width="1920" height="800" loading="lazy" decoding="async">
      </figure>
    </div>
  </section>

  <section class="service-banner service-banner--contain" aria-label="Control de asistencia y visitantes">
    <img src="<?php echo htmlspecialchars($basePath); ?>assets/img/optimized/home-hero-control-acceso.jpg" alt="Control de accesos conectado con CCTV y monitoreo" width="1920" height="500" loading="lazy" decoding="async">
    <div>
      <p>Usuarios, horarios y evidencia en una sola operación</p>
      <h2>Acceso, asistencia y visitas sin procesos duplicados</h2>
    </div>
  </section>

  <section class="detail-section section-light section-pad">
    <div class="container detail-grid detail-grid--reverse">
      <div class="detail-panel reveal">
        <h3>Entregables esperados</h3>
        <ul>
          <li>Levantamiento de accesos y flujos</li>
          <li>Arquitectura de dispositivos y red</li>
          <li>Configuración de usuarios y perfiles</li>
          <li>Pruebas de apertura, evento y reporte</li>
          <li>Capacitación básica y memoria técnica</li>
        </ul>
      </div>
      <div class="detail-copy reveal reveal--delay">
        <p class="eyebrow">Implementación ordenada</p>
        <h2>Del levantamiento a una operación administrable.</h2>
        <p>Documentamos el sistema para que recursos humanos, seguridad patrimonial, TI y mantenimiento sepan cómo operar, ajustar permisos y escalar el proyecto sin depender de improvisaciones.</p>
      </div>
    </div>
  </section>

  <section class="detail-section section-dark section-pad">
    <div class="container split">
      <figure class="service__image reveal">
        <img src="<?php echo htmlspecialchars($basePath); ?>assets/img/optimized/service-cctv.jpg" alt="CCTV integrado con eventos de control de acceso" width="1920" height="800" loading="lazy" decoding="async">
      </figure>
      <div class="split__content reveal reveal--delay">
        <p class="eyebrow">Integración con video</p>
        <h2>Control de accesos conectado con CCTV industrial.</h2>
        <p>La unión de identidad y video ayuda a revisar eventos puntuales sin perder tiempo buscando manualmente. Es útil para accesos especiales, visitantes, áreas restringidas y horarios fuera de operación normal.</p>
        <ul class="service-list">
          <li>Eventos asociados a usuario y cámara</li>
          <li>Alertas por intentos fallidos o horarios especiales</li>
          <li>Monitoreo remoto con permisos definidos</li>
          <li>Reportes para auditoría interna</li>
        </ul>
      </div>
    </div>
  </section>

  <section class="faq-section section-light section-pad">
    <div class="container">
      <div class="section-head reveal">
        <p class="eyebrow">Preguntas técnicas</p>
        <h2>Accesos, asistencia, visitantes e integración con CCTV.</h2>
      </div>
      <div class="faq-list">
        <?php foreach ($faqItems as $item): ?>
          <details class="faq-item reveal">
            <summary><?php echo htmlspecialchars($item['q']); ?></summary>
            <p><?php echo htmlspecialchars($item['a']); ?></p>
          </details>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="cta-strip section-light">
    <div class="container cta-strip__inner reveal">
      <div>
        <p class="eyebrow">Propuesta técnica</p>
        <h2>Recibe una propuesta de control de acceso conectada con operación, CCTV y reportes.</h2>
      </div>
      <a class="button button--primary" href="<?php echo htmlspecialchars($basePath); ?>?servicio=accesos#cotizacion">Solicitar cotización</a>
    </div>
  </section>
</main>

<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "Service",
      "@id": "https://idindustrial.com.mx/sistema/control-de-acceso-de-personal-queretaro/#service",
      "name": "Control de acceso de personal en Querétaro",
      "serviceType": "Control de acceso, asistencia e integración con CCTV",
      "provider": {
        "@type": "Organization",
        "@id": "https://idindustrial.com.mx/sistema/#organization",
        "name": "ID Industrial",
        "url": "https://idindustrial.com.mx/sistema/"
      },
      "areaServed": {
        "@type": "Place",
        "name": "Querétaro"
      },
      "description": "Implementación de control de acceso para empresas industriales con biometría, RFID, visitantes, horarios, reportes e integración con CCTV.",
      "hasOfferCatalog": {
        "@type": "OfferCatalog",
        "name": "Alcances de control de acceso",
        "itemListElement": [
          {"@type": "Service", "name": "Control biométrico y RFID"},
          {"@type": "Service", "name": "Control de asistencia"},
          {"@type": "Service", "name": "Administración de visitantes"},
          {"@type": "Service", "name": "Integración con CCTV"}
        ]
      }
    },
    {
      "@type": "FAQPage",
      "@id": "https://idindustrial.com.mx/sistema/control-de-acceso-de-personal-queretaro/#faq",
      "mainEntity": [
        <?php foreach ($faqItems as $index => $item): ?>
          {
            "@type": "Question",
            "name": <?php echo json_encode($item['q'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
            "acceptedAnswer": {
              "@type": "Answer",
              "text": <?php echo json_encode($item['a'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
            }
          }<?php echo $index < count($faqItems) - 1 ? ',' : ''; ?>
        <?php endforeach; ?>
      ]
    },
    {
      "@type": "BreadcrumbList",
      "@id": "https://idindustrial.com.mx/sistema/control-de-acceso-de-personal-queretaro/#breadcrumb",
      "itemListElement": [
        {"@type": "ListItem", "position": 1, "name": "Inicio", "item": "https://idindustrial.com.mx/sistema/"},
        {"@type": "ListItem", "position": 2, "name": "Control de acceso", "item": "https://idindustrial.com.mx/sistema/control-de-acceso-de-personal-queretaro/"}
      ]
    }
  ]
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
