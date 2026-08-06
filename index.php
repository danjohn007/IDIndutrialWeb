<?php
$siteUrl = 'https://idindustrial.com.mx/';
$publicOrigin = 'https://idindustrial.com.mx';
$assetUrlBase = 'https://idindustrial.com.mx/';
$phone = '+52 442 598 6318';
$whatsapp = '524425986318';
$contactEmail = 'tecnologia@idindustrial.com.mx';
$currentSection = 'inicio';
require_once __DIR__ . '/crm/lib/database.php';
if (crm_uses_legacy_php_url('index.php')) {
  header('Location: ' . crm_public_url('', $_GET), true, 301);
  exit;
}

$title = 'Infraestructura industrial en Querétaro | ID Industrial';
$description = 'Ingeniería industrial en Querétaro para cableado estructurado, fibra óptica, CCTV, control de accesos, detección de incendios y sistemas HVAC.';
$keywords = 'ID Industrial, infraestructura industrial Querétaro, cableado estructurado Querétaro, CCTV industrial, control de accesos Querétaro, HVAC industrial';
$requestPath = strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/';
$canonicalUrl = rtrim($publicOrigin, '/') . ($requestPath === '/' ? '/' : $requestPath);
try {
  $quoteRequestAdminEmail = crm_quote_request_admin_email(crm_db(), $contactEmail);
} catch (Throwable $error) {
  $crmConfig = crm_config();
  $quoteRequestAdminEmail = trim((string) ($crmConfig['quote_request_admin_email'] ?? $contactEmail));
  if (!filter_var($quoteRequestAdminEmail, FILTER_VALIDATE_EMAIL)) {
    $quoteRequestAdminEmail = $contactEmail;
  }
}
$publicClients = [];
try {
  $publicClients = crm_public_clients();
} catch (Throwable $error) {
  error_log('Public clients unavailable: ' . $error->getMessage());
}

function idindustrial_mobile_image($image)
{
  $candidate = str_replace('assets/img/optimized/', 'assets/img/optimized/mobile/', $image);
  return is_file(__DIR__ . '/' . $candidate) ? $candidate : $image;
}

function idindustrial_quote_request_rows(array $data): string
{
  $fields = [
    'Nombre y empresa' => trim((string) ($data['name'] ?? '')),
    'Correo' => trim((string) ($data['email'] ?? '')),
    'Telefono' => trim((string) ($data['phone'] ?? '')) ?: 'Sin telefono',
    'Servicio de interes' => trim((string) ($data['service'] ?? '')) ?: 'Por definir',
    'Mensaje' => trim((string) ($data['message'] ?? '')),
  ];
  $rows = '';
  foreach ($fields as $label => $value) {
    $rows .= '<tr><td style="padding:12px 14px;border-bottom:1px solid #ece3d4;font-size:12px;font-weight:800;letter-spacing:1.8px;text-transform:uppercase;color:#876500;width:180px;vertical-align:top;">' . crm_email_h($label) . '</td><td style="padding:12px 14px;border-bottom:1px solid #ece3d4;font-size:15px;line-height:1.55;color:#161a20;white-space:pre-wrap;word-break:break-word;">' . crm_email_h($value) . '</td></tr>';
  }
  return $rows;
}

function idindustrial_quote_request_admin_email_html(array $data, string $crmUrl): string
{
  $safeCrmUrl = crm_email_h($crmUrl);
  $safeService = crm_email_h(trim((string) ($data['service'] ?? '')) ?: 'Solicitud web');
  $rows = idindustrial_quote_request_rows($data);
  return '<!doctype html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Nueva solicitud de cotizacion</title></head><body style="margin:0;padding:0;background:#f4f1eb;font-family:Arial,Helvetica,sans-serif;color:#11151c;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f1eb;margin:0;padding:24px 12px;"><tr><td align="center"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;max-width:680px;background:#ffffff;border:1px solid #ded6c8;border-radius:14px;overflow:hidden;"><tr><td style="background:#111412;padding:26px 28px;"><div style="font-size:13px;letter-spacing:4px;font-weight:800;color:#f3c433;text-transform:uppercase;">ID Industrial</div><div style="margin-top:8px;font-size:22px;line-height:1.2;font-weight:800;color:#ffffff;">Nueva solicitud de cotizacion</div></td></tr><tr><td style="padding:30px 28px;"><h1 style="margin:0 0 12px;font-size:28px;line-height:1.15;color:#11151c;">' . $safeService . '</h1><p style="margin:0 0 22px;font-size:15px;line-height:1.6;color:#586170;">La solicitud ya fue registrada como oportunidad en el CRM. Revisa los datos y da seguimiento desde el panel administrativo.</p><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 24px;border:1px solid #e5dccb;border-radius:12px;overflow:hidden;">' . $rows . '</table><table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td align="center" bgcolor="#d6a91f" style="border-radius:10px;"><a href="' . $safeCrmUrl . '" style="display:block;padding:16px 22px;font-size:16px;font-weight:800;color:#11151c;text-decoration:none;">Abrir oportunidad en CRM</a></td></tr></table><p style="margin:18px 0 0;font-size:13px;line-height:1.6;color:#6b7280;">Enlace directo: <a href="' . $safeCrmUrl . '" style="color:#9b7200;text-decoration:underline;word-break:break-all;">' . $safeCrmUrl . '</a></p></td></tr><tr><td style="padding:20px 28px;background:#f7f4ee;border-top:1px solid #e5dccb;"><p style="margin:0;font-size:12px;line-height:1.6;color:#69727f;">ID Industrial - Solicitudes web</p></td></tr></table></td></tr></table></body></html>';
}

