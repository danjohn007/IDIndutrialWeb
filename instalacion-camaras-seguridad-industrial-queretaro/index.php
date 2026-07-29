<?php
$basePath = '../';
$siteUrl = 'https://idindustrial.com.mx/sistema/';
$assetUrlBase = 'https://idindustrial.com.mx/sistema/';
$canonicalUrl = 'https://idindustrial.com.mx/sistema/instalacion-camaras-seguridad-industrial-queretaro/';
$phone = '+52 442 598 6318';
$whatsapp = '524425986318';
$contactEmail = 'contacto@idindustrial.com.mx';
$heroMobileImage = 'assets/img/optimized/home-hero-cctv.jpg';
$heroDesktopImage = 'assets/img/optimized/home-hero-cctv.jpg';

$title = 'Instalación de cámaras de seguridad industrial en Querétaro | CCTV';
$description = 'Diseño, instalación y mantenimiento de CCTV industrial en Querétaro para naves, plantas, oficinas, almacenes y perímetros.';
$keywords = 'CCTV industrial Querétaro, cámaras de seguridad industrial, videovigilancia industrial, instalación CCTV empresas';

$decisionItems = [
  [
    'term' => 'CCTV IP o analógico',
    'definition' => 'La elección depende de la infraestructura existente, distancia de cableado, calidad requerida, presupuesto y necesidad de administración remota.',
  ],
  [
    'term' => 'Días de almacenamiento',
    'definition' => 'Se calculan por cantidad de cámaras, resolución, cuadros por segundo, tipo de compresión, horarios de grabación y retención de evidencia.',
  ],
  [
    'term' => 'Resolución por zona',
    'definition' => 'No todas las áreas necesitan la misma definición: accesos, andenes, cajas, perímetros y pasillos requieren objetivos visuales distintos.',
  ],
  [
    'term' => 'Tipo de cámara',
    'definition' => 'Bullet, domo, PTZ, térmica o LPR se seleccionan según distancia, iluminación, intemperie, ángulo de vista y tipo de evento a revisar.',
  ],
  [
    'term' => 'Grabación local, nube o híbrida',
    'definition' => 'El esquema debe equilibrar continuidad, ancho de banda, acceso remoto, respaldo y políticas internas de manejo de evidencia.',
  ],
  [
    'term' => 'Entregables del proyecto',
    'definition' => 'Un sistema profesional debe quedar con planos, configuración documentada, usuarios definidos, pruebas de grabación y recomendaciones de mantenimiento.',
  ],
  [
    'term' => 'Mantenimiento preventivo',
    'definition' => 'Limpieza, reenfoque, revisión de conectividad, respaldo, salud del grabador y pruebas de acceso remoto evitan perder evidencia cuando más se necesita.',
  ],
];

