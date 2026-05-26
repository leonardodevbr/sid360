<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import { RouterLink } from 'vue-router';
import publicApi from '@/services/publicApi';
import { developmentSlug } from '@/site/utils/slug';

const whatsapp = ref('5574988230151');
const waBase = computed(() => `https://wa.me/${whatsapp.value.replace(/\D/g, '')}`);

const currentSlide = ref(0);
const slides = ['/img/slide1.jpg', '/img/slide2.jpg', '/img/slide3.jpg'];
let slideTimer = null;

function goToSlide(n) {
  currentSlide.value = (n + slides.length) % slides.length;
  resetTimer();
}
function resetTimer() {
  clearInterval(slideTimer);
  slideTimer = setInterval(() => goToSlide(currentSlide.value + 1), 5000);
}

const heroScrollY = ref(0);
function onHeroScroll() {
  heroScrollY.value = window.scrollY;
}

const developments = ref([]);
const loadingDevs = ref(true);
const displayDevelopments = computed(() => developments.value.slice(0, 6));

// ── Simulador genérico ──────────────────────────────────────────────────────
const SIM_DOWN_PCT = 20;
const SIM_MONTHS_OPTIONS = [12, 24, 36, 48, 60, 120];

const simMin = ref(30000);
const simMax = ref(200000);
const simValue = ref(80000);
const simMonths = ref(36);

// clamp value when min/max change
watch([simMin, simMax], () => {
  if (simValue.value < simMin.value) simValue.value = simMin.value;
  if (simValue.value > simMax.value) simValue.value = simMax.value;
});

const simEntrada = computed(() => Math.round(simValue.value * SIM_DOWN_PCT / 100));
const simSaldo = computed(() => simValue.value - simEntrada.value);
const simParcela = computed(() => Math.round(simSaldo.value / simMonths.value));

function formatBRL(n) {
  return Math.round(n).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL', maximumFractionDigits: 0 });
}

const simWaText = computed(() => {
  return `Olá! Simulei um lote de ${formatBRL(simValue.value)}: entrada ${formatBRL(simEntrada.value)} + ${simMonths.value}x de ${formatBRL(simParcela.value)}. Tenho interesse!`;
});
const simWaHref = computed(() => `${waBase.value}?text=${encodeURIComponent(simWaText.value)}`);

// ── FAB ─────────────────────────────────────────────────────────────────────
const fabVisible = ref(false);
function updateFabVisibility() {
  const sim = document.getElementById('simulador');
  if (!sim) { fabVisible.value = window.scrollY > 600; return; }
  const r = sim.getBoundingClientRect();
  fabVisible.value = r.top > window.innerHeight || r.bottom < 0;
}

async function loadConfig() {
  try {
    const { data } = await publicApi.get('/public/config');
    if (data?.whatsapp) whatsapp.value = String(data.whatsapp);
  } catch { /* defaults */ }
}

async function loadDevelopments() {
  try {
    const { data } = await publicApi.get('/public/developments');
    developments.value = Array.isArray(data) ? data : [];

    // compute global min/max from all developments
    const values = developments.value.flatMap((d) => {
      const min = d.min_lot_value;
      const max = d.max_lot_value;
      const arr = [];
      if (min && min > 0) arr.push(Math.round(min / 100));
      if (max && max > 0) arr.push(Math.round(max / 100));
      return arr;
    }).filter(Boolean);

    if (values.length >= 2) {
      simMin.value = Math.min(...values);
      simMax.value = Math.max(...values);
      simValue.value = Math.round((simMin.value + simMax.value) / 2 / 1000) * 1000;
    }
  } catch {
    developments.value = [];
  } finally {
    loadingDevs.value = false;
  }
}

onMounted(() => {
  resetTimer();
  window.addEventListener('scroll', onHeroScroll, { passive: true });
  window.addEventListener('scroll', updateFabVisibility, { passive: true });
  loadConfig();
  loadDevelopments();
  requestAnimationFrame(updateFabVisibility);
});

onUnmounted(() => {
  clearInterval(slideTimer);
  window.removeEventListener('scroll', onHeroScroll);
  window.removeEventListener('scroll', updateFabVisibility);
});

const heroContentStyle = computed(() => ({
  transform: `translateY(${heroScrollY.value * 0.1}px)`,
}));
</script>

