{{-- resources/views/site.blade.php — Site público Sid360 (GET /) --}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">

{{-- ===== SEO BÁSICO ===== --}}
<title>Sid360 — Lotes e Imóveis em Cafarnaum-BA</title>
<meta name="description" content="Lotes residenciais e comerciais em Cafarnaum-BA. Uma oportunidade real de investir no seu futuro. Negocie direto com o Sid.">
<meta name="keywords" content="imóveis Cafarnaum, lotes Cafarnaum, corretor Cafarnaum, terrenos Cafarnaum BA, loteamento Cafarnaum, imóveis Bahia, lotes residenciais, terreno rural Cafarnaum">
<meta name="author" content="Sid Nunes — Corretor de Imóveis">
<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
<meta name="googlebot" content="index, follow">
<link rel="canonical" href="https://sid360.com.br/">

{{-- ===== OPEN GRAPH (Facebook, WhatsApp, LinkedIn) ===== --}}
<meta property="og:type" content="website">
<meta property="og:site_name" content="Sid360 Imóveis">
<meta property="og:title" content="Sid360 — Imóveis em Cafarnaum-BA">
<meta property="og:description" content="Lotes, casas e terrenos rurais em Cafarnaum e região. Negociação direta com o Sid, corretor de confiança há mais de 10 anos.">
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
<link rel="icon" type="image/svg+xml" href="{{ asset('img/favicon.svg') }}">
<link rel="icon" type="image/png" sizes="96x96" href="{{ asset('favicon/favicon-96x96.png') }}">
<link rel="shortcut icon" href="{{ asset('favicon/favicon.ico') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('favicon/apple-touch-icon.png') }}">
<link rel="manifest" href="{{ asset('site.webmanifest') }}">
<meta name="theme-color" content="#2A1F14">
<meta name="msapplication-TileColor" content="#2A1F14">
<meta name="apple-mobile-web-app-title" content="Sid360">
<style>
:root {
  --accent:        #C8A96E;
  --accent-light:  #DFC08A;
  --accent-dark:   #A88A50;

  --bg-page:       #F7F3EE;
  --bg-section:    #EDE8E0;
  --bg-dark:       #2A1F14;
  --bg-darker:     #1C1410;

  --text-primary:  #1C1410;
  --text-secondary:#6B5F52;
  --text-light:    #F7F3EE;
  --text-muted:    rgba(247,243,238,0.55);

  --border-light:  rgba(28,20,16,0.1);
  --border-accent: rgba(200,169,110,0.25);

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
  background: rgba(28,20,16,0.96);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  padding: 14px 5%;
  box-shadow: 0 1px 0 rgba(200,169,110,0.15);
}

.nav-logo {
  display: flex;
  align-items: center;
  text-decoration: none;
}

.nav-logo-img {
  height: 40px;
  width: auto;
  display: block;
  max-width: 200px;
  object-fit: contain;
}

.footer-logo-img {
  height: 32px;
  width: auto;
  display: block;
  max-width: 180px;
  object-fit: contain;
  opacity: 0.9;
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
  color: var(--bg-darker) !important;
  padding: 8px 20px;
  border-radius: 8px;
  font-weight: 700 !important;
}

.nav-cta:hover { background: var(--accent-light) !important; }

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
    rgba(28,20,16,0.2) 0%,
    rgba(28,20,16,0.5) 50%,
    rgba(28,20,16,0.75) 100%
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
  background: rgba(200,169,110,0.15);
  border: 1px solid rgba(200,169,110,0.3);
  color: var(--accent-light);
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
  color: var(--bg-darker);
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
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0;
  border-top: 1px solid rgba(200,169,110,0.1);
  border-bottom: 1px solid rgba(200,169,110,0.1);
}

.stat-item {
  text-align: center;
  flex: 1;
  max-width: 240px;
  padding: 0 32px;
}

.stat-item + .stat-item {
  border-left: 1px solid rgba(200,169,110,0.2);
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
  box-shadow: 0 16px 40px rgba(28,20,16,0.1);
  border-color: var(--border-accent);
}

.type-card:hover::after { transform: scaleX(1); }

.type-icon {
  width: 46px; height: 46px;
  background: rgba(200,169,110,0.12);
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

.lotes-section .section-label { color: rgba(200,169,110,0.7); }
.lotes-section .section-title { color: var(--text-light); }
.lotes-section .section-title span { color: var(--accent); }
.lotes-section .section-sub { color: var(--text-muted); }

.lotes-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 18px;
}

