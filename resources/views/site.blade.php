{{-- resources/views/site.blade.php
    Site público Sid360 — servido em GET /
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ config('app.name', 'Sid360') }} — Imóveis Residencial, Comercial e Rural</title>
<meta name="description" content="Lotes, casas, terrenos rurais e comerciais em Cafarnaum-BA. Negociação direta com o Sid, corretor de confiança da região.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600;700&display=swap">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
@font-face { font-display: swap; }

:root {
  --green-dark: #1a3a28;
  --green-mid: #2d6a45;
  --green-light: #3d8a5a;
  --gold: #c9a84c;
  --gold-light: #e8c96a;
  --gold-dark: #a07a28;
  --white: #ffffff;
  --off-white: #f8f6f0;
  --gray-light: #e8e4d8;
  --gray-mid: #8a8474;
  --text-dark: #1a1a14;
  --font-display: 'Syne', 'Trebuchet MS', Arial, sans-serif;
  --font-body: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
}

* { margin: 0; padding: 0; box-sizing: border-box; }
html { scroll-behavior: smooth; }

body {
  font-family: var(--font-body);
  font-size: 16px;
  background: var(--off-white);
  color: var(--text-dark);
  overflow-x: hidden;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  text-rendering: optimizeLegibility;
  font-feature-settings: "kern" 1;
}

/* NAV */
nav {
  position: fixed;
  top: 0; left: 0; right: 0;
  z-index: 100;
  background: rgba(26, 58, 40, 0.97);
  backdrop-filter: blur(12px);
  border-bottom: 1px solid rgba(201,168,76,0.2);
  padding: 14px 5%;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.nav-logo {
  display: flex;
  align-items: center;
  text-decoration: none;
}

.nav-links {
  display: flex;
  align-items: center;
  gap: 28px;
  list-style: none;
}

.nav-links a {
  color: rgba(255,255,255,0.7);
  text-decoration: none;
  font-size: 0.88rem;
  font-weight: 500;
  transition: color 0.2s;
  font-family: var(--font-body);
}

.nav-links a:hover { color: var(--gold); }

.nav-cta {
  background: var(--gold) !important;
  color: var(--green-dark) !important;
  padding: 8px 20px;
  border-radius: 8px;
  font-weight: 600 !important;
}

.nav-cta:hover { background: var(--gold-light) !important; }

/* HERO */
.hero {
  min-height: 100vh;
  background: var(--green-dark);
  position: relative;
  overflow: hidden;
  display: flex;
  align-items: center;
  padding: 100px 5% 80px;
}

.hero-bg {
  position: absolute;
  inset: 0;
  background:
    radial-gradient(ellipse 50% 70% at 75% 50%, rgba(201,168,76,0.07) 0%, transparent 70%),
    radial-gradient(ellipse 40% 40% at 15% 80%, rgba(45,106,69,0.35) 0%, transparent 60%);
}

.hero-grid {
  position: absolute;
  inset: 0;
  background-image:
    linear-gradient(rgba(201,168,76,0.04) 1px, transparent 1px),
    linear-gradient(90deg, rgba(201,168,76,0.04) 1px, transparent 1px);
  background-size: 64px 64px;
}

.hero-inner {
  position: relative;
  z-index: 2;
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 40px;
}

.hero-content { max-width: 580px; }

.hero-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: rgba(201,168,76,0.12);
  border: 1px solid rgba(201,168,76,0.25);
  color: var(--gold-light);
  padding: 6px 14px;
  border-radius: 100px;
  font-size: 0.72rem;
  font-weight: 600;
  letter-spacing: 1px;
  text-transform: uppercase;
  margin-bottom: 24px;
  font-family: var(--font-body);
}

.hero-badge-dot {
  width: 5px; height: 5px;
  background: var(--gold);
  border-radius: 50%;
}

.hero-title {
  font-family: var(--font-display);
  font-weight: 800;
  font-size: clamp(2.4rem, 5.5vw, 4rem);
  color: var(--white);
  line-height: 1.08;
  letter-spacing: -1px;
  margin-bottom: 20px;
}

.hero-title span { color: var(--gold); }

.hero-sub {
  font-size: 1.05rem;
  color: rgba(255,255,255,0.65);
  line-height: 1.75;
  margin-bottom: 36px;
  max-width: 460px;
  font-weight: 400;
  letter-spacing: 0;
}