function idindustrial_quote_request_client_email_html(array $data): string
{
  $safeName = crm_email_h(trim((string) ($data['name'] ?? '')) ?: 'cliente');
  $rows = idindustrial_quote_request_rows($data);
  return '<!doctype html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Solicitud recibida</title></head><body style="margin:0;padding:0;background:#f4f1eb;font-family:Arial,Helvetica,sans-serif;color:#11151c;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f1eb;margin:0;padding:24px 12px;"><tr><td align="center"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;max-width:640px;background:#ffffff;border:1px solid #ded6c8;border-radius:14px;overflow:hidden;"><tr><td style="background:#111412;padding:26px 28px;"><div style="font-size:13px;letter-spacing:4px;font-weight:800;color:#f3c433;text-transform:uppercase;">ID Industrial</div><div style="margin-top:8px;font-size:22px;line-height:1.2;font-weight:800;color:#ffffff;">Solicitud recibida</div></td></tr><tr><td style="padding:30px 28px;"><h1 style="margin:0 0 12px;font-size:28px;line-height:1.15;color:#11151c;">Gracias, ' . $safeName . '</h1><p style="margin:0 0 22px;font-size:15px;line-height:1.6;color:#586170;">Recibimos tu solicitud de cotizacion. Nuestro equipo revisara los detalles y te contactara para confirmar alcance, tiempos y siguientes pasos.</p><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 22px;border:1px solid #e5dccb;border-radius:12px;overflow:hidden;">' . $rows . '</table><p style="margin:0;font-size:13px;line-height:1.6;color:#6b7280;">Si necesitas agregar informacion, responde este correo o contactanos por WhatsApp.</p></td></tr><tr><td style="padding:20px 28px;background:#f7f4ee;border-top:1px solid #e5dccb;"><p style="margin:0;font-size:12px;line-height:1.6;color:#69727f;">ID Industrial - Ingenieria industrial en Queretaro y Bajio</p></td></tr></table></td></tr></table></body></html>';
}

$homeCarouselItems = [
  [
    'image' => 'assets/img/optimized/home-hero-cableado.jpg',
    'alt' => '',
    'width' => 1920,
    'height' => 500,
    'label' => 'Cableado estructurado',
  ],
  [
    'image' => 'assets/img/optimized/home-hero-cctv.jpg',
    'alt' => '',
    'width' => 1920,
    'height' => 500,
    'label' => 'CCTV industrial',
  ],
  [
    'image' => 'assets/img/optimized/home-hero-control-acceso.jpg',
    'alt' => '',
    'width' => 1920,
    'height' => 500,
    'label' => 'Control de accesos',
  ],
  [
    'image' => 'assets/img/optimized/home-hero-servidores.jpg',
    'alt' => '',
    'width' => 1920,
    'height' => 500,
    'label' => 'Servidores y sites',
  ],
  [
    'image' => 'assets/img/optimized/home-hero-logicas.jpg',
    'alt' => '',
    'width' => 1920,
    'height' => 500,
    'label' => 'Lógicas industriales',
  ],
];

$heroDesktopImage = $homeCarouselItems[0]['image'];
$heroMobileImage = $homeCarouselItems[0]['image'];

