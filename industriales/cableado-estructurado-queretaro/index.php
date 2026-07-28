<?php
$basePath = '../../';
$siteUrl = 'https://idindustrial.com.mx/sistema/';
$assetUrlBase = 'https://idindustrial.com.mx/sistema/';
$canonicalUrl = 'https://idindustrial.com.mx/sistema/industriales/cableado-estructurado-queretaro/';
$phone = '+52 442 598 6318';
$whatsapp = '524425986318';
$contactEmail = 'contacto@idindustrial.com.mx';
$heroMobileImage = 'assets/imagesnew/BANNERS/CABLEADO%20ESTRUCTURADO/LANDING%20ID%20INDUSTRIAL_10.jpg';
$heroDesktopImage = 'assets/imagesnew/BANNERS%20VER2/CABLEADO%20ESTRUCTURADO/PAGINA%20WEB%20ID%20INDUSTRIAL_10.jpg';

$title = 'Empresas de Cableado Estructurado en Querétaro | Redes, fibra óptica, sites, servidores';
$description = 'Instalación de cableado estructurado en Querétaro para empresas industriales: redes, fibra óptica, servidores y voz y datos. +20 años de experiencia.';
$keywords = 'empresas de cableado estructurado en queretaro, cableado estructurado queretaro, instalacion de cableado estructurado, instaladores de cableado estructurado, instalacion de cableado de red, cableado estructurado industrial, instalacion de voz y datos, instalacion cableado fibra óptica, instalacion servidores, servicios de cableado estructurado, Smart Factory Querétaro, IIoT industrial, SCADA industrial, infraestructura TI industrial';

$faqItems = [
  [
    'q' => '¿Cómo diseñar una red industrial que garantice conectividad estable en toda la planta?',
    'a' => 'Una red industrial estable requiere cableado estructurado certificado, segmentación por áreas, backbone de fibra óptica y redundancia en puntos críticos para sistemas ERP, SCADA, IIoT y nube.',
  ],
  [
    'q' => '¿Qué arquitectura de red es ideal para plantas con múltiples áreas operativas y administrativas?',
    'a' => 'La arquitectura recomendada es una topología jerárquica con MDF e IDF distribuidos, backbone en fibra óptica y cableado estructurado para distribución horizontal.',
  ],
  [
    'q' => '¿Cómo evitar interferencias electromagnéticas en redes industriales?',
    'a' => 'Se recomienda utilizar cableado STP o fibra óptica, canalización adecuada y separación de líneas eléctricas para reducir interferencias y proteger datos críticos.',
  ],
  [
    'q' => '¿Cuánto cuesta la instalación de cableado estructurado en una nave industrial?',
    'a' => 'El costo depende del tamaño del proyecto, tipo de cableado, cantidad de nodos y complejidad de instalación. Una cotización técnica evita sobrecostos y retrabajos.',
  ],
];