.hero-actions {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 52px;
}

.btn-primary {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: var(--gold);
  color: var(--green-dark);
  padding: 13px 26px;
  border-radius: 10px;
  font-weight: 700;
  font-size: 0.9rem;
  text-decoration: none;
  transition: all 0.2s;
  font-family: var(--font-body);
}

.btn-primary:hover { background: var(--gold-light); transform: translateY(-2px); }

.btn-secondary {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: transparent;
  color: rgba(255,255,255,0.85);
  border: 1px solid rgba(255,255,255,0.2);
  padding: 13px 26px;
  border-radius: 10px;
  font-weight: 500;
  font-size: 0.9rem;
  text-decoration: none;
  transition: all 0.2s;
  font-family: var(--font-body);
}

.btn-secondary:hover { border-color: rgba(255,255,255,0.45); }

.hero-stats {
  display: flex;
  gap: 32px;
  flex-wrap: wrap;
}

.hero-stat-value {
  font-family: var(--font-display);
  font-weight: 800;
  font-size: 1.9rem;
  color: var(--gold);
  letter-spacing: -1px;
  line-height: 1;
}

.hero-stat-label {
  font-size: 0.75rem;
  color: rgba(255,255,255,0.45);
  margin-top: 4px;
  font-weight: 400;
}

.hero-divider {
  width: 1px;
  background: rgba(255,255,255,0.08);
}

/* FLOAT CARDS */
.hero-float {
  display: flex;
  flex-direction: column;
  gap: 14px;
  flex-shrink: 0;
}

.float-card {
  background: rgba(255,255,255,0.05);
  border: 1px solid rgba(201,168,76,0.18);
  border-radius: 14px;
  padding: 18px 22px;
  width: 230px;
}

.float-card-icon {
  width: 36px; height: 36px;
  background: rgba(201,168,76,0.12);
  border-radius: 9px;
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 12px;
}

.float-card-value {
  font-family: var(--font-display);
  font-weight: 700;
  font-size: 1.15rem;
  color: var(--white);
  margin-bottom: 2px;
}

.float-card-label {
  font-size: 0.72rem;
  color: rgba(255,255,255,0.45);
  font-weight: 400;
}

/* SEÇÕES */
.section { padding: 80px 5%; }

.section-label {
  display: inline-block;
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1.5px;
  color: var(--green-mid);
  margin-bottom: 12px;
  font-family: var(--font-body);
}

.section-title {
  font-family: var(--font-display);
  font-weight: 800;
  font-size: clamp(1.8rem, 3.5vw, 2.8rem);
  color: var(--green-dark);
  letter-spacing: -0.5px;
  line-height: 1.1;
  margin-bottom: 14px;
}

.section-title span { color: var(--gold-dark); }

.section-sub {
  font-size: 1rem;
  color: var(--gray-mid);
  line-height: 1.7;
  max-width: 500px;
  margin-bottom: 44px;
  font-weight: 400;
  letter-spacing: 0;
}

/* TIPOS */
.types-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
  gap: 14px;
}

.type-card {
  background: var(--white);
  border: 1px solid var(--gray-light);
  border-radius: 16px;
  padding: 28px 22px;
  cursor: pointer;
  transition: all 0.25s;
  position: relative;
  overflow: hidden;
}

.type-card::after {
  content: '';
  position: absolute;
  bottom: 0; left: 0; right: 0;
  height: 3px;
  background: var(--gold);
  transform: scaleX(0);
  transform-origin: left;
  transition: transform 0.3s;
}

.type-card:hover { transform: translateY(-4px); box-shadow: 0 16px 40px rgba(26,58,40,0.1); border-color: rgba(201,168,76,0.4); }
.type-card:hover::after { transform: scaleX(1); }

.type-icon {
  width: 44px; height: 44px;
  background: rgba(45,106,69,0.08);
  border-radius: 11px;
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 16px;
}

.type-title {
  font-family: var(--font-display);
  font-weight: 700;
  font-size: 0.95rem;
  color: var(--green-dark);
  margin-bottom: 6px;
}

.type-desc {
  font-size: 0.8rem;
  color: var(--gray-mid);
  line-height: 1.65;
  font-weight: 400;
}

/* LOTES */
.lotes-section {
  background: var(--green-dark);
  padding: 80px 5%;
}

