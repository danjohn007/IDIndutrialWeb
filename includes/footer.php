<footer class="site-footer">
  <?php
    $assetBase = $basePath ?? '';
    $scriptFile = is_file(__DIR__ . '/../assets/js/main.min.js') ? 'main.min.js' : 'main.js';
    $scriptVersion = @filemtime(__DIR__ . '/../assets/js/' . $scriptFile) ?: '1';
    $scriptHref = $assetBase . 'assets/js/' . $scriptFile . '?v=' . $scriptVersion;
  ?>
  <div class="container footer__grid">
    <div class="footer__brand">
      <img src="<?php echo htmlspecialchars($assetBase); ?>assets/img/logo-idindustrial-small.webp" alt="ID Industrial" class="footer__logo" width="280" height="74" loading="lazy" decoding="async" fetchpriority="low">
      <span class="footer__rule" aria-hidden="true"></span>
      <p>Ingeniería industrial en Querétaro para infraestructura TI crítica, seguridad, climatización e integración técnica en plantas, naves y edificios corporativos.</p>
      <div class="footer__badges" aria-label="Capacidades de trabajo">
        <span>Levantamiento</span>
        <span>Instalación</span>
        <span>Soporte</span>
      </div>
    </div>

    <nav class="footer__nav" aria-label="Especializaciones ID Industrial">
      <h2>Especializaciones</h2>
      <a href="<?php echo htmlspecialchars($assetBase); ?>industriales/cableado-estructurado-queretaro/">Cableado Estructurado &amp; Fibra</a>
      <a href="<?php echo htmlspecialchars($assetBase); ?>instalacion-aire-acondicionado-industrial-queretaro/">HVAC Industrial &amp; Chillers</a>
      <a href="<?php echo htmlspecialchars($assetBase); ?>instalacion-camaras-seguridad-industrial-queretaro/">CCTV Industrial</a>
      <a href="<?php echo htmlspecialchars($assetBase); ?>control-de-acceso-de-personal-queretaro/">Control de Accesos</a>
      <a href="<?php echo htmlspecialchars($assetBase); ?>#deteccion-incendios">Detección de Incendios</a>
    </nav>

    <div class="footer__region">
      <h2>Región Bajío</h2>
      <ul>
        <li>Querétaro &amp; Corregidora</li>
        <li>El Marqués &amp; Colón</li>
        <li>Apaseo el Grande &amp; Celaya</li>
      </ul>
      <a class="footer__phone" href="tel:+524425986318">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.62 10.78a15.3 15.3 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1.02-.24 11.4 11.4 0 0 0 3.56.56 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1 11.4 11.4 0 0 0 .56 3.56 1 1 0 0 1-.24 1.02l-2.2 2.2Z"/></svg>
        <strong>+52 442 598 6318</strong>
      </a>
      <div class="footer__socials" aria-label="Redes sociales">
        <a class="footer__social-link footer__social-link--facebook" href="https://www.facebook.com/share/1PZdBWCVkd/" target="_blank" rel="noopener noreferrer" aria-label="Abrir Facebook de ID Industrial">
          <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="#1877f2"/><path fill="#fff" d="M13.55 20v-7h2.35l.35-2.72h-2.7V8.54c0-.79.22-1.33 1.35-1.33h1.44V4.78c-.25-.03-1.1-.1-2.1-.1-2.08 0-3.5 1.27-3.5 3.6v2H8.4V13h2.34v7h2.81Z"/></svg>
          <span>Facebook</span>
        </a>
        <a class="footer__social-link footer__social-link--instagram" href="https://www.instagram.com/idindustrialmx?igsh=dzBkOTF0b3ZvY2Zw" target="_blank" rel="noopener noreferrer" aria-label="Abrir Instagram de ID Industrial">
          <svg viewBox="0 0 24 24" aria-hidden="true"><defs><linearGradient id="instagram-footer" x1="2" y1="22" x2="22" y2="2" gradientUnits="userSpaceOnUse"><stop stop-color="#ffdc80"/><stop offset=".32" stop-color="#fcaf45"/><stop offset=".58" stop-color="#f77737"/><stop offset=".78" stop-color="#c13584"/><stop offset="1" stop-color="#833ab4"/></linearGradient></defs><rect x="1" y="1" width="22" height="22" rx="6" fill="url(#instagram-footer)"/><path fill="#fff" d="M12 6.45A5.55 5.55 0 1 0 12 17.55 5.55 5.55 0 0 0 12 6.45Zm0 8.96A3.41 3.41 0 1 1 12 8.59a3.41 3.41 0 0 1 0 6.82Zm7.2-9.2a1.3 1.3 0 1 1-2.6 0 1.3 1.3 0 0 1 2.6 0Z"/></svg>
          <span>Instagram</span>
        </a>
      </div>
    </div>
  </div>

  <div class="container footer__bottom">
    <span>© <?php echo date('Y'); ?> ID Industrial · Ingeniería industrial en Querétaro y Bajío.</span>
    <div>
      <a href="<?php echo htmlspecialchars($assetBase); ?>aviso-de-privacidad/">Aviso de privacidad</a>
      <a href="<?php echo htmlspecialchars($assetBase); ?>terminos-y-condiciones/">Términos</a>
    </div>
  </div>
