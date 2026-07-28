<?php
$basePath = '../';
$siteUrl = 'https://idindustrial.com.mx/sistema/';
$assetUrlBase = 'https://idindustrial.com.mx/sistema/';
$canonicalUrl = 'https://idindustrial.com.mx/sistema/terminos-y-condiciones/';
$phone = '+52 442 598 6318';
$whatsapp = '524425986318';
$contactEmail = 'contacto@idindustrial.com.mx';
$heroMobileImage = 'assets/img/hero-mobile.webp';
$heroDesktopImage = 'assets/img/hero-desktop.webp';

$title = 'Términos y condiciones | ID Industrial';
$description = 'Términos y condiciones de uso del sitio web de ID Industrial, información comercial, cotizaciones y propiedad intelectual.';
$keywords = 'términos y condiciones ID Industrial, uso del sitio, cotizaciones, propiedad intelectual';

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
      <h1><span>Términos y</span><span>condiciones</span></h1>
      <p>Última actualización: 28 de julio de 2026.</p>
    </div>
  </section>

  <section class="detail-section section-light section-pad">
    <div class="container legal-layout">
      <article class="legal-copy reveal">
        <h2>Uso del sitio</h2>
        <p>Al navegar en este sitio aceptas utilizar la información de forma lícita y respetuosa. El contenido se ofrece para presentar servicios de ingeniería, infraestructura industrial, seguridad, HVAC, cableado, CCTV y soluciones relacionadas.</p>

        <h3>Información comercial</h3>
        <p>Las descripciones, imágenes, alcances y referencias técnicas son informativas. Cada proyecto requiere levantamiento, validación técnica y propuesta específica antes de confirmar precios, tiempos, marcas, disponibilidad o condiciones de servicio.</p>

        <h3>Cotizaciones y contratación</h3>
        <ul>
          <li>Las solicitudes enviadas por el sitio no constituyen una orden de compra automática.</li>
          <li>Las cotizaciones estarán sujetas a alcance, disponibilidad, condiciones del sitio y vigencia indicada.</li>
          <li>Los trabajos iniciarán conforme a la propuesta aceptada, anticipo, calendario y documentación aplicable.</li>
          <li>ID Industrial podrá solicitar información adicional para dimensionar correctamente un proyecto.</li>
        </ul>

        <h3>Propiedad intelectual</h3>
        <p>El nombre, logotipos, imágenes, textos, diseño, estructura y contenidos del sitio pertenecen a ID Industrial o se utilizan con autorización. No está permitido copiarlos, distribuirlos o modificarlos sin consentimiento previo por escrito.</p>

        <h3>Enlaces y terceros</h3>
        <p>El sitio puede incluir enlaces a canales externos como WhatsApp, correo, redes sociales o servicios de terceros. ID Industrial no controla las políticas, disponibilidad o funcionamiento de sitios externos.</p>

        <h3>Limitación de responsabilidad</h3>
        <p>Se procura mantener la información actualizada y disponible, pero no se garantiza que el sitio esté libre de interrupciones, errores técnicos o cambios de contenido. ID Industrial no será responsable por daños derivados del uso indebido del sitio.</p>

        <h3>Modificaciones</h3>
        <p>ID Industrial puede actualizar estos términos en cualquier momento. Los cambios estarán disponibles en esta página y serán aplicables desde su publicación.</p>
      </article>

      <aside class="detail-panel legal-aside reveal reveal--delay">
        <h3>Atención comercial</h3>
        <ul>
          <li>Correo: contacto@idindustrial.com.mx</li>
          <li>Teléfono: +52 442 598 6318</li>
          <li>WhatsApp: +52 442 598 6318</li>
        </ul>
      </aside>
    </div>
  </section>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