.lote-card {
  background: rgba(255,255,255,0.04);
  border: 1px solid rgba(200,169,110,0.12);
  border-radius: 16px;
  overflow: hidden;
  transition: all 0.25s;
}

.lote-card:hover {
  transform: translateY(-4px);
  border-color: rgba(200,169,110,0.35);
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
    rgba(28,20,16,0.15) 0%,
    rgba(28,20,16,0.5) 100%
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
  color: var(--bg-darker);
  font-size: 0.65rem;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 6px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.lote-body {
  padding: 20px;
  background: rgba(255,255,255,0.04);
}

.lote-tag {
  background: rgba(200,169,110,0.12);
  color: var(--accent-light);
  font-size: 0.65rem;
  font-weight: 600;
  padding: 3px 10px;
  border-radius: 6px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  display: inline-block;
  margin-bottom: 10px;
}

.lote-title { color: var(--text-light); font-weight: 700; font-size: 0.98rem; margin-bottom: 6px; }
.lote-info  { color: var(--text-muted); font-size: 0.78rem; line-height: 1.6; margin-bottom: 16px; }

.lote-price {
  display: flex;
  align-items: baseline;
  gap: 8px;
  margin-bottom: 14px;
}

.lote-price-value { color: var(--accent); font-weight: 800; font-size: 1.4rem; letter-spacing: -0.5px; }
.lote-price-label { color: rgba(247,243,238,0.35); font-size: 0.75rem; }

.lote-cta {
  display: block;
  text-align: center;
  background: rgba(200,169,110,0.1);
  border: 1px solid rgba(200,169,110,0.2);
  color: var(--accent-light);
  padding: 10px;
  border-radius: 10px;
  font-size: 0.82rem;
  font-weight: 600;
  text-decoration: none;
  transition: all 0.2s;
}

.lote-cta:hover { background: var(--accent); color: var(--bg-darker); }

.lote-actions {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.lote-simular {
  display: block;
  width: 100%;
  text-align: center;
  background: var(--accent);
  border: none;
  color: var(--bg-darker);
  padding: 10px;
  border-radius: 10px;
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
  font-family: inherit;
}

.lote-simular:hover {
  background: var(--accent-light);
  transform: translateY(-1px);
}

.lote-price-hint {
  font-size: 0.75rem;
  color: var(--text-muted);
  margin-bottom: 14px;
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
  box-shadow: 0 8px 32px rgba(28,20,16,0.08);
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
  box-shadow: 0 12px 40px rgba(28,20,16,0.07);
}

.sim-field { margin-bottom: 20px; }

.sim-label {
  display: block;
  font-weight: 600;
  font-size: 0.88rem;
  color: var(--text-primary);
  margin-bottom: 8px;
}

.sim-select,
.sim-input {
  width: 100%;
  padding: 12px 14px;
  border: 1px solid var(--border-light);
  border-radius: 10px;
  font-size: 0.9rem;
  font-family: inherit;
  color: var(--text-primary);
  background: var(--bg-section);
  transition: border-color 0.2s;
}

.sim-select:focus,
.sim-input:focus {
  outline: none;
  border-color: var(--accent);
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
  color: var(--bg-darker);
  border: none;
  border-radius: 10px;
  font-weight: 700;
  font-size: 0.95rem;
  cursor: pointer;
  font-family: inherit;
  transition: background 0.2s;
}

.sim-btn-calc:hover { background: var(--accent-light); }

.sim-result {
  margin-top: 28px;
  padding: 24px;
  background: var(--bg-section);
  border-radius: 14px;
  border: 1px solid var(--border-light);
  display: none;
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

.sim-wa {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  margin-top: 18px;
  padding: 10px 18px;
  background: #25d366;
  color: white;
  border-radius: 10px;
  font-weight: 600;
  font-size: 0.85rem;
  text-decoration: none;
}

.sim-wa:hover { background: #1db954; }

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
  color: var(--bg-darker);
  border-color: var(--accent);
}

.sim-radio-item:hover span {
  border-color: var(--accent);
  color: var(--text-primary);
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
  box-shadow: 0 8px 24px rgba(28,20,16,0.08);
}

.diferencial-icon {
  width: 46px; height: 46px;
  background: rgba(200,169,110,0.1);
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
    linear-gradient(rgba(200,169,110,0.04) 1px, transparent 1px),
    linear-gradient(90deg, rgba(200,169,110,0.04) 1px, transparent 1px);
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
  gap: 10px;
  background: #25d366;
  color: white;
  padding: 13px 26px;
  border-radius: 10px;
  font-weight: 700;
  font-size: 0.9rem;
  text-decoration: none;
  transition: all 0.2s;
}

.btn-whatsapp:hover { background: #1db954; transform: translateY(-2px); }

/* FOOTER */
footer {
  background: var(--bg-darker);
  padding: 40px 5% 28px;
  border-top: 1px solid rgba(200,169,110,0.1);
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

.footer-links a:hover { color: var(--accent); }

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

/* MOBILE */
@media (max-width: 900px) {
  .localizacao-grid { grid-template-columns: 1fr; }
  .localizacao-map-wrap iframe { height: 320px; }
}

@media (max-width: 640px) {
  .nav-links { display: none; }
  .hero-title { letter-spacing: -0.5px; }
  .hero-scroll-arrow { display: none; }
  .stats-bar { flex-wrap: wrap; gap: 20px; padding: 28px 5%; }
  .stat-item { max-width: none; flex: 1 1 140px; padding: 0 12px; }
  .stat-item + .stat-item { border-left: none; }
  .sim-grid { grid-template-columns: 1fr; }
  .simulador-card { padding: 24px 20px; }
}

/* ANIMAÇÕES HERO */
@keyframes fadeUp {
  from { opacity: 0; transform: translateY(18px); }
  to { opacity: 1; transform: translateY(0); }
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
      src="{{ asset('img/light-logo-full.png') }}"
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
</nav>

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
</div>

<!-- LOTES DESTAQUE -->
<section class="lotes-section" id="lotes">
  <div class="section-label">Loteamento em destaque</div>
  <h2 class="section-title">Lotes disponíveis <span style="color:var(--accent)">agora</span></h2>
  <p class="section-sub" style="margin-bottom:40px">Ótima oportunidade de adquirir seu lote em Cafarnaum. Escolha o seu e garanta já.</p>

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
          <div class="lote-price-value">R$ 65.000</div>
        </div>
        <p class="lote-price-hint">Simule entrada e parcelas conforme seu orçamento</p>
        <div class="lote-actions">
          <button type="button" class="lote-simular" data-lote="frente-br">Simular Parcelas</button>
          <a href="https://wa.me/5574988230151?text=Olá, tenho interesse no lote frente à BR!" class="lote-cta">Tenho Interesse</a>
        </div>
      </div>
    </div>

    <div class="lote-card">
      <div class="lote-thumb" style="background-image: url('{{ asset('img/lote2.jpeg') }}');"></div>
      <div class="lote-body">
        <div class="lote-tag">Lote Residencial</div>
        <div class="lote-title">Lote Residencial 20x30</div>
        <div class="lote-info">Ótima localização · Parcelas acessíveis · Ideal para residência</div>
        <div class="lote-price">
          <div class="lote-price-value">R$ 25.000</div>
        </div>
        <p class="lote-price-hint">Simule entrada e parcelas conforme seu orçamento</p>
        <div class="lote-actions">
          <button type="button" class="lote-simular" data-lote="residencial">Simular Parcelas</button>
          <a href="https://wa.me/5574988230151?text=Olá, tenho interesse em um lote residencial!" class="lote-cta">Tenho Interesse</a>
        </div>
      </div>
    </div>

    <div class="lote-card">
      <div class="lote-thumb" style="background-image: url('{{ asset('img/lote3.jpeg') }}');">
        <div class="lote-badge-destaque">À Vista</div>
      </div>
      <div class="lote-body">
        <div class="lote-tag">Oferta Especial</div>
        <div class="lote-title">Compra à Vista</div>
        <div class="lote-info">Condições especiais para pagamento à vista</div>
        <div class="lote-price">
          <div class="lote-price-value">Consulte</div>
        </div>
        <p class="lote-price-hint">Condições especiais para pagamento à vista</p>
        <div class="lote-actions">
          <button type="button" class="lote-simular" data-lote="avista">Simular à Vista</button>
          <a href="https://wa.me/5574988230151?text=Olá, quero saber o preço à vista dos lotes!" class="lote-cta">Consultar Preço</a>
        </div>
      </div>
    </div>
  </div>
</section>

@php
  $loteamento = config('site.loteamento');
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
    <p class="simulador-sub">Faça uma simulação e descubra as melhores condições para realizar o sonho de viver aqui</p>
    <div class="simulador-divider"></div>
  </div>

  <div class="simulador-card">
    <div class="sim-field">
      <label class="sim-label" for="simMode">Escolha a forma de busca:</label>
      <select id="simMode" class="sim-select">
        <option value="price">Por Preço do Lote</option>
        <option value="parcela">Por Valor da Parcela</option>
      </select>
    </div>

    <div class="sim-field">
      <label class="sim-label" for="simLoteType">Tipo de lote:</label>
      <select id="simLoteType" class="sim-select">
        <option value="frente-br">Lote Frente à Rodovia — R$ 65.000</option>
        <option value="residencial">Lote Residencial 20×30 — R$ 25.000</option>
        <option value="avista">Compra à Vista — consulte condições</option>
      </select>
    </div>

    <div id="simPanelPrice" class="sim-panel active">
      <div class="sim-grid">
        <div class="sim-field">
          <label class="sim-label" for="simTotal">Valor total (R$)</label>
          <input type="text" id="simTotal" class="sim-input sim-money" inputmode="numeric" autocomplete="off" placeholder="R$ 0,00">
        </div>
        <div class="sim-field">
          <label class="sim-label" for="simEntradaPrice">Entrada (R$)</label>
          <input type="text" id="simEntradaPrice" class="sim-input sim-money" inputmode="numeric" autocomplete="off" placeholder="R$ 0,00">
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
          <input type="text" id="simEntradaParcela" class="sim-input sim-money" inputmode="numeric" autocomplete="off" placeholder="R$ 0,00">
        </div>
        <div class="sim-field">
          <label class="sim-label" for="simParcelaMensal">Parcela mensal desejada (R$)</label>
          <input type="text" id="simParcelaMensal" class="sim-input sim-money" inputmode="numeric" autocomplete="off" placeholder="R$ 0,00">
        </div>
      </div>
    </div>

    <button type="button" class="sim-btn-calc" id="simCalcular">Calcular simulação</button>

    <div class="sim-result" id="simResult">
      <div class="sim-result-title">Resultado da simulação</div>
      <div class="sim-result-grid" id="simResultGrid"></div>
      <p class="sim-result-note" id="simResultNote">Valores estimados. Condições finais confirmadas diretamente com o corretor.</p>
      <a href="#" class="sim-wa" id="simWaLink" target="_blank" rel="noopener">Enviar simulação no WhatsApp</a>
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
      <a href="https://wa.me/5574988230151" class="btn-whatsapp">(74) 9 8823-0151 · WhatsApp</a>
      <a href="#lotes" class="btn-secondary">Ver Lotes &rarr;</a>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer>
  <div class="footer-top">
    <div class="footer-logo">
      <img
        src="{{ asset('img/light-logo-full.png') }}"
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

<script>
(function() {
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
  const LOT_TYPES = {
    'frente-br': { name: 'Lote Frente à Rodovia', total: 65000, entradaMin: 15000, parcelaRef: 2000 },
    'residencial': { name: 'Lote Residencial 20×30', total: 25000, entradaMin: 5000, parcelaRef: 1000 },
    'avista': { name: 'Compra à Vista', total: null, entradaMin: 0, desconto: 0.08 }
  };

  const simMode = document.getElementById('simMode');
  const simLoteType = document.getElementById('simLoteType');
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

  function applyLoteType(key) {
    const lot = LOT_TYPES[key];
    if (!lot) return;
    simLoteType.value = key;
    if (lot.total) {
      setMoneyValue(simTotal, lot.total);
      setMoneyValue(simEntradaPrice, lot.entradaMin);
      setMoneyValue(simEntradaParcela, lot.entradaMin);
      setMoneyValue(simParcelaMensal, lot.parcelaRef);
      const restante = lot.total - lot.entradaMin;
      const parcelas = Math.max(1, Math.round(restante / lot.parcelaRef));
      setSimParcelas(parcelas);
    } else {
      simTotal.value = '';
      simEntradaPrice.value = '';
      simEntradaParcela.value = '';
      simParcelaMensal.value = '';
      setSimParcelas(6);
    }
  }

  simMode.addEventListener('change', function() {
    const byPrice = simMode.value === 'price';
    simPanelPrice.classList.toggle('active', byPrice);
    simPanelParcela.classList.toggle('active', !byPrice);
    simResult.classList.remove('visible');
  });

  simLoteType.addEventListener('change', function() {
    applyLoteType(simLoteType.value);
    simResult.classList.remove('visible');
  });

  function renderResult(items, note, waText) {
    simResultGrid.innerHTML = items.map(function(item) {
      return '<div class="sim-result-item"><span>' + item.label + '</span><strong>' + item.value + '</strong></div>';
    }).join('');
    simResultNote.textContent = note;
    simWaLink.href = 'https://wa.me/5574988230151?text=' + encodeURIComponent(waText);
    simResult.classList.add('visible');
  }

  document.getElementById('simCalcular').addEventListener('click', function() {
    const key = simLoteType.value;
    const lot = LOT_TYPES[key];
    const mode = simMode.value;

    if (key === 'avista') {
      renderResult(
        [{ label: 'Modalidade', value: 'À vista' }, { label: 'Desconto estimado', value: 'até 8%' }],
        'Valor final e desconto confirmados com o corretor. Simulação indicativa.',
        'Olá! Fiz uma simulação de compra à vista no loteamento. Gostaria de saber o valor com desconto.'
      );
      return;
    }

    if (mode === 'price') {
      const total = moneyToNum(simTotal);
      const entrada = moneyToNum(simEntradaPrice);
      const parcelas = getSimParcelas();
      if (total <= 0) return alert('Informe o valor total do lote.');
      if (entrada >= total) return alert('A entrada deve ser menor que o valor total.');
      const restante = total - entrada;
      const mensal = restante / parcelas;
      const wa = 'Olá! Simulei o lote "' + lot.name + '": entrada ' + formatBRL(entrada) + ', ' + parcelas + 'x de ' + formatBRL(mensal) + '. Tenho interesse!';
      renderResult(
        [
          { label: 'Valor total', value: formatBRL(total) },
          { label: 'Entrada', value: formatBRL(entrada) },
          { label: 'Parcelas', value: String(parcelas) + 'x' },
          { label: 'Parcela mensal', value: formatBRL(mensal) }
        ],
        'Simulação sem juros. Condições finais podem variar — confirme com o corretor.',
        wa
      );
    } else {
      const total = moneyToNum(simTotal) || LOT_TYPES[key].total || 0;
      const entrada = moneyToNum(simEntradaParcela);
      const mensal = moneyToNum(simParcelaMensal);
      if (total <= 0 || mensal <= 0) return alert('Informe o valor do lote e a parcela desejada.');
      if (entrada >= total) return alert('A entrada deve ser menor que o valor total.');
      const restante = total - entrada;
      const parcelas = Math.ceil(restante / mensal);
      renderResult(
        [
          { label: 'Valor total', value: formatBRL(total) },
          { label: 'Entrada', value: formatBRL(entrada) },
          { label: 'Parcela desejada', value: formatBRL(mensal) },
          { label: 'Parcelas estimadas', value: '~' + parcelas + 'x' }
        ],
        'Quantidade de parcelas estimada para atingir a parcela informada (sem juros).',
        'Olá! Simulei "' + lot.name + '" com entrada ' + formatBRL(entrada) + ' e parcelas de ' + formatBRL(mensal) + '. Tenho interesse!'
      );
    }
  });

  document.querySelectorAll('.lote-simular').forEach(function(btn) {
    btn.addEventListener('click', function() {
      const key = btn.getAttribute('data-lote');
      applyLoteType(key);
      document.getElementById('simulador').scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  });

  applyLoteType('residencial');

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
      "priceRange": "R$25.000 - R$65.000"
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
          "price": "25000",
          "priceCurrency": "BRL",
          "availability": "https://schema.org/InStock",
          "seller": { "@id": "https://sid360.com.br/#agent" }
        },
        {
          "@type": "Offer",
          "name": "Lote Comercial Frente à Rodovia",
          "description": "Lote comercial com visibilidade privilegiada na rodovia BR",
          "price": "65000",
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