</footer>

<aside class="social-dock" aria-label="Canales de contacto">
  <a class="social-dock__link social-dock__link--whatsapp" href="https://wa.me/<?php echo htmlspecialchars($whatsapp ?? '524425986318'); ?>?text=Hola%20ID%20Industrial,%20quiero%20solicitar%20una%20evaluaci%C3%B3n%20t%C3%A9cnica" target="_blank" rel="noopener noreferrer" aria-label="Contactar por WhatsApp">
    <svg viewBox="0 0 32 32" aria-hidden="true">
      <path d="M16.04 3.2A12.72 12.72 0 0 0 5.2 22.6L4 29l6.56-1.72A12.72 12.72 0 1 0 16.04 3.2Zm0 22.84a10.1 10.1 0 0 1-5.14-1.4l-.36-.22-3.9 1.02 1.04-3.78-.24-.4A10.08 10.08 0 1 1 16.04 26.04Zm5.52-7.54c-.3-.16-1.8-.9-2.08-1-.28-.1-.48-.16-.68.16-.2.3-.78 1-.96 1.2-.18.2-.36.22-.66.08-.3-.16-1.28-.48-2.44-1.52-.9-.8-1.5-1.78-1.68-2.08-.18-.3-.02-.46.14-.62.14-.14.3-.36.46-.54.16-.18.2-.3.3-.5.1-.2.06-.38-.02-.54-.08-.16-.68-1.64-.94-2.24-.24-.58-.5-.5-.68-.5h-.58c-.2 0-.52.08-.8.38-.28.3-1.06 1.04-1.06 2.54s1.1 2.94 1.24 3.14c.16.2 2.16 3.3 5.24 4.62.74.32 1.3.5 1.74.64.74.24 1.4.2 1.94.12.6-.1 1.8-.74 2.06-1.46.26-.72.26-1.34.18-1.46-.08-.14-.28-.22-.58-.38Z"/>
    </svg>
  </a>
  <a class="social-dock__link" href="tel:+524425986318" aria-label="Llamar a ID Industrial">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.62 10.78a15.3 15.3 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1.02-.24 11.4 11.4 0 0 0 3.56.56 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1 11.4 11.4 0 0 0 .56 3.56 1 1 0 0 1-.24 1.02l-2.2 2.2Z"/></svg>
  </a>
  <a class="social-dock__link" href="mailto:tecnologia@idindustrial.com.mx" aria-label="Enviar correo a ID Industrial">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Zm0 3.2V17h16V8.2l-7.42 5.16a1 1 0 0 1-1.16 0L4 8.2Zm1.1-1.2 6.9 4.8L18.9 7H5.1Z"/></svg>
  </a>
  <a class="social-dock__link social-dock__link--facebook" href="https://www.facebook.com/share/1PZdBWCVkd/" target="_blank" rel="noopener noreferrer" aria-label="Facebook de ID Industrial">
    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="#1877f2"/><path fill="#fff" d="M13.55 20v-7h2.35l.35-2.72h-2.7V8.54c0-.79.22-1.33 1.35-1.33h1.44V4.78c-.25-.03-1.1-.1-2.1-.1-2.08 0-3.5 1.27-3.5 3.6v2H8.4V13h2.34v7h2.81Z"/></svg>
  </a>
</aside>

<script src="<?php echo htmlspecialchars($scriptHref); ?>" defer></script>
</body>
</html>