$serviceOverview = [
  [
    'id' => 'deteccion-incendios',
    'title' => 'Detección de incendios',
    'copy' => 'Paneles, sensores, estaciones manuales y alarmamiento para áreas críticas.',
    'application' => 'Aplicación: producción, almacenes, cuartos técnicos y corporativos.',
    'href' => '#contacto',
    'image' => 'assets/img/optimized/card-incendios.jpg',
    'alt' => 'Panel de detección de incendios industrial',
    'width' => 1920,
    'height' => 500,
    'badge' => 'Seguridad',
    'linkText' => 'Consultar detección de incendios',
  ],
  [
    'id' => 'sistemas-hvac',
    'title' => 'Sistemas HVAC',
    'copy' => 'Climatización, ventilación, chillers y soporte técnico para continuidad operativa.',
    'application' => 'Aplicación: oficinas, cuartos técnicos y procesos de precisión.',
    'href' => 'instalacion-aire-acondicionado-industrial-queretaro/',
    'image' => 'assets/img/optimized/card-hvac.jpg',
    'alt' => 'Sistemas HVAC industriales',
    'width' => 1920,
    'height' => 500,
    'badge' => 'Climatización',
    'linkText' => 'Consultar sistemas HVAC industriales',
  ],
  [
    'id' => 'cctv-industrial',
    'title' => 'CCTV industrial',
    'copy' => 'Videovigilancia, grabación, monitoreo e integración con red y accesos.',
    'application' => 'Aplicación: perímetros, casetas, producción y edificios corporativos.',
    'href' => 'instalacion-camaras-seguridad-industrial-queretaro/',
    'image' => 'assets/img/optimized/home-hero-cctv.jpg',
    'alt' => 'Sistema de CCTV industrial en Querétaro',
    'width' => 1920,
    'height' => 500,
    'badge' => 'Videovigilancia',
    'linkText' => 'Ver más',
  ],
  [
    'id' => 'cableado-estructurado',
    'title' => 'Cableado estructurado',
    'copy' => 'Redes de voz y datos, racks, canalización, fibra óptica y pruebas para operación estable.',
    'application' => 'Aplicación: plantas, oficinas, sites y naves industriales.',
    'href' => 'industriales/cableado-estructurado-queretaro/',
    'image' => 'assets/img/optimized/card-cableado.jpg',
    'alt' => 'Cableado estructurado industrial en Querétaro',
    'width' => 1920,
    'height' => 500,
    'badge' => 'Infraestructura',
    'linkText' => 'Conocer soluciones de cableado estructurado',
  ],
  [
    'id' => 'fibra-optica',
    'title' => 'Fibra óptica',
    'copy' => 'Backbone, fusiones, enlaces y certificación para redes de alto desempeño.',
    'application' => 'Aplicación: campus industriales, naves y edificios conectados.',
    'href' => '#contacto',
    'image' => 'assets/img/optimized/card-fibra.jpg',
    'alt' => 'Instalación de fibra óptica en Querétaro',
    'width' => 1920,
    'height' => 500,
    'badge' => 'Conectividad',
    'linkText' => 'Explorar soluciones de fibra óptica',
  ],
  [
    'id' => 'control-accesos',
    'title' => 'Control de Accesos',
    'copy' => 'Biométricos, tarjetas, plumas, perfiles de acceso, registros e integración con CCTV.',
    'application' => 'Aplicación: personal, proveedores, visitantes y áreas restringidas.',
    'href' => 'control-de-acceso-de-personal-queretaro/',
    'image' => 'assets/img/optimized/home-hero-control-acceso.jpg',
    'alt' => 'Control de accesos biométrico industrial',
    'width' => 1920,
    'height' => 500,
    'badge' => 'Trazabilidad',
    'linkText' => 'Conocer control de acceso para empresas',
  ],
];

$trustItems = [
  'Levantamiento técnico en sitio para definir rutas, puntos críticos y condiciones reales de operación.',
  'Soluciones adaptadas a la infraestructura existente y al crecimiento previsto de cada planta o edificio.',
  'Instalación, pruebas y puesta en marcha con entregables técnicos para mantenimiento posterior.',
  'Integración entre redes, CCTV, accesos, HVAC y sistemas de seguridad cuando el proyecto lo requiere.',
  'Mantenimiento preventivo y correctivo para conservar disponibilidad y trazabilidad.',
  'Documentación clara para operación, soporte y futuras ampliaciones.',
];

$processSteps = [
  ['title' => 'Levantamiento y diagnóstico técnico', 'copy' => 'Visitamos sitio, revisamos infraestructura existente, riesgos operativos, rutas y puntos críticos.'],
  ['title' => 'Ingeniería y propuesta de solución', 'copy' => 'Definimos alcance, materiales, arquitectura, prioridades y criterios técnicos para instalación.'],
  ['title' => 'Suministro e instalación', 'copy' => 'Ejecutamos canalización, montaje, cableado, configuración e integración con orden de obra.'],
  ['title' => 'Pruebas y puesta en operación', 'copy' => 'Validamos funcionamiento, cobertura, conectividad, alarmas, registros y continuidad del sistema.'],
  ['title' => 'Documentación, capacitación y soporte', 'copy' => 'Entregamos memoria técnica, recomendaciones de operación y ruta de mantenimiento.'],
];