$faqItems = [
  [
    'q' => '¿Qué incluye una instalación de CCTV industrial?',
    'a' => 'Incluye levantamiento, diseño de puntos de cámara, canalización, cableado, montaje, configuración de grabación, pruebas, documentación técnica y capacitación básica.',
  ],
  [
    'q' => '¿Cuánto cuesta instalar cámaras de seguridad en una empresa?',
    'a' => 'Depende del número de puntos, tipo de cámara, almacenamiento, infraestructura existente, alturas de instalación e integración con red, accesos o alarmas.',
  ],
  [
    'q' => '¿Se puede integrar CCTV con control de accesos?',
    'a' => 'Sí. La integración permite asociar eventos de entrada, salida o zonas restringidas con video, usuarios, horarios y reportes para auditoría.',
  ],
  [
    'q' => '¿Dan mantenimiento a sistemas ya instalados?',
    'a' => 'Sí. Podemos diagnosticar pérdida de video, fallas de red, problemas de almacenamiento, cámaras dañadas y ajustes de enfoque o configuración.',
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
      <p class="eyebrow">CCTV industrial Querétaro</p>
      <h1><span>Instalación de cámaras</span><span>de seguridad industrial</span><span>en Querétaro</span></h1>
      <p>Diseñamos e instalamos videovigilancia para naves, plantas, oficinas, almacenes y perímetros donde la evidencia, el monitoreo y la continuidad operativa importan.</p>
      <div class="hero__actions">
        <a class="button button--primary" href="<?php echo htmlspecialchars($basePath); ?>?servicio=cctv#cotizacion">Solicitar evaluación</a>
        <a class="button button--ghost" href="#criterios-cctv">Ver criterios técnicos</a>
      </div>
    </div>
  </section>

  <section id="criterios-cctv" class="detail-section section-light section-pad">
    <div class="container detail-grid">
      <div class="detail-copy reveal">
        <p class="eyebrow">Diseño antes que cantidad</p>
        <h2>Un buen sistema CCTV empieza por qué necesitas ver, grabar y comprobar.</h2>
        <p>El levantamiento define puntos ciegos, condiciones de luz, alturas, rutas de cableado, retención de video y permisos de usuario. Así se evita instalar cámaras de más en zonas de poco valor o dejar áreas críticas sin evidencia útil.</p>
      </div>
      <div class="detail-panel reveal reveal--delay">
        <h3>Aplicaciones frecuentes</h3>
        <ul>
          <li>Accesos peatonales y vehiculares</li>
          <li>Andenes, almacenes y patios</li>
          <li>Líneas de producción y zonas restringidas</li>
          <li>Oficinas, recepción y casetas</li>
          <li>Perímetros y puntos de control</li>
        </ul>
      </div>
    </div>
  </section>

  <section class="detail-section section-dark section-pad">
    <div class="container split">
      <div class="split__content reveal">
        <p class="eyebrow">Proyecto llave en mano</p>
        <h2>Instalación, red, almacenamiento y documentación en un solo alcance.</h2>
        <p>Integramos cámaras, cableado, grabadores, red, usuarios y monitoreo para que el sistema quede operable desde el primer día, con evidencia fácil de consultar y criterios claros de mantenimiento.</p>
        <ul class="service-list">
          <li>Diseño de ubicación y ángulos de cámara</li>
          <li>Canalización y cableado estructurado</li>
          <li>Configuración de NVR/DVR y usuarios</li>
          <li>Pruebas de grabación y visualización remota</li>
          <li>Memoria técnica y recomendaciones de soporte</li>
        </ul>
      </div>
      <figure class="service__image service__image--cctv reveal reveal--delay">
        <img src="<?php echo htmlspecialchars($basePath); ?>assets/img/optimized/service-cctv.jpg" alt="Cámara CCTV para nave industrial" width="1920" height="800" loading="lazy" decoding="async">
      </figure>
    </div>
  </section>

  <section class="service-banner service-banner--contain" aria-label="CCTV industrial para monitoreo operativo">
    <img src="<?php echo htmlspecialchars($basePath); ?>assets/img/optimized/home-hero-cctv.jpg" alt="Sistema CCTV industrial para monitoreo operativo" width="1920" height="500" loading="lazy" decoding="async">
    <div>
      <p>Video útil para operación, seguridad y auditoría</p>
      <h2>Evidencia clara en las zonas que más importan</h2>
    </div>
  </section>

  <section class="faq-section section-light section-pad">
    <div class="container">
      <div class="section-head reveal">
        <p class="eyebrow">Criterios técnicos</p>
        <h2>Decisiones que conviene resolver antes de comprar cámaras.</h2>
      </div>
      <div class="faq-list">
        <?php foreach ($decisionItems as $item): ?>
          <details class="faq-item reveal">
            <summary><?php echo htmlspecialchars($item['term']); ?></summary>
            <p><?php echo htmlspecialchars($item['definition']); ?></p>
          </details>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="detail-section section-dark section-pad">
    <div class="container split">
      <figure class="service__image service__image--monitoring reveal">
        <img src="<?php echo htmlspecialchars($basePath); ?>assets/img/optimized/home-hero-control-acceso.jpg" alt="Monitoreo integrado con CCTV y control de accesos" width="1920" height="500" loading="lazy" decoding="async">
      </figure>
      <div class="split__content reveal reveal--delay">
        <p class="eyebrow">Integración operativa</p>
        <h2>CCTV conectado con accesos, red y monitoreo.</h2>
        <p>Cuando el video se integra con control de accesos, alarmas o red interna, cada evento se vuelve más fácil de revisar: quién entró, cuándo ocurrió y qué evidencia existe.</p>
        <ul class="service-list">
          <li>Eventos asociados a usuarios y horarios</li>
          <li>Monitoreo remoto con permisos definidos</li>
          <li>Respaldo de evidencia por incidentes</li>
          <li>Soporte preventivo y correctivo</li>
        </ul>
      </div>
    </div>
  </section>

  <section class="faq-section section-light section-pad">
    <div class="container">
      <div class="section-head reveal">
        <p class="eyebrow">Preguntas frecuentes</p>
        <h2>CCTV, almacenamiento, integración y mantenimiento.</h2>
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
        <p class="eyebrow">Levantamiento técnico</p>
        <h2>Recibe una propuesta de CCTV industrial conectada con operación, accesos y red.</h2>
      </div>
      <a class="button button--primary" href="<?php echo htmlspecialchars($basePath); ?>?servicio=cctv#cotizacion">Solicitar cotización</a>
    </div>
  </section>
</main>

<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "Service",
      "@id": "https://idindustrial.com.mx/sistema/instalacion-camaras-seguridad-industrial-queretaro/#service",
      "name": "Instalación de cámaras de seguridad industrial",
      "serviceType": "CCTV industrial y videovigilancia",
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
      "description": "Diseño, instalación, integración y mantenimiento de sistemas CCTV para empresas industriales en Querétaro.",
      "hasOfferCatalog": {
        "@type": "OfferCatalog",
        "name": "Alcances CCTV industrial",
        "itemListElement": [
          {"@type": "Service", "name": "Diseño de puntos de cámara"},
          {"@type": "Service", "name": "Instalación de CCTV"},
          {"@type": "Service", "name": "Integración con control de accesos"},
          {"@type": "Service", "name": "Mantenimiento preventivo y correctivo"}
        ]
      }
    },
    {
      "@type": "FAQPage",
      "@id": "https://idindustrial.com.mx/sistema/instalacion-camaras-seguridad-industrial-queretaro/#faq",
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
      "@id": "https://idindustrial.com.mx/sistema/instalacion-camaras-seguridad-industrial-queretaro/#breadcrumb",
      "itemListElement": [
        {"@type": "ListItem", "position": 1, "name": "Inicio", "item": "https://idindustrial.com.mx/sistema/"},
        {"@type": "ListItem", "position": 2, "name": "CCTV industrial", "item": "https://idindustrial.com.mx/sistema/instalacion-camaras-seguridad-industrial-queretaro/"}
      ]
    }
  ]
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