.lotes-section .section-title { color: var(--white); }
.lotes-section .section-label { color: rgba(201,168,76,0.8); }
.lotes-section .section-sub { color: rgba(255,255,255,0.5); }

.lotes-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 18px;
}

.lote-card {
  background: rgba(255,255,255,0.04);
  border: 1px solid rgba(201,168,76,0.15);
  border-radius: 16px;
  overflow: hidden;
  transition: all 0.25s;
}

.lote-card:hover { transform: translateY(-4px); border-color: rgba(201,168,76,0.4); }

.lote-thumb {
  height: 150px;
  background: linear-gradient(135deg, var(--green-mid), var(--green-dark));
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
}

.lote-thumb-icon {
  opacity: 0.25;
}

.lote-badge-destaque {
  position: absolute;
  top: 12px; left: 12px;
  background: var(--gold);
  color: var(--green-dark);
  font-size: 0.68rem;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 6px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-family: var(--font-body);
}

.lote-body { padding: 20px; }

.lote-tag {
  display: inline-block;
  background: rgba(201,168,76,0.12);
  color: var(--gold-light);
  font-size: 0.68rem;
  font-weight: 600;
  padding: 3px 10px;
  border-radius: 6px;
  margin-bottom: 10px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-family: var(--font-body);
}

.lote-title {
  font-family: var(--font-display);
  font-weight: 700;
  font-size: 0.98rem;
  color: var(--white);
  margin-bottom: 6px;
}

.lote-info {
  font-size: 0.78rem;
  color: rgba(255,255,255,0.45);
  margin-bottom: 16px;
  line-height: 1.6;
  font-weight: 400;
}

.lote-price {
  display: flex;
  align-items: baseline;
  gap: 8px;
  margin-bottom: 14px;
}

.lote-price-value {
  font-family: var(--font-display);
  font-weight: 800;
  font-size: 1.4rem;
  color: var(--gold);
  letter-spacing: -0.5px;
}

.lote-price-label {
  font-size: 0.75rem;
  color: rgba(255,255,255,0.35);
  font-weight: 400;
}

.lote-cta {
  display: block;
  text-align: center;
  background: rgba(201,168,76,0.1);
  border: 1px solid rgba(201,168,76,0.25);
  color: var(--gold-light);
  padding: 10px;
  border-radius: 10px;
  font-size: 0.82rem;
  font-weight: 600;
  text-decoration: none;
  transition: all 0.2s;
  font-family: var(--font-body);
}

.lote-cta:hover { background: var(--gold); color: var(--green-dark); }

/* DIFERENCIAIS */
.diferenciais-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
  gap: 16px;
}

.diferencial-card {
  background: var(--white);
  border: 1px solid var(--gray-light);
  border-radius: 16px;
  padding: 28px;
  transition: all 0.2s;
}

.diferencial-card:hover { border-color: rgba(45,106,69,0.3); transform: translateY(-2px); }

.diferencial-icon {
  width: 46px; height: 46px;
  background: rgba(45,106,69,0.08);
  border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 16px;
}

.diferencial-title {
  font-family: var(--font-display);
  font-weight: 700;
  font-size: 0.95rem;
  color: var(--green-dark);
  margin-bottom: 8px;
}

.diferencial-desc {
  font-size: 0.82rem;
  color: var(--gray-mid);
  line-height: 1.7;
  font-weight: 400;
}

/* CONTATO */
.contato-section {
  background: var(--green-dark);
  padding: 90px 5%;
  text-align: center;
  position: relative;
  overflow: hidden;
}

.contato-section::before {
  content: '';
  position: absolute;
  inset: 0;
  background-image:
    linear-gradient(rgba(201,168,76,0.04) 1px, transparent 1px),
    linear-gradient(90deg, rgba(201,168,76,0.04) 1px, transparent 1px);
  background-size: 40px 40px;
}

.contato-content { position: relative; z-index: 2; }

.contato-title {
  font-family: var(--font-display);
  font-weight: 800;
  font-size: clamp(2rem, 4.5vw, 3.2rem);
  color: var(--white);
  letter-spacing: -0.5px;
  margin-bottom: 16px;
  line-height: 1.08;
}

.contato-title span { color: var(--gold); }