$recommendations = [
  [
    'tag' => 'Red industrial',
    'title' => 'Qué revisar antes de intervenir una red industrial',
    'copy' => 'Ubicación de racks, rutas disponibles, energía, etiquetado, densidad de nodos y ventanas de trabajo.',
  ],
  [
    'tag' => 'HVAC',
    'title' => 'Señales de alerta en un sistema HVAC',
    'copy' => 'Variaciones térmicas, ruido, humedad, consumo inusual o paros intermitentes suelen anticipar fallas.',
  ],
  [
    'tag' => 'Seguridad',
    'title' => 'Ventajas de integrar accesos, CCTV y bitácoras',
    'copy' => 'Cada evento queda asociado a persona, hora, zona y evidencia visual para auditoría y operación diaria.',
  ],
];

$serviceOptions = [
  'Cableado estructurado',
  'Detección de incendios',
  'Sistemas HVAC',
  'CCTV industrial',
  'Fibra óptica',
  'Control de Accesos',
];

$serviceParamMap = [
  'cableado' => 'Cableado estructurado',
  'incendios' => 'Detección de incendios',
  'hvac' => 'Sistemas HVAC',
  'cctv' => 'CCTV industrial',
  'fibra' => 'Fibra óptica',
  'accesos' => 'Control de Accesos',
];

$formStatus = null;
$formErrors = [];
$formData = [
  'name' => '',
  'email' => '',
  'phone' => '',
  'service' => $serviceParamMap[$_GET['servicio'] ?? ''] ?? '',
  'message' => '',
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  $formData['name'] = trim($_POST['name'] ?? '');
  $formData['email'] = trim($_POST['email'] ?? '');
  $formData['phone'] = trim($_POST['phone'] ?? '');
  $formData['service'] = trim($_POST['service'] ?? '');
  $formData['message'] = trim($_POST['message'] ?? '');
  $honeypot = trim($_POST['company_site'] ?? '');

  if ($honeypot !== '') {
    $formStatus = ['type' => 'ok', 'text' => 'Gracias. Recibimos tu solicitud.'];
  } else {
    if ($formData['name'] === '') {
      $formErrors['name'] = 'Indica tu nombre y empresa.';
    }
    if (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
      $formErrors['email'] = 'Ingresa un correo válido.';
    }
    if ($formData['message'] === '') {
      $formErrors['message'] = 'Cuéntanos brevemente qué necesitas instalar o mejorar.';
    }

    if ($formErrors) {
      $formStatus = ['type' => 'error', 'text' => 'Revisa los campos marcados para enviar tu solicitud.'];
    } else {
      $leadData = [
        'company_name' => $formData['name'],
        'contact_name' => $formData['name'],
        'contact_email' => $formData['email'],
        'contact_phone' => $formData['phone'],
        'service' => $formData['service'],
        'notes' => $formData['message'],
      ];
      $opportunityId = crm_capture_public_lead($leadData);
      if ($opportunityId) {
        try {
          $notificationService = $formData['service'] !== '' ? $formData['service'] : 'servicio por definir';
          crm_create_notification(crm_db(), [
            'recipient_type' => 'admin',
            'opportunity_id' => $opportunityId,
            'event_type' => 'web_lead_received',
            'title' => 'Nuevo lead web',
            'message' => $formData['name'] . ' solicito ' . $notificationService . ' desde el formulario publico.',
            'target_url' => crm_admin_url('opportunity', $opportunityId),
          ]);
        } catch (Throwable $error) {
          error_log('CRM web lead notification failed: ' . $error->getMessage());
        }
      }

      $adminOpportunityUrl = $opportunityId ? crm_app_url('oportunidades/' . $opportunityId) : crm_app_url('oportunidades');
      $notificationService = $formData['service'] !== '' ? $formData['service'] : 'servicio por definir';
      $subject = 'Nueva solicitud de cotizacion web - ' . $notificationService;
      $body = "Nueva solicitud de cotizacion web\n\nNombre: {$formData['name']}\nCorreo: {$formData['email']}\nTelefono: {$formData['phone']}\nServicio de interes: {$formData['service']}\n\nMensaje:\n{$formData['message']}\n\nAbrir en CRM: {$adminOpportunityUrl}";
      $emailSent = crm_send_email($quoteRequestAdminEmail, $subject, $body, idindustrial_quote_request_admin_email_html($formData, $adminOpportunityUrl), [
        'reply_to' => $formData['email'],
      ]);
      $clientBody = "Hola {$formData['name']},\n\nRecibimos tu solicitud de cotizacion con estos datos:\n\nServicio de interes: {$formData['service']}\nTelefono: {$formData['phone']}\n\nMensaje:\n{$formData['message']}\n\nNuestro equipo te contactara para confirmar alcance, tiempos y siguientes pasos.\n\nID Industrial";
      $clientEmailSent = crm_send_email($formData['email'], 'Recibimos tu solicitud - ID Industrial', $clientBody, idindustrial_quote_request_client_email_html($formData));
      if (!$clientEmailSent) {
        error_log('CRM web lead client copy failed for opportunity ' . (int) $opportunityId);
      }

      if ($opportunityId) {
        $formStatus = ['type' => 'ok', 'text' => 'Listo. Registramos tu solicitud y te contactaremos para preparar la cotizacion.'];
      } elseif ($emailSent) {
        $formStatus = ['type' => 'ok', 'text' => 'Recibimos tu solicitud por correo. Si es urgente, tambien puedes escribirnos por WhatsApp.'];
      } else {
        $formStatus = ['type' => 'error', 'text' => 'No se pudo registrar desde el servidor. Escribenos por WhatsApp y te atendemos.'];
      }
    }
  }
}

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/navbar.php';
?>

