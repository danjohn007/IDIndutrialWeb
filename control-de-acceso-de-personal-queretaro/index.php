<?php
$basePath = '../';
$siteUrl = 'https://idindustrial.com.mx/sistema/';
$assetUrlBase = 'https://idindustrial.com.mx/sistema/';
$canonicalUrl = 'https://idindustrial.com.mx/sistema/control-de-acceso-de-personal-queretaro/';
$phone = '+52 442 598 6318';
$whatsapp = '524425986318';
$contactEmail = 'contacto@idindustrial.com.mx';
$heroMobileImage = 'assets/imagesnew/BANNERS/CONTROL%20DE%20ACCESOS/LANDING%20ID%20INDUSTRIAL_1.jpg';
$heroDesktopImage = 'assets/imagesnew/BANNERS%20VER2/CONTROL%20DE%20ACCESO/PAGINA%20WEB%20ID%20INDUSTRIAL_13.jpg';

$title = 'Control de acceso de personal Querétaro | Biométricos, CCTV y Nómina';
$description = 'Instalación de control de acceso de personal en Querétaro para empresas industriales y corporativos. Sistemas biométricos, tarjetas RFID, CCTV y nómina.';
$keywords = 'control de acceso de personal queretaro, control de acceso biométrico queretaro, sistema de control de acceso en queretaro, control de acceso para puertas, control de acceso con tarjeta, control de accesos biométrico, puertas de seguridad precios, puertas de alta seguridad, puerta de control de acceso, seguridad para puertas parque industrial queretaro, control de acceso de personal el marques, control de acceso para puertas sjr, control de acceso con tarjeta qro, integración control de accesos y nómina, control de acceso conectado a CCTV, seguridad industrial inteligente, control de asistencia biométrico, sistemas de acceso para empresas industriales';

