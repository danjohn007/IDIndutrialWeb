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
  $crmPdoForQuoteEmail = crm_db();
  $quoteRequestAdminEmail = crm_quote_request_admin_email($crmPdoForQuoteEmail, $contactEmail);
  $quoteRequestSecondaryEmail = crm_quote_request_secondary_email($crmPdoForQuoteEmail);
} catch (Throwable $error) {
  $crmConfig = crm_config();
  $quoteRequestAdminEmail = trim((string) ($crmConfig['quote_request_admin_email'] ?? $contactEmail));
  $quoteRequestSecondaryEmail = trim((string) ($crmConfig['quote_request_secondary_email'] ?? ''));
  if (!filter_var($quoteRequestAdminEmail, FILTER_VALIDATE_EMAIL)) {
    $quoteRequestAdminEmail = $contactEmail;
  }
  if (!filter_var($quoteRequestSecondaryEmail, FILTER_VALIDATE_EMAIL)) {
    $quoteRequestSecondaryEmail = '';
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
  $desiredDate = trim((string) ($data['desired_execution_date'] ?? ''));
  if ($desiredDate !== '') {
    $dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $desiredDate);
    $desiredDate = $dateObject ? $dateObject->format('d/m/Y') : $desiredDate;
  }
  $attachmentNames = array_values(array_filter(array_map('strval', (array) ($data['attachment_names'] ?? []))));
  $fields = [
    'Nombre' => trim((string) ($data['name'] ?? '')),
    'Empresa' => trim((string) ($data['company'] ?? '')),
    'Correo' => trim((string) ($data['email'] ?? '')),
    'Teléfono WhatsApp' => trim((string) ($data['phone'] ?? '')),
    'Tipo de solicitud' => trim((string) ($data['request_type'] ?? '')),
    'Servicio de interés' => trim((string) ($data['service'] ?? '')),
    'Locación del proyecto' => trim((string) ($data['city'] ?? '')),
    'Fecha deseada de ejecución' => $desiredDate,
    'Adjuntos' => implode("\n", $attachmentNames),
    'Mensaje' => trim((string) ($data['message'] ?? '')),
  ];
  $rows = '';
  foreach ($fields as $label => $value) {
    $value = $value !== '' ? $value : 'Sin información';
    $rows .= '<tr><td style="padding:0 0 10px;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;border:1px solid #e8dfcf;border-radius:10px;background:#fffdf8;border-collapse:separate;"><tr><td style="padding:13px 15px 4px;font-size:11px;font-weight:800;letter-spacing:2px;text-transform:uppercase;color:#876500;line-height:1.3;">' . crm_email_h($label) . '</td></tr><tr><td style="padding:0 15px 14px;font-size:16px;line-height:1.55;color:#161a20;white-space:pre-wrap;overflow-wrap:anywhere;word-break:normal;">' . crm_email_h($value) . '</td></tr></table></td></tr>';
  }
  return $rows;
}
function idindustrial_quote_request_admin_email_html(array $data, string $crmUrl): string
{
  $safeCrmUrl = crm_email_h($crmUrl);
  $safeService = crm_email_h(trim((string) ($data['service'] ?? '')) ?: 'Solicitud web');
  $safeName = crm_email_h(trim((string) ($data['name'] ?? '')) ?: 'Prospecto web');
  $rows = idindustrial_quote_request_rows($data);
  return '<!doctype html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Nueva solicitud de cotizacion</title><style>@media only screen and (max-width:520px){.email-shell{padding:12px 8px!important}.email-card{border-radius:0!important}.email-pad{padding:22px 18px!important}.email-title{font-size:24px!important}.email-button{display:block!important;width:100%!important;box-sizing:border-box!important}.email-link{font-size:12px!important}}</style></head><body style="margin:0;padding:0;background:#f4f1eb;font-family:Arial,Helvetica,sans-serif;color:#11151c;-webkit-text-size-adjust:100%;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" class="email-shell" style="background:#f4f1eb;margin:0;padding:24px 12px;"><tr><td align="center"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" class="email-card" style="width:100%;max-width:640px;background:#ffffff;border:1px solid #ded6c8;border-radius:14px;overflow:hidden;border-collapse:separate;"><tr><td style="background:#101312;padding:24px 26px;"><div style="font-size:12px;letter-spacing:4px;font-weight:800;color:#f3c433;text-transform:uppercase;line-height:1.4;">ID Industrial</div><div style="margin-top:8px;font-size:22px;line-height:1.2;font-weight:800;color:#ffffff;">Nueva solicitud web</div></td></tr><tr><td class="email-pad" style="padding:28px 26px;"><div style="display:inline-block;margin:0 0 14px;padding:6px 10px;border-radius:999px;background:#fff3bf;color:#876500;font-size:11px;font-weight:800;letter-spacing:1.8px;text-transform:uppercase;">Oportunidad registrada</div><h1 class="email-title" style="margin:0 0 10px;font-size:28px;line-height:1.15;color:#11151c;">' . $safeService . '</h1><p style="margin:0 0 6px;font-size:16px;line-height:1.55;color:#303842;"><strong>' . $safeName . '</strong> envio una solicitud desde la pagina web.</p><p style="margin:0 0 22px;font-size:14px;line-height:1.6;color:#586170;">Revisa los datos y continua el seguimiento desde el CRM.</p><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;margin:0 0 20px;">' . $rows . '</table><table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td align="center" bgcolor="#d6a91f" style="border-radius:10px;"><a class="email-button" href="' . $safeCrmUrl . '" style="display:block;padding:15px 20px;font-size:16px;font-weight:800;color:#11151c;text-decoration:none;line-height:1.2;">Abrir oportunidad en CRM</a></td></tr></table><p style="margin:16px 0 0;font-size:12px;line-height:1.6;color:#6b7280;">Enlace directo: <a class="email-link" href="' . $safeCrmUrl . '" style="color:#876500;text-decoration:underline;overflow-wrap:anywhere;word-break:break-all;">' . $safeCrmUrl . '</a></p></td></tr><tr><td style="padding:18px 26px;background:#f7f4ee;border-top:1px solid #e5dccb;"><p style="margin:0;font-size:12px;line-height:1.6;color:#69727f;">ID Industrial - Solicitudes web</p></td></tr></table></td></tr></table></body></html>';
}