<main id="inicio">
  <section class="hero section-dark" aria-labelledby="hero-title">
    <div class="hero-carousel" data-carousel aria-label="Especialidades ID Industrial">
      <div class="hero-carousel__viewport">
        <?php foreach ($homeCarouselItems as $index => $item): ?>
          <?php
            $slideImage = htmlspecialchars($item['image']);
            $slideMobileImage = htmlspecialchars(idindustrial_mobile_image($item['image']));
            $slideBackground = htmlspecialchars($assetUrlBase . $item['image']);
            $slideMobileBackground = htmlspecialchars($assetUrlBase . idindustrial_mobile_image($item['image']));
          ?>
          <figure class="hero-carousel__slide <?php echo $index === 0 ? 'is-active' : ''; ?>" <?php echo $index === 0 ? 'style="--slide-image: url(\'' . $slideBackground . '\'); --slide-image-mobile: url(\'' . $slideMobileBackground . '\');"' : ''; ?> data-bg="<?php echo $slideBackground; ?>" data-bg-mobile="<?php echo $slideMobileBackground; ?>" aria-hidden="<?php echo $index === 0 ? 'false' : 'true'; ?>" data-carousel-slide>
            <?php if ($index === 0): ?>
              <picture>
                <source media="(max-width: 640px)" srcset="<?php echo $slideMobileImage; ?>">
                <img src="<?php echo $slideImage; ?>" srcset="<?php echo $slideMobileImage; ?> 960w, <?php echo $slideImage; ?> 1920w" sizes="100vw" fetchpriority="high" alt="<?php echo htmlspecialchars($item['alt']); ?>" width="<?php echo (int) $item['width']; ?>" height="<?php echo (int) $item['height']; ?>" decoding="async">
              </picture>
            <?php else: ?>
              <img data-src="<?php echo $slideImage; ?>" data-srcset="<?php echo $slideMobileImage; ?> 960w, <?php echo $slideImage; ?> 1920w" sizes="100vw" loading="lazy" alt="<?php echo htmlspecialchars($item['alt']); ?>" width="<?php echo (int) $item['width']; ?>" height="<?php echo (int) $item['height']; ?>" decoding="async">
            <?php endif; ?>
            <figcaption><?php echo htmlspecialchars($item['label']); ?></figcaption>
          </figure>
        <?php endforeach; ?>
      </div>
      <button class="hero-carousel__arrow hero-carousel__arrow--prev" type="button" aria-label="Imagen anterior" data-carousel-prev>
        <span aria-hidden="true"></span>
      </button>
      <button class="hero-carousel__arrow hero-carousel__arrow--next" type="button" aria-label="Imagen siguiente" data-carousel-next>
        <span aria-hidden="true"></span>
      </button>
      <div class="hero-carousel__dots" aria-label="Seleccionar imagen">
        <?php foreach ($homeCarouselItems as $index => $item): ?>
          <button class="hero-carousel__dot <?php echo $index === 0 ? 'is-active' : ''; ?>" type="button" aria-label="Ver <?php echo htmlspecialchars($item['label']); ?>" aria-current="<?php echo $index === 0 ? 'true' : 'false'; ?>" data-carousel-dot="<?php echo (int) $index; ?>"></button>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="hero__overlay" aria-hidden="true"></div>
    <div class="container hero__grid">
      <div class="hero__copy reveal">
        <p class="eyebrow">ID Industrial · Querétaro y Bajío</p>
        <h1 id="hero-title"><span>Infraestructura e ingeniería</span><span>industrial en Querétaro</span></h1>
        <p class="hero__lead">Diseñamos e implementamos soluciones de cableado estructurado, fibra óptica, CCTV, control de accesos, detección de incendios y sistemas HVAC para plantas, naves industriales y edificios corporativos.</p>
        <div class="hero__actions">
          <a class="button button--primary" href="#cotizacion" data-quote-open aria-controls="cotizacion">Solicitar evaluación técnica</a>
          <a class="button button--ghost" href="#servicios">Conocer servicios</a>
        </div>
      </div>
      <div class="hero__panel reveal reveal--delay">
        <span class="status-dot"></span>
        <p>Soluciones llave en mano</p>
        <strong>Diagnóstico, ingeniería, instalación, documentación y soporte técnico.</strong>
      </div>
    </div>
  </section>

  <section class="metrics section-light" aria-label="Indicadores de confianza de ID Industrial">
    <div class="container metrics__grid">
      <div class="metric reveal">
        <span data-count="20" data-suffix="+">20+</span>
        <p>Años de experiencia técnica</p>
      </div>
      <div class="metric reveal">
        <span data-count="6">6</span>
        <p>Especialidades industriales</p>
      </div>
      <div class="metric reveal">
        <span>QRO</span>
        <p>Cobertura en Querétaro y el Bajío</p>
      </div>
      <div class="metric reveal">
        <span>MPC</span>
        <p>Soporte preventivo y correctivo</p>
      </div>
    </div>
  </section>

  <?php if ($publicClients): ?>
    <section class="clients-strip section-light" aria-labelledby="clients-title">
      <div class="container">
        <div class="section-head reveal">
          <p class="eyebrow">Clientes y referencias</p>
          <h2 id="clients-title">Empresas que confían en nuestras soluciones técnicas industriales</h2>
        </div>
        <div class="clients-strip__grid">
          <?php foreach ($publicClients as $client): ?>
            <span class="reveal"><?php echo htmlspecialchars($client['name']); ?></span>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <section id="servicios" class="services-overview section-dark section-pad">
    <div class="container">
      <div class="section-head reveal">
        <p class="eyebrow">Servicios principales</p>
        <h2>Soluciones para infraestructura, seguridad y continuidad operativa.</h2>
        <p>Un solo equipo técnico para coordinar sistemas que normalmente se instalan por separado.</p>
      </div>

      <div class="services-overview__grid">
        <?php foreach ($serviceOverview as $item): ?>
          <article id="<?php echo htmlspecialchars($item['id']); ?>" class="service-card service-card--wide reveal">
            <img src="<?php echo htmlspecialchars($item['image']); ?>" srcset="<?php echo htmlspecialchars(idindustrial_mobile_image($item['image'])); ?> 960w, <?php echo htmlspecialchars($item['image']); ?> 1920w" sizes="(max-width: 640px) calc(100vw - 28px), (max-width: 1120px) 33vw, 390px" alt="<?php echo htmlspecialchars($item['alt']); ?>" width="<?php echo (int) $item['width']; ?>" height="<?php echo (int) $item['height']; ?>" loading="lazy" decoding="async">
            <em><?php echo htmlspecialchars($item['badge']); ?></em>
            <span><?php echo htmlspecialchars($item['title']); ?></span>
            <p><?php echo htmlspecialchars($item['copy']); ?></p>
            <small><?php echo htmlspecialchars($item['application']); ?></small>
            <a class="service-card__more" href="#cotizacion" aria-label="Cotizar <?php echo htmlspecialchars($item['title']); ?>" data-quote-open data-quote-service="<?php echo htmlspecialchars($item['title']); ?>" aria-controls="cotizacion">Cotizar este servicio</a>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section id="quienes-somos" class="about section-dark section-pad">
    <div class="container split">
      <div class="split__content reveal">
        <p class="eyebrow">Por qué elegir ID Industrial</p>
        <h2>Coordinamos infraestructura, seguridad y operación con criterio técnico.</h2>
        <p>Trabajamos para entornos donde una falla puede afectar producción, seguridad o continuidad. Nuestro enfoque combina levantamiento en sitio, instalación profesional, pruebas y documentación clara.</p>
        <div class="check-grid">
          <?php foreach ($trustItems as $item): ?>
            <span><?php echo htmlspecialchars($item); ?></span>
          <?php endforeach; ?>
        </div>
      </div>
      <figure class="image-lockup reveal reveal--delay">
        <img src="assets/img/optimized/home-industrial.jpg" alt="Equipo técnico trabajando en infraestructura industrial" width="1920" height="800" loading="lazy" decoding="async">
        <figcaption>Instalaciones preparadas para operar, mantenerse y crecer con orden técnico.</figcaption>
      </figure>
    </div>
  </section>

  <section class="process section-light section-pad" aria-labelledby="process-title">
    <div class="container">
      <div class="section-head reveal">
        <p class="eyebrow">Nuestro proceso de trabajo</p>
        <h2 id="process-title">De la visita técnica a la puesta en operación.</h2>
      </div>
      <div class="process__grid process__grid--five">
        <?php foreach ($processSteps as $index => $step): ?>
          <article class="process-step reveal">
            <span><?php echo str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT); ?></span>
            <h3><?php echo htmlspecialchars($step['title']); ?></h3>
            <p><?php echo htmlspecialchars($step['copy']); ?></p>
          </article>
        <?php endforeach; ?>
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
        <p class="eyebrow">Capacidad destacada</p>
        <h2 id="lamp-title">Sistemas industriales que trabajan como una sola operación.</h2>
        <p>Redes, fibra, HVAC, detección, CCTV y accesos con una arquitectura pensada para continuidad, trazabilidad y crecimiento.</p>
        <a class="button button--primary" href="#cotizacion" data-quote-open aria-controls="cotizacion">Evaluar proyecto</a>
      </div>
    </div>
  </section>

  <section class="integration section-light section-pad" aria-labelledby="coverage-title">
    <div class="container integration__grid">
      <div class="section-head reveal">
        <p class="eyebrow">Cobertura</p>
        <h2 id="coverage-title">Atención técnica para Querétaro y polos industriales del Bajío.</h2>
        <p>Atendemos proyectos en zonas industriales, corporativos y edificios técnicos donde se requiere coordinación entre infraestructura, seguridad y mantenimiento.</p>
        <ul class="coverage-list">
          <li>Querétaro y Corregidora</li>
          <li>El Marqués, Colón y parques industriales cercanos</li>
          <li>Apaseo el Grande, Celaya y corredores del Bajío</li>
        </ul>
      </div>
      <div class="integration__visual reveal reveal--delay">
        <img src="assets/img/optimized/home-servidores.jpg" alt="Infraestructura de servidores y site industrial" width="1920" height="800" loading="lazy" decoding="async">
      </div>
    </div>
  </section>

  <section id="recomendaciones-tecnicas" class="journal section-dark section-pad">
    <div class="container">
      <div class="section-head reveal">
        <p class="eyebrow">Recomendaciones técnicas</p>
        <h2>Decisiones prácticas antes de intervenir infraestructura crítica.</h2>
      </div>
      <div class="journal__grid">
        <?php foreach ($recommendations as $entry): ?>
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
            <strong>QRO</strong>
            <span>Cobertura en polos industriales</span>
          </div>
          <div>
            <strong>MPC</strong>
            <span>Mantenimiento preventivo y correctivo</span>
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
          <a href="https://wa.me/<?php echo htmlspecialchars($whatsapp); ?>?text=Hola%20ID%20Industrial,%20quiero%20solicitar%20una%20evaluaci%C3%B3n%20t%C3%A9cnica" target="_blank" rel="noopener noreferrer" aria-label="Contactar por WhatsApp">
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

      <div class="next-steps reveal" aria-label="Que pasa despues">
        <span>Que pasa despues</span>
        <div class="next-steps__grid">
          <strong>Diagnostico</strong>
          <strong>Llamada</strong>
          <strong>Visita tecnica</strong>
          <strong>Propuesta</strong>
        </div>
      </div>
    </div>
  </section>
