{{-- resources/views/site.blade.php — Site público Sid360 (GET /) --}}
@php
  $siteLots = config('site.lots');
  $loteamento = config('site.loteamento');
  $fmtBrl = static fn (int|float $amount): string => number_format((int) $amount, 0, ',', '.');
  $fmtBrlShort = static function (int|float $amount): string {
      $amount = (int) $amount;
      if ($amount >= 1000 && $amount % 1000 === 0) {
          return 'R$ ' . number_format($amount / 1000, 0, ',', '.') . ' mil';
      }
      return 'R$ ' . number_format($amount, 0, ',', '.');
  };
  $lotRes = $siteLots['residencial'];
  $lotBr = $siteLots['frente-br'];
  $lotPriceOriginal = static fn (array $lot): int => (int) ($lot['price_original'] ?? $lot['price_installment']);
  $priceTriggerResShort = 'de ' . $fmtBrlShort($lotPriceOriginal($lotRes)) . ' por ' . $fmtBrlShort($lotRes['price_cash']);
  $priceTriggerBrShort = 'de ' . $fmtBrlShort($lotPriceOriginal($lotBr)) . ' por ' . $fmtBrlShort($lotBr['price_cash']);
  $priceTriggersMetaPlain = 'À vista: ' . $priceTriggerResShort . ' (residencial) e ' . $priceTriggerBrShort . ' (frente BR).';
  $lotDiscountPct = static function (array $lot) use ($lotPriceOriginal): int {
      $from = $lotPriceOriginal($lot);
      $to = (int) $lot['price_cash'];
      if ($from <= 0 || $to >= $from) {
          return 0;
      }
      return (int) round((($from - $to) / $from) * 100);
  };
  $lotBrDiscountPct = $lotDiscountPct($lotBr);
  $lotResDiscountPct = $lotDiscountPct($lotRes);
  $lotParcelaFrom30x = static function (array $lot): int {
      $total = (int) $lot['price_installment'];
      $entrada = (int) round($total * 0.2);
      return (int) round(($total - $entrada) / 30);
  };
  $lotBrParcelaFrom = $lotParcelaFrom30x($lotBr);
  $lotResParcelaFrom = $lotParcelaFrom30x($lotRes);
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">

{{-- ===== SEO BÁSICO ===== --}}
<title>Sid360 — Lotes e Imóveis em Cafarnaum-BA</title>
<meta name="description" content="Lotes residenciais e comerciais em Cafarnaum-BA. {{ $priceTriggersMetaPlain }} Negocie direto com o Sid.">
<meta name="keywords" content="imóveis Cafarnaum, lotes Cafarnaum, corretor Cafarnaum, terrenos Cafarnaum BA, loteamento Cafarnaum, imóveis Bahia, lotes residenciais, terreno rural Cafarnaum">
<meta name="author" content="Sid Nunes — Corretor de Imóveis">
<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
<meta name="googlebot" content="index, follow">
<link rel="canonical" href="https://sid360.com.br/">

{{-- ===== OPEN GRAPH (Facebook, WhatsApp, LinkedIn) ===== --}}
<meta property="og:type" content="website">
<meta property="og:site_name" content="Sid360 Imóveis">
<meta property="og:title" content="Sid360 — Imóveis em Cafarnaum-BA">
<meta property="og:description" content="Lotes em Cafarnaum-BA. {{ $priceTriggersMetaPlain }} Negociação direta com o Sid.">
<meta property="og:url" content="https://sid360.com.br/">
<meta property="og:image" content="https://sid360.com.br/img/og-image.jpg">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="Sid360 — Loteamento em Cafarnaum-BA">
<meta property="og:locale" content="pt_BR">

{{-- ===== TWITTER CARD ===== --}}
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="Sid360 — Imóveis em Cafarnaum-BA">
<meta name="twitter:description" content="Lotes, casas e terrenos rurais em Cafarnaum e região. Negociação direta com o Sid.">
<meta name="twitter:image" content="https://sid360.com.br/img/og-image.jpg">

{{-- ===== GEO / LOCAL SEO ===== --}}
<meta name="geo.region" content="BR-BA">
<meta name="geo.placename" content="Cafarnaum, Bahia, Brasil">
<meta name="geo.position" content="-11.4667;-39.9833">
<meta name="ICBM" content="-11.4667, -39.9833">

{{-- ===== FAVICON ===== --}}
<link rel="icon" type="image/png" href="{{ asset('favicon/favicon-96x96.png') }}" sizes="96x96" />
<link rel="icon" type="image/svg+xml" href="{{ asset('img/favicon.svg') }}" />
<link rel="shortcut icon" href="{{ asset('favicon/favicon.ico') }}" />
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('favicon/apple-touch-icon.png') }}" />
<meta name="apple-mobile-web-app-title" content="Sid360" />
<link rel="manifest" href="{{ asset('site.webmanifest') }}" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css" />

<style>
:root {
  /* Logo red (S/D letters) — primary CTA & buttons */
  --accent:        #C23028;
  --accent-light:  #D44840;

  /* Logo gold (360 letters) — labels, icons, highlights */
  --accent-dark:   #C9A84C;

  /* Light backgrounds — warm cream matching logo background */
  --bg-page:       #FAF5EE;
  --bg-section:    #F0E8DB;

  /* Dark backgrounds — warm deep (not pure crimson) */
  --bg-dark:       #2A1008;
  --bg-darker:     #1C0A06;

  --text-primary:  #1C0A06;
  --text-secondary:#7A4535;
  --text-light:    #FAF5EE;
  --text-muted:    rgba(250,245,238,0.55);

  --border-light:  rgba(28,10,6,0.1);
  --border-accent: rgba(201,168,76,0.3);

  --font-display: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, Arial, sans-serif;
  --font-body:    -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, Arial, sans-serif;
}

* { margin: 0; padding: 0; box-sizing: border-box; }
html { scroll-behavior: smooth; }

body {
  font-family: var(--font-body);
  font-size: 16px;
  background: var(--bg-page);
  color: var(--text-primary);
  overflow-x: hidden;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  text-rendering: optimizeLegibility;
}

/* NAV */
nav {
  position: fixed;
  top: 0; left: 0; right: 0;
  z-index: 100;
  padding: 20px 5%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: transparent;
  transition: background 0.45s ease, padding 0.45s ease, box-shadow 0.45s ease;
}

nav.scrolled {
  background: #fbf2ea;
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  padding: 14px 5%;
  box-shadow: 0 1px 20px rgba(26,7,7,0.1);
}

nav.scrolled .nav-links a {
  color: var(--text-secondary);
}

nav.scrolled .nav-links a:hover {
  color: var(--accent);
}

nav.scrolled .nav-cta {
  background: var(--accent) !important;
  color: var(--text-light) !important;
}

nav.scrolled .nav-cta:hover {
  background: var(--accent-dark) !important;
}

.nav-logo {
  display: flex;
  align-items: center;
  text-decoration: none;
}

.nav-logo-img {
  height: 64px;
  width: auto;
  display: block;
  max-width: 240px;
  object-fit: contain;
}

.footer-logo-img {
  height: 56px;
  width: auto;
  display: block;
  max-width: 220px;
  object-fit: contain;
}

.nav-links {
  display: flex;
  align-items: center;
  gap: 28px;
  list-style: none;
}

.nav-links a {
  color: rgba(247,243,238,0.75);
  text-decoration: none;
  font-size: 0.88rem;
  font-weight: 500;
  transition: color 0.2s;
}

.nav-links a:hover { color: var(--accent); }

.nav-cta {
  background: var(--accent) !important;
  color: var(--text-light) !important;
  padding: 8px 20px;
  border-radius: 8px;
  font-weight: 700 !important;
}

.nav-cta:hover { background: var(--accent-light) !important; }

/* NAV — mobile actions & drawer */
.nav-mobile-actions {
  display: none;
  align-items: center;
  gap: 10px;
}

.nav-wa-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  height: 42px;
  padding: 0 14px;
  background: #25d366;
  color: #fff;
  border-radius: 10px;
  text-decoration: none;
  flex-shrink: 0;
  font-size: 0.8rem;
  font-weight: 700;
  white-space: nowrap;
  transition: background 0.2s, transform 0.2s;
}

.nav-wa-btn:hover {
  background: #1db954;
  transform: scale(1.02);
}

.nav-wa-btn svg {
  width: 18px;
  height: 18px;
  fill: currentColor;
  flex-shrink: 0;
}

.nav-toggle {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 5px;
  width: 42px;
  height: 42px;
  padding: 0;
  border: 1px solid rgba(255,255,255,0.3);
  border-radius: 10px;
  background: rgba(255,255,255,0.1);
  cursor: pointer;
  flex-shrink: 0;
  transition: background 0.2s, border-color 0.2s;
}

.nav-toggle span {
  display: block;
  width: 18px;
  height: 2px;
  background: var(--text-light);
  border-radius: 2px;
  transition: transform 0.25s ease, opacity 0.2s ease, background 0.2s;
}

nav.scrolled .nav-toggle {
  background: rgba(28,10,6,0.05);
  border-color: var(--border-light);
}

nav.scrolled .nav-toggle span {
  background: var(--text-primary);
}

.nav-toggle.is-open span:nth-child(1) {
  transform: translateY(7px) rotate(45deg);
}

.nav-toggle.is-open span:nth-child(2) {
  opacity: 0;
}

.nav-toggle.is-open span:nth-child(3) {
  transform: translateY(-7px) rotate(-45deg);
}

.nav-backdrop {
  position: fixed;
  inset: 0;
  z-index: 98;
  background: rgba(28,10,6,0.55);
  opacity: 0;
  visibility: hidden;
  transition: opacity 0.3s ease, visibility 0.3s;
}

.nav-backdrop.is-visible {
  opacity: 1;
  visibility: visible;
}

.nav-drawer {
  position: fixed;
  top: 0;
  right: 0;
  z-index: 99;
  width: min(300px, 88vw);
  height: 100%;
  background: var(--bg-page);
  box-shadow: -8px 0 32px rgba(28,10,6,0.15);
  padding: 88px 24px 32px;
  transform: translateX(100%);
  transition: transform 0.32s ease;
  overflow-y: auto;
}

.nav-drawer.is-open {
  transform: translateX(0);
}

.nav-drawer-links {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.nav-drawer-links a {
  display: block;
  padding: 14px 0;
  color: var(--text-primary);
  text-decoration: none;
  font-size: 1rem;
  font-weight: 500;
  border-bottom: 1px solid var(--border-light);
  transition: color 0.2s;
}

.nav-drawer-links a:hover {
  color: var(--accent);
}

.nav-drawer-links .nav-drawer-cta {
  margin-top: 16px;
  text-align: center;
  background: var(--accent);
  color: var(--text-light) !important;
  border: none;
  border-radius: 10px;
  padding: 14px 20px;
  font-weight: 700;
}

.nav-drawer-links .nav-drawer-cta:hover {
  background: var(--accent-light);
}

body.nav-menu-open {
  overflow: hidden;
}

/* HERO FULLSCREEN */
.hero {
  position: relative;
  width: 100%;
  height: 100vh;
  min-height: 600px;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
}

.hero-media {
  position: absolute;
  inset: 0;
  z-index: 0;
}

.hero-video {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  z-index: 2;
}

.hero-slides {
  position: absolute;
  inset: 0;
  z-index: 1;
}

.hero-slide {
  position: absolute;
  inset: -60px;
  background-size: cover;
  background-position: center;
  opacity: 0;
  transition: opacity 1.2s ease;
  will-change: transform, background-position;
}

.hero-slide.active { opacity: 1; }

.hero-overlay {
  position: absolute;
  inset: 0;
  z-index: 3;
  background: linear-gradient(
    to bottom,
    rgba(26,7,7,0.2) 0%,
    rgba(26,7,7,0.55) 50%,
    rgba(26,7,7,0.78) 100%
  );
}

.hero-content {
  position: relative;
  z-index: 10;
  text-align: center;
  color: var(--text-light);
  padding: 0 5%;
  max-width: 800px;
  will-change: transform;
}

.hero-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: rgba(201,168,76,0.15);
  border: 1px solid rgba(201,168,76,0.35);
  color: #EDD882;
  padding: 6px 16px;
  border-radius: 100px;
  font-size: 0.72rem;
  font-weight: 600;
  letter-spacing: 1.5px;
  text-transform: uppercase;
  margin-bottom: 28px;
}