function idindustrial_quote_request_client_email_html(array $data): string
{
  $safeName = crm_email_h(trim((string) ($data['name'] ?? '')) ?: 'cliente');
  $rows = idindustrial_quote_request_rows($data);
  return '<!doctype html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Solicitud recibida</title><style>@media only screen and (max-width:520px){.email-shell{padding:12px 8px!important}.email-card{border-radius:0!important}.email-pad{padding:22px 18px!important}.email-title{font-size:24px!important}.email-step{display:block!important;width:100%!important;box-sizing:border-box!important;margin-bottom:8px!important}}</style></head><body style="margin:0;padding:0;background:#f4f1eb;font-family:Arial,Helvetica,sans-serif;color:#11151c;-webkit-text-size-adjust:100%;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" class="email-shell" style="background:#f4f1eb;margin:0;padding:24px 12px;"><tr><td align="center"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" class="email-card" style="width:100%;max-width:640px;background:#ffffff;border:1px solid #ded6c8;border-radius:14px;overflow:hidden;border-collapse:separate;"><tr><td style="background:#101312;padding:24px 26px;"><div style="font-size:12px;letter-spacing:4px;font-weight:800;color:#f3c433;text-transform:uppercase;line-height:1.4;">ID Industrial</div><div style="margin-top:8px;font-size:22px;line-height:1.2;font-weight:800;color:#ffffff;">Solicitud recibida</div></td></tr><tr><td class="email-pad" style="padding:28px 26px;"><div style="display:inline-block;margin:0 0 14px;padding:6px 10px;border-radius:999px;background:#e9f7f0;color:#176044;font-size:11px;font-weight:800;letter-spacing:1.8px;text-transform:uppercase;">Confirmacion</div><h1 class="email-title" style="margin:0 0 10px;font-size:28px;line-height:1.15;color:#11151c;">Gracias, ' . $safeName . '</h1><p style="margin:0 0 20px;font-size:15px;line-height:1.65;color:#586170;">Recibimos tu solicitud de cotizacion. Nuestro equipo revisara la informacion y te contactara para confirmar alcance, tiempos y siguientes pasos.</p><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;margin:0 0 18px;">' . $rows . '</table><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 18px;"><tr><td class="email-step" width="33.33%" style="padding:10px;border:1px solid #e8dfcf;background:#fffdf8;font-size:13px;line-height:1.45;color:#303842;"><strong style="display:block;color:#876500;font-size:11px;letter-spacing:1.6px;text-transform:uppercase;margin-bottom:4px;">1. Revision</strong>Validamos los detalles enviados.</td><td class="email-step" width="33.33%" style="padding:10px;border:1px solid #e8dfcf;background:#fffdf8;font-size:13px;line-height:1.45;color:#303842;"><strong style="display:block;color:#876500;font-size:11px;letter-spacing:1.6px;text-transform:uppercase;margin-bottom:4px;">2. Contacto</strong>Confirmamos alcance y prioridad.</td><td class="email-step" width="33.33%" style="padding:10px;border:1px solid #e8dfcf;background:#fffdf8;font-size:13px;line-height:1.45;color:#303842;"><strong style="display:block;color:#876500;font-size:11px;letter-spacing:1.6px;text-transform:uppercase;margin-bottom:4px;">3. Propuesta</strong>Preparamos la ruta tecnica.</td></tr></table><p style="margin:0;font-size:13px;line-height:1.6;color:#6b7280;">Si necesitas agregar informacion, responde este correo o contactanos por WhatsApp.</p></td></tr><tr><td style="padding:18px 26px;background:#f7f4ee;border-top:1px solid #e5dccb;"><p style="margin:0;font-size:12px;line-height:1.6;color:#69727f;">ID Industrial - Ingenieria industrial en Queretaro y Bajio</p></td></tr></table></td></tr></table></body></html>';
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
    'image' => 'assets/img/optimized/service-card-deteccion-incendios.jpg',
    'alt' => 'Panel de detección de incendios industrial',
    'width' => 1254,
    'height' => 1254,
    'badge' => 'Seguridad',
    'linkText' => 'Consultar detección de incendios',
  ],
  [
    'id' => 'sistemas-hvac',
    'title' => 'Sistemas HVAC',
    'copy' => 'Climatización, ventilación, chillers y soporte técnico para continuidad operativa.',
    'application' => 'Aplicación: oficinas, cuartos técnicos y procesos de precisión.',
    'href' => 'instalacion-aire-acondicionado-industrial-queretaro/',
    'image' => 'assets/img/optimized/service-card-hvac-industrial.jpg',
    'alt' => 'Sistemas HVAC industriales',
    'width' => 1280,
    'height' => 960,
    'badge' => 'Climatización',
    'linkText' => 'Consultar sistemas HVAC industriales',
  ],
  [
    'id' => 'cctv-industrial',
    'title' => 'CCTV industrial',
    'copy' => 'Videovigilancia, grabación, monitoreo e integración con red y accesos.',
    'application' => 'Aplicación: perímetros, casetas, producción y edificios corporativos.',
    'href' => 'instalacion-camaras-seguridad-industrial-queretaro/',
    'image' => 'assets/img/optimized/service-card-cctv-industrial.jpg',
    'alt' => 'Sistema de CCTV industrial en Querétaro',
    'width' => 1254,
    'height' => 1254,
    'badge' => 'Videovigilancia',
    'linkText' => 'Ver más',
  ],
  [
    'id' => 'cableado-estructurado',
    'title' => 'Cableado estructurado',
    'copy' => 'Redes de voz y datos, racks, canalización, fibra óptica y pruebas para operación estable.',
    'application' => 'Aplicación: plantas, oficinas, sites y naves industriales.',
    'href' => 'industriales/cableado-estructurado-queretaro/',
    'image' => 'assets/img/optimized/service-card-cableado-estructurado.jpg',
    'alt' => 'Cableado estructurado industrial en Querétaro',
    'width' => 1280,
    'height' => 960,
    'badge' => 'Infraestructura',
    'linkText' => 'Conocer soluciones de cableado estructurado',
  ],
  [
    'id' => 'fibra-optica',
    'title' => 'Fibra óptica',
    'copy' => 'Backbone, fusiones, enlaces y certificación para redes de alto desempeño.',
    'application' => 'Aplicación: campus industriales, naves y edificios conectados.',
    'href' => '#contacto',
    'image' => 'assets/img/optimized/service-card-fibra-optica.jpg',
    'alt' => 'Instalación de fibra óptica en Querétaro',
    'width' => 1254,
    'height' => 1254,
    'badge' => 'Conectividad',
    'linkText' => 'Explorar soluciones de fibra óptica',
  ],
  [
    'id' => 'control-accesos',
    'title' => 'Control de Accesos',
    'copy' => 'Biométricos, tarjetas, plumas, perfiles de acceso, registros e integración con CCTV.',
    'application' => 'Aplicación: personal, proveedores, visitantes y áreas restringidas.',
    'href' => 'control-de-acceso-de-personal-queretaro/',
    'image' => 'assets/img/optimized/service-card-control-accesos.jpg',
    'alt' => 'Control de accesos biométrico industrial',
    'width' => 1254,
    'height' => 1254,
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

$bitacoraItems = [
  ['title' => 'Solicitudes y reportes', 'copy' => 'Tus equipos pueden levantar reportes técnicos con contexto, ubicación y prioridad desde el portal.'],
  ['title' => 'Seguimiento visible', 'copy' => 'Cada solicitud conserva estatus, respuesta del equipo, fecha objetivo y responsable de atención.'],
  ['title' => 'Historial del proyecto', 'copy' => 'La bitácora concentra evidencias, cotizaciones, mantenimiento y acuerdos posteriores a la entrega.'],
];

$bitacoraStatuses = [
  ['label' => 'Recibida', 'meta' => 'Solicitud registrada'],
  ['label' => 'En revisión', 'meta' => 'Alcance y prioridad'],
  ['label' => 'Programada', 'meta' => 'Atención coordinada'],
  ['label' => 'Resuelta', 'meta' => 'Cierre documentado'],
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
  'Soporte técnico / Bitácora ID',
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
$quoteTimezone = new DateTimeZone('America/Mexico_City');
$quoteMinDate = new DateTimeImmutable('today', $quoteTimezone);
$quoteMaxDate = $quoteMinDate->modify('+6 months');
$requestTypeOptions = ['Nuevo Sistema', 'Reparación', 'Mantenimiento'];
$formData = [
  'name' => '',
  'company' => '',
  'email' => '',
  'phone' => '',
  'request_type' => '',
  'service' => $serviceParamMap[$_GET['servicio'] ?? ''] ?? '',
  'city' => '',
  'desired_execution_date' => '',
  'attachment_names' => [],
  'message' => '',
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  $formData['name'] = trim((string) ($_POST['name'] ?? ''));
  $formData['company'] = trim((string) ($_POST['company'] ?? ''));
  $formData['email'] = trim((string) ($_POST['email'] ?? ''));
  $formData['phone'] = preg_replace('/\D+/', '', (string) ($_POST['phone'] ?? '')) ?? '';
  $formData['request_type'] = trim((string) ($_POST['request_type'] ?? ''));
  $formData['service'] = trim((string) ($_POST['service'] ?? ''));
  $formData['city'] = trim((string) ($_POST['city'] ?? ''));
  $formData['desired_execution_date'] = trim((string) ($_POST['desired_execution_date'] ?? ''));
  $formData['message'] = trim((string) ($_POST['message'] ?? ''));
  $honeypot = trim((string) ($_POST['company_site'] ?? ''));
  $preparedAttachments = [];

  if ($honeypot !== '') {
    $formStatus = ['type' => 'ok', 'text' => 'Gracias. Recibimos tu solicitud.'];
  } else {
    if ($formData['name'] === '' || mb_strlen($formData['name']) > 160) {
      $formErrors['name'] = 'Ingresa tu nombre.';
    }
    if ($formData['company'] === '' || mb_strlen($formData['company']) > 190) {
      $formErrors['company'] = 'Ingresa el nombre de tu empresa.';
    }
    if (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
      $formErrors['email'] = 'Ingresa un correo válido.';
    }
    if (strlen($formData['phone']) !== 10) {
      $formErrors['phone'] = 'Ingresa un teléfono WhatsApp de 10 dígitos.';
    }
    if (!in_array($formData['request_type'], $requestTypeOptions, true)) {
      $formErrors['request_type'] = 'Selecciona el tipo de solicitud.';
    }
    if (!in_array($formData['service'], $serviceOptions, true)) {
      $formErrors['service'] = 'Selecciona el servicio de interés.';
    }
    if ($formData['city'] === '' || mb_strlen($formData['city']) > 160) {
      $formErrors['city'] = 'Indica la ciudad donde se realizará el proyecto.';
    }

    $desiredDate = DateTimeImmutable::createFromFormat('!Y-m-d', $formData['desired_execution_date'], $quoteTimezone);
    $dateIsExact = $desiredDate && $desiredDate->format('Y-m-d') === $formData['desired_execution_date'];
    if (!$dateIsExact || $desiredDate < $quoteMinDate || $desiredDate > $quoteMaxDate) {
      $formErrors['desired_execution_date'] = 'Elige una fecha entre hoy y los próximos 6 meses.';
    }
    if ($formData['message'] === '' || mb_strlen($formData['message']) > 4000) {
      $formErrors['message'] = 'Describe brevemente los requerimientos del proyecto.';
    }

    try {
      $preparedAttachments = crm_prepare_opportunity_attachments($_FILES['project_files'] ?? null);
      $formData['attachment_names'] = array_column($preparedAttachments, 'original_name');
    } catch (RuntimeException $error) {
      $formErrors['project_files'] = $error->getMessage();
    }

    if ($formErrors) {
      $formStatus = ['type' => 'error', 'text' => 'Revisa los campos marcados para enviar tu solicitud.'];
    } else {
      $leadData = [
        'company_name' => $formData['company'],
        'contact_name' => $formData['name'],
        'contact_email' => $formData['email'],
        'contact_phone' => $formData['phone'],
        'request_type' => $formData['request_type'],
        'service' => $formData['service'],
        'project_location' => $formData['city'],
        'desired_execution_date' => $formData['desired_execution_date'],
        'notes' => $formData['message'],
      ];
      $opportunityId = crm_capture_public_lead($leadData, $preparedAttachments);
      if ($opportunityId) {
        $notificationService = $formData['service'];
        $adminOpportunityUrl = crm_app_url('oportunidades/' . $opportunityId);
        try {
          crm_create_notification(crm_db(), [
            'recipient_type' => 'admin',
            'opportunity_id' => $opportunityId,
            'event_type' => 'web_lead_received',
            'title' => 'Nueva solicitud de cotización',
            'message' => $formData['company'] . ' solicitó ' . $notificationService . ' (' . $formData['request_type'] . ').',
            'target_url' => crm_admin_url('opportunity', $opportunityId),
          ]);
        } catch (Throwable $error) {
          error_log('CRM web lead notification failed: ' . $error->getMessage());
        }
        try {
          $queuedPushNotifications = crm_enqueue_quote_push_notifications(
            crm_db(),
            $opportunityId,
            $formData['company'],
            $notificationService,
            $adminOpportunityUrl
          );
          if ($queuedPushNotifications > 0) {
            $pushResult = crm_dispatch_quote_push_notifications(crm_db(), $opportunityId);
            if (
              !($pushResult['ok'] ?? false)
              || (int) ($pushResult['enviadas'] ?? 0) < $queuedPushNotifications
            ) {
              error_log(
                'CRM quote push immediate dispatch incomplete: '
                . json_encode($pushResult, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
              );
            }
          }
        } catch (Throwable $error) {
          error_log('CRM quote push immediate dispatch failed: ' . $error->getMessage());
        }
      }

      $adminOpportunityUrl = $opportunityId ? crm_app_url('oportunidades/' . $opportunityId) : crm_app_url('oportunidades');
      $subject = 'Nueva solicitud de cotización web - ' . $formData['service'];
      $body = "Nueva solicitud de cotización web\n\nNombre: {$formData['name']}\nEmpresa: {$formData['company']}\nCorreo: {$formData['email']}\nTeléfono WhatsApp: {$formData['phone']}\nTipo: {$formData['request_type']}\nServicio: {$formData['service']}\nLocación: {$formData['city']}\nFecha deseada: {$formData['desired_execution_date']}\nAdjuntos: " . implode(', ', $formData['attachment_names']) . "\n\nMensaje:\n{$formData['message']}\n\nAbrir en CRM: {$adminOpportunityUrl}";
      $emailSent = crm_send_email($quoteRequestAdminEmail, $subject, $body, idindustrial_quote_request_admin_email_html($formData, $adminOpportunityUrl), [
        'cc' => $quoteRequestSecondaryEmail !== '' ? [$quoteRequestSecondaryEmail] : [],
        'reply_to' => $formData['email'],
      ]);
      $clientBody = "Hola {$formData['name']},\n\nRecibimos la solicitud de {$formData['request_type']} para {$formData['company']}.\nServicio: {$formData['service']}\nLocación: {$formData['city']}\nFecha deseada: {$formData['desired_execution_date']}\nTeléfono WhatsApp: {$formData['phone']}\n\nNuestro equipo te contactará para confirmar alcance, tiempos y siguientes pasos.\n\nID Industrial";
      $clientEmailSent = crm_send_email($formData['email'], 'Recibimos tu solicitud - ID Industrial', $clientBody, idindustrial_quote_request_client_email_html($formData));
      if (!$clientEmailSent) {
        error_log('CRM web lead client copy failed for opportunity ' . (int) $opportunityId);
      }

      if ($opportunityId) {
        $formStatus = ['type' => 'ok', 'text' => 'Listo. Registramos tu solicitud y los archivos del proyecto. Te contactaremos para preparar la cotización.'];
        $formData = array_fill_keys(array_keys($formData), '');
        $formData['attachment_names'] = [];
      } else {
        $formStatus = ['type' => 'error', 'text' => 'No fue posible guardar la solicitud y sus archivos. Intenta nuevamente o escríbenos por WhatsApp.'];
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
          <?php $serviceImageVersion = @filemtime(__DIR__ . '/' . $item['image']) ?: 1; ?>
          <article id="<?php echo htmlspecialchars($item['id']); ?>" class="service-card service-card--wide reveal">
            <img src="<?php echo htmlspecialchars($item['image']); ?>?v=<?php echo $serviceImageVersion; ?>" srcset="<?php echo htmlspecialchars(idindustrial_mobile_image($item['image'])); ?>?v=<?php echo $serviceImageVersion; ?> 640w, <?php echo htmlspecialchars($item['image']); ?>?v=<?php echo $serviceImageVersion; ?> <?php echo (int) $item['width']; ?>w" sizes="(max-width: 640px) calc(100vw - 28px), (max-width: 1120px) 33vw, 390px" alt="<?php echo htmlspecialchars($item['alt']); ?>" width="<?php echo (int) $item['width']; ?>" height="<?php echo (int) $item['height']; ?>" loading="lazy" decoding="async">
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

  <section id="bitacora-id" class="bitacora-id section-dark section-pad" aria-labelledby="bitacora-title">
    <div class="container bitacora-id__grid">
      <div class="bitacora-id__content reveal">
        <p class="eyebrow">Soporte posterior a entrega</p>
        <h2 id="bitacora-title">Cada proyecto puede continuar con soporte técnico mediante Bitácora ID.</h2>
        <p>Al cierre o entrega de un proyecto, podemos habilitar un acceso de soporte para que tu equipo reporte necesidades técnicas, consulte avances y conserve el historial operativo en un solo lugar.</p>
        <div class="bitacora-id__items">
          <?php foreach ($bitacoraItems as $item): ?>
            <article class="bitacora-id__item">
              <span aria-hidden="true"></span>
              <div>
                <h3><?php echo htmlspecialchars($item['title']); ?></h3>
                <p><?php echo htmlspecialchars($item['copy']); ?></p>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
        <div class="bitacora-id__actions">
          <a class="button button--primary" href="#cotizacion" data-quote-open data-quote-service="Soporte técnico / Bitácora ID" aria-controls="cotizacion">Solicitar acceso al soporte</a>
          <a class="button button--portal" href="<?php echo htmlspecialchars(crm_portal_url()); ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 11V8a7 7 0 0 1 14 0v3"/><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M12 15v2"/></svg>
            Ya soy cliente
          </a>
        </div>
        <p class="bitacora-id__access-help">Si ya recibiste tus credenciales, entra para consultar proyectos, mantenimientos, solicitudes y cotizaciones.</p>
      </div>
      <aside class="bitacora-id__panel reveal reveal--delay" aria-label="Flujo de seguimiento en Bitácora ID">
        <div class="bitacora-id__panel-head">
          <span>Bitácora ID</span>
          <strong>Seguimiento técnico</strong>
        </div>
        <div class="bitacora-id__status-list">
          <?php foreach ($bitacoraStatuses as $index => $status): ?>
            <div class="bitacora-id__status <?php echo $index === 3 ? 'is-complete' : ''; ?>">
              <span><?php echo str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT); ?></span>
              <div>
                <strong><?php echo htmlspecialchars($status['label']); ?></strong>
                <small><?php echo htmlspecialchars($status['meta']); ?></small>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="bitacora-id__note">
          <span>Portal de cliente</span>
          <p>Reportes, evidencias, cotizaciones y mantenimientos posteriores quedan documentados para consulta.</p>
        </div>
      </aside>
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

</main>

<button class="quote-fab" type="button" data-quote-open aria-controls="cotizacion">
  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H9.4L5 20v-4H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm0 2v8h3v1.4L8.6 14H20V6H4Zm3 2h10v2H7V8Zm0 3h7v2H7v-2Z"/></svg>
  <span>Cotizar</span>
</button>

<?php $quoteModalStartsOpen = (bool) $formStatus || $formData['service'] !== ''; ?>
<div id="cotizacion" class="quote-modal <?php echo $quoteModalStartsOpen ? 'is-open' : ''; ?>" role="dialog" aria-modal="true" aria-labelledby="quote-modal-title" aria-describedby="quote-modal-description" aria-hidden="<?php echo $quoteModalStartsOpen ? 'false' : 'true'; ?>" data-quote-modal>
  <div class="quote-modal__overlay" data-quote-close></div>
  <div class="quote-modal__panel" role="document">
    <header class="quote-modal__head">
      <span class="quote-modal__icon" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="M4 4h16a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H9.4L5 20v-4H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm0 2v8h3v1.4L8.6 14H20V6H4Zm3 2h10v2H7V8Zm0 3h7v2H7v-2Z"/></svg>
      </span>
      <div class="quote-modal__head-copy">
        <span class="quote-modal__eyebrow">Solicitud técnica</span>
        <h3 id="quote-modal-title">Cotiza tu servicio</h3>
        <p id="quote-modal-description">Cuéntanos lo esencial del proyecto. Nuestro equipo revisará la información antes de contactarte.</p>
      </div>
      <div class="quote-modal__assurances" aria-label="Beneficios de la solicitud">
        <span><i aria-hidden="true"></i> Seguimiento visible</span>
        <span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 4 5v6c0 5 3.4 9.7 8 11 4.6-1.3 8-6 8-11V5l-8-3Zm0 2.2L18 6.5V11c0 3.8-2.4 7.5-6 8.8C8.4 18.5 6 14.8 6 11V6.5l6-2.3Zm-1 10.6-3-3 1.4-1.4 1.6 1.6 3.6-3.6 1.4 1.4-5 5Z"/></svg> Datos protegidos</span>
      </div>
      <button class="quote-modal__close" type="button" aria-label="Cerrar solicitud" data-quote-close>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6.4 5 12.6 12.6-1.4 1.4L5 6.4 6.4 5Zm12.6 1.4L6.4 19 5 17.6 17.6 5 19 6.4Z"/></svg>
      </button>
    </header>

    <div class="quote-modal__body">
      <form id="quote-request-form" class="contact-form quote-form" action="<?php echo htmlspecialchars(crm_public_url('', [], 'cotizacion')); ?>" method="post" enctype="multipart/form-data" data-contact-form>
        <?php if ($formStatus): ?>
          <div class="quote-form__status">
            <p class="form-status form-status--<?php echo htmlspecialchars($formStatus['type']); ?>" role="<?php echo $formStatus['type'] === 'error' ? 'alert' : 'status'; ?>"><?php echo htmlspecialchars($formStatus['text']); ?></p>
            <?php if ($formStatus['type'] === 'error'): ?><a class="form-fallback" href="https://wa.me/<?php echo htmlspecialchars($whatsapp); ?>?text=Hola%20ID%20Industrial,%20quiero%20solicitar%20una%20evaluacion%20tecnica" target="_blank" rel="noopener noreferrer">Continuar por WhatsApp</a><?php endif; ?>
          </div>
        <?php endif; ?>

        <section class="quote-form__section" aria-labelledby="quote-step-contact-title">
          <header class="quote-form__section-head">
            <span class="quote-form__step" aria-hidden="true">01</span>
            <span class="quote-form__section-copy">
              <span class="quote-form__section-kicker">Paso 1 de 3</span>
              <strong id="quote-step-contact-title">Datos de contacto</strong>
              <small>Para identificarte y darte seguimiento.</small>
            </span>
          </header>
          <div class="quote-form__section-body">
          <div class="quote-form__grid">
            <label class="quote-field" for="contact-name">
              <span class="quote-field__label">Nombre completo <b aria-hidden="true">*</b></span>
              <input id="contact-name" type="text" name="name" autocomplete="name" maxlength="160" placeholder="Ej. Andrea Martínez" value="<?php echo htmlspecialchars($formData['name']); ?>" required aria-invalid="<?php echo isset($formErrors['name']) ? 'true' : 'false'; ?>"<?php if (isset($formErrors['name'])): ?> aria-describedby="contact-name-error"<?php endif; ?>>
              <?php if (isset($formErrors['name'])): ?><span class="field-error" id="contact-name-error" role="alert"><?php echo htmlspecialchars($formErrors['name']); ?></span><?php endif; ?>
            </label>
            <label class="quote-field" for="contact-company">
              <span class="quote-field__label">Empresa <b aria-hidden="true">*</b></span>
              <input id="contact-company" type="text" name="company" autocomplete="organization" maxlength="190" placeholder="Nombre de la empresa" value="<?php echo htmlspecialchars($formData['company']); ?>" required aria-invalid="<?php echo isset($formErrors['company']) ? 'true' : 'false'; ?>"<?php if (isset($formErrors['company'])): ?> aria-describedby="contact-company-error"<?php endif; ?>>
              <?php if (isset($formErrors['company'])): ?><span class="field-error" id="contact-company-error" role="alert"><?php echo htmlspecialchars($formErrors['company']); ?></span><?php endif; ?>
            </label>
            <label class="quote-field" for="contact-email">
              <span class="quote-field__label">Correo corporativo <b aria-hidden="true">*</b></span>
              <input id="contact-email" type="email" name="email" autocomplete="email" maxlength="190" placeholder="correo@empresa.com" value="<?php echo htmlspecialchars($formData['email']); ?>" required aria-invalid="<?php echo isset($formErrors['email']) ? 'true' : 'false'; ?>"<?php if (isset($formErrors['email'])): ?> aria-describedby="contact-email-error"<?php endif; ?>>
              <?php if (isset($formErrors['email'])): ?><span class="field-error" id="contact-email-error" role="alert"><?php echo htmlspecialchars($formErrors['email']); ?></span><?php endif; ?>
            </label>
            <label class="quote-field" for="contact-phone">
              <span class="quote-field__label">Teléfono WhatsApp <b aria-hidden="true">*</b></span>
              <input id="contact-phone" type="tel" name="phone" autocomplete="tel-national" inputmode="numeric" minlength="10" maxlength="10" pattern="[0-9]{10}" placeholder="4420000000" value="<?php echo htmlspecialchars($formData['phone']); ?>" required data-quote-phone aria-invalid="<?php echo isset($formErrors['phone']) ? 'true' : 'false'; ?>" aria-describedby="contact-phone-help<?php echo isset($formErrors['phone']) ? ' contact-phone-error' : ''; ?>">
              <span class="field-help" id="contact-phone-help">10 dígitos, sin espacios ni +52.</span>
              <?php if (isset($formErrors['phone'])): ?><span class="field-error" id="contact-phone-error" role="alert"><?php echo htmlspecialchars($formErrors['phone']); ?></span><?php endif; ?>
            </label>
          </div>
          </div>
        </section>

        <section class="quote-form__section" aria-labelledby="quote-step-project-title">
          <header class="quote-form__section-head">
            <span class="quote-form__step" aria-hidden="true">02</span>
            <span class="quote-form__section-copy">
              <span class="quote-form__section-kicker">Paso 2 de 3</span>
              <strong id="quote-step-project-title">Información del proyecto</strong>
              <small>Ayúdanos a preparar el alcance inicial.</small>
            </span>
          </header>
          <div class="quote-form__section-body">
          <div class="quote-form__grid">
            <label class="quote-field" for="contact-request-type">
              <span class="quote-field__label">Tipo de solicitud <b aria-hidden="true">*</b></span>
              <select id="contact-request-type" name="request_type" required aria-invalid="<?php echo isset($formErrors['request_type']) ? 'true' : 'false'; ?>"<?php if (isset($formErrors['request_type'])): ?> aria-describedby="contact-request-type-error"<?php endif; ?>>
                <option value="">Seleccionar una opción</option>
                <?php foreach ($requestTypeOptions as $option): ?>
                  <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $formData['request_type'] === $option ? 'selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
                <?php endforeach; ?>
              </select>
              <?php if (isset($formErrors['request_type'])): ?><span class="field-error" id="contact-request-type-error" role="alert"><?php echo htmlspecialchars($formErrors['request_type']); ?></span><?php endif; ?>
            </label>
            <label class="quote-field" for="contact-service">
              <span class="quote-field__label">Servicio de interés <b aria-hidden="true">*</b></span>
              <select id="contact-service" name="service" data-quote-service-field required aria-invalid="<?php echo isset($formErrors['service']) ? 'true' : 'false'; ?>"<?php if (isset($formErrors['service'])): ?> aria-describedby="contact-service-error"<?php endif; ?>>
                <option value="">Seleccionar un servicio</option>
                <?php foreach ($serviceOptions as $option): ?>
                  <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $formData['service'] === $option ? 'selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
                <?php endforeach; ?>
              </select>
              <?php if (isset($formErrors['service'])): ?><span class="field-error" id="contact-service-error" role="alert"><?php echo htmlspecialchars($formErrors['service']); ?></span><?php endif; ?>
            </label>
            <label class="quote-field" for="contact-city">
              <span class="quote-field__label">Locación del proyecto <b aria-hidden="true">*</b></span>
              <input id="contact-city" type="text" name="city" autocomplete="address-level2" maxlength="160" placeholder="Ej. Querétaro, Qro." value="<?php echo htmlspecialchars($formData['city']); ?>" required aria-invalid="<?php echo isset($formErrors['city']) ? 'true' : 'false'; ?>"<?php if (isset($formErrors['city'])): ?> aria-describedby="contact-city-error"<?php endif; ?>>
              <?php if (isset($formErrors['city'])): ?><span class="field-error" id="contact-city-error" role="alert"><?php echo htmlspecialchars($formErrors['city']); ?></span><?php endif; ?>
            </label>
            <label class="quote-field" for="contact-execution-date">
              <span class="quote-field__label">Fecha deseada de ejecución <b aria-hidden="true">*</b></span>
              <input id="contact-execution-date" type="date" name="desired_execution_date" min="<?php echo htmlspecialchars($quoteMinDate->format('Y-m-d')); ?>" max="<?php echo htmlspecialchars($quoteMaxDate->format('Y-m-d')); ?>" value="<?php echo htmlspecialchars($formData['desired_execution_date']); ?>" required aria-invalid="<?php echo isset($formErrors['desired_execution_date']) ? 'true' : 'false'; ?>" aria-describedby="contact-date-help<?php echo isset($formErrors['desired_execution_date']) ? ' contact-date-error' : ''; ?>">
              <span class="field-help" id="contact-date-help">Disponible desde hoy y hasta 6 meses.</span>
              <?php if (isset($formErrors['desired_execution_date'])): ?><span class="field-error" id="contact-date-error" role="alert"><?php echo htmlspecialchars($formErrors['desired_execution_date']); ?></span><?php endif; ?>
            </label>
          </div>
          </div>
        </section>

        <section class="quote-form__section quote-form__section--documents" aria-labelledby="quote-step-documents-title">
          <header class="quote-form__section-head">
            <span class="quote-form__step" aria-hidden="true">03</span>
            <span class="quote-form__section-copy">
              <span class="quote-form__section-kicker">Paso 3 de 3</span>
              <strong id="quote-step-documents-title">Documentación y requerimientos</strong>
              <small>Comparte contexto técnico para una revisión más precisa.</small>
            </span>
          </header>
          <div class="quote-form__section-body">
          <label class="file-field" for="contact-project-files">
            <input class="file-field__input" id="contact-project-files" type="file" name="project_files[]" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp" multiple required data-quote-files aria-invalid="<?php echo isset($formErrors['project_files']) ? 'true' : 'false'; ?>" aria-describedby="contact-files-help contact-files-summary<?php echo isset($formErrors['project_files']) ? ' contact-files-error' : ''; ?>">
            <span class="file-field__visual">
              <span class="file-field__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M11 16V8.8L8.4 11.4 7 10l5-5 5 5-1.4 1.4L13 8.8V16h-2Zm-5 4a4 4 0 0 1-4-4c0-1.8 1.2-3.4 2.9-3.8A7 7 0 0 1 18.7 10 5 5 0 0 1 19 20H6Zm0-2h13a3 3 0 1 0-1.8-5.4l-.7.5-.7-.5A5 5 0 0 0 7 15v1H6a2 2 0 1 0 0 4v-2Z"/></svg>
              </span>
              <span class="file-field__copy">
                <strong>Arrastra archivos aquí o selecciónalos</strong>
                <small id="contact-files-help">PDF, JPG, PNG o WEBP · Hasta 5 archivos · 8 MB por archivo</small>
              </span>
              <span class="file-field__action">Elegir archivos</span>
            </span>
            <span class="file-selection" id="contact-files-summary" data-file-summary aria-live="polite">Aún no has seleccionado archivos.</span>
            <?php if (isset($formErrors['project_files'])): ?><span class="field-error" id="contact-files-error" role="alert"><?php echo htmlspecialchars($formErrors['project_files']); ?></span><?php endif; ?>
          </label>

          <label class="quote-field quote-field--wide" for="contact-message">
            <span class="quote-field__label">Requerimientos del proyecto <b aria-hidden="true">*</b></span>
            <textarea id="contact-message" name="message" rows="5" maxlength="4000" placeholder="Describe el alcance, tipo de instalación, prioridad y cualquier detalle técnico relevante." required aria-invalid="<?php echo isset($formErrors['message']) ? 'true' : 'false'; ?>"<?php if (isset($formErrors['message'])): ?> aria-describedby="contact-message-error"<?php endif; ?>><?php echo htmlspecialchars($formData['message']); ?></textarea>
            <?php if (isset($formErrors['message'])): ?><span class="field-error" id="contact-message-error" role="alert"><?php echo htmlspecialchars($formErrors['message']); ?></span><?php endif; ?>
          </label>
          </div>
        </section>

        <label class="honeypot" for="company-site">
          Sitio
          <input id="company-site" type="text" name="company_site" tabindex="-1" autocomplete="off">
        </label>

        <footer class="quote-form__footer">
          <p><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 4 5v6c0 5 3.4 9.7 8 11 4.6-1.3 8-6 8-11V5l-8-3Zm0 2.2L18 6.5V11c0 3.8-2.4 7.5-6 8.8C8.4 18.5 6 14.8 6 11V6.5l6-2.3Z"/></svg><span>Todos los campos son obligatorios. Al enviar aceptas el <a href="aviso-de-privacidad/">Aviso de Privacidad</a>.</span></p>
          <button class="button button--primary" type="submit">
            <span>Enviar solicitud</span>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.01 21 23 12 2.01 3 2 10l15 2-15 2Z"/></svg>
          </button>
        </footer>
      </form>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
