<?php
$basePath = '../';
$siteUrl = 'https://idindustrial.com.mx/sistema/';
$assetUrlBase = 'https://idindustrial.com.mx/sistema/';
$canonicalUrl = 'https://idindustrial.com.mx/sistema/aviso-de-privacidad/';
$phone = '+52 442 598 6318';
$whatsapp = '524425986318';
$contactEmail = 'contacto@idindustrial.com.mx';
$heroMobileImage = 'assets/img/hero-mobile.webp';
$heroDesktopImage = 'assets/img/hero-desktop.webp';

$title = 'Aviso de privacidad | ID Industrial';
$description = 'Aviso de privacidad de ID Industrial sobre tratamiento de datos personales, finalidades, derechos ARCO y medios de contacto.';
$keywords = 'aviso de privacidad ID Industrial, datos personales, derechos ARCO, privacidad Querétaro';

include __DIR__ . '/../includes/head.php';
include __DIR__ . '/../includes/navbar.php';
?>

<main class="service-page legal-page" id="inicio">
  <section class="service-hero service-hero--compact section-dark">
    <div class="service-hero__media" aria-hidden="true">
      <picture>
        <source srcset="<?php echo htmlspecialchars($basePath . $heroDesktopImage); ?>" media="(min-width: 900px)">
        <img src="<?php echo htmlspecialchars($basePath . $heroMobileImage); ?>" alt="" width="820" height="342" fetchpriority="high" decoding="async">
      </picture>
    </div>
    <div class="service-hero__overlay" aria-hidden="true"></div>
    <div class="container service-hero__content reveal">
      <p class="eyebrow">ID Industrial</p>
      <h1><span>Aviso de</span><span>privacidad</span></h1>
      <p>Última actualización: 28 de julio de 2026.</p>
    </div>
  </section>

  <section class="detail-section section-light section-pad">
    <div class="container legal-layout">
      <article class="legal-copy reveal">
        <h2>Tratamiento de datos personales</h2>
        <p>ID Industrial, con domicilio de atención en Querétaro, Querétaro, México, es responsable del uso y protección de los datos personales que proporciones por medio de formularios, WhatsApp, correo electrónico, llamadas telefónicas o solicitudes comerciales.</p>

        <h3>Datos que podemos recabar</h3>
        <p>Podemos solicitar nombre, empresa, cargo, teléfono, correo electrónico, ubicación del proyecto, servicio de interés, datos técnicos del inmueble o instalación y cualquier información necesaria para responder una solicitud de cotización, diagnóstico o soporte.</p>

        <h3>Finalidades</h3>
        <ul>
          <li>Atender solicitudes de información, cotización o diagnóstico técnico.</li>
          <li>Dar seguimiento comercial, operativo y administrativo a proyectos.</li>
          <li>Coordinar visitas, levantamientos, propuestas y servicios contratados.</li>
          <li>Generar documentación técnica, facturación y comunicación de servicio.</li>
          <li>Mejorar la atención, seguridad y funcionamiento del sitio web.</li>
        </ul>

        <h3>Transferencias</h3>
        <p>Los datos podrán compartirse únicamente con personal interno, proveedores técnicos, aliados operativos o autoridades competentes cuando sea necesario para atender un proyecto, cumplir obligaciones legales o ejecutar servicios solicitados.</p>

        <h3>Derechos ARCO</h3>
        <p>Puedes solicitar acceso, rectificación, cancelación u oposición al tratamiento de tus datos personales, así como revocar tu consentimiento, escribiendo a <a href="mailto:contacto@idindustrial.com.mx">contacto@idindustrial.com.mx</a>. La solicitud deberá incluir nombre, medio de contacto, derecho que deseas ejercer y datos que permitan localizar tu información.</p>

        <h3>Cookies y analítica</h3>
        <p>El sitio puede utilizar cookies técnicas o herramientas de medición para mejorar la experiencia de navegación, entender el uso de las páginas y mantener seguridad operativa. Puedes limitar el uso de cookies desde la configuración de tu navegador.</p>

        <h3>Cambios al aviso</h3>
        <p>Cualquier modificación a este aviso se publicará en esta misma página. Te recomendamos revisarlo periódicamente.</p>
      </article>

      <aside class="detail-panel legal-aside reveal reveal--delay">
        <h3>Contacto de privacidad</h3>
        <ul>
          <li>Correo: contacto@idindustrial.com.mx</li>
          <li>Teléfono: +52 442 598 6318</li>
          <li>Atención: Querétaro y región Bajío</li>
        </ul>
      </aside>
    </div>
  </section>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