.hero-badge-dot {
  width: 6px; height: 6px;
  background: var(--accent);
  border-radius: 50%;
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.5; transform: scale(0.7); }
}

.hero-title {
  font-family: var(--font-display);
  font-weight: 800;
  font-size: clamp(2.6rem, 6vw, 5rem);
  line-height: 1.05;
  letter-spacing: -1.5px;
  margin-bottom: 20px;
  text-shadow: 0 2px 20px rgba(0,0,0,0.4);
}

.hero-title span { color: var(--accent); }

.hero-sub {
  font-size: clamp(1rem, 2vw, 1.15rem);
  color: rgba(247,243,238,0.8);
  line-height: 1.7;
  margin-bottom: 36px;
  max-width: 520px;
  margin-left: auto;
  margin-right: auto;
}

.hero-actions {
  display: flex;
  gap: 14px;
  justify-content: center;
  flex-wrap: wrap;
}

.hero-dots {
  position: absolute;
  bottom: 40px;
  left: 50%;
  transform: translateX(-50%);
  z-index: 10;
  display: flex;
  gap: 10px;
}

.hero-dot {
  width: 8px; height: 8px;
  border-radius: 50%;
  border: none;
  background: rgba(255,255,255,0.35);
  cursor: pointer;
  transition: all 0.3s;
  padding: 0;
}

.hero-dot.active {
  background: var(--accent);
  width: 24px;
  border-radius: 4px;
}

.hero-scroll-arrow {
  position: absolute;
  bottom: 36px;
  right: 5%;
  z-index: 10;
  color: rgba(255,255,255,0.6);
  text-decoration: none;
  animation: bounce 2.5s infinite;
}

@keyframes bounce {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(8px); }
}

.btn-primary {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: var(--accent);
  color: var(--text-light);
  padding: 13px 26px;
  border-radius: 10px;
  font-weight: 700;
  font-size: 0.9rem;
  text-decoration: none;
  transition: all 0.2s;
}

.btn-primary:hover { background: var(--accent-light); transform: translateY(-2px); }

.btn-secondary {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: transparent;
  color: rgba(247,243,238,0.85);
  border: 1px solid rgba(247,243,238,0.2);
  padding: 13px 26px;
  border-radius: 10px;
  font-weight: 500;
  font-size: 0.9rem;
  text-decoration: none;
  transition: all 0.2s;
}

.btn-secondary:hover { border-color: rgba(247,243,238,0.5); }

/* FAIXA DE STATS (abaixo do hero) */
.stats-bar {
  background: var(--bg-dark);
  padding: 40px 5%;
  border-top: 1px solid rgba(201,168,76,0.15);
  border-bottom: 1px solid rgba(201,168,76,0.15);
  overflow: hidden;
}

.stats-track {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
}

.stat-item {
  text-align: center;
  flex: 1;
  max-width: 240px;
  padding: 0 32px;
  flex-shrink: 0;
}

.stat-item + .stat-item {
  border-left: 1px solid rgba(201,168,76,0.2);
}

.stat-item--clone {
  display: none;
}

@keyframes statsMarquee {
  0%   { transform: translateX(0); }
  100% { transform: translateX(-50%); }
}

.stat-value {
  font-family: var(--font-display);
  font-weight: 800;
  font-size: 2.4rem;
  color: var(--accent);
  letter-spacing: -1.5px;
  line-height: 1;
  margin-bottom: 8px;
}

.stat-label {
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 2px;
  color: var(--text-muted);
  font-weight: 500;
}

/* SEÇÕES */
.section { padding: 80px 5%; }

.section-label {
  display: block;
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 2px;
  color: var(--accent-dark);
  margin-bottom: 12px;
}

.section-title {
  font-family: var(--font-display);
  font-weight: 800;
  font-size: clamp(1.8rem, 3.5vw, 2.8rem);
  color: var(--text-primary);
  letter-spacing: -1px;
  line-height: 1.1;
  margin-bottom: 12px;
}

.section-title span { color: var(--accent-dark); }

.section-sub {
  font-size: 0.95rem;
  color: var(--text-secondary);
  line-height: 1.75;
  max-width: 480px;
  margin-bottom: 44px;
}

#imoveis { background: var(--bg-page); }
#diferenciais { background: var(--bg-section); }

/* TIPOS */
.types-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
  gap: 14px;
}

.type-card {
  background: var(--bg-section);
  border: 1px solid var(--border-light);
  border-radius: 14px;
  padding: 30px 24px;
  transition: all 0.25s;
  position: relative;
  overflow: hidden;
}

.type-card::after {
  content: '';
  position: absolute;
  bottom: 0; left: 0; right: 0;
  height: 3px;
  background: var(--accent);
  transform: scaleX(0);
  transform-origin: left;
  transition: transform 0.3s;
}

.type-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 16px 40px rgba(26,7,7,0.1);
  border-color: var(--border-accent);
}

.type-card:hover::after { transform: scaleX(1); }

.type-icon {
  width: 46px; height: 46px;
  background: rgba(193,51,41,0.1);
  border-radius: 11px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 18px;
}

.type-icon svg { stroke: var(--accent-dark); }

.type-title {
  font-weight: 700;
  font-size: 0.95rem;
  color: var(--text-primary);
  margin-bottom: 8px;
}

.type-desc {
  font-size: 0.8rem;
  color: var(--text-secondary);
  line-height: 1.7;
}

/* LOTES */
.lotes-section {
  background: var(--bg-dark);
  padding: 80px 5%;
}

.lotes-section .section-label { color: rgba(201,168,76,0.85); }
.lotes-section .section-title { color: var(--text-light); }
.lotes-section .section-title span { color: var(--accent); }
.lotes-section .section-sub { color: var(--text-muted); }

.lotes-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 18px;
}

.lote-card {
  display: flex;
  flex-direction: column;
  background: rgba(255,255,255,0.04);
  border: 1px solid rgba(201,168,76,0.15);
  border-radius: 16px;
  overflow: hidden;
  transition: all 0.25s;
}

.lote-card:hover {
  transform: translateY(-4px);
  border-color: rgba(201,168,76,0.4);
}

.lote-thumb {
  height: 200px;
  position: relative;
  overflow: hidden;
  background-size: cover;
  background-position: center;
  background-repeat: no-repeat;
  background-color: var(--bg-dark);
}

.lote-thumb::after {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(
    to bottom,
    rgba(26,7,7,0.15) 0%,
    rgba(26,7,7,0.5) 100%
  );
}

.lote-thumb-icon { display: none; }

.lote-card .lote-thumb {
  transition: background-size 0.6s ease;
}

.lote-card:hover .lote-thumb {
  background-size: 110%;
}

