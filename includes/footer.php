<footer class="site-footer">
  <?php $assetBase = $basePath ?? ''; ?>
  <div class="container footer__grid">
    <div>
      <img src="<?php echo htmlspecialchars($assetBase); ?>assets/img/logo-idindustrial.png" alt="ID Industrial" class="footer__logo" width="500" height="132" loading="lazy" decoding="async">
      <p>Ingeniería industrial para continuidad operativa, seguridad e infraestructura crítica en Querétaro y Bajío.</p>
    </div>
    <div>
      <h2>Servicios</h2>
      <a href="<?php echo htmlspecialchars($assetBase); ?>industriales/cableado-estructurado-queretaro/">Cableado estructurado</a>
      <a href="<?php echo htmlspecialchars($assetBase); ?>#deteccion-incendios">Detección de incendios</a>
      <a href="<?php echo htmlspecialchars($assetBase); ?>#sistemas-hvac">Sistemas HVAC</a>
      <a href="<?php echo htmlspecialchars($assetBase); ?>#fibra-optica">Fibra óptica</a>
      <a href="<?php echo htmlspecialchars($assetBase); ?>#control-accesos">Control de Accesos</a>
    </div>
    <div>
      <h2>Atención directa</h2>
      <a href="tel:+524425986318">+52 442 598 6318</a>
      <a href="mailto:contacto@idindustrial.com.mx">contacto@idindustrial.com.mx</a>
      <a href="https://wa.me/524425986318" target="_blank" rel="noopener">WhatsApp</a>
    </div>
  </div>
  <div class="container footer__bottom">
    <span>© <?php echo date('Y'); ?> ID Industrial. Todos los derechos reservados.</span>
    <a href="<?php echo htmlspecialchars($assetBase); ?>#inicio">Volver arriba</a>
  </div>
</footer>

<aside class="social-dock" aria-label="Canales de contacto">
  <a class="social-dock__link social-dock__link--whatsapp" href="https://wa.me/<?php echo htmlspecialchars($whatsapp ?? '524425986318'); ?>?text=Hola%20ID%20Industrial,%20quiero%20cotizar%20un%20proyecto" target="_blank" rel="noopener" aria-label="Contactar por WhatsApp">
    <svg viewBox="0 0 32 32" aria-hidden="true">
      <path d="M16.04 3.2A12.72 12.72 0 0 0 5.2 22.6L4 29l6.56-1.72A12.72 12.72 0 1 0 16.04 3.2Zm0 22.84a10.1 10.1 0 0 1-5.14-1.4l-.36-.22-3.9 1.02 1.04-3.78-.24-.4A10.08 10.08 0 1 1 16.04 26.04Zm5.52-7.54c-.3-.16-1.8-.9-2.08-1-.28-.1-.48-.16-.68.16-.2.3-.78 1-.96 1.2-.18.2-.36.22-.66.08-.3-.16-1.28-.48-2.44-1.52-.9-.8-1.5-1.78-1.68-2.08-.18-.3-.02-.46.14-.62.14-.14.3-.36.46-.54.16-.18.2-.3.3-.5.1-.2.06-.38-.02-.54-.08-.16-.68-1.64-.94-2.24-.24-.58-.5-.5-.68-.5h-.58c-.2 0-.52.08-.8.38-.28.3-1.06 1.04-1.06 2.54s1.1 2.94 1.24 3.14c.16.2 2.16 3.3 5.24 4.62.74.32 1.3.5 1.74.64.74.24 1.4.2 1.94.12.6-.1 1.8-.74 2.06-1.46.26-.72.26-1.34.18-1.46-.08-.14-.28-.22-.58-.38Z"/>
    </svg>
  </a>
  <a class="social-dock__link" href="tel:+524425986318" aria-label="Llamar a ID Industrial">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.62 10.78a15.3 15.3 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1.02-.24 11.4 11.4 0 0 0 3.56.56 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1 11.4 11.4 0 0 0 .56 3.56 1 1 0 0 1-.24 1.02l-2.2 2.2Z"/></svg>
  </a>
  <a class="social-dock__link" href="mailto:contacto@idindustrial.com.mx" aria-label="Enviar correo a ID Industrial">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Zm0 3.2V17h16V8.2l-7.42 5.16a1 1 0 0 1-1.16 0L4 8.2Zm1.1-1.2 6.9 4.8L18.9 7H5.1Z"/></svg>
  </a>
  <a class="social-dock__link social-dock__link--placeholder" href="#contacto" aria-label="LinkedIn ID Industrial por configurar">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4.98 3.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5ZM3 9.8h4v10.7H3V9.8Zm6.3 0h3.84v1.46h.06c.54-.96 1.86-1.76 3.54-1.76 3.78 0 4.48 2.48 4.48 5.72v5.28h-4v-4.68c0-1.12-.02-2.56-1.56-2.56-1.56 0-1.8 1.22-1.8 2.48v4.76h-4V9.8Z"/></svg>
  </a>
  <a class="social-dock__link social-dock__link--placeholder" href="#contacto" aria-label="Instagram ID Industrial por configurar">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 2h10a5 5 0 0 1 5 5v10a5 5 0 0 1-5 5H7a5 5 0 0 1-5-5V7a5 5 0 0 1 5-5Zm0 2a3 3 0 0 0-3 3v10a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3H7Zm5 3.5a4.5 4.5 0 1 1 0 9 4.5 4.5 0 0 1 0-9Zm0 2a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5Zm5.25-2.25a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5Z"/></svg>
  </a>
</aside>

<script src="<?php echo htmlspecialchars($assetBase); ?>assets/js/main.js" defer></script>
</body>
</html>