<template>
  <div>
    <section id="hero" class="hero">
      <div class="hero-media">
        <video
          class="hero-video"
          autoplay
          muted
          loop
          playsinline
          poster="/img/slide1.jpg"
        >
          <source src="/video/loteamento.mp4" type="video/mp4">
        </video>

        <div class="hero-slides">
          <div
            v-for="(src, i) in slides"
            :key="src"
            class="hero-slide"
            :class="{ active: i === currentSlide }"
            :style="{ backgroundImage: `url('${src}')` }"
          />
        </div>

        <div class="hero-overlay" />
      </div>

      <div id="heroContent" class="hero-content" :style="heroContentStyle">
        <div class="hero-badge">
          <span class="hero-badge-dot" />
          Cafarnaum · Bahia · Brasil
        </div>
        <h1 class="hero-title">
          O imóvel certo<br>para o seu <span>futuro</span>
        </h1>
        <p class="hero-sub">
          Lotes residenciais e comerciais em Cafarnaum. Uma oportunidade real de investir no seu futuro. Negocie direto com o Sid.
        </p>
        <div class="hero-actions">
          <a :href="waBase" class="btn-primary" target="_blank" rel="noopener noreferrer">Falar no WhatsApp</a>
          <a href="#loteamentos" class="btn-secondary">Ver Loteamentos &rarr;</a>
        </div>
      </div>

      <div class="hero-dots">
        <button
          v-for="(_, i) in slides"
          :key="i"
          type="button"
          class="hero-dot"
          :class="{ active: i === currentSlide }"
          :aria-label="`Slide ${i + 1}`"
          @click="goToSlide(i)"
        />
      </div>

      <a href="#loteamentos" class="hero-scroll-arrow" aria-label="Rolar para loteamentos">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
          <line x1="12" y1="5" x2="12" y2="19" />
          <polyline points="19 12 12 19 5 12" />
        </svg>
      </a>
    </section>

    <div class="stats-bar">
      <div class="stats-track" aria-label="Destaques Sid360">
        <div class="stat-item">
          <div class="stat-value">
            +10
          </div>
          <div class="stat-label">
            Anos de experiência
          </div>
        </div>
        <div class="stat-item">
          <div class="stat-value">
            +200
          </div>
          <div class="stat-label">
            Negócios realizados
          </div>
        </div>
        <div class="stat-item">
          <div class="stat-value">
            100%
          </div>
          <div class="stat-label">
            Segurança jurídica
          </div>
        </div>
        <div class="stat-item stat-item--clone" aria-hidden="true">
          <div class="stat-value">
            +10
          </div>
          <div class="stat-label">
            Anos de experiência
          </div>
        </div>
        <div class="stat-item stat-item--clone" aria-hidden="true">
          <div class="stat-value">
            +200
          </div>
          <div class="stat-label">
            Negócios realizados
          </div>
        </div>
        <div class="stat-item stat-item--clone" aria-hidden="true">
          <div class="stat-value">
            100%
          </div>
          <div class="stat-label">
            Segurança jurídica
          </div>
        </div>
      </div>
    </div>

    <section id="loteamentos" class="lotes-section">
      <div class="section-label">
        Empreendimentos
      </div>
      <h2 class="section-title">
        Loteamentos <span style="color:var(--accent)">disponíveis</span>
      </h2>
      <p class="section-sub" style="margin-bottom:40px">
        Escolha o empreendimento ideal e veja os lotes com simulador e formulário de interesse na página dedicada.
      </p>

      <div v-if="loadingDevs" class="lotes-grid">
        <div
          v-for="skeleton in 3"
          :key="skeleton"
          class="site-skeleton"
          style="min-height:380px;border-radius:16px;background:rgba(255,255,255,0.06);"
        />
      </div>

      <div
        v-else-if="displayDevelopments.length"
        class="lotes-grid"
      >
        <RouterLink
          v-for="item in displayDevelopments"
          :key="item.id"
          :to="{ name: 'site.loteamento', params: { slug: developmentSlug(item) } }"
          class="dev-card"
        >
          <div style="height:200px;position:relative;overflow:hidden;background:#1C0A06;">
            <img
              v-if="item.cover_photo"
              :src="item.cover_photo"
              :alt="item.name"
              style="width:100%;height:100%;object-fit:cover;transition:transform 0.5s;"
              loading="lazy"
            >
            <div
              v-else
              style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;"
            >
              <svg style="width:48px;height:48px;color:rgba(201,168,76,0.4);" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
              </svg>
            </div>
            <div style="position:absolute;inset:0;background:linear-gradient(to bottom,transparent 40%,rgba(28,10,6,0.7));" />

            <!-- Destaque badge -->
            <span
              v-if="item.is_featured"
              style="position:absolute;top:10px;left:10px;background:var(--accent);color:#1c0a06;font-size:0.6rem;font-weight:800;padding:4px 10px;border-radius:6px;text-transform:uppercase;letter-spacing:0.06em;"
            >Em destaque</span>

            <!-- Disponibilidade -->
            <span
              v-if="item.lots_available_count > 0"
              style="position:absolute;bottom:12px;left:12px;background:#25d366;color:#FAF5EE;font-size:0.65rem;font-weight:700;padding:4px 10px;border-radius:6px;text-transform:uppercase;"
            >
              {{ item.lots_available_count }} disponível{{ item.lots_available_count !== 1 ? 'is' : '' }}
            </span>
            <span
              v-else
              style="position:absolute;bottom:12px;left:12px;background:#b91c1c;color:#FAF5EE;font-size:0.65rem;font-weight:700;padding:4px 10px;border-radius:6px;text-transform:uppercase;"
            >
              Esgotado
            </span>
          </div>

          <div style="padding:20px;flex:1;display:flex;flex-direction:column;gap:8px;">
            <span style="font-size:0.65rem;font-weight:600;text-transform:uppercase;letter-spacing:0.8px;color:var(--accent-dark);">
              Loteamento
            </span>
            <h3 style="font-size:1.1rem;font-weight:700;color:#FAF5EE;margin:0;">
              {{ item.name }}
            </h3>
            <p v-if="item.location" style="font-size:0.8rem;color:rgba(250,245,238,0.55);margin:0;">
              {{ item.location }}
            </p>
            <p v-if="item.description" style="font-size:0.82rem;color:rgba(250,245,238,0.65);line-height:1.6;margin:0;flex:1;">
              {{ item.description?.slice(0, 100) }}{{ item.description?.length > 100 ? '...' : '' }}
            </p>
            <div style="display:flex;align-items:center;justify-content:space-between;margin-top:8px;padding-top:12px;border-top:1px solid rgba(201,168,76,0.15);">
              <span style="font-size:0.78rem;color:rgba(250,245,238,0.45);">
                {{ item.lots_count }} lotes no total
              </span>
              <span style="color:var(--accent-dark);font-size:0.82rem;font-weight:600;">
                Ver lotes →
              </span>
            </div>
          </div>
        </RouterLink>
      </div>

      <div v-else class="lotes-grid" style="grid-template-columns:1fr;">
        <div style="text-align:center;padding:32px;border:1px solid rgba(201,168,76,0.2);border-radius:16px;background:rgba(255,255,255,0.04);">
          <p style="color:var(--text-muted);margin-bottom:16px;">
            Nenhum loteamento cadastrado no momento. Fale com o corretor para saber mais.
          </p>
          <a :href="waBase" class="btn-primary" target="_blank" rel="noopener noreferrer">Falar no WhatsApp</a>
        </div>
      </div>

      <div style="text-align:center;margin-top:36px;">
        <RouterLink
          :to="{ name: 'site.loteamentos' }"
          class="btn-primary"
          style="display:inline-flex;"
        >
          Ver todos os loteamentos →
        </RouterLink>
      </div>
    </section>

    <section id="atuacao" class="atuacao-section">
      <div class="section-label">
        Área de atuação
      </div>
      <h2 class="section-title">
        Onde o Sid <span>atua</span>
      </h2>
      <p class="section-sub">
        Loteamentos, terrenos e imóveis na região do Sertão baiano. Quem conhece a terra sabe onde estão as melhores oportunidades.
      </p>

      <div class="atuacao-grid">
        <div class="atuacao-card">
          <div class="atuacao-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" /><circle cx="12" cy="10" r="3" />
            </svg>
          </div>
          <div class="atuacao-title">Cafarnaum</div>
          <div class="atuacao-desc">Sede principal. Loteamentos residenciais e comerciais com fácil acesso à BR-122.</div>
        </div>
        <div class="atuacao-card">
          <div class="atuacao-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" /><circle cx="12" cy="10" r="3" />
            </svg>
          </div>
          <div class="atuacao-title">Região do Sertão Baiano</div>
          <div class="atuacao-desc">Cidades vizinhas e interior da Bahia. Terrenos rurais, chácaras e imóveis com alto potencial de valorização.</div>
        </div>
        <div class="atuacao-card">
          <div class="atuacao-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" /><polyline points="9 22 9 12 15 12 15 22" />
            </svg>
          </div>
          <div class="atuacao-title">Frente de Rodovia (BR-122)</div>
          <div class="atuacao-desc">Lotes com visibilidade privilegiada na BR, ideais para comércio e investimento de longo prazo.</div>
        </div>
        <div class="atuacao-card">
          <div class="atuacao-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M2 22V12a10 10 0 0 1 20 0v10" /><path d="M6 22V16a6 6 0 0 1 12 0v6" />
            </svg>
          </div>
          <div class="atuacao-title">Zona Rural</div>
          <div class="atuacao-desc">Fazendas, chácaras e sítios para quem busca qualidade de vida ou produção agrícola na região.</div>
        </div>
      </div>
    </section>

    <section id="simulador" class="simulador-section">
      <div class="simulador-header">
        <h2 class="simulador-title">
          Simule seu lote
        </h2>
        <p class="simulador-sub">
          Mova a barra para o valor que te interessa e veja como fica o parcelamento.
        </p>
        <div class="simulador-divider" />
      </div>

      <div class="simulador-card sim-generic">
        <!-- Range slider -->
        <div class="sim-range-wrap">
          <div class="sim-range-labels">
            <span>{{ formatBRL(simMin) }}</span>
            <span class="sim-range-value">{{ formatBRL(simValue) }}</span>
            <span>{{ formatBRL(simMax) }}</span>
          </div>
          <input
            v-model.number="simValue"
            type="range"
            :min="simMin"
            :max="simMax"
            :step="Math.max(1000, Math.round((simMax - simMin) / 100 / 1000) * 1000)"
            class="sim-range-input"
            aria-label="Valor do lote"
          >
        </div>

        <!-- Parcelas toggle -->
        <div class="sim-months-wrap">
          <span class="sim-label">Número de parcelas</span>
          <div class="sim-radio-group">
            <label
              v-for="m in SIM_MONTHS_OPTIONS"
              :key="m"
              class="sim-radio-item"
            >
              <input v-model.number="simMonths" type="radio" name="simMonthsGeneric" :value="m">
              <span>{{ m }}x</span>
            </label>
          </div>
        </div>

        <!-- Result -->
        <div class="sim-generic-result">
          <div class="sim-generic-cell">
            <span class="sim-generic-cell__label">Entrada ({{ SIM_DOWN_PCT }}%)</span>
            <strong class="sim-generic-cell__value">{{ formatBRL(simEntrada) }}</strong>
          </div>
          <div class="sim-generic-cell">
            <span class="sim-generic-cell__label">Saldo a parcelar</span>
            <strong class="sim-generic-cell__value">{{ formatBRL(simSaldo) }}</strong>
          </div>
          <div class="sim-generic-cell sim-generic-cell--highlight">
            <span class="sim-generic-cell__label">{{ simMonths }}x de</span>
            <strong class="sim-generic-cell__value sim-generic-cell__value--big">{{ formatBRL(simParcela) }}</strong>
          </div>
        </div>

        <p class="sim-result-note">
          Valores estimados. Condições finais confirmadas diretamente com o corretor.
        </p>

        <div class="sim-result-actions">
          <RouterLink :to="{ name: 'site.loteamentos' }" class="sim-btn-lotes">
            Ver loteamentos
          </RouterLink>
          <a :href="simWaHref" class="sim-wa" target="_blank" rel="noopener noreferrer">
            Enviar simulação no WhatsApp
          </a>
        </div>
      </div>
    </section>

    <section id="imoveis" class="section">
      <div class="section-label">
        O que negociamos
      </div>
      <h2 class="section-title">
        Tudo que você precisa,<br><span>num só lugar</span>
      </h2>
      <p class="section-sub">
        Lotes, casas, terrenos rurais e comerciais. Quem conhece Cafarnaum sabe onde estão as melhores oportunidades.
      </p>

      <div class="types-grid">
        <div class="type-card">
          <div class="type-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
              <polyline points="9 22 9 12 15 12 15 22" />
            </svg>
          </div>
          <div class="type-title">
            Casas
          </div>
          <div class="type-desc">
            Casas à venda em Cafarnaum e região. Consulte disponibilidade.
          </div>
        </div>
        <div class="type-card">
          <div class="type-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <rect x="2" y="7" width="20" height="14" rx="2" />
              <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" />
            </svg>
          </div>
          <div class="type-title">
            Comércio
          </div>
          <div class="type-desc">
            Pontos comerciais e galpões para seu negócio crescer.
          </div>
        </div>
        <div class="type-card">
          <div class="type-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
              <rect x="9" y="14" width="6" height="7" />
            </svg>
          </div>
          <div class="type-title">
            Terreno Residencial
          </div>
          <div class="type-desc">
            Lotes para construir do jeito que você quer, na localização que você escolher.
          </div>
        </div>
        <div class="type-card">
          <div class="type-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M2 22V12a10 10 0 0 1 20 0v10" />
              <path d="M6 22V16a6 6 0 0 1 12 0v6" />
            </svg>
          </div>
          <div class="type-title">
            Terreno Rural
          </div>
          <div class="type-desc">
            Chácaras, fazendas e propriedades rurais na região.
          </div>
        </div>
        <div class="type-card">
          <div class="type-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M5 17H3a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11a2 2 0 0 1 2 2v3" />
              <rect x="9" y="11" width="14" height="10" rx="2" />
              <line x1="12" y1="11" x2="12" y2="21" />
              <line x1="9" y1="16" x2="23" y2="16" />
            </svg>
          </div>
          <div class="type-title">
            Frente de Rodovia
          </div>
          <div class="type-desc">
            Lotes com visibilidade privilegiada na BR, ideais para investimento.
          </div>
        </div>
      </div>
    </section>

    <section id="diferenciais" class="section">
      <div class="section-label">
        Por que escolher
      </div>
      <h2 class="section-title">
        Corretor de <span>confiança</span><br>na sua cidade
      </h2>
      <p class="section-sub">
        Quem é de Cafarnaum, sabe o valor de negociar com quem conhece cada rua, cada terreno e cada oportunidade da região.
      </p>

      <div class="diferenciais-grid">
        <div class="diferencial-card">
          <div class="diferencial-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
            </svg>
          </div>
          <div class="diferencial-title">
            Negociação Direta
          </div>
          <div class="diferencial-desc">
            Sem intermediários. Você fala direto com o corretor que conhece todos os detalhes do imóvel.
          </div>
        </div>
        <div class="diferencial-card">
          <div class="diferencial-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
              <polyline points="14 2 14 8 20 8" />
              <line x1="16" y1="13" x2="8" y2="13" />
              <line x1="16" y1="17" x2="8" y2="17" />
              <polyline points="10 9 9 9 8 9" />
            </svg>
          </div>
          <div class="diferencial-title">
            Documentação Segura
          </div>
          <div class="diferencial-desc">
            Negócios realizados com transparência e segurança. O Sid te orienta em cada etapa.
          </div>
        </div>
        <div class="diferencial-card">
          <div class="diferencial-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
              <circle cx="12" cy="10" r="3" />
            </svg>
          </div>
          <div class="diferencial-title">
            Conhecimento Local
          </div>
          <div class="diferencial-desc">
            Mais de 10 anos no mercado imobiliário de Cafarnaum e região. Acesso às melhores oportunidades.
          </div>
        </div>
        <div class="diferencial-card">
          <div class="diferencial-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
            </svg>
          </div>
          <div class="diferencial-title">
            Suporte Completo
          </div>
          <div class="diferencial-desc">
            Do primeiro contato ao fechamento do negócio. Atendimento direto, sem enrolação.
          </div>
        </div>
      </div>
    </section>

    <section id="contato" class="contato-section">
      <div class="contato-content">
        <h2 class="contato-title">
          Pronto para encontrar<br>seu <span>imóvel ideal</span>?
        </h2>
        <p class="contato-sub">
          Fale agora mesmo com o Sid. Atendimento rápido, direto e sem complicação.
        </p>
        <div class="contato-actions">
          <a
            :href="waBase"
            class="btn-whatsapp"
            target="_blank"
            rel="noopener noreferrer"
            aria-label="Falar no WhatsApp"
          >
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
            </svg>
            <span class="btn-whatsapp-text">
              <span class="btn-whatsapp-label">Falar no WhatsApp</span>
              <span class="btn-whatsapp-phone">(74) 9 8823-0151</span>
            </span>
          </a>
          <a href="#loteamentos" class="btn-secondary">Ver Loteamentos &rarr;</a>
        </div>
      </div>
    </section>

    <a
      href="#simulador"
      class="fab-simular"
      :class="{ 'is-hidden': !fabVisible }"
    >
      <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
        <path stroke="currentColor" stroke-width="2" d="M4 6h16M4 12h10M4 18h14" />
      </svg>
      Simular
    </a>
  </div>
</template>