.lote-badge-destaque {
  position: absolute;
  top: 12px;
  left: 12px;
  z-index: 2;
  background: var(--accent);
  color: var(--text-light);
  font-size: 0.65rem;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 6px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.lote-body {
  display: flex;
  flex-direction: column;
  flex: 1;
  padding: 20px;
  background: rgba(255,255,255,0.03);
}

.lote-tag {
  align-self: flex-start;
  width: fit-content;
  max-width: 100%;
  background: rgba(201,168,76,0.15);
  color: #EDD882;
  font-size: 0.65rem;
  font-weight: 600;
  padding: 3px 10px;
  border-radius: 6px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 10px;
}

.lote-title { color: var(--text-light); font-weight: 700; font-size: 0.98rem; margin-bottom: 6px; }
.lote-info  { color: var(--text-muted); font-size: 0.78rem; line-height: 1.6; margin-bottom: 16px; }

.lote-price {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 4px;
  margin-bottom: 12px;
}

.lote-price-label {
  font-size: 0.65rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  color: rgba(237, 216, 130, 0.75);
  line-height: 1;
}

.lote-price-main {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 2rem;
}

.lote-price-value {
  font-family: var(--font-display);
  color: #EDD882;
  font-weight: 800;
  font-size: 1.5rem;
  letter-spacing: -0.5px;
  line-height: 1;
  white-space: nowrap;
}

.lote-price-badge {
  display: inline-flex;
  align-items: center;
  padding: 5px 9px;
  border-radius: 100px;
  background: linear-gradient(135deg, #3d8a5a 0%, #2d6a45 100%);
  color: #f0faf4;
  font-size: 0.65rem;
  font-weight: 800;
  letter-spacing: 0.35px;
  text-transform: uppercase;
  line-height: 1;
  flex-shrink: 0;
}

.lote-price-was {
  font-size: 0.82rem;
  color: rgba(250, 245, 238, 0.42);
  line-height: 1.3;
}

.lote-price-was s {
  text-decoration: line-through;
  text-decoration-thickness: 1px;
}

.lote-price-hint {
  font-size: 0.75rem;
  color: rgba(250, 245, 238, 0.38);
  line-height: 1.45;
  margin: 0 0 14px;
}

.lote-conditions-list {
  list-style: none;
  margin: 0 0 16px;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.lote-conditions-list li {
  position: relative;
  padding-left: 18px;
  font-size: 0.78rem;
  line-height: 1.45;
  color: rgba(250, 245, 238, 0.72);
}

.lote-conditions-list li::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0.45em;
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--accent-dark);
}

.lote-cta {
  display: block;
  text-align: center;
  background: rgba(201,168,76,0.1);
  border: 1px solid rgba(201,168,76,0.25);
  color: #EDD882;
  padding: 10px;
  border-radius: 10px;
  font-size: 0.82rem;
  font-weight: 600;
  text-decoration: none;
  transition: all 0.2s;
}

.lote-cta:hover { background: var(--accent); color: var(--text-light); }

.lote-actions {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: auto;
}

.lote-simular {
  display: block;
  width: 100%;
  text-align: center;
  background: var(--accent);
  border: none;
  color: var(--text-light);
  padding: 10px;
  border-radius: 10px;
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
  font-family: inherit;
  text-decoration: none;
  box-sizing: border-box;
}

.lote-simular:hover {
  background: var(--accent-light);
  transform: translateY(-1px);
}

/* LOCALIZAÇÃO */
.localizacao-section {
  background: var(--bg-section);
  padding: 80px 5%;
}

.localizacao-grid {
  display: grid;
  grid-template-columns: 1fr 1.4fr;
  gap: 32px;
  align-items: start;
  margin-top: 8px;
}

.localizacao-info h3 {
  font-weight: 700;
  font-size: 1.15rem;
  color: var(--text-primary);
  margin-bottom: 12px;
}

.localizacao-info p {
  font-size: 0.92rem;
  color: var(--text-secondary);
  line-height: 1.75;
  margin-bottom: 20px;
}

.localizacao-list {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.localizacao-list li {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  font-size: 0.88rem;
  color: var(--text-primary);
}

.localizacao-list svg {
  flex-shrink: 0;
  margin-top: 2px;
  stroke: var(--accent-dark);
}

.localizacao-map-wrap {
  border-radius: 16px;
  overflow: hidden;
  border: 1px solid var(--border-light);
  box-shadow: 0 8px 32px rgba(26,7,7,0.08);
  min-height: 380px;
  background: var(--bg-section);
}

.localizacao-map-wrap iframe {
  display: block;
  width: 100%;
  height: 420px;
  border: 0;
}

.localizacao-link {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  margin-top: 20px;
  color: var(--accent-dark);
  font-weight: 600;
  font-size: 0.88rem;
  text-decoration: none;
}

.localizacao-link:hover { color: var(--accent); }

/* SIMULADOR */
.simulador-section {
  background: var(--bg-page);
  padding: 80px 5%;
  text-align: center;
}

.simulador-header {
  max-width: 560px;
  margin: 0 auto 40px;
}

.simulador-title {
  font-weight: 700;
  font-size: clamp(1.6rem, 3vw, 2.2rem);
  color: var(--text-primary);
  margin-bottom: 12px;
  letter-spacing: -0.5px;
}

.simulador-sub {
  font-size: 0.95rem;
  color: var(--text-secondary);
  line-height: 1.7;
  margin-bottom: 16px;
}

.simulador-divider {
  width: 48px;
  height: 4px;
  background: var(--accent);
  border-radius: 2px;
  margin: 0 auto;
}

.simulador-card {
  max-width: 720px;
  margin: 0 auto;
  background: #FFFFFF;
  border: 1px solid var(--border-light);
  border-radius: 20px;
  padding: 36px 32px;
  text-align: left;
  box-shadow: 0 12px 40px rgba(26,7,7,0.07);
  overflow: visible;
}

.sim-field {
  margin-bottom: 20px;
  overflow: visible;
  position: relative;
}

.sim-label {
  display: block;
  font-weight: 600;
  font-size: 0.88rem;
  color: var(--text-primary);
  margin-bottom: 8px;
}

.sim-input {
  display: block;
  width: 100%;
  padding: 12px 14px;
  border: 1px solid var(--border-light);
  border-radius: 10px;
  font-size: 0.9rem;
  font-family: inherit;
  color: var(--text-primary);
  background-color: var(--bg-section);
  transition: border-color 0.2s;
}

.sim-input:focus {
  outline: none;
  border-color: var(--accent);
}

.sim-input-readonly {
  color: var(--text-secondary);
  cursor: default;
  user-select: none;
}

.sim-input-readonly:focus {
  border-color: var(--border-light);
}

/* TomSelect — oculta o <select> nativo após inicializar */
.sim-field select.sim-select.tomselected,
.sim-field:has(.ts-wrapper) > select.sim-select {
  display: none !important;
  visibility: hidden !important;
  position: absolute !important;
  width: 0 !important;
  height: 0 !important;
  margin: 0 !important;
  padding: 0 !important;
  border: 0 !important;
  opacity: 0 !important;
  pointer-events: none !important;
}

/* TomSelect — wrapper invisível; visual só no .ts-control (igual .sim-input) */
.sim-field .ts-wrapper {
  width: 100%;
  border: none;
  box-shadow: none;
  background: transparent;
  padding: 0;
  position: relative;
}

.sim-field .ts-wrapper.single .ts-control {
  display: flex;
  align-items: center;
  width: 100%;
  min-height: 46px;
  padding: 10px 40px 10px 14px;
  border: 1px solid var(--border-light);
  border-radius: 10px;
  font-size: 0.9rem;
  font-family: inherit;
  color: var(--text-primary);
  background: var(--bg-section);
  box-shadow: none;
  transition: border-color 0.2s;
  cursor: pointer;
}

.sim-field .ts-wrapper.single.focus .ts-control,
.sim-field .ts-wrapper.single.dropdown-active .ts-control {
  border-color: var(--accent);
  box-shadow: none;
  outline: none;
}

.sim-field .ts-wrapper.single .ts-control .item {
  padding: 0;
  margin: 0;
  font-size: 0.9rem;
  color: var(--text-primary);
}

.sim-field .ts-wrapper.single .ts-control > input {
  display: none !important;
}

.sim-field .ts-wrapper.single .ts-control::after {
  border-color: #7A4535 transparent transparent;
  right: 14px;
  margin-top: -2px;
}

/* Dropdown flutuante (renderizado no body via dropdownParent) */
.ts-dropdown {
  position: absolute !important;
  z-index: 50 !important;
  border: 1px solid var(--border-light);
  border-radius: 10px;
  box-shadow: 0 10px 28px rgba(26,7,6,0.15);
  background: #fff;
  margin: 0 !important;
}

.ts-dropdown .option {
  padding: 11px 14px;
  font-size: 0.88rem;
  color: var(--text-primary);
}

.ts-dropdown .option:hover,
.ts-dropdown .option.active {
  background: var(--accent);
  color: var(--text-light);
}

.sim-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.sim-btn-calc {
  width: 100%;
  margin-top: 8px;
  padding: 14px;
  background: var(--accent);
  color: var(--text-light);
  border: none;
  border-radius: 10px;
  font-weight: 700;
  font-size: 0.95rem;
  cursor: pointer;
  font-family: inherit;
  transition: background 0.2s;
}

.sim-btn-calc:hover { background: var(--accent-light); }

.sim-simulate-wrap.is-hidden {
  display: none;
}

.sim-avista-wrap {
  display: none;
  margin-top: 4px;
}

.sim-avista-wrap.is-visible {
  display: block;
}

.sim-avista-offer {
  padding: 24px 20px;
  background: var(--bg-section);
  border: 1px solid var(--border-light);
  border-radius: 14px;
  text-align: center;
  margin-bottom: 20px;
}

.sim-avista-lot {
  font-size: 0.78rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1px;
  color: var(--accent-dark);
  margin-bottom: 12px;
}

.sim-avista-de {
  font-size: 0.9rem;
  color: var(--text-secondary);
  margin-bottom: 8px;
}

.sim-avista-de s {
  text-decoration: line-through;
  text-decoration-thickness: 1px;
}

.sim-avista-label {
  display: block;
  font-size: 0.65rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  color: var(--text-secondary);
  margin-bottom: 6px;
}

.sim-avista-main {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  margin-bottom: 8px;
}

.sim-avista-por {
  font-family: var(--font-display);
  font-weight: 800;
  font-size: clamp(1.6rem, 5vw, 2.1rem);
  color: var(--accent-dark);
  letter-spacing: -0.5px;
  line-height: 1;
  white-space: nowrap;
}

.sim-avista-badge {
  display: inline-flex;
  align-items: center;
  padding: 5px 10px;
  border-radius: 100px;
  background: linear-gradient(135deg, #3d8a5a 0%, #2d6a45 100%);
  color: #f0faf4;
  font-size: 0.68rem;
  font-weight: 800;
  letter-spacing: 0.35px;
  text-transform: uppercase;
  line-height: 1;
  flex-shrink: 0;
}

.sim-avista-economia {
  font-size: 0.85rem;
  font-weight: 600;
  color: #2d6a45;
}

.sim-btn-contact {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  padding: 14px 20px;
  background: var(--accent);
  color: var(--text-light);
  border: none;
  border-radius: 10px;
  font-weight: 700;
  font-size: 0.95rem;
  text-decoration: none;
  font-family: inherit;
  transition: background 0.2s;
  cursor: pointer;
}

.sim-btn-contact:hover {
  background: var(--accent-light);
  color: var(--text-light);
}

.sim-result {
  margin-top: 28px;
  padding: 24px;
  background: var(--bg-section);
  border-radius: 14px;
  border: 1px solid var(--border-light);
  display: none;
  scroll-margin-top: 88px;
}

.sim-result.visible { display: block; }

.sim-result-title {
  font-weight: 700;
  font-size: 0.85rem;
  text-transform: uppercase;
  letter-spacing: 1px;
  color: var(--accent-dark);
  margin-bottom: 16px;
}

.sim-result-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 16px;
}

.sim-result-item span {
  display: block;
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--text-secondary);
  margin-bottom: 4px;
}

.sim-result-item strong {
  font-size: 1.25rem;
  color: var(--text-primary);
  letter-spacing: -0.5px;
}

.sim-result-note {
  margin-top: 14px;
  font-size: 0.78rem;
  color: var(--text-secondary);
  line-height: 1.6;
}

.sim-result-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 18px;
  align-items: stretch;
}

.sim-btn-lotes {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 1 1 180px;
  padding: 10px 18px;
  background: transparent;
  color: var(--accent-dark);
  border: 1px solid var(--accent-dark);
  border-radius: 10px;
  font-weight: 600;
  font-size: 0.85rem;
  font-family: inherit;
  cursor: pointer;
  transition: background 0.2s, color 0.2s;
}

.sim-btn-lotes:hover {
  background: var(--accent-dark);
  color: var(--text-light);
}

.sim-wa {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  flex: 1 1 220px;
  margin-top: 0;
  padding: 10px 18px;
  background: #25d366;
  color: white;
  border-radius: 10px;
  font-weight: 600;
  font-size: 0.85rem;
  text-decoration: none;
}

.sim-wa:hover { background: #1db954; }

/* Modal — mapa de lotes */
.lots-map-modal {
  position: fixed;
  inset: 0;
  z-index: 200;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  opacity: 0;
  visibility: hidden;
  transition: opacity 0.25s, visibility 0.25s;
}

.lots-map-modal.is-open {
  opacity: 1;
  visibility: visible;
}

.lots-map-modal-backdrop {
  position: absolute;
  inset: 0;
  background: rgba(28, 10, 6, 0.72);
  cursor: pointer;
}

.lots-map-modal-dialog {
  position: relative;
  z-index: 1;
  width: min(920px, 100%);
  max-height: calc(100vh - 40px);
  display: flex;
  flex-direction: column;
  background: var(--bg-page);
  border-radius: 16px;
  border: 1px solid var(--border-light);
  box-shadow: 0 24px 64px rgba(28, 10, 6, 0.35);
  overflow: hidden;
}

.lots-map-modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 16px 20px;
  border-bottom: 1px solid var(--border-light);
}

.lots-map-modal-title {
  font-family: var(--font-display);
  font-weight: 700;
  font-size: 1.1rem;
  color: var(--text-primary);
  margin: 0;
}

.lots-map-modal-close {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border: none;
  border-radius: 8px;
  background: var(--bg-section);
  color: var(--text-primary);
  font-size: 1.4rem;
  line-height: 1;
  cursor: pointer;
  transition: background 0.2s;
}

.lots-map-modal-close:hover {
  background: var(--border-light);
}

.lots-map-canvas {
  width: 100%;
  height: min(52vh, 480px);
  min-height: 280px;
  background: #e8e4d8;
}

.lots-map-legend {
  display: flex;
  flex-wrap: wrap;
  gap: 14px 20px;
  padding: 12px 20px 16px;
  font-size: 0.75rem;
  color: var(--text-secondary);
}