include __DIR__ . '/../../includes/head.php';
include __DIR__ . '/../../includes/navbar.php';
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
      <p class="eyebrow">Cableado estructurado Querétaro</p>
      <h1><span>Empresa de</span><span>cableado</span><span>estructurado</span><span>en Querétaro</span></h1>
      <p>Diseño, instalación y mantenimiento de redes industriales con fibra óptica, servidores, CCTV, control de accesos y equipo de cómputo para sistemas críticos.</p>
      <div class="hero__actions">
        <a class="button button--primary" href="<?php echo htmlspecialchars($basePath); ?>#contacto">Cotizar proyecto</a>
        <a class="button button--ghost" href="#alcance">Ver alcance técnico</a>
      </div>
    </div>
  </section>

  <section id="alcance" class="detail-section section-light section-pad">
    <div class="container detail-grid">
      <div class="detail-copy reveal">
        <p class="eyebrow">Industria y corporativos</p>
        <h2>Instalación de cableado estructurado en Querétaro con enfoque industrial.</h2>
        <p>Integramos infraestructura confiable para operar ERP, SCADA, IIoT, redes de voz y datos y plataformas en la nube, garantizando conectividad, estabilidad y escalabilidad.</p>
      </div>
      <div class="detail-panel reveal reveal--delay">
        <h3>Capacidades técnicas</h3>
        <ul>
          <li>Venta e instalación de MDFs e IDFs</li>
          <li>Segmentación de red y alta disponibilidad</li>
          <li>Cableado Cat6, Cat6A, Cat7 y Cat8</li>
          <li>Fibra óptica monomodo y multimodo</li>
          <li>Pruebas, certificación y memoria técnica</li>
        </ul>
      </div>
    </div>
  </section>

  <section class="detail-section section-dark section-pad">
    <div class="container split">
      <div class="split__content reveal">
        <p class="eyebrow">Smart Factory Querétaro</p>
        <h2>Redes robustas para IIoT, SCADA y datos en tiempo real.</h2>
        <p>La evolución hacia plantas inteligentes exige infraestructura capaz de conectar PLCs, sensores, plataformas cloud, analítica e inteligencia artificial industrial sin sacrificar seguridad ni disponibilidad.</p>
        <ul class="service-list">
          <li>Integración de sistemas de producción</li>
          <li>Comunicación entre PLCs y áreas operativas</li>
          <li>Backbone de fibra óptica para baja latencia</li>
          <li>Seguridad de datos y crecimiento modular</li>
        </ul>
      </div>
      <figure class="service__image reveal reveal--delay">
        <img src="<?php echo htmlspecialchars($basePath); ?>assets/imagesnew/SLIDES2/CABLEADO%20ESTRUCTURADO/PAGINA%20WEB%20ID%20INDUSTRIAL_1.jpg" alt="Cableado estructurado industrial con fibra óptica" width="1920" height="800" loading="lazy" decoding="async">
      </figure>
    </div>
  </section>

  <section class="service-banner" aria-label="Cableado estructurado industrial">
    <img src="<?php echo htmlspecialchars($basePath); ?>assets/imagesnew/SLIDES2/CABLEADO%20ESTRUCTURADO/ID%20INDUSTRIAL%20WEB_5.jpg" alt="Instalación de voz y datos Querétaro" width="1920" height="500" loading="lazy" decoding="async">
    <div>
      <p>Diseño e instalación de redes con fibra óptica</p>
      <h2>Cableado estructurado industrial</h2>
    </div>
  </section>

  <section class="detail-section section-light section-pad">
    <div class="container detail-grid detail-grid--reverse">
      <div class="detail-panel reveal">
        <h3>Servicios de cableado estructurado</h3>
        <ul>
          <li>Instalación de cableado estructurado</li>
          <li>Instalación de fibra óptica</li>
          <li>Redes de voz y datos</li>
          <li>Instalación de servidores y sites</li>
          <li>Redes WiFi industriales</li>
          <li>Integración con CCTV</li>
          <li>Mantenimiento de redes</li>
        </ul>
      </div>
      <div class="detail-copy reveal reveal--delay">
        <p class="eyebrow">Fortalezas ID Industrial</p>
        <h2>Proyectos llave en mano para gerentes de TI y compras industriales.</h2>
        <p>Más de 20 años de experiencia, cuadrillas técnicas especializadas, documentación completa, cumplimiento normativo y ejecución en parques industriales de Querétaro, El Marqués y San Juan del Río.</p>
      </div>
    </div>
  </section>

  <section class="faq-section section-dark section-pad">
    <div class="container">
      <div class="section-head reveal">
        <p class="eyebrow">Preguntas técnicas</p>
        <h2>Cómo evaluar una solución de cableado estructurado industrial.</h2>
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
        <p class="eyebrow">Evaluación técnica</p>
        <h2>Planea una red estable, escalable y preparada para crecimiento industrial.</h2>
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
      "@type": "Organization",
      "@id": "https://idindustrial.com.mx/#organization",
      "name": "ID Industrial",
      "url": "https://idindustrial.com.mx/",
      "areaServed": "Querétaro",
      "knowsAbout": [
        "cableado estructurado industrial",
        "fibra óptica",
        "infraestructura TI industrial",
        "CCTV industrial",
        "control de accesos",
        "HVAC industrial",
        "subestaciones eléctricas",
        "sistemas contra incendio",
        "SCADA",
        "IIoT"
      ],
      "hasOfferCatalog": {
        "@type": "OfferCatalog",
        "name": "Soluciones industriales",
        "itemListElement": [
          {"@type": "Service", "name": "Cableado estructurado y fibra óptica"},
          {"@type": "Service", "name": "Servidores y sites"},
          {"@type": "Service", "name": "CCTV y control de accesos"},
          {"@type": "Service", "name": "HVAC industrial"},
          {"@type": "Service", "name": "Sistemas contra incendio"}
        ]
      }
    },
    {
      "@type": "Service",
      "@id": "https://idindustrial.com.mx/industriales/cableado-estructurado-queretaro/#service",
      "name": "Instalación de cableado estructurado y fibra óptica",
      "serviceType": "Infraestructura de red industrial",
      "provider": {
        "@id": "https://idindustrial.com.mx/#organization"
      },
      "areaServed": "Querétaro",
      "audience": {
        "@type": "Audience",
        "audienceType": "Gerentes de TI y compras industriales"
      },
      "hasOfferCatalog": {
        "@type": "OfferCatalog",
        "name": "Servicios de red",
        "itemListElement": [
          {"@type": "Offer", "itemOffered": {"@type": "Service", "name": "Instalación de fibra óptica"}},
          {"@type": "Offer", "itemOffered": {"@type": "Service", "name": "Redes de voz y datos"}},
          {"@type": "Offer", "itemOffered": {"@type": "Service", "name": "Servidores y sites"}},
          {"@type": "Offer", "itemOffered": {"@type": "Service", "name": "Redes WiFi industriales"}},
          {"@type": "Offer", "itemOffered": {"@type": "Service", "name": "Mantenimiento de redes"}}
        ]
      }
    },
    {
      "@type": "FAQPage",
      "@id": "https://idindustrial.com.mx/industriales/cableado-estructurado-queretaro/#faq",
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
    }
  ]
}
</script>

<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    {
      "@type": "ListItem",
      "position": 1,
      "name": "Inicio",
      "item": "https://idindustrial.com.mx/"
    },
    {
      "@type": "ListItem",
      "position": 2,
      "name": "Cableado estructurado Querétaro",
      "item": "https://idindustrial.com.mx/industriales/cableado-estructurado-queretaro/"
    }
  ]
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