</main>

<button class="quote-fab" type="button" data-quote-open aria-controls="cotizacion">
  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H9.4L5 20v-4H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm0 2v8h3v1.4L8.6 14H20V6H4Zm3 2h10v2H7V8Zm0 3h7v2H7v-2Z"/></svg>
  <span>Cotizar</span>
</button>

<div id="cotizacion" class="quote-modal <?php echo $formStatus ? 'is-open' : ''; ?>" role="dialog" aria-modal="true" aria-labelledby="quote-modal-title" aria-hidden="<?php echo $formStatus ? 'false' : 'true'; ?>" data-quote-modal>
  <div class="quote-modal__overlay" data-quote-close></div>
  <div class="quote-modal__panel" role="document">
    <button class="quote-modal__close" type="button" aria-label="Cerrar solicitud" data-quote-close>
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6.4 5 12.6 12.6-1.4 1.4L5 6.4 6.4 5Zm12.6 1.4L6.4 19 5 17.6 17.6 5 19 6.4Z"/></svg>
    </button>
    <form id="quote-request-form" class="contact-form" action="<?php echo htmlspecialchars(crm_public_url('', [], 'cotizacion')); ?>" method="post" data-contact-form novalidate>
      <div class="form-head">
        <span>Solicitud tecnica</span>
        <h3 id="quote-modal-title">Solicitud de cotizacion</h3>
        <p>Comparte tus datos y te contactamos.</p>
      </div>
      <?php if ($formStatus): ?>
        <p class="form-status form-status--<?php echo htmlspecialchars($formStatus['type']); ?>" role="status"><?php echo htmlspecialchars($formStatus['text']); ?></p>
        <?php if ($formStatus['type'] === 'error'): ?><a class="form-fallback" href="https://wa.me/<?php echo htmlspecialchars($whatsapp); ?>?text=Hola%20ID%20Industrial,%20quiero%20solicitar%20una%20evaluacion%20tecnica" target="_blank" rel="noopener noreferrer">Continuar por WhatsApp</a><?php endif; ?>
      <?php endif; ?>
      <div class="form-row">
        <label for="contact-name">
          <span class="field-pill">Nombre y empresa *</span>
          <input id="contact-name" type="text" name="name" autocomplete="name" placeholder="Nombre y empresa" value="<?php echo htmlspecialchars($formData['name']); ?>" required aria-invalid="<?php echo isset($formErrors['name']) ? 'true' : 'false'; ?>" aria-describedby="<?php echo isset($formErrors['name']) ? 'contact-name-error' : ''; ?>">
          <?php if (isset($formErrors['name'])): ?><span class="field-error" id="contact-name-error"><?php echo htmlspecialchars($formErrors['name']); ?></span><?php endif; ?>
        </label>
        <label for="contact-email">
          <span class="field-pill">Correo *</span>
          <input id="contact-email" type="email" name="email" autocomplete="email" placeholder="correo@empresa.com" value="<?php echo htmlspecialchars($formData['email']); ?>" required aria-invalid="<?php echo isset($formErrors['email']) ? 'true' : 'false'; ?>" aria-describedby="<?php echo isset($formErrors['email']) ? 'contact-email-error' : ''; ?>">
          <?php if (isset($formErrors['email'])): ?><span class="field-error" id="contact-email-error"><?php echo htmlspecialchars($formErrors['email']); ?></span><?php endif; ?>
        </label>
      </div>
      <div class="form-row">
        <label for="contact-phone">
          <span class="field-pill">Teléfono</span>
          <input id="contact-phone" type="tel" name="phone" autocomplete="tel" placeholder="+52 442 000 0000" value="<?php echo htmlspecialchars($formData['phone']); ?>">
        </label>
        <label for="contact-service">
          <span class="field-pill">Servicio de interés</span>
          <select id="contact-service" name="service" data-quote-service-field>
            <option value="">Seleccionar</option>
            <?php foreach ($serviceOptions as $option): ?>
              <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $formData['service'] === $option ? 'selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>
      <label class="honeypot" for="company-site">
        Sitio
        <input id="company-site" type="text" name="company_site" tabindex="-1" autocomplete="off">
      </label>
      <label for="contact-message">
        <span class="field-pill">Mensaje *</span>
        <textarea id="contact-message" name="message" rows="3" placeholder="Ubicacion, tipo de instalacion y prioridad." required aria-invalid="<?php echo isset($formErrors['message']) ? 'true' : 'false'; ?>" aria-describedby="<?php echo isset($formErrors['message']) ? 'contact-message-error' : ''; ?>"><?php echo htmlspecialchars($formData['message']); ?></textarea>
        <?php if (isset($formErrors['message'])): ?><span class="field-error" id="contact-message-error"><?php echo htmlspecialchars($formErrors['message']); ?></span><?php endif; ?>
      </label>
      <p class="form-privacy">Al enviar aceptas el <a href="aviso-de-privacidad/">Aviso de Privacidad</a>.</p>
      <button class="button button--primary" type="submit">Enviar solicitud</button>
    </form>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