.lots-map-legend-item {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.lots-map-legend-swatch {
  width: 12px;
  height: 12px;
  border-radius: 3px;
  flex-shrink: 0;
}

.lots-map-legend-swatch--comercial { background: #C23028; }
.lots-map-legend-swatch--residencial { background: #3d8a5a; }

body.lots-map-modal-open {
  overflow: hidden;
}

.sim-radio-group {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.sim-radio-item {
  cursor: pointer;
}

.sim-radio-item input[type="radio"] {
  display: none;
}

.sim-radio-item span {
  display: inline-block;
  padding: 9px 20px;
  border: 1px solid var(--border-light);
  border-radius: 8px;
  font-size: 0.88rem;
  font-weight: 600;
  color: var(--text-secondary);
  background: var(--bg-section);
  transition: all 0.18s;
  cursor: pointer;
  user-select: none;
}

.sim-radio-item input[type="radio"]:checked + span {
  background: var(--accent);
  color: var(--text-light);
  border-color: var(--accent);
}

.sim-radio-item:hover span {
  border-color: var(--accent);
  color: var(--text-primary);
}

.sim-radio-group--mode .sim-radio-item {
  flex: 1;
  min-width: 0;
}

.sim-radio-group--mode .sim-radio-item span {
  display: block;
  text-align: center;
  padding: 11px 12px;
  font-size: 0.82rem;
  line-height: 1.35;
}

.sim-hint {
  font-size: 0.72rem;
  color: var(--text-secondary);
  margin-top: 5px;
  line-height: 1.4;
}

.sim-panel { display: none; }
.sim-panel.active { display: block; }

/* DIFERENCIAIS */
.diferenciais-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
  gap: 16px;
}

.diferencial-card {
  background: var(--bg-page);
  border: 1px solid var(--border-light);
  border-radius: 16px;
  padding: 28px;
  transition: all 0.2s;
}

.diferencial-card:hover {
  border-color: var(--border-accent);
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(26,7,7,0.08);
}

.diferencial-icon {
  width: 46px; height: 46px;
  background: rgba(193,51,41,0.08);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 16px;
}

.diferencial-icon svg { stroke: var(--accent-dark); }

.diferencial-title {
  font-family: var(--font-display);
  font-weight: 700;
  font-size: 0.95rem;
  color: var(--text-primary);
  margin-bottom: 8px;
}

.diferencial-desc {
  font-size: 0.82rem;
  color: var(--text-secondary);
  line-height: 1.7;
}

/* CONTATO */
.contato-section {
  background: var(--bg-dark);
  padding: 100px 5%;
  text-align: center;
  position: relative;
  overflow: hidden;
}

.contato-section::before {
  content: '';
  position: absolute;
  inset: 0;
  background-image:
    linear-gradient(rgba(201,168,76,0.06) 1px, transparent 1px),
    linear-gradient(90deg, rgba(201,168,76,0.06) 1px, transparent 1px);
  background-size: 48px 48px;
  pointer-events: none;
}

.contato-content { position: relative; z-index: 2; }

.contato-title {
  font-family: var(--font-display);
  font-weight: 800;
  font-size: clamp(2rem, 4vw, 3.2rem);
  color: var(--text-light);
  letter-spacing: -1px;
  margin-bottom: 16px;
  line-height: 1.1;
}

.contato-title span { color: var(--accent); }

.contato-sub {
  font-size: 0.95rem;
  color: var(--text-muted);
  margin-bottom: 36px;
  max-width: 420px;
  margin-left: auto;
  margin-right: auto;
  line-height: 1.75;
}

.contato-actions {
  display: flex;
  gap: 12px;
  justify-content: center;
  flex-wrap: wrap;
}

.btn-whatsapp {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  background: #25d366;
  color: white;
  padding: 14px 28px;
  border-radius: 12px;
  font-weight: 700;
  text-decoration: none;
  transition: all 0.2s;
  white-space: nowrap;
}

.btn-whatsapp:hover { background: #1db954; transform: translateY(-2px); }

.btn-whatsapp svg {
  width: 24px;
  height: 24px;
  fill: currentColor;
  flex-shrink: 0;
}

.btn-whatsapp-text {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  line-height: 1.2;
}

.btn-whatsapp-label {
  font-size: 0.95rem;
}

.btn-whatsapp-phone {
  font-size: 0.78rem;
  font-weight: 500;
  opacity: 0.92;
}

/* FOOTER */
footer {
  background: var(--bg-darker);
  padding: 40px 5% 28px;
  border-top: 1px solid rgba(201,168,76,0.12);
}

.footer-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 20px;
  margin-bottom: 24px;
  padding-bottom: 24px;
  border-bottom: 1px solid rgba(255,255,255,0.05);
}

.footer-links {
  display: flex;
  gap: 22px;
  list-style: none;
  flex-wrap: wrap;
}

.footer-links a {
  color: rgba(247,243,238,0.7);
  text-decoration: none;
  font-size: 0.82rem;
  transition: color 0.2s;
}

.footer-links a:hover { color: var(--accent-light); }

.footer-bottom {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
}

.footer-copy {
  font-size: 0.75rem;
  color: rgba(247,243,238,0.5);
}

.footer-poweredby {
  font-size: 0.72rem;
  color: rgba(247,243,238,0.25);
  cursor: pointer;
  background: none;
  border: none;
  padding: 0;
  font-family: inherit;
  transition: color 0.2s;
  text-align: right;
}

.footer-poweredby:hover {
  color: var(--accent);
}

.footer-poweredby-copied {
  color: var(--accent) !important;
}

/* FAB — Simular (mobile) */
.fab-simular {
  display: none;
}

/* MOBILE */
@media (max-width: 900px) {
  .localizacao-grid { grid-template-columns: 1fr; }
  .localizacao-map-wrap iframe { height: 320px; }
}

@media (max-width: 640px) {
  nav { padding: 14px 4%; }
  nav.scrolled { padding: 12px 4%; }

  .nav-logo-img {
    height: 52px;
    max-width: 180px;
  }

  .nav-links { display: none; }

  .nav-mobile-actions { display: flex; gap: 8px; }

  .nav-wa-btn {
    height: 40px;
    padding: 0 11px;
    font-size: 0.75rem;
    gap: 5px;
  }

  .nav-wa-btn svg {
    width: 16px;
    height: 16px;
  }

  .hero-title { letter-spacing: -0.5px; }
  .hero-scroll-arrow { display: none; }
  .stats-bar { padding: 28px 0; }

  .stats-track {
    justify-content: flex-start;
    width: max-content;
    animation: statsMarquee 22s linear infinite;
    will-change: transform;
  }

  .stats-bar:hover .stats-track {
    animation-play-state: paused;
  }

  .stat-item {
    flex: 0 0 auto;
    min-width: 72vw;
    max-width: none;
    padding: 0 28px;
    border-left: none !important;
  }

  .stat-item--clone {
    display: block;
  }
  .sim-grid { grid-template-columns: 1fr; }
  .simulador-card { padding: 24px 20px; }

  .sim-radio-group {
    flex-wrap: nowrap;
    gap: 5px;
  }

  .sim-radio-item {
    flex: 1;
    min-width: 0;
  }

  .sim-radio-item span {
    display: block;
    text-align: center;
    padding: 8px 2px;
    font-size: 0.78rem;
  }

  .contato-section { padding: 72px 5%; }

  .contato-actions {
    display: grid;
    grid-template-columns: 1.35fr 1fr;
    gap: 10px;
    width: 100%;
    max-width: none;
    margin: 0;
    padding: 0;
  }

  .btn-whatsapp {
    grid-column: 1;
    width: 100%;
    padding: 13px 12px;
    gap: 8px;
    min-height: 52px;
  }

  .btn-whatsapp-phone { display: none; }

  .btn-whatsapp-text {
    align-items: flex-start;
  }

  .btn-whatsapp-label { font-size: 0.82rem; }

  .btn-whatsapp svg {
    width: 20px;
    height: 20px;
  }

  .contato-actions .btn-secondary {
    grid-column: 2;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    min-height: 52px;
    padding: 13px 12px;
    font-size: 0.85rem;
    color: var(--text-light);
    border-color: rgba(247,243,238,0.35);
  }

  footer {
    padding: 28px 5% 24px;
  }

  .footer-top {
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 20px;
    margin-bottom: 20px;
    padding-bottom: 20px;
  }

  .footer-logo {
    width: 100%;
    display: flex;
    justify-content: center;
  }

  .footer-logo-img {
    height: 48px;
  }

  .footer-links {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px 16px;
    width: 100%;
    max-width: 300px;
    justify-items: center;
  }

  .footer-links a {
    font-size: 0.8rem;
  }

  .footer-bottom {
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 14px;
  }

  .footer-copy {
    font-size: 0.72rem;
    line-height: 1.5;
  }

  .footer-poweredby {
    text-align: center;
    font-size: 0.7rem;
  }

  body { padding-bottom: 76px; }

  .fab-simular {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    position: fixed;
    bottom: calc(16px + env(safe-area-inset-bottom, 0px));
    right: calc(16px + env(safe-area-inset-right, 0px));
    left: auto;
    z-index: 90;
    padding: 13px 18px 13px 16px;
    background: var(--accent);
    color: var(--text-light);
    font-size: 0.85rem;
    font-weight: 700;
    text-decoration: none;
    border-radius: 100px;
    box-shadow: 0 6px 24px rgba(28,10,6,0.35);
    transition: background 0.2s, transform 0.2s, box-shadow 0.2s, opacity 0.3s, visibility 0.3s;
    white-space: nowrap;
  }

  .fab-simular:hover {
    background: var(--accent-light);
    transform: translateY(-2px);
    box-shadow: 0 8px 28px rgba(28,10,6,0.4);
  }

  .fab-simular svg {
    width: 20px;
    height: 20px;
    stroke: currentColor;
    fill: none;
    flex-shrink: 0;
  }

  .fab-simular.is-hidden {
    opacity: 0;
    visibility: hidden;
    transform: translateY(12px);
    pointer-events: none;
  }
}

/* ANIMAÇÕES HERO */
@keyframes fadeUp {
  from { opacity: 0; transform: translateY(18px); }
  to { opacity: 1; transform: translateY(0); }
}

@media (prefers-reduced-motion: reduce) {
  .stats-track {
    animation: none;
    width: 100%;
    justify-content: center;
  }

  .stat-item--clone { display: none !important; }

  @media (max-width: 640px) {
    .stats-bar {
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
    }

    .stat-item {
      min-width: auto;
      flex: 0 0 70vw;
    }
  }
}

.hero-badge { animation: fadeUp 0.5s ease both; }
.hero-title { animation: fadeUp 0.55s 0.08s ease both; }
.hero-sub { animation: fadeUp 0.55s 0.16s ease both; }
.hero-actions { animation: fadeUp 0.55s 0.24s ease both; }
</style>
</head>
<body>

<!-- NAV -->
<nav>
  <a href="#" class="nav-logo">
    <img
      src="{{ asset('img/logo-full-bg.png') }}"
      alt="Sid360"
      class="nav-logo-img"
    >
  </a>
  <ul class="nav-links">
    <li><a href="#lotes">Loteamento</a></li>
    <li><a href="#localizacao">Localização</a></li>
    <li><a href="#simulador">Simular</a></li>
    <li><a href="#contato" class="nav-cta">Falar com Corretor</a></li>
  </ul>
  <div class="nav-mobile-actions">
    <a
      href="https://wa.me/5574988230151"
      class="nav-wa-btn"
      target="_blank"
      rel="noopener noreferrer"
      aria-label="Chamar no WhatsApp"
    >
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
      <span>Chamar</span>
    </a>
    <button
      type="button"
      class="nav-toggle"
      id="navToggle"
      aria-label="Abrir menu"
      aria-expanded="false"
      aria-controls="navDrawer"
    >
      <span></span>
      <span></span>
      <span></span>
    </button>
  </div>
</nav>
<div class="nav-backdrop" id="navBackdrop" aria-hidden="true"></div>
<aside class="nav-drawer" id="navDrawer" aria-hidden="true">
  <ul class="nav-drawer-links">
    <li><a href="#lotes">Loteamento</a></li>
    <li><a href="#localizacao">Localização</a></li>
    <li><a href="#simulador">Simular</a></li>
    <li><a href="#imoveis">Tipos de imóveis</a></li>
    <li><a href="#contato" class="nav-drawer-cta">Falar com Corretor</a></li>
  </ul>
</aside>

<!-- HERO -->
<section class="hero" id="hero">
  <div class="hero-media">
    <video
      class="hero-video"
      autoplay
      muted
      loop
      playsinline
      poster="{{ asset('img/slide1.jpg') }}"
    >
      <source src="{{ asset('video/loteamento.mp4') }}" type="video/mp4">
    </video>

    <div class="hero-slides" id="heroSlides">
      <div class="hero-slide active" style="background-image: url('{{ asset('img/slide1.jpg') }}')"></div>
      <div class="hero-slide" style="background-image: url('{{ asset('img/slide2.jpg') }}')"></div>
      <div class="hero-slide" style="background-image: url('{{ asset('img/slide3.jpg') }}')"></div>
    </div>

    <div class="hero-overlay"></div>
  </div>

  <div class="hero-content" id="heroContent">
    <div class="hero-badge">
      <span class="hero-badge-dot"></span>
      Cafarnaum · Bahia · Brasil
    </div>
    <h1 class="hero-title">
      O imóvel certo<br>para o seu <span>futuro</span>
    </h1>
    <p class="hero-sub">
      Lotes residenciais e comerciais em Cafarnaum. Uma oportunidade real de investir no seu futuro. Negocie direto com o Sid.
    </p>
    <div class="hero-actions">
      <a href="https://wa.me/5574988230151" class="btn-primary">Falar no WhatsApp</a>
      <a href="#lotes" class="btn-secondary">Ver Loteamento &rarr;</a>
    </div>
  </div>

  <div class="hero-dots" id="heroDots">
    <button type="button" class="hero-dot active" aria-label="Slide 1" onclick="goToSlide(0)"></button>
    <button type="button" class="hero-dot" aria-label="Slide 2" onclick="goToSlide(1)"></button>
    <button type="button" class="hero-dot" aria-label="Slide 3" onclick="goToSlide(2)"></button>
  </div>

  <a href="#lotes" class="hero-scroll-arrow" aria-label="Rolar para loteamento">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
      <line x1="12" y1="5" x2="12" y2="19"/>
      <polyline points="19 12 12 19 5 12"/>
    </svg>
  </a>
</section>

<!-- STATS -->
<div class="stats-bar">
  <div class="stats-track" aria-label="Destaques Sid360">
    <div class="stat-item">
      <div class="stat-value">+10</div>
      <div class="stat-label">Anos de experiência</div>
    </div>
    <div class="stat-item">
      <div class="stat-value">+200</div>
      <div class="stat-label">Negócios realizados</div>
    </div>
    <div class="stat-item">
      <div class="stat-value">100%</div>
      <div class="stat-label">Segurança jurídica</div>
    </div>
    <div class="stat-item stat-item--clone" aria-hidden="true">
      <div class="stat-value">+10</div>
      <div class="stat-label">Anos de experiência</div>
    </div>
    <div class="stat-item stat-item--clone" aria-hidden="true">
      <div class="stat-value">+200</div>
      <div class="stat-label">Negócios realizados</div>
    </div>
    <div class="stat-item stat-item--clone" aria-hidden="true">
      <div class="stat-value">100%</div>
      <div class="stat-label">Segurança jurídica</div>
    </div>
  </div>
</div>

<!-- LOTES DESTAQUE -->
<section class="lotes-section" id="lotes">
  <div class="section-label">Loteamento em destaque</div>
  <h2 class="section-title">Lotes disponíveis <span style="color:var(--accent)">agora</span></h2>
  <p class="section-sub" style="margin-bottom:40px">Ótima oportunidade de adquirir seu lote em Cafarnaum. Parcelamento facilitado ou condição especial no pagamento à vista.</p>

  <div class="lotes-grid">
    <div class="lote-card">
      <div class="lote-thumb" style="background-image: url('{{ asset('img/lote1.jpeg') }}');">
        <div class="lote-badge-destaque">Frente BR</div>
      </div>
      <div class="lote-body">
        <div class="lote-tag">Lote Comercial</div>
        <div class="lote-title">Lote Frente à Rodovia</div>
        <div class="lote-info">Visibilidade máxima · Área privilegiada · Ideal para comércio</div>
        <div class="lote-price">
          <span class="lote-price-label">À vista</span>
          <div class="lote-price-main">
            <span class="lote-price-value">R$ {{ $fmtBrl($lotBr['price_cash']) }}</span>
            @if ($lotBrDiscountPct > 0)
              <span class="lote-price-badge">{{ $lotBrDiscountPct }}% OFF</span>
            @endif
          </div>
          <span class="lote-price-was">De <s>R$ {{ $fmtBrl($lotPriceOriginal($lotBr)) }}</s></span>
        </div>
        <p class="lote-price-hint">Parcelamento: 20% de entrada + parcelas a partir de R$ {{ $fmtBrl($lotBrParcelaFrom) }} (30x)</p>
        <div class="lote-actions">
          <button type="button" class="lote-simular" data-lote="frente-br">Simular Parcelas</button>
          <a href="https://wa.me/5574988230151?text=Olá, tenho interesse no lote frente à BR!" class="lote-cta">Tenho Interesse</a>
        </div>
      </div>
    </div>

    <div class="lote-card">
      <div class="lote-thumb" style="background-image: url('{{ asset('img/lote2.jpeg') }}');">
        <div class="lote-badge-destaque">Lotes Internos</div>
      </div>
      <div class="lote-body">
        <div class="lote-tag">Lote Residencial</div>
        <div class="lote-title">Lote Residencial 20x30</div>
        <div class="lote-info">Ótima localização · Parcelas acessíveis · Ideal para residência</div>
        <div class="lote-price">
          <span class="lote-price-label">À vista</span>
          <div class="lote-price-main">
            <span class="lote-price-value">R$ {{ $fmtBrl($lotRes['price_cash']) }}</span>
            @if ($lotResDiscountPct > 0)
              <span class="lote-price-badge">{{ $lotResDiscountPct }}% OFF</span>
            @endif
          </div>
          <span class="lote-price-was">De <s>R$ {{ $fmtBrl($lotPriceOriginal($lotRes)) }}</s></span>
        </div>
        <p class="lote-price-hint">Parcelamento: 20% de entrada + parcelas a partir de R$ {{ $fmtBrl($lotResParcelaFrom) }} (30x)</p>
        <div class="lote-actions">
          <button type="button" class="lote-simular" data-lote="residencial">Simular Parcelas</button>
          <a href="https://wa.me/5574988230151?text=Olá, tenho interesse em um lote residencial!" class="lote-cta">Tenho Interesse</a>
        </div>
      </div>
    </div>

    <div class="lote-card">
      <div class="lote-thumb" style="background-image: url('{{ asset('img/lote3.jpeg') }}');">
        <div class="lote-badge-destaque">Negociação</div>
      </div>
      <div class="lote-body">
        <div class="lote-tag">Condições flexíveis</div>
        <div class="lote-title">Outras condições</div>
        <div class="lote-info">Monte o pagamento do seu jeito — o Sid analisa a melhor proposta para o seu perfil.</div>
        <ul class="lote-conditions-list">
          <li>Entrada negociável conforme o lote</li>
          <li>Parcelamento personalizado</li>
          <li>Prazo de pagamento ajustado ao seu planejamento</li>
          <li>Condições exclusivas direto com o corretor</li>
        </ul>
        <div class="lote-actions">
          <a
            href="https://wa.me/5574988230151?text={{ rawurlencode('Olá! Gostaria de conversar sobre outras condições de pagamento para um lote no loteamento.') }}"
            class="lote-simular"
            target="_blank"
            rel="noopener noreferrer"
          >Consultar condições</a>
          <a href="https://wa.me/5574988230151?text=Olá! Tenho interesse em negociar condições para um lote." class="lote-cta">Tenho Interesse</a>
        </div>
      </div>
    </div>
  </div>
</section>

@php
  $mapsEmbed = $loteamento['maps_embed_url']
    ?? 'https://maps.google.com/maps?q=' . $loteamento['lat'] . ',' . $loteamento['lng'] . '&hl=pt-BR&z=16&output=embed';
  $mapsLink = 'https://www.google.com/maps/search/?api=1&query=' . $loteamento['lat'] . ',' . $loteamento['lng'];
@endphp

<!-- LOCALIZAÇÃO -->
<section class="localizacao-section" id="localizacao">
  <div class="section-label">Onde fica</div>
  <h2 class="section-title">Localização do <span>loteamento</span></h2>
  <p class="section-sub">Novo empreendimento em Cafarnaum com fácil acesso e infraestrutura em implantação. Veja no mapa e planeje sua visita.</p>

  <div class="localizacao-grid">
    <div class="localizacao-info">
      <h3>{{ $loteamento['name'] }}</h3>
      <p>{{ $loteamento['address'] }}. Região em expansão, ideal para moradia ou investimento com valorização.</p>
      <ul class="localizacao-list">
        <li>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
          Acesso pela região de Cafarnaum — Bahia
        </li>
        <li>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round"><path d="M5 17H3a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11a2 2 0 0 1 2 2v3"/><rect x="9" y="11" width="14" height="10" rx="2"/></svg>
          Lotes frente à rodovia e residenciais disponíveis
        </li>
        <li>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
          Infraestrutura do loteamento em andamento
        </li>
      </ul>
      <a href="{{ $mapsLink }}" target="_blank" rel="noopener noreferrer" class="localizacao-link">
        Abrir no Google Maps &rarr;
      </a>
    </div>
    <div class="localizacao-map-wrap">
      <iframe
        src="{{ $mapsEmbed }}"
        allowfullscreen
        loading="lazy"
        referrerpolicy="no-referrer-when-downgrade"
        title="Mapa — {{ $loteamento['name'] }}"
      ></iframe>
    </div>
  </div>
</section>

<!-- SIMULADOR -->
<section class="simulador-section" id="simulador">
  <div class="simulador-header">
    <h2 class="simulador-title">Simulação de Parcelamento</h2>
    <p class="simulador-sub">Faça uma simulação e descubra as melhores condições de pagamento para o seu lote.</p>
    <div class="simulador-divider"></div>
  </div>

  <div class="simulador-card">
    <div class="sim-field">
      <div class="sim-label" id="simModeLabel">Tipo de simulação</div>
      <div class="sim-radio-group sim-radio-group--mode" id="simModeGroup" role="radiogroup" aria-labelledby="simModeLabel">
        <label class="sim-radio-item">
          <input type="radio" name="simMode" value="price" checked>
          <span>Valor Total</span>
        </label>
        <label class="sim-radio-item">
          <input type="radio" name="simMode" value="parcela">
          <span>Valor da Parcela</span>
        </label>
        <label class="sim-radio-item">
          <input type="radio" name="simMode" value="avista">
          <span>à Vista</span>
        </label>
      </div>
    </div>

    <div id="simLotField" class="sim-field">
      <label class="sim-label" for="simLoteType">Tipo de lote:</label>
      <select id="simLoteType" class="sim-select">
        <option value="frente-br">Lote Frente à Rodovia</option>
        <option value="residencial">Lote Residencial 20×30</option>
      </select>
    </div>

    <div id="simAvistaWrap" class="sim-avista-wrap" hidden>
      <div class="sim-avista-offer">
        <div class="sim-avista-lot" id="simAvistaLotName"></div>
        <span class="sim-avista-label">À vista</span>
        <div class="sim-avista-main">
          <div class="sim-avista-por" id="simAvistaPor"></div>
          <span class="sim-avista-badge" id="simAvistaBadge" hidden></span>
        </div>
        <div class="sim-avista-de" id="simAvistaDe"></div>
        <div class="sim-avista-economia" id="simAvistaEconomia"></div>
      </div>
      <a href="#" class="sim-btn-contact" id="simAvistaContact" target="_blank" rel="noopener noreferrer">Entrar em contato</a>
    </div>

    <div id="simSimulateWrap" class="sim-simulate-wrap">
    <div class="sim-field">
      <label class="sim-label" for="simTotal" id="simTotalLabel">Valor parcelado do lote (R$)</label>
      <input type="text" id="simTotal" class="sim-input sim-input-readonly" readonly tabindex="-1" aria-readonly="true" autocomplete="off" placeholder="R$ 0,00">
    </div>

    <div id="simPanelPrice" class="sim-panel active">
      <div class="sim-grid">
        <div class="sim-field">
          <label class="sim-label" for="simEntradaPrice">Entrada (R$)</label>
          <input type="text" id="simEntradaPrice" class="sim-input sim-money" inputmode="decimal" autocomplete="off" placeholder="R$ 0,00">
          <p class="sim-hint" id="simEntradaPriceHint">Mínimo: 20% do valor total</p>
        </div>
      </div>
      <div class="sim-field">
        <div class="sim-label">Número de parcelas</div>
        <div class="sim-radio-group" id="simParcelasGroup">
          <label class="sim-radio-item">
            <input type="radio" name="simParcelas" value="6"> <span>6x</span>
          </label>
          <label class="sim-radio-item">
            <input type="radio" name="simParcelas" value="12"> <span>12x</span>
          </label>
          <label class="sim-radio-item">
            <input type="radio" name="simParcelas" value="18"> <span>18x</span>
          </label>
          <label class="sim-radio-item">
            <input type="radio" name="simParcelas" value="24" checked> <span>24x</span>
          </label>
          <label class="sim-radio-item">
            <input type="radio" name="simParcelas" value="30"> <span>30x</span>
          </label>
        </div>
      </div>
    </div>

    <div id="simPanelParcela" class="sim-panel">
      <div class="sim-grid">
        <div class="sim-field">
          <label class="sim-label" for="simEntradaParcela">Entrada (R$)</label>
          <input type="text" id="simEntradaParcela" class="sim-input sim-money" inputmode="decimal" autocomplete="off" placeholder="R$ 0,00">
          <p class="sim-hint" id="simEntradaParcelaHint">Mínimo: 20% do valor total</p>
        </div>
        <div class="sim-field">
          <label class="sim-label" for="simParcelaMensal">Parcela mensal desejada (R$)</label>
          <input type="text" id="simParcelaMensal" class="sim-input sim-money" inputmode="decimal" autocomplete="off" placeholder="R$ 0,00">
        </div>
      </div>
    </div>

    <button type="button" class="sim-btn-calc" id="simCalcular">Calcular simulação</button>

    <div class="sim-result" id="simResult">
      <div class="sim-result-title">Resultado da simulação</div>
      <div class="sim-result-grid" id="simResultGrid"></div>
      <p class="sim-result-note" id="simResultNote">Valores estimados. Condições finais confirmadas diretamente com o corretor.</p>
      <div class="sim-result-actions">
        <button type="button" class="sim-btn-lotes" id="simVerLotesBtn">Ver lotes disponíveis</button>
        <a href="#" class="sim-wa" id="simWaLink" target="_blank" rel="noopener">Enviar simulação no WhatsApp</a>
      </div>
    </div>
    </div>
  </div>
</section>

<!-- TIPOS -->
<section class="section" id="imoveis">
  <div class="section-label">O que negociamos</div>
  <h2 class="section-title">Tudo que você precisa,<br><span>num só lugar</span></h2>
  <p class="section-sub">Lotes, casas, terrenos rurais e comerciais. Quem conhece Cafarnaum sabe onde estão as melhores oportunidades.</p>

  <div class="types-grid">
    <div class="type-card">
      <div class="type-icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      </div>
      <div class="type-title">Casas</div>
      <div class="type-desc">Casas à venda em Cafarnaum e região. Consulte disponibilidade.</div>
    </div>
    <div class="type-card">
      <div class="type-icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
      </div>
      <div class="type-title">Comércio</div>
      <div class="type-desc">Pontos comerciais e galpões para seu negócio crescer.</div>
    </div>
    <div class="type-card">
      <div class="type-icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><rect x="9" y="14" width="6" height="7"/></svg>
      </div>
      <div class="type-title">Terreno Residencial</div>
      <div class="type-desc">Lotes para construir do jeito que você quer, na localização que você escolher.</div>
    </div>
    <div class="type-card">
      <div class="type-icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 22V12a10 10 0 0 1 20 0v10"/><path d="M6 22V16a6 6 0 0 1 12 0v6"/></svg>
      </div>
      <div class="type-title">Terreno Rural</div>
      <div class="type-desc">Chácaras, fazendas e propriedades rurais na região.</div>
    </div>
    <div class="type-card">
      <div class="type-icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 17H3a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11a2 2 0 0 1 2 2v3"/><rect x="9" y="11" width="14" height="10" rx="2"/><line x1="12" y1="11" x2="12" y2="21"/><line x1="9" y1="16" x2="23" y2="16"/></svg>
      </div>
      <div class="type-title">Frente de Rodovia</div>
      <div class="type-desc">Lotes com visibilidade privilegiada na BR, ideais para investimento.</div>
    </div>
  </div>
</section>

<!-- DIFERENCIAIS -->
<section class="section" id="diferenciais">
  <div class="section-label">Por que escolher</div>
  <h2 class="section-title">Corretor de <span>confiança</span><br>na sua cidade</h2>
  <p class="section-sub">Quem é de Cafarnaum, sabe o valor de negociar com quem conhece cada rua, cada terreno e cada oportunidade da região.</p>

  <div class="diferenciais-grid">
    <div class="diferencial-card">
      <div class="diferencial-icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
      </div>
      <div class="diferencial-title">Negociação Direta</div>
      <div class="diferencial-desc">Sem intermediários. Você fala direto com o corretor que conhece todos os detalhes do imóvel.</div>
    </div>
    <div class="diferencial-card">
      <div class="diferencial-icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
      </div>
      <div class="diferencial-title">Documentação Segura</div>
      <div class="diferencial-desc">Negócios realizados com transparência e segurança. O Sid te orienta em cada etapa.</div>
    </div>
    <div class="diferencial-card">
      <div class="diferencial-icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
      </div>
      <div class="diferencial-title">Conhecimento Local</div>
      <div class="diferencial-desc">Mais de 10 anos no mercado imobiliário de Cafarnaum e região. Acesso às melhores oportunidades.</div>
    </div>
    <div class="diferencial-card">
      <div class="diferencial-icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      </div>
      <div class="diferencial-title">Suporte Completo</div>
      <div class="diferencial-desc">Do primeiro contato ao fechamento do negócio. Atendimento direto, sem enrolação.</div>
    </div>
  </div>
</section>

<!-- CONTATO -->
<section class="contato-section" id="contato">
  <div class="contato-content">
    <h2 class="contato-title">Pronto para encontrar<br>seu <span>imóvel ideal</span>?</h2>
    <p class="contato-sub">Fale agora mesmo com o Sid. Atendimento rápido, direto e sem complicação.</p>
    <div class="contato-actions">
      <a
        href="https://wa.me/5574988230151"
        class="btn-whatsapp"
        target="_blank"
        rel="noopener noreferrer"
        aria-label="Falar no WhatsApp — (74) 9 8823-0151"
      >
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
        <span class="btn-whatsapp-text">
          <span class="btn-whatsapp-label">Falar no WhatsApp</span>
          <span class="btn-whatsapp-phone">(74) 9 8823-0151</span>
        </span>
      </a>
      <a href="#lotes" class="btn-secondary">Ver Lotes &rarr;</a>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer>
  <div class="footer-top">
    <div class="footer-logo">
      <img
        src="{{ asset('img/logo-full-bg.png') }}"
        alt="Sid360"
        class="footer-logo-img"
      >
    </div>
    <ul class="footer-links">
      <li><a href="#lotes">Loteamento</a></li>
      <li><a href="#localizacao">Localização</a></li>
      <li><a href="#simulador">Simular</a></li>
      <li><a href="#contato">Contato</a></li>
    </ul>
  </div>
  <div class="footer-bottom">
    <div class="footer-copy">&copy; {{ date('Y') }} Sid360 Imóveis · Cafarnaum, Bahia</div>
    <button type="button" class="footer-poweredby" id="poweredByBtn" title="Copiar contato do desenvolvedor">
      Desenvolvido por Nunes, Leonardo
    </button>
  </div>
</footer>

<a href="#simulador" class="fab-simular is-hidden" id="fabSimular" aria-label="Simular parcelas do lote">
  <svg viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <rect x="4" y="2" width="16" height="20" rx="2"/>
    <line x1="8" y1="6" x2="16" y2="6"/>
    <line x1="8" y1="10" x2="8" y2="10.01"/>
    <line x1="12" y1="10" x2="12" y2="10.01"/>
    <line x1="16" y1="10" x2="16" y2="10.01"/>
    <line x1="8" y1="14" x2="8" y2="14.01"/>
    <line x1="12" y1="14" x2="12" y2="14.01"/>
    <line x1="16" y1="14" x2="16" y2="14.01"/>
    <line x1="8" y1="18" x2="12" y2="18"/>
    <line x1="16" y1="18" x2="16" y2="18.01"/>
  </svg>
  <span>Simular parcelas</span>
</a>

<div class="lots-map-modal" id="lotsMapModal" aria-hidden="true" role="dialog" aria-labelledby="lotsMapModalTitle">
  <div class="lots-map-modal-backdrop" data-lots-map-close></div>
  <div class="lots-map-modal-dialog">
    <div class="lots-map-modal-header">
      <h3 class="lots-map-modal-title" id="lotsMapModalTitle">Lotes disponíveis</h3>
      <button type="button" class="lots-map-modal-close" data-lots-map-close aria-label="Fechar mapa">&times;</button>
    </div>
    <div id="lotsMapCanvas" class="lots-map-canvas" role="img" aria-label="Mapa do loteamento com lotes demarcados"></div>
    <div class="lots-map-legend">
      <span class="lots-map-legend-item">
        <span class="lots-map-legend-swatch lots-map-legend-swatch--comercial"></span>
        Frente à Rodovia (comercial)
      </span>
      <span class="lots-map-legend-item">
        <span class="lots-map-legend-swatch lots-map-legend-swatch--residencial"></span>
        Lotes residenciais 20×30
      </span>
      <span class="lots-map-legend-item">Clique no lote para ver detalhes</span>
    </div>
  </div>
</div>

@php
  $lotTypesForJs = collect(config('site.lots'))->mapWithKeys(function ($lot, $key) use ($lotDiscountPct, $lotPriceOriginal, $lotParcelaFrom30x) {
      return [$key => [
          'name' => $lot['name'],
          'totalOriginal' => $lotPriceOriginal($lot),
          'totalPrazo' => $lot['price_installment'],
          'totalAvista' => $lot['price_cash'],
          'entradaMin' => $lot['down_payment_min'],
          'parcelaRef' => $lot['installment_ref'],
          'parcelaFrom30x' => $lotParcelaFrom30x($lot),
          'discountPct' => $lotDiscountPct($lot),
      ]];
  })->all();
@endphp
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/js/tom-select.complete.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function() {
  const LOT_TYPES = @json($lotTypesForJs);
  const LOTEAMENTO_CENTER = [{{ $loteamento['lat'] }}, {{ $loteamento['lng'] }}];
  const slides = document.querySelectorAll('.hero-slide');
  const dots   = document.querySelectorAll('.hero-dot');
  let current  = 0;
  let timer;

  function goToSlide(n) {
    slides[current].classList.remove('active');
    dots[current].classList.remove('active');
    current = (n + slides.length) % slides.length;
    slides[current].classList.add('active');
    dots[current].classList.add('active');
    resetTimer();
  }

  function resetTimer() {
    clearInterval(timer);
    timer = setInterval(function() { goToSlide(current + 1); }, 5000);
  }

  window.goToSlide = goToSlide;
  resetTimer();

  const heroContent = document.getElementById('heroContent');
  const heroSlide   = function() { return document.querySelector('.hero-slide.active'); };

  window.addEventListener('scroll', function() {
    const scrollY = window.scrollY;
    const maxScroll = window.innerHeight;

    if (scrollY > maxScroll) return;

    if (heroContent) {
      heroContent.style.transform = 'translateY(' + (scrollY * 0.35) + 'px)';
      heroContent.style.opacity   = String(1 - (scrollY / (maxScroll * 0.7)));
    }

    const slide = heroSlide();
    if (slide) {
      slide.style.transform = 'translateY(' + (scrollY * 0.2) + 'px)';
    }
  }, { passive: true });

  const nav = document.querySelector('nav');
  window.addEventListener('scroll', function() {
    nav.classList.toggle('scrolled', window.scrollY > 60);
  }, { passive: true });

  const navToggle   = document.getElementById('navToggle');
  const navDrawer   = document.getElementById('navDrawer');
  const navBackdrop = document.getElementById('navBackdrop');

  function setNavMenuOpen(open) {
    if (!navToggle || !navDrawer || !navBackdrop) return;
    navToggle.classList.toggle('is-open', open);
    navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    navToggle.setAttribute('aria-label', open ? 'Fechar menu' : 'Abrir menu');
    navDrawer.classList.toggle('is-open', open);
    navDrawer.setAttribute('aria-hidden', open ? 'false' : 'true');
    navBackdrop.classList.toggle('is-visible', open);
    navBackdrop.setAttribute('aria-hidden', open ? 'false' : 'true');
    document.body.classList.toggle('nav-menu-open', open);
  }

  if (navToggle) {
    navToggle.addEventListener('click', function() {
      setNavMenuOpen(!navToggle.classList.contains('is-open'));
    });
  }

  if (navBackdrop) {
    navBackdrop.addEventListener('click', function() { setNavMenuOpen(false); });
  }

  if (navDrawer) {
    navDrawer.querySelectorAll('a').forEach(function(link) {
      link.addEventListener('click', function() { setNavMenuOpen(false); });
    });
  }

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') setNavMenuOpen(false);
  });

  // === MÁSCARA MONEY ===
  function applyMoneyMask(input) {
    input.addEventListener('input', function () {
      var digits = this.value.replace(/\D/g, '');
      if (!digits) { this.value = ''; return; }
      var cents = parseInt(digits, 10);
      var reais = (cents / 100).toFixed(2);
      this.value = 'R$ ' + reais
        .replace('.', ',')
        .replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    });
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Backspace') {
        var digits = this.value.replace(/\D/g, '');
        digits = digits.slice(0, -1);
        if (!digits) { this.value = ''; return; }
        var cents = parseInt(digits || '0', 10);
        var reais = (cents / 100).toFixed(2);
        this.value = 'R$ ' + reais
          .replace('.', ',')
          .replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        e.preventDefault();
      }
    });
    function selectAllMoney(el) {
      requestAnimationFrame(function () {
        el.setSelectionRange(0, el.value.length);
      });
    }

    input.addEventListener('mousedown', function (e) {
      if (e.button !== 0) return;
      var el = this;
      if (document.activeElement !== el) {
        e.preventDefault();
        el.focus();
        selectAllMoney(el);
      }
    });

    input.addEventListener('focus', function () {
      selectAllMoney(this);
    });

    input.addEventListener('click', function () {
      if (document.activeElement === this) {
        selectAllMoney(this);
      }
    });

    input.addEventListener('contextmenu', function (e) {
      e.preventDefault();
    });
  }

  function moneyToNum(input) {
    var raw = input.value.replace(/\D/g, '');
    return raw ? parseInt(raw, 10) / 100 : 0;
  }

  function setMoneyValue(input, num) {
    if (!num) { input.value = ''; return; }
    var reais = num.toFixed(2);
    input.value = 'R$ ' + reais
      .replace('.', ',')
      .replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  }

  document.querySelectorAll('.sim-money').forEach(applyMoneyMask);

  // === SIMULADOR DE PARCELAS ===
  function getLotTotal(lot, mode) {
    if (!lot) return 0;
    return mode === 'avista' ? lot.totalAvista : lot.totalPrazo;
  }

  function setSimModeValue(mode) {
    if (!simModeGroup) return;
    const radio = simModeGroup.querySelector('input[name="simMode"][value="' + mode + '"]');
    if (radio) radio.checked = true;
  }

  const simModeGroup = document.getElementById('simModeGroup');
  const simLoteType = document.getElementById('simLoteType');
  let simLoteTypeTom = null;

  const tomSelectOpts = {
    allowEmptyOption: false,
    closeAfterSelect: true,
    controlInput: null,
    dropdownParent: 'body',
  };

  if (typeof TomSelect !== 'undefined' && simLoteType) {
    simLoteTypeTom = new TomSelect(simLoteType, tomSelectOpts);
  }

  function getSimModeValue() {
    if (!simModeGroup) return 'price';
    const checked = simModeGroup.querySelector('input[name="simMode"]:checked');
    return checked ? checked.value : 'price';
  }

  function getSimLoteTypeValue() {
    return simLoteTypeTom ? simLoteTypeTom.getValue() : simLoteType.value;
  }

  function setSimLoteTypeValue(key) {
    if (simLoteTypeTom) {
      simLoteTypeTom.setValue(key, true);
    } else {
      simLoteType.value = key;
    }
  }

  const simSimulateWrap = document.getElementById('simSimulateWrap');
  const simAvistaWrap = document.getElementById('simAvistaWrap');
  const simAvistaLotName = document.getElementById('simAvistaLotName');
  const simAvistaDe = document.getElementById('simAvistaDe');
  const simAvistaPor = document.getElementById('simAvistaPor');
  const simAvistaEconomia = document.getElementById('simAvistaEconomia');
  const simAvistaBadge = document.getElementById('simAvistaBadge');
  const simAvistaContact = document.getElementById('simAvistaContact');
  const simPanelPrice = document.getElementById('simPanelPrice');
  const simPanelParcela = document.getElementById('simPanelParcela');
  const simTotal = document.getElementById('simTotal');
  const simEntradaPrice = document.getElementById('simEntradaPrice');
  const simParcelasGroup = document.getElementById('simParcelasGroup');
  function getSimParcelas() {
    const checked = simParcelasGroup.querySelector('input[type="radio"]:checked');
    return checked ? Number(checked.value) : 24;
  }
  function setSimParcelas(val) {
    const opt = simParcelasGroup.querySelector('input[value="' + val + '"]');
    if (opt) { opt.checked = true; return; }
    // se o valor não está nas opções, seleciona a mais próxima
    const opts = Array.from(simParcelasGroup.querySelectorAll('input[type="radio"]'));
    let closest = opts[0];
    opts.forEach(function(o) {
      if (Math.abs(Number(o.value) - val) < Math.abs(Number(closest.value) - val)) closest = o;
    });
    closest.checked = true;
  }
  const simEntradaParcela = document.getElementById('simEntradaParcela');
  const simParcelaMensal = document.getElementById('simParcelaMensal');
  const simResult = document.getElementById('simResult');
  const simResultGrid = document.getElementById('simResultGrid');
  const simResultNote = document.getElementById('simResultNote');
  const simWaLink = document.getElementById('simWaLink');

  function formatBRL(n) {
    return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL', maximumFractionDigits: 0 });
  }

  function renderAvistaOffer() {
    const lot = LOT_TYPES[getSimLoteTypeValue()];
    if (!lot || !simAvistaWrap) return;
    const pct = lot.discountPct || 0;
    if (simAvistaLotName) simAvistaLotName.textContent = lot.name;
    if (simAvistaBadge) {
      if (pct > 0) {
        simAvistaBadge.textContent = pct + '% OFF';
        simAvistaBadge.hidden = false;
      } else {
        simAvistaBadge.hidden = true;
      }
    }
    if (simAvistaPor) simAvistaPor.innerHTML = formatBRL(lot.totalAvista);
    if (simAvistaDe) simAvistaDe.innerHTML = 'De <s>' + formatBRL(lot.totalOriginal) + '</s>';
    if (simAvistaEconomia) {
      const parcelaMin = lot.parcelaFrom30x || 0;
      let msg = 'Economia de ' + formatBRL(lot.totalOriginal - lot.totalAvista) + ' em relação ao valor de tabela';
      if (lot.totalOriginal !== lot.totalPrazo) {
        msg += ' · Parcelado: ' + formatBRL(lot.totalPrazo);
      }
      msg += ' · Parcelas a partir de ' + formatBRL(parcelaMin) + ' (30x)';
      simAvistaEconomia.textContent = msg;
    }
    if (simAvistaContact) {
      const wa = 'Olá! Tenho interesse no ' + lot.name + ' à vista por ' + formatBRL(lot.totalAvista)
        + ' (de ' + formatBRL(lot.totalOriginal) + ', parcelado ' + formatBRL(lot.totalPrazo) + '). Gostaria de mais informações.';
      simAvistaContact.href = 'https://wa.me/5574988230151?text=' + encodeURIComponent(wa);
    }
  }

  const ENTRADA_MIN_PCT = 0.2;

  function getMinEntrada(total) {
    return total > 0 ? Math.round(total * ENTRADA_MIN_PCT * 100) / 100 : 0;
  }

  function getActiveTotal() {
    const fromInput = moneyToNum(simTotal);
    if (fromInput > 0) return fromInput;
    const lot = LOT_TYPES[getSimLoteTypeValue()];
    return getLotTotal(lot, getSimModeValue());
  }

  function clampEntrada(input, total) {
    if (!input || total <= 0) return;
    const min = getMinEntrada(total);
    if (moneyToNum(input) < min) {
      setMoneyValue(input, min);
    }
  }

  function validateEntrada(entrada, total) {
    const min = getMinEntrada(total);
    if (entrada < min) {
      alert('A entrada mínima é de 20% do valor total (' + formatBRL(min) + ').');
      return false;
    }
    return true;
  }

  function updateEntradaHints() {
    if (getSimModeValue() === 'avista') return;
    const total = getActiveTotal();
    const min = getMinEntrada(total);
    const hintText = total > 0
      ? 'Mínimo: 20% do valor total (' + formatBRL(min) + ')'
      : 'Mínimo: 20% do valor total';
    const hintPrice = document.getElementById('simEntradaPriceHint');
    const hintParcela = document.getElementById('simEntradaParcelaHint');
    if (hintPrice) hintPrice.textContent = hintText;
    if (hintParcela) hintParcela.textContent = hintText;
  }

  function applyLoteType(key) {
    const lot = LOT_TYPES[key];
    if (!lot) return;
    setSimLoteTypeValue(key);
    const mode = getSimModeValue();
    const total = getLotTotal(lot, mode);
    setMoneyValue(simTotal, total);

    if (mode === 'avista') {
      renderAvistaOffer();
      return;
    }

    const minEntrada = getMinEntrada(lot.totalPrazo);
    const defaultEntrada = Math.max(minEntrada, lot.entradaMin || minEntrada);
    setMoneyValue(simEntradaPrice, defaultEntrada);
    setMoneyValue(simEntradaParcela, defaultEntrada);
    setMoneyValue(simParcelaMensal, lot.parcelaRef);
    const restante = lot.totalPrazo - defaultEntrada;
    const parcelas = Math.max(1, Math.round(restante / lot.parcelaRef));
    setSimParcelas(parcelas);
    updateEntradaHints();
  }

  simEntradaPrice.addEventListener('blur', function() {
    clampEntrada(simEntradaPrice, moneyToNum(simTotal));
  });

  simEntradaParcela.addEventListener('blur', function() {
    clampEntrada(simEntradaParcela, getActiveTotal());
  });

  function onSimModeChange() {
    const mode = getSimModeValue();
    const isAvista = mode === 'avista';

    if (simSimulateWrap) simSimulateWrap.classList.toggle('is-hidden', isAvista);
    if (simAvistaWrap) {
      simAvistaWrap.classList.toggle('is-visible', isAvista);
      simAvistaWrap.hidden = !isAvista;
    }

    simPanelPrice.classList.toggle('active', mode === 'price');
    simPanelParcela.classList.toggle('active', mode === 'parcela');

    simResult.classList.remove('visible');

    if (isAvista) {
      renderAvistaOffer();
      return;
    }

    applyLoteType(getSimLoteTypeValue());
  }

  function onSimLoteTypeChange() {
    if (getSimModeValue() === 'avista') {
      renderAvistaOffer();
    } else {
      applyLoteType(getSimLoteTypeValue());
    }
    simResult.classList.remove('visible');
  }

  if (simModeGroup) {
    simModeGroup.querySelectorAll('input[name="simMode"]').forEach(function(radio) {
      radio.addEventListener('change', onSimModeChange);
    });
  }

  if (simLoteTypeTom) {
    simLoteTypeTom.on('change', onSimLoteTypeChange);
  } else if (simLoteType) {
    simLoteType.addEventListener('change', onSimLoteTypeChange);
  }

  function scrollSimResultIntoView() {
    if (!simResult) return;
    requestAnimationFrame(function() {
      requestAnimationFrame(function() {
        const rect = simResult.getBoundingClientRect();
        const bottomPadding = 40;
        const maxScroll = Math.max(0, document.documentElement.scrollHeight - window.innerHeight);
        const isDesktop = window.matchMedia('(min-width: 901px)').matches;

        if (!isDesktop) {
          simResult.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
          return;
        }

        if (rect.height > window.innerHeight - bottomPadding - 24) {
          const topOffset = 88;
          const targetTop = window.scrollY + rect.top - topOffset;
          window.scrollTo({
            top: Math.max(0, Math.min(targetTop, maxScroll)),
            behavior: 'smooth'
          });
          return;
        }

        const targetTop = window.scrollY + rect.bottom - window.innerHeight + bottomPadding;
        window.scrollTo({
          top: Math.max(0, Math.min(targetTop, maxScroll)),
          behavior: 'smooth'
        });
      });
    });
  }

  function renderResult(items, note, waText) {
    simResultGrid.innerHTML = items.map(function(item) {
      return '<div class="sim-result-item"><span>' + item.label + '</span><strong>' + item.value + '</strong></div>';
    }).join('');
    simResultNote.textContent = note;
    simWaLink.href = 'https://wa.me/5574988230151?text=' + encodeURIComponent(waText);
    simResult.classList.add('visible');
    scrollSimResultIntoView();
  }

  document.getElementById('simCalcular').addEventListener('click', function() {
    const key = getSimLoteTypeValue();
    const lot = LOT_TYPES[key];
    const mode = getSimModeValue();

    if (mode === 'avista') return;

    if (mode === 'price') {
      const total = moneyToNum(simTotal);
      const entrada = moneyToNum(simEntradaPrice);
      const parcelas = getSimParcelas();
      if (total <= 0) return alert('Informe o valor total do lote.');
      if (!validateEntrada(entrada, total)) return;
      if (entrada >= total) return alert('A entrada deve ser menor que o valor total.');
      const restante = total - entrada;
      const mensal = restante / parcelas;
      const wa = 'Olá! Simulei o lote "' + lot.name + '": valor parcelado ' + formatBRL(total)
        + ', entrada ' + formatBRL(entrada) + ', ' + parcelas + 'x de ' + formatBRL(mensal) + '. Tenho interesse!';
      renderResult(
        [
          { label: 'Valor parcelado', value: formatBRL(total) },
          { label: 'Entrada', value: formatBRL(entrada) },
          { label: 'Parcelas', value: String(parcelas) + 'x' },
          { label: 'Parcela mensal', value: formatBRL(mensal) }
        ],
        'Simulação estimada sem juros. Condições finais podem variar — confirme com o corretor.',
        wa
      );
    } else {
      const total = getActiveTotal();
      const entrada = moneyToNum(simEntradaParcela);
      const mensal = moneyToNum(simParcelaMensal);
      if (total <= 0 || mensal <= 0) return alert('Informe o valor do lote e a parcela desejada.');
      if (!validateEntrada(entrada, total)) return;
      if (entrada >= total) return alert('A entrada deve ser menor que o valor total.');
      const restante = total - entrada;
      const parcelas = Math.ceil(restante / mensal);
      const waParcela = 'Olá! Simulei "' + lot.name + '": valor parcelado ' + formatBRL(total)
        + ', entrada ' + formatBRL(entrada) + ', parcelas de ' + formatBRL(mensal)
        + ' (~' + parcelas + 'x). Tenho interesse!';
      renderResult(
        [
          { label: 'Valor parcelado', value: formatBRL(total) },
          { label: 'Entrada', value: formatBRL(entrada) },
          { label: 'Parcela desejada', value: formatBRL(mensal) },
          { label: 'Parcelas estimadas', value: '~' + parcelas + 'x' }
        ],
        'Quantidade de parcelas estimada (sem juros). Condições finais podem variar — confirme com o corretor.',
        waParcela
      );
    }
  });

  document.querySelectorAll('.lote-simular').forEach(function(btn) {
    btn.addEventListener('click', function() {
      const key = btn.getAttribute('data-lote');
      const mode = btn.getAttribute('data-sim-mode');
      if (mode) {
        setSimModeValue(mode);
        onSimModeChange();
      } else {
        setSimModeValue('price');
        onSimModeChange();
      }
      document.getElementById('simulador').scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  });

  applyLoteType('residencial');

  // === Mapa de lotes (Leaflet — dados em public/data/lotes-map.json) ===
  const LOTES_MAP_URL = @json(asset('data/lotes-map.json'));
  const LOTES_MAP_STYLES = {
    comercial: { color: '#C23028', fillOpacity: 0.4 },
    residencial: { color: '#3d8a5a', fillOpacity: 0.45 }
  };

  const lotsMapModal = document.getElementById('lotsMapModal');
  const lotsMapCanvas = document.getElementById('lotsMapCanvas');
  const simVerLotesBtn = document.getElementById('simVerLotesBtn');
  let lotsMapInstance = null;
  let lotsMapLayerGroup = null;
  let lotsMapConfig = { center: LOTEAMENTO_CENTER, zoom: 17 };
  let lotsMapData = [];
  let lotsMapDataLoaded = false;
  let lotsMapDataLoading = null;

  function normalizeLot(lot, index) {
    const type = lot.type === 'comercial' ? 'comercial' : 'residencial';
    const style = LOTES_MAP_STYLES[type];
    return {
      id: lot.id || ('lot-' + (index + 1)),
      name: lot.name || ('Lote ' + (index + 1)),
      color: lot.color || style.color,
      fillOpacity: lot.fillOpacity != null ? lot.fillOpacity : style.fillOpacity,
      coords: lot.coords,
      popup: lot.popup || ('<strong>' + (lot.name || 'Lote') + '</strong>')
    };
  }

  function lotsFromGeoJson(geojson) {
    const features = geojson.features || [];
    return features
      .filter(function(f) { return f.geometry && f.geometry.type === 'Polygon'; })
      .map(function(f, index) {
        const ring = f.geometry.coordinates[0] || [];
        const props = f.properties || {};
        return normalizeLot({
          id: props.id,
          name: props.name,
          type: props.type,
          popup: props.popup,
          coords: ring.map(function(c) { return [c[1], c[0]]; })
        }, index);
      });
  }

  function parseLotsMapPayload(data) {
    if (data.center) lotsMapConfig.center = data.center;
    if (data.zoom) lotsMapConfig.zoom = data.zoom;
    if (data.geojson) return lotsFromGeoJson(data.geojson);
    if (Array.isArray(data.lots)) {
      return data.lots.map(function(lot, index) { return normalizeLot(lot, index); });
    }
    return [];
  }

  function loadLotsMapData() {
    if (lotsMapDataLoaded) return Promise.resolve(lotsMapData);
    if (lotsMapDataLoading) return lotsMapDataLoading;

    lotsMapDataLoading = fetch(LOTES_MAP_URL)
      .then(function(res) {
        if (!res.ok) throw new Error('lotes-map.json not found');
        return res.json();
      })
      .then(function(data) {
        lotsMapData = parseLotsMapPayload(data);
        lotsMapDataLoaded = true;
        return lotsMapData;
      })
      .catch(function() {
        lotsMapData = [];
        lotsMapDataLoaded = true;
        return lotsMapData;
      });

    return lotsMapDataLoading;
  }

  function renderLotsOnMap() {
    if (!lotsMapInstance || !lotsMapLayerGroup) return;
    lotsMapLayerGroup.clearLayers();

    lotsMapData.forEach(function(lot) {
      if (!lot.coords || lot.coords.length < 3) return;
      const polygon = L.polygon(lot.coords, {
        color: lot.color,
        fillColor: lot.color,
        fillOpacity: lot.fillOpacity,
        weight: 2
      });
      polygon.bindPopup(lot.popup);
      polygon.bindTooltip(lot.name, { sticky: true, direction: 'top' });
      lotsMapLayerGroup.addLayer(polygon);
    });

    if (lotsMapLayerGroup.getLayers().length > 0) {
      lotsMapInstance.fitBounds(lotsMapLayerGroup.getBounds().pad(0.15));
    } else {
      lotsMapInstance.setView(lotsMapConfig.center, lotsMapConfig.zoom);
    }
  }

  function initLotsMap() {
    if (typeof L === 'undefined' || !lotsMapCanvas) return Promise.resolve();

    if (!lotsMapInstance) {
      lotsMapInstance = L.map(lotsMapCanvas, {
        scrollWheelZoom: true,
        zoomControl: true
      }).setView(lotsMapConfig.center, lotsMapConfig.zoom);

      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
      }).addTo(lotsMapInstance);

      lotsMapLayerGroup = L.featureGroup().addTo(lotsMapInstance);
    }

    return loadLotsMapData().then(function() {
      renderLotsOnMap();
    });
  }

  function openLotsMapModal() {
    if (!lotsMapModal) return;
    lotsMapModal.classList.add('is-open');
    lotsMapModal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('lots-map-modal-open');

    initLotsMap().then(function() {
      window.setTimeout(function() {
        if (lotsMapInstance) lotsMapInstance.invalidateSize();
        if (lotsMapLayerGroup && lotsMapLayerGroup.getLayers().length > 0) {
          lotsMapInstance.fitBounds(lotsMapLayerGroup.getBounds().pad(0.12));
        }
      }, 200);
    });
  }

  function closeLotsMapModal() {
    if (!lotsMapModal) return;
    lotsMapModal.classList.remove('is-open');
    lotsMapModal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('lots-map-modal-open');
  }

  if (simVerLotesBtn) {
    simVerLotesBtn.addEventListener('click', openLotsMapModal);
  }

  if (lotsMapModal) {
    lotsMapModal.querySelectorAll('[data-lots-map-close]').forEach(function(el) {
      el.addEventListener('click', closeLotsMapModal);
    });
  }

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && lotsMapModal && lotsMapModal.classList.contains('is-open')) {
      closeLotsMapModal();
    }
  });

  // === FAB Simular — após localização; oculta no simulador ===
  const fabSimular = document.getElementById('fabSimular');
  const localizacaoSection = document.getElementById('localizacao');
  const simuladorSection = document.getElementById('simulador');

  if (fabSimular && 'IntersectionObserver' in window) {
    let passedLocalizacao = false;
    let onSimulador = false;

    function updateFabVisibility() {
      fabSimular.classList.toggle('is-hidden', !passedLocalizacao || onSimulador);
    }

    if (localizacaoSection) {
      const locObserver = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
          if (entry.isIntersecting) {
            passedLocalizacao = false;
          } else {
            passedLocalizacao = entry.boundingClientRect.top < 0;
          }
          updateFabVisibility();
        });
      }, { threshold: 0 });
      locObserver.observe(localizacaoSection);
    }

    if (simuladorSection) {
      const simObserver = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
          onSimulador = entry.isIntersecting;
          updateFabVisibility();
        });
      }, { threshold: 0.15, rootMargin: '0px 0px -80px 0px' });
      simObserver.observe(simuladorSection);
    }

    updateFabVisibility();
  }

  // === POWERED BY ===
  const poweredByBtn = document.getElementById('poweredByBtn');
  if (poweredByBtn) {
    poweredByBtn.addEventListener('click', function () {
      const contato = 'Leonardo Nunes — Dev\nWhatsApp: (61) 9 9249-5212\nE-mail: adsleonardo.o@gmail.com';
      navigator.clipboard.writeText(contato).then(function () {
        poweredByBtn.textContent = 'Contato copiado!';
        poweredByBtn.classList.add('footer-poweredby-copied');
        setTimeout(function () {
          poweredByBtn.textContent = 'Desenvolvido por Nunes, Leonardo';
          poweredByBtn.classList.remove('footer-poweredby-copied');
        }, 3000);
      }).catch(function () {
        prompt('Copie o contato do desenvolvedor:', contato);
      });
    });
  }
})();
</script>

