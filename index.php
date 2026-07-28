<?php
$siteUrl = 'https://idindustrial.com.mx/';
$phone = '+52 442 598 6318';
$whatsapp = '524425986318';
$contactEmail = 'contacto@idindustrial.com.mx';

$title = 'ID Industrial | Ingeniería industrial, cableado, HVAC y seguridad en Querétaro';
$description = 'Soluciones de ingeniería industrial en Querétaro: cableado estructurado, detección de incendios, sistemas HVAC, fibra óptica, control de accesos e infraestructura crítica.';
$keywords = 'ID Industrial, cableado estructurado Querétaro, detección de incendios industrial, sistemas HVAC Querétaro, fibra óptica industrial, control de accesos Querétaro';

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
    'image' => 'assets/img/instalacion-de-cableado-estructurado-en-queretaro.webp',
    'alt' => 'Instalación de cableado estructurado en Querétaro',
    'bullets' => ['Cableado UTP, fibra y canalización', 'Racks, patch panels y ordenamiento', 'Memoria técnica y pruebas de enlace'],
  ],
  [
    'id' => 'deteccion-incendios',
    'eyebrow' => 'Protección temprana',
    'title' => 'Detección de incendios con integración para áreas críticas.',
    'copy' => 'Implementamos paneles, sensores, sirenas, estaciones manuales y lógica de alerta para reducir tiempos de respuesta y proteger activos estratégicos.',
    'image' => 'assets/img/fire-alarm-industrial.webp',
    'alt' => 'Sistema de detección de incendios industrial',
    'bullets' => ['Paneles y sensores direccionables', 'Alarmamiento y supervisión', 'Diseño orientado a normativas aplicables'],
  ],
  [
    'id' => 'sistemas-hvac',
    'eyebrow' => 'Control ambiental',
    'title' => 'Sistemas HVAC industriales para continuidad operativa.',
    'copy' => 'Integramos climatización, ventilación, chillers y mantenimiento para oficinas, cuartos técnicos, procesos productivos y espacios de precisión.',
    'image' => 'assets/img/chillers-industriales-queretaro.webp',
    'alt' => 'Sistemas HVAC industriales en Querétaro',
    'bullets' => ['Instalación y mantenimiento', 'Ventilación y ductería', 'Sistemas de precisión para cuartos técnicos'],
  ],
  [
    'id' => 'fibra-optica',
    'eyebrow' => 'Alta disponibilidad',
    'title' => 'Fibra óptica para comunicación industrial de alto desempeño.',
    'copy' => 'Tendidos, fusiones, certificación y enlaces de fibra óptica para naves, campus industriales, edificios corporativos y redes críticas.',
    'image' => 'assets/img/instaladores-de-fibra-optica.webp',
    'alt' => 'Instaladores de fibra óptica industrial',
    'bullets' => ['Fusión y certificación', 'Backbone para naves y campus', 'Canalización y protección de enlace'],
  ],
  [
    'id' => 'control-accesos',
    'eyebrow' => 'Seguridad y trazabilidad',
    'title' => 'Control de accesos conectado con operación y vigilancia.',
    'copy' => 'Integramos biométricos, tarjetas, plumas, torniquetes, CCTV y monitoreo para controlar personal, proveedores y perímetros industriales.',
    'image' => 'assets/img/control-de-acceso-conectado-a-CCTV-queretaro.webp',
    'alt' => 'Control de accesos conectado a CCTV',
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
        <source srcset="assets/img/slide2.jpg" media="(min-width: 900px)">
        <img src="assets/img/slide.jpg" alt="" fetchpriority="high">
      </picture>
    </div>
    <div class="hero__overlay" aria-hidden="true"></div>
    <div class="container hero__grid">
      <div class="hero__copy reveal">
        <p class="eyebrow">Ingeniería industrial en Querétaro y Bajío</p>
        <h1 id="hero-title">ID Industrial</h1>
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
        <img src="assets/img/personal-capacitado-industrial.webp" alt="Personal capacitado de ID Industrial">
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
          <a class="text-link" href="#contacto">Solicitar evaluación técnica</a>
        </div>
        <figure class="service__image reveal reveal--delay">
          <img src="<?php echo htmlspecialchars($service['image']); ?>" alt="<?php echo htmlspecialchars($service['alt']); ?>" loading="lazy">
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
        <img src="assets/img/centros-de-monitoreo-inteligentes.webp" alt="Centro de monitoreo inteligente industrial" loading="lazy">
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
          <a href="tel:+524425986318">
            <span>Teléfono</span>
            <?php echo htmlspecialchars($phone); ?>
          </a>
          <a href="https://wa.me/<?php echo htmlspecialchars($whatsapp); ?>?text=Hola%20ID%20Industrial,%20quiero%20cotizar%20un%20proyecto" target="_blank" rel="noopener">
            <span>WhatsApp</span>
            Atención directa
          </a>
          <a href="mailto:<?php echo htmlspecialchars($contactEmail); ?>">
            <span>Correo</span>
            <?php echo htmlspecialchars($contactEmail); ?>
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
            Nombre
            <input type="text" name="name" autocomplete="name" placeholder="Nombre y empresa" required>
          </label>
          <label>
            Correo
            <input type="email" name="email" autocomplete="email" placeholder="correo@empresa.com" required>
          </label>
        </div>
        <div class="form-row">
          <label>
            Teléfono
            <input type="tel" name="phone" autocomplete="tel" placeholder="+52 442 000 0000">
          </label>
          <label>
            Servicio
            <select name="service">
              <option value="">Seleccionar</option>
              <option value="Cableado estructurado">Cableado estructurado</option>
              <option value="Detección de incendios">Detección de incendios</option>
              <option value="Sistemas HVAC">Sistemas HVAC</option>
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
          Mensaje
          <textarea name="message" rows="5" placeholder="Cuéntanos ubicación, tipo de instalación y prioridad del proyecto." required></textarea>
        </label>
        <button class="button button--primary" type="submit">Enviar solicitud</button>
      </form>
    </div>
  </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