$faqItems = [
  [
    'q' => '¿Cómo evitar robos internos con sistemas de control de acceso y CCTV?',
    'a' => 'Los sistemas biométricos conectados a CCTV registran quién entra, sale y permanece en áreas críticas, reduciendo robo hormiga, accesos no autorizados y pérdida de inventario.',
  ],
  [
    'q' => '¿Qué tipo de control de acceso ayuda a evitar ingreso de personas no autorizadas?',
    'a' => 'Las empresas industriales utilizan reconocimiento facial, tarjetas RFID cifradas, puertas de alta seguridad y monitoreo centralizado para validar identidad y bloquear accesos no autorizados.',
  ],
  [
    'q' => '¿Se puede conectar control de acceso con nómina?',
    'a' => 'Sí. Los sistemas modernos se integran con plataformas de recursos humanos, ERP y nómina para sincronizar horarios, incidencias, permisos y asistencia automáticamente.',
  ],
  [
    'q' => '¿Cómo proteger almacenes, sites o laboratorios dentro de una empresa?',
    'a' => 'Las áreas críticas requieren puertas de alta seguridad, acceso biométrico y CCTV con inteligencia artificial para detectar movimientos sospechosos y generar alertas.',
  ],
  [
    'q' => '¿Qué ventajas tiene integrar control de acceso, CCTV y asistencia en una sola plataforma?',
    'a' => 'Permite centralizar seguridad, asistencia y monitoreo operativo en tiempo real, facilitando auditorías, evidencia visual automática y administración remota.',
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
        <img src="<?php echo htmlspecialchars($basePath . $heroMobileImage); ?>" alt="" width="1920" height="500" fetchpriority="high" decoding="async">
      </picture>
    </div>
    <div class="service-hero__overlay" aria-hidden="true"></div>
    <div class="container service-hero__content reveal">
      <p class="eyebrow">Control de acceso de personal Querétaro</p>
      <h1><span>Control de acceso</span><span>de personal</span><span>en Querétaro</span></h1>
      <p>Sistemas biométricos, tarjetas RFID, puertas de alta seguridad, CCTV inteligente e integración con nómina para empresas industriales, corporativos, clínicas, escuelas y edificios inteligentes.</p>
      <div class="hero__actions">
        <a class="button button--primary" href="<?php echo htmlspecialchars($basePath); ?>#contacto">Cotizar proyecto</a>
        <a class="button button--ghost" href="#tecnologia">Ver tecnología</a>
      </div>
    </div>
  </section>

  <section id="tecnologia" class="detail-section section-light section-pad">
    <div class="container detail-grid">
      <div class="detail-copy reveal">
        <p class="eyebrow">Seguridad y trazabilidad</p>
        <h2>Control de acceso para empresas que necesitan restringir, automatizar y auditar.</h2>
        <p>Implementamos soluciones para responsables de RH, gerentes de compras, CEOs industriales, seguridad patrimonial, TICs y administradores de infraestructura que buscan reducir riesgos y centralizar información operativa.</p>
      </div>
      <div class="detail-panel reveal reveal--delay">
        <h3>Objetivos del sistema</h3>
        <ul>
          <li>Evitar robos internos y accesos indebidos</li>
          <li>Restringir áreas críticas por perfil u horario</li>
          <li>Automatizar asistencia, turnos y nómina</li>
          <li>Integrar CCTV y monitoreo inteligente</li>
          <li>Instalar tecnología nueva y certificada</li>
        </ul>
      </div>
    </div>
  </section>

  <section class="detail-section section-dark section-pad">
    <div class="container split">
      <div class="split__content reveal">
        <p class="eyebrow">Tecnología de última generación</p>
        <h2>Biometría, tarjetas RFID y monitoreo centralizado.</h2>
        <p>Trabajamos con soluciones de acceso facial, huella, palma, tarjetas cifradas, cerraduras electromagnéticas, puertas de alta seguridad e inteligencia artificial para reconocimiento y alertas.</p>
        <ul class="service-list">
          <li>Reconocimiento facial con IA</li>
          <li>Lectores de huella, palma y RFID</li>
          <li>Puertas, torniquetes y barreras vehiculares</li>
          <li>Control de acceso conectado a CCTV</li>
        </ul>
      </div>
      <figure class="service__image reveal reveal--delay">
        <img src="<?php echo htmlspecialchars($basePath); ?>assets/imagesnew/SLIDES2/CONTROL%20DE%20ACCESOS/PAGINA%20WEB%20ID%20INDUSTRIAL_4.jpg" alt="Control de acceso con tarjeta en Querétaro" width="1920" height="800" loading="lazy" decoding="async">
      </figure>
    </div>
  </section>

  <section class="service-banner" aria-label="Control de acceso RH conectado a cálculo de nómina">
    <img src="<?php echo htmlspecialchars($basePath); ?>assets/imagesnew/BANNERS/CONTROL%20DE%20ACCESOS/LANDING%20ID%20INDUSTRIAL_4.jpg" alt="Control de asistencia biométrico conectado a nómina" width="1920" height="500" loading="lazy" decoding="async">
    <div>
      <p>RH, asistencia y seguridad en una sola plataforma</p>
      <h2>Control de acceso conectado a nómina</h2>
    </div>
  </section>

  <section class="detail-section section-light section-pad">
    <div class="container detail-grid detail-grid--reverse">
      <div class="detail-panel reveal">
        <h3>Funcionalidades para corporativos</h3>
        <ul>
          <li>Registro automático de asistencia</li>
          <li>Entradas, salidas, reingresos y turnos</li>
          <li>Estadísticas, reportes y alertas en tiempo real</li>
          <li>Validación biométrica de personal</li>
          <li>Control de visitantes y proveedores</li>
          <li>Integración con ERP, RH y nómina</li>
        </ul>
      </div>
      <div class="detail-copy reveal reveal--delay">
        <p class="eyebrow">Automatización de RH</p>
        <h2>Control de acceso de personal integrado con nómina y recursos humanos.</h2>
        <p>La sincronización con plataformas administrativas reduce captura manual, errores en incidencias y tiempos de conciliación. Cada evento queda asociado a identidad, hora, zona y evidencia.</p>
      </div>
    </div>
  </section>

  <section class="detail-section section-dark section-pad">
    <div class="container split">
      <figure class="service__image reveal">
        <img src="<?php echo htmlspecialchars($basePath); ?>assets/imagesnew/BANNERS/CONTROL%20DE%20ACCESOS/LANDING%20ID%20INDUSTRIAL_9.jpg" alt="Puertas de alta seguridad y control de acceso" width="1920" height="500" loading="lazy" decoding="async">
      </figure>
      <div class="split__content reveal reveal--delay">
        <p class="eyebrow">Áreas críticas</p>
        <h2>Puertas de alta seguridad y accesos inteligentes.</h2>
        <p>Además de dispositivos electrónicos, instalamos soluciones físicas para almacenes, laboratorios, sites, cuartos de servidores y zonas restringidas.</p>
        <ul class="service-list">
          <li>Puertas electromagnéticas y cerraduras industriales</li>
          <li>Torniquetes y barreras vehiculares</li>
          <li>Accesos biométricos híbridos</li>
          <li>Puertas inteligentes con CCTV</li>
        </ul>
      </div>
    </div>
  </section>

  <section class="service-banner" aria-label="CCTV industrial conectado a accesos">
    <img src="<?php echo htmlspecialchars($basePath); ?>assets/imagesnew/BANNERS%20VER2/CCTV/PAGINA%20WEB%20ID%20INDUSTRIAL_11.jpg" alt="Control de acceso conectado a CCTV industrial" width="1920" height="500" loading="lazy" decoding="async">
    <div>
      <p>CCTV industrial conectado a accesos y monitoreo</p>
      <h2>Video, identidad y alertas en tiempo real</h2>
    </div>
  </section>

  <section class="faq-section section-dark section-pad">
    <div class="container">
      <div class="section-head reveal">
        <p class="eyebrow">Preguntas técnicas</p>
        <h2>Seguridad, robo interno, nómina y monitoreo inteligente.</h2>
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
        <h2>Recibe una propuesta de control de acceso conectada con CCTV y nómina.</h2>
      </div>
      <a class="button button--primary" href="<?php echo htmlspecialchars($basePath); ?>#contacto">Solicitar cotización</a>
    </div>
  </section>
</main>

<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "Service",
      "@id": "https://idindustrial.com.mx/control-de-acceso-de-personal-queretaro/#service",
      "name": "Control de acceso de personal Querétaro",
      "serviceType": "Sistema de control de acceso biométrico",
      "provider": {
        "@type": "Organization",
        "@id": "https://idindustrial.com.mx/#organization",
        "name": "ID Industrial",
        "url": "https://idindustrial.com.mx/"
      },
      "areaServed": {
        "@type": "Place",
        "name": "Querétaro"
      },
      "audience": {
        "@type": "Audience",
        "audienceType": "Recursos Humanos, Seguridad Patrimonial, CEOs"
      },
      "hasOfferCatalog": {
        "@type": "OfferCatalog",
        "name": "Soluciones de seguridad industrial",
        "itemListElement": [
          {"@type": "Service", "name": "Control de acceso biométrico"},
          {"@type": "Service", "name": "CCTV industrial"},
          {"@type": "Service", "name": "Puertas de alta seguridad"},
          {"@type": "Service", "name": "Control de asistencia biométrico"},
          {"@type": "Service", "name": "Integración control de accesos y nómina"}
        ]
      }
    },
    {
      "@type": "FAQPage",
      "@id": "https://idindustrial.com.mx/control-de-acceso-de-personal-queretaro/#faq",
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
      "@id": "https://idindustrial.com.mx/control-de-acceso-de-personal-queretaro/#breadcrumb",
      "itemListElement": [
        {"@type": "ListItem", "position": 1, "name": "Inicio", "item": "https://idindustrial.com.mx/"},
        {"@type": "ListItem", "position": 2, "name": "Control de acceso de personal Querétaro", "item": "https://idindustrial.com.mx/control-de-acceso-de-personal-queretaro/"}
      ]
    }
  ]
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