{{-- ===== SCHEMA.ORG STRUCTURED DATA ===== --}}
<script type="application/ld+json">
@verbatim
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "RealEstateAgent",
      "@id": "https://sid360.com.br/#agent",
      "name": "Sid360 Imóveis",
      "alternateName": "Sid Nunes Corretor",
      "description": "Corretor de imóveis em Cafarnaum-BA especializado em lotes residenciais, comerciais e terrenos rurais.",
      "url": "https://sid360.com.br",
      "telephone": "+55-74-9-8823-0151",
      "email": "contato@sid360.com.br",
      "image": "https://sid360.com.br/img/og-image.jpg",
      "logo": "https://sid360.com.br/img/logo-full.png",
      "address": {
        "@type": "PostalAddress",
        "streetAddress": "Centro",
        "addressLocality": "Cafarnaum",
        "addressRegion": "BA",
        "postalCode": "44780-000",
        "addressCountry": "BR"
      },
      "geo": {
        "@type": "GeoCoordinates",
        "latitude": -11.4667,
        "longitude": -39.9833
      },
      "areaServed": {
        "@type": "City",
        "name": "Cafarnaum",
        "sameAs": "https://www.wikidata.org/wiki/Q1022777"
      },
      "sameAs": [
        "https://wa.me/5574988230151"
      ],
      "openingHoursSpecification": [
        {
          "@type": "OpeningHoursSpecification",
          "dayOfWeek": ["Monday","Tuesday","Wednesday","Thursday","Friday"],
          "opens": "08:00",
          "closes": "18:00"
        },
        {
          "@type": "OpeningHoursSpecification",
          "dayOfWeek": "Saturday",
          "opens": "08:00",
          "closes": "12:00"
        }
      ],
