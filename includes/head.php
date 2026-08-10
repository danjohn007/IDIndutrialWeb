<!doctype html>
<html lang="es-MX">
<head>
  <?php
    $assetBase = $basePath ?? '';
    $stylesFile = is_file(__DIR__ . '/../assets/css/styles.min.css') ? 'styles.min.css' : 'styles.css';
    $stylesVersion = @filemtime(__DIR__ . '/../assets/css/' . $stylesFile) ?: '1';
    $stylesHref = $assetBase . 'assets/css/' . $stylesFile . '?v=' . $stylesVersion;
    $publicAssetBase = $assetUrlBase ?? 'https://idindustrial.com.mx/';
  ?>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($title ?? 'ID Industrial'); ?></title>
  <meta name="description" content="<?php echo htmlspecialchars($description ?? 'Soluciones industriales en Querétaro.'); ?>">
  <meta name="keywords" content="<?php echo htmlspecialchars($keywords ?? 'ID Industrial'); ?>">
  <meta name="robots" content="index, follow, max-image-preview:large">
  <link rel="canonical" href="<?php echo htmlspecialchars($canonicalUrl ?? $siteUrl ?? 'https://idindustrial.com.mx/'); ?>">
  <meta name="theme-color" content="#0a0b0d">
  <meta property="og:type" content="website">
  <meta property="og:locale" content="es_MX">
  <meta property="og:title" content="<?php echo htmlspecialchars($title ?? 'ID Industrial'); ?>">
  <meta property="og:description" content="<?php echo htmlspecialchars($description ?? 'Soluciones industriales en Querétaro.'); ?>">
  <meta property="og:url" content="<?php echo htmlspecialchars($canonicalUrl ?? $siteUrl ?? 'https://idindustrial.com.mx/'); ?>">
  <meta property="og:image" content="<?php echo htmlspecialchars($publicAssetBase . 'assets/img/og-id-industrial.webp'); ?>">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?php echo htmlspecialchars($title ?? 'ID Industrial'); ?>">
  <meta name="twitter:description" content="<?php echo htmlspecialchars($description ?? 'Soluciones industriales en Querétaro.'); ?>">
  <meta name="twitter:image" content="<?php echo htmlspecialchars($publicAssetBase . 'assets/img/og-id-industrial.webp'); ?>">
  <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($assetBase); ?>assets/img/favicon-id-industrial.png">
  <script>document.documentElement.classList.add('js');</script>
  <style>
    :root{--bg:#08090b;--ink:#f7f7f2;--yellow:#f3c623;--muted:#a7abb2;--container:min(1240px,calc(100vw - 40px))}
    *{box-sizing:border-box}
    html{scroll-behavior:smooth;scroll-padding-top:88px;overflow-x:hidden}
    body{margin:0;background:#08090b;color:var(--ink);font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;line-height:1.6;overflow-x:hidden}
    img{display:block;max-width:100%}
    a{color:inherit;text-decoration:none}
    .container{width:var(--container);margin-inline:auto}
    .site-header{position:fixed;inset:0 0 auto;z-index:60;padding:12px 0}
    .nav{min-height:68px;display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:18px}
    .brand img{width:116px;height:auto}
    .hero{position:relative;height:100svh;min-height:680px;max-height:100svh;display:grid;align-items:center;overflow:hidden;background:#08090b}
    .hero-carousel,.hero-carousel__viewport,.hero-carousel__slide,.hero__overlay{position:absolute;inset:0;width:100%;height:100%}
    .hero-carousel{overflow:hidden;background:#050607}.hero-carousel__slide{margin:0;opacity:0}.hero-carousel__slide.is-active{opacity:1}
    .hero-carousel__slide::before{content:"";position:absolute;inset:-26px;background-image:var(--slide-image);background-position:center;background-size:cover;filter:blur(14px) saturate(.85);opacity:.64;transform:scale(1.12)}
    .hero-carousel__slide img{position:relative;width:100%;height:100%;object-fit:cover;object-position:center;transform:scale(1.01)}
    .hero-carousel__slide figcaption{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
    .hero__overlay{z-index:1;background:linear-gradient(90deg,rgba(8,9,11,.96),rgba(8,9,11,.62) 48%,rgba(8,9,11,.26)),linear-gradient(0deg,rgba(8,9,11,.93),rgba(8,9,11,.38) 58%,rgba(8,9,11,.62))}
    .hero__grid{position:relative;z-index:2;padding:118px 0 54px}
    .eyebrow{margin:0 0 16px;color:var(--yellow);font-size:.76rem;font-weight:850;letter-spacing:.16em;text-transform:uppercase}
    h1{margin:0 0 22px;max-width:1120px;font-size:clamp(3.4rem,7.6vw,7.25rem);line-height:.92;font-weight:900}
    h1 span{display:block}
    .hero__lead{max-width:720px;color:#e7e7e0;font-size:clamp(1rem,1.45vw,1.16rem)}
    .button{display:inline-flex;align-items:center;justify-content:center;min-height:54px;padding:0 28px;border-radius:999px;font-weight:900}
    .button--primary{background:linear-gradient(135deg,#ffd84d,#f3c623);color:#111316}
    @media (max-width:899px){.nav{grid-template-columns:auto 1fr}.nav-toggle{grid-column:2;justify-self:end;order:0}.hero{height:auto;min-height:100svh;max-height:none}.hero__grid{padding:96px 0 28px}h1{font-size:clamp(2.5rem,12.4vw,3.8rem);line-height:.94}}
  </style>
  <link rel="preload" href="<?php echo htmlspecialchars($stylesHref); ?>" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript><link rel="stylesheet" href="<?php echo htmlspecialchars($stylesHref); ?>"></noscript>
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "LocalBusiness",
    "name": "ID Industrial",
    "url": "<?php echo htmlspecialchars($siteUrl ?? 'https://idindustrial.com.mx/'); ?>",
    "image": "<?php echo htmlspecialchars($publicAssetBase . 'assets/img/logo-idindustrial.webp'); ?>",
    "telephone": "<?php echo htmlspecialchars($phone ?? '+52 442 598 6318'); ?>",
    "email": "<?php echo htmlspecialchars($contactEmail ?? 'contacto@idindustrial.com.mx'); ?>",
    "address": {
      "@type": "PostalAddress",
      "addressLocality": "Querétaro",
      "addressRegion": "Querétaro",
      "addressCountry": "MX"
    },
    "areaServed": ["Querétaro", "El Marqués", "Corregidora", "Bajío"],
    "knowsAbout": [
      "cableado estructurado industrial",
      "fibra óptica",
      "infraestructura TI industrial",
      "CCTV industrial",
      "control de accesos",
      "HVAC industrial",
      "detección de incendios",
      "Smart Factories e IoT industrial"
    ],
    "hasOfferCatalog": {
      "@type": "OfferCatalog",
      "name": "Soluciones industriales",
      "itemListElement": [
        {"@type": "Service", "name": "Cableado estructurado y fibra óptica"},
        {"@type": "Service", "name": "Detección de incendios"},
        {"@type": "Service", "name": "Sistemas HVAC"},
        {"@type": "Service", "name": "Control de accesos"},
        {"@type": "Service", "name": "CCTV industrial"},
        {"@type": "Service", "name": "Smart Factories / IoT"}
      ]
    }
  }
  </script>
</head>
<body>
<a class="skip-link" href="#inicio">Saltar al contenido</a>