.contato-sub {
  font-size: 0.95rem;
  color: rgba(255,255,255,0.55);
  margin-bottom: 36px;
  max-width: 420px;
  margin-left: auto;
  margin-right: auto;
  line-height: 1.7;
  font-weight: 400;
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
  font-family: var(--font-body);
}

.btn-whatsapp:hover { background: #1db954; transform: translateY(-2px); }

/* FOOTER */
footer {
  background: #0f1f16;
  padding: 36px 5% 28px;
  border-top: 1px solid rgba(201,168,76,0.1);
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

.footer-logo {
  font-family: var(--font-display);
  font-weight: 800;
  font-size: 1.2rem;
  color: var(--white);
  letter-spacing: -0.5px;
}

.footer-logo span { color: var(--gold); }

.footer-links {
  display: flex;
  gap: 22px;
  list-style: none;
  flex-wrap: wrap;
}

.footer-links a {
  color: rgba(255,255,255,0.35);
  text-decoration: none;
  font-size: 0.82rem;
  transition: color 0.2s;
  font-family: var(--font-body);
}

.footer-links a:hover { color: var(--gold); }

.footer-bottom {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
}

.footer-copy, .footer-creci {
  font-size: 0.75rem;
  color: rgba(255,255,255,0.25);
  font-family: var(--font-body);
}

/* MOBILE */
@media (max-width: 900px) {
  .hero-float { display: none; }
  .hero-inner { flex-direction: column; }
}

@media (max-width: 640px) {
  .nav-links { display: none; }
  .hero { padding: 90px 5% 60px; }
}

/* ANIMAÇÕES */
@keyframes fadeUp {
  from { opacity: 0; transform: translateY(18px); }
  to { opacity: 1; transform: translateY(0); }
}

.hero-badge { animation: fadeUp 0.5s ease both; }
.hero-title { animation: fadeUp 0.55s 0.08s ease both; }
.hero-sub { animation: fadeUp 0.55s 0.16s ease both; }
.hero-actions { animation: fadeUp 0.55s 0.24s ease both; }
.hero-stats { animation: fadeUp 0.55s 0.32s ease both; }
.float-card:nth-child(1) { animation: fadeUp 0.6s 0.28s ease both; }
.float-card:nth-child(2) { animation: fadeUp 0.6s 0.4s ease both; }
.float-card:nth-child(3) { animation: fadeUp 0.6s 0.52s ease both; }
</style>
</head>
<body>

<!-- NAV -->
<nav>
  <a href="#" class="nav-logo">
    <img
      src="{{ asset('img/logo-full.png') }}"
      alt="Sid360"
      height="38"
      style="height:38px;width:auto;display:block;max-width:160px;"
      onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"
    >
    <div style="display:none;align-items:center;gap:10px;">
      <div style="width:36px;height:36px;background:#c9a84c;border-radius:9px;display:flex;align-items:center;justify-content:center;font-family:'Syne',Arial,sans-serif;font-weight:800;font-size:1.1rem;color:#1a3a28;">S</div>
      <span style="font-family:'Syne',Arial,sans-serif;font-weight:800;font-size:1.2rem;color:#fff;letter-spacing:-0.5px;">Sid<span style="color:#c9a84c;">360</span></span>
    </div>
  </a>
  <ul class="nav-links">
    <li><a href="#imoveis">Imóveis</a></li>
    <li><a href="#lotes">Loteamentos</a></li>
    <li><a href="#diferenciais">Diferenciais</a></li>
    <li><a href="#contato" class="nav-cta">Falar com Corretor</a></li>
  </ul>
</nav>

<!-- HERO -->
<section class="hero">
  <div class="hero-bg"></div>
  <div class="hero-grid"></div>
  <div class="hero-inner">

    <div class="hero-content">
      <div class="hero-badge">
        <span class="hero-badge-dot"></span>
        Cafarnaum · Bahia · Brasil
      </div>
      <h1 class="hero-title">
        O imóvel certo<br>para o seu <span>futuro</span>
      </h1>
      <p class="hero-sub">
        Lotes, casas, terrenos rurais e comerciais em Cafarnaum e região. Negociação direta, transparente e segura com quem entende do mercado local.
      </p>
      <div class="hero-actions">
        <a href="https://wa.me/5574988230151" class="btn-primary">Falar no WhatsApp</a>
        <a href="#imoveis" class="btn-secondary">Ver Imóveis &rarr;</a>
      </div>
      <div class="hero-stats">
        <div class="hero-stat">
          <div class="hero-stat-value">+10</div>
          <div class="hero-stat-label">Anos de experiência</div>
        </div>
        <div class="hero-divider"></div>
        <div class="hero-stat">
          <div class="hero-stat-value">+200</div>
          <div class="hero-stat-label">Negócios realizados</div>
        </div>
        <div class="hero-divider"></div>
        <div class="hero-stat">
          <div class="hero-stat-value">100%</div>
          <div class="hero-stat-label">Segurança jurídica</div>
        </div>
      </div>
    </div>

    <div class="hero-float">
      <div class="float-card">
        <div class="float-card-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#c9a84c" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        </div>
        <div class="float-card-value">140 lotes</div>
        <div class="float-card-label">Novo loteamento disponível</div>
      </div>
      <div class="float-card">
        <div class="float-card-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#c9a84c" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
        </div>
        <div class="float-card-value">Frente à BR</div>
        <div class="float-card-label">Localização privilegiada</div>
      </div>
      <div class="float-card">
        <div class="float-card-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#c9a84c" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div class="float-card-value">A partir de R$25k</div>
        <div class="float-card-label">Entrada facilitada</div>
      </div>
    </div>

  </div>
</section>

<!-- TIPOS -->
<section class="section" id="imoveis">
  <div class="section-label">O que negociamos</div>
  <h2 class="section-title">Tudo que você precisa,<br><span>num só lugar</span></h2>
  <p class="section-sub">Do lote rural ao imóvel comercial, atendemos todos os perfis com expertise e conhecimento do mercado de Cafarnaum.</p>

  <div class="types-grid">
    <div class="type-card">
      <div class="type-icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2d6a45" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      </div>
      <div class="type-title">Casas</div>
      <div class="type-desc">Residências prontas para morar ou na planta, em ótimas localizações.</div>
    </div>
    <div class="type-card">
      <div class="type-icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2d6a45" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
      </div>
      <div class="type-title">Comércio</div>
      <div class="type-desc">Pontos comerciais e galpões para seu negócio crescer.</div>
    </div>
    <div class="type-card">
      <div class="type-icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2d6a45" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><rect x="9" y="14" width="6" height="7"/></svg>
      </div>
      <div class="type-title">Terreno Residencial</div>
      <div class="type-desc">Lotes em loteamentos regulares para construir do jeito que você quer.</div>
    </div>
    <div class="type-card">
      <div class="type-icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2d6a45" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 22V12a10 10 0 0 1 20 0v10"/><path d="M6 22V16a6 6 0 0 1 12 0v6"/></svg>
      </div>
      <div class="type-title">Terreno Rural</div>
      <div class="type-desc">Chácaras, fazendas e propriedades rurais na região.</div>
    </div>
    <div class="type-card">
      <div class="type-icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2d6a45" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 17H3a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11a2 2 0 0 1 2 2v3"/><rect x="9" y="11" width="14" height="10" rx="2"/><line x1="12" y1="11" x2="12" y2="21"/><line x1="9" y1="16" x2="23" y2="16"/></svg>
      </div>
      <div class="type-title">Frente de Rodovia</div>
      <div class="type-desc">Lotes com visibilidade privilegiada na BR, ideais para investimento.</div>
    </div>
  </div>
</section>

<!-- LOTES DESTAQUE -->
<section class="lotes-section" id="lotes">
  <div class="section-label">Loteamento em destaque</div>
  <h2 class="section-title" style="color:var(--white)">Lotes disponíveis <span style="color:var(--gold)">agora</span></h2>
  <p class="section-sub" style="margin-bottom:40px">Novo loteamento com infraestrutura completa em Cafarnaum. Escolha seu lote e garanta já.</p>

  <div class="lotes-grid">
    <div class="lote-card">
      <div class="lote-thumb">
        <svg class="lote-thumb-icon" width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><path d="M5 17H3a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11a2 2 0 0 1 2 2v3"/><rect x="9" y="11" width="14" height="10" rx="2"/></svg>
        <div class="lote-badge-destaque">Frente BR</div>
      </div>
      <div class="lote-body">
        <div class="lote-tag">Lote Comercial</div>
        <div class="lote-title">Lote Frente à Rodovia</div>
        <div class="lote-info">Visibilidade máxima · Área privilegiada · Ideal para comércio</div>
        <div class="lote-price">
          <div class="lote-price-value">R$ 65.000</div>
          <div class="lote-price-label">ou R$15k + R$2k/mês</div>
        </div>
        <a href="https://wa.me/5574988230151?text=Olá, tenho interesse no lote frente à BR!" class="lote-cta">Tenho Interesse</a>
      </div>
    </div>

    <div class="lote-card">
      <div class="lote-thumb" style="background: linear-gradient(135deg, #2d5a3a, #1a3a28)">
        <svg class="lote-thumb-icon" width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      </div>
      <div class="lote-body">
        <div class="lote-tag">Lote Residencial</div>
        <div class="lote-title">Lote Residencial 20x30</div>
        <div class="lote-info">Loteamento regular · Infraestrutura completa · Ótima localização</div>
        <div class="lote-price">
          <div class="lote-price-value">R$ 25.000</div>
          <div class="lote-price-label">ou R$5k + R$1k/mês</div>
        </div>
        <a href="https://wa.me/5574988230151?text=Olá, tenho interesse em um lote residencial!" class="lote-cta">Tenho Interesse</a>
      </div>
    </div>

    <div class="lote-card">
      <div class="lote-thumb" style="background: linear-gradient(135deg, #3a5a2a, #1f3a18)">
        <svg class="lote-thumb-icon" width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        <div class="lote-badge-destaque" style="background:var(--green-light);color:white">À Vista</div>
      </div>
      <div class="lote-body">
        <div class="lote-tag">Oferta Especial</div>
        <div class="lote-title">Compra à Vista</div>
        <div class="lote-info">Condições especiais para pagamento à vista · Melhor preço garantido</div>
        <div class="lote-price">
          <div class="lote-price-value">Consulte</div>
          <div class="lote-price-label">preço especial à vista</div>
        </div>
        <a href="https://wa.me/5574988230151?text=Olá, quero saber o preço à vista dos lotes!" class="lote-cta">Consultar Preço</a>
      </div>
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
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2d6a45" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
      </div>
      <div class="diferencial-title">Negociação Direta</div>
      <div class="diferencial-desc">Sem intermediários. Você fala direto com o corretor que conhece todos os detalhes do imóvel.</div>
    </div>
    <div class="diferencial-card">
      <div class="diferencial-icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2d6a45" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
      </div>
      <div class="diferencial-title">Documentação Segura</div>
      <div class="diferencial-desc">Toda a documentação verificada e regularizada. Compre com segurança jurídica total.</div>
    </div>
    <div class="diferencial-card">
      <div class="diferencial-icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2d6a45" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
      </div>
      <div class="diferencial-title">Conhecimento Local</div>
      <div class="diferencial-desc">Mais de 10 anos no mercado imobiliário de Cafarnaum e região. Acesso às melhores oportunidades.</div>
    </div>
    <div class="diferencial-card">
      <div class="diferencial-icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2d6a45" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      </div>
      <div class="diferencial-title">Suporte Completo</div>
      <div class="diferencial-desc">Do primeiro contato à escritura. Acompanhamento em todas as etapas da negociação.</div>
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
      <a href="#lotes" class="btn-secondary" style="border-color:rgba(255,255,255,0.2);color:rgba(255,255,255,0.8)">Ver Lotes &rarr;</a>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer>
  <div class="footer-top">
    <div class="footer-logo">
      <img
        src="{{ asset('img/logo-full.png') }}"
        alt="Sid360"
        height="28"
        style="height:28px;width:auto;opacity:0.85;"
        onerror="this.style.display='none';this.nextElementSibling.style.display='inline';"
      >
      <span style="display:none;font-family:'Syne',Arial,sans-serif;font-weight:800;font-size:1.1rem;color:#fff;">Sid<span style="color:#c9a84c;">360</span></span>
    </div>
    <ul class="footer-links">
      <li><a href="#imoveis">Imóveis</a></li>
      <li><a href="#lotes">Loteamentos</a></li>
      <li><a href="#diferenciais">Sobre</a></li>
      <li><a href="#contato">Contato</a></li>
    </ul>
  </div>
  <div class="footer-bottom">
    <div class="footer-copy">© 2026 Sid360 Imóveis · Cafarnaum, Bahia</div>
    <div class="footer-creci">CRECI · Corretor Sid Nunes</div>
  </div>
</footer>

</body>
</html>