@endverbatim
      "priceRange": "R$ {{ $fmtBrl($lotRes['price_cash']) }} - R$ {{ $fmtBrl($lotPriceOriginal($lotBr)) }}"
@verbatim
    },
    {
      "@type": "WebSite",
      "@id": "https://sid360.com.br/#website",
      "url": "https://sid360.com.br",
      "name": "Sid360 Imóveis",
      "description": "Site oficial de Sid360 Imóveis — Cafarnaum, Bahia",
      "publisher": { "@id": "https://sid360.com.br/#agent" },
      "inLanguage": "pt-BR"
    },
    {
      "@type": "WebPage",
      "@id": "https://sid360.com.br/#webpage",
      "url": "https://sid360.com.br",
      "name": "Sid360 — Imóveis em Cafarnaum-BA | Lotes, Casas e Terrenos",
      "isPartOf": { "@id": "https://sid360.com.br/#website" },
      "about": { "@id": "https://sid360.com.br/#agent" },
      "description": "Corretor de imóveis em Cafarnaum-BA. Lotes residenciais, comerciais e rurais com negociação direta.",
      "inLanguage": "pt-BR",
      "breadcrumb": {
        "@type": "BreadcrumbList",
        "itemListElement": [
          { "@type": "ListItem", "position": 1, "name": "Início", "item": "https://sid360.com.br" }
        ]
      }
    },
    {
      "@type": "LandAndFarms",
      "@id": "https://sid360.com.br/#loteamento",
      "name": "Novo Loteamento Cafarnaum — Lotes Frente à BR",
      "description": "Loteamento em Cafarnaum-BA com lotes residenciais e comerciais. Localização privilegiada frente à rodovia, infraestrutura completa.",
      "url": "https://sid360.com.br/#lotes",
      "offers": [
        {
          "@type": "Offer",
          "name": "Lote Residencial 20x30",
          "description": "Lote residencial em loteamento regular com infraestrutura completa",
          "price": "30000",
          "priceCurrency": "BRL",
          "availability": "https://schema.org/InStock",
          "seller": { "@id": "https://sid360.com.br/#agent" }
        },
        {
          "@type": "Offer",
          "name": "Lote Comercial Frente à Rodovia",
          "description": "Lote comercial com visibilidade privilegiada na rodovia BR. À vista R$60.000, parcelado R$65.000.",
          "price": "60000",
          "priceCurrency": "BRL",
          "availability": "https://schema.org/InStock",
          "seller": { "@id": "https://sid360.com.br/#agent" }
        }
      ],
      "address": {
        "@type": "PostalAddress",
        "addressLocality": "Cafarnaum",
        "addressRegion": "BA",
        "addressCountry": "BR"
      }
    }
  ]
}
@endverbatim
</script>

</body>
</html>
