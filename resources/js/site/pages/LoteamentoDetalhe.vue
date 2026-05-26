<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import { useToast } from 'vue-toastification';
import { XMarkIcon, ChevronLeftIcon, ChevronRightIcon } from '@heroicons/vue/24/outline';
import publicApi from '@/services/publicApi';
import { parseDevelopmentIdFromSlug } from '@/site/utils/slug';

const route = useRoute();
const toast = useToast();

const siteConfig = ref({ whatsapp: '5574988230151' });
const waUrl = computed(() => `https://wa.me/${String(siteConfig.value.whatsapp).replace(/\D/g, '')}`);

const dev = ref(null);
const lots = ref([]);
const zones = ref([]);
const loading = ref(true);
const loadingLot = ref(false);
const selectedLot = ref(null);
const leadSent = ref(false);
const submitting = ref(false);

const filterZone = ref('');
const filterValue = ref('');
const onlyAvailable = ref(true);

const lead = ref({ name: '', phone: '', email: '', message: '' });
const sim = ref({ total: 0, downPct: 20, months: 36 });

const galleryOpen = ref(false);
const galleryIndex = ref(0);

const mapContainer = ref(null);
let mapInstance = null;

const availableLots = computed(() => lots.value.filter((lot) => lot.status === 'available'));

const filteredLots = computed(() => lots.value.filter((lot) => {
  if (onlyAvailable.value && lot.status !== 'available') {
    return false;
  }
  if (filterZone.value !== '' && String(lot.zone?.id) !== String(filterZone.value)) {
    return false;
  }
  if (filterValue.value !== '' && lot.total_value > Number(filterValue.value) * 100) {
    return false;
  }
  return true;
}));

const devPhotos = computed(() => {
  const p = dev.value?.photos;
  return Array.isArray(p) ? p.filter((x) => Boolean(x?.url)) : [];
});

const simWaText = computed(() => {
  if (!sim.value.total) {
    return `Olá! Tenho interesse em um lote no ${dev.value?.name}. Pode me informar?`;
  }
  const entrada = sim.value.total * sim.value.downPct / 100;
  const parcela = sim.value.total * (1 - sim.value.downPct / 100) / sim.value.months;
  return `Olá! Simulei um lote no ${dev.value?.name}: R$ ${sim.value.total.toLocaleString('pt-BR')}, entrada ${sim.value.downPct}% (R$ ${Math.round(entrada).toLocaleString('pt-BR')}) + ${sim.value.months}x de R$ ${Math.round(parcela).toLocaleString('pt-BR')}. Tenho interesse!`;
});

function formatCurrencyCents(value) {
  if (!value) {
    return 'Consulte';
  }
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value / 100);
}

function formatCurrencyReais(value) {
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
}

function lotStatusLabel(status) {
  if (status === 'available') {
    return 'Disponível';
  }
  if (status === 'reserved') {
    return 'Reservado';
  }
  return 'Vendido';
}

function lotStatusStyle(status) {
  if (status === 'available') {
    return 'background:#25d366;color:#fff;';
  }
  if (status === 'reserved') {
    return 'background:#f59e0b;color:#fff;';
  }
  return 'background:#ef4444;color:#fff;';
}

function openGallery(i) {
  galleryIndex.value = i;
  galleryOpen.value = true;
}

function closeGallery() {
  galleryOpen.value = false;
}

function galleryPrev() {
  const n = devPhotos.value.length;
  if (!n) {
    return;
  }
  galleryIndex.value = (galleryIndex.value - 1 + n) % n;
}

function galleryNext() {
  const n = devPhotos.value.length;
  if (!n) {
    return;
  }
  galleryIndex.value = (galleryIndex.value + 1) % n;
}

async function openLot(lot) {
  loadingLot.value = true;
  try {
    const { data } = await publicApi.get(`/public/developments/${dev.value.id}/lots/${lot.id}`);
    selectedLot.value = data;
  } catch {
    selectedLot.value = lot;
  } finally {
    loadingLot.value = false;
  }

  leadSent.value = false;
  lead.value = { name: '', phone: '', email: '', message: '' };

  if (lot.total_value) {
    sim.value.total = Math.round(lot.total_value / 100);
  }
  if (lot.down_payment_percent != null) {
    sim.value.downPct = Math.round(Number(lot.down_payment_percent));
  } else if (dev.value?.down_payment_percent != null) {
    sim.value.downPct = Math.round(Number(dev.value.down_payment_percent));
  }
}

function closeModal() {
  selectedLot.value = null;
}

async function submitLead() {
  if (!lead.value.name || !lead.value.phone || !selectedLot.value) {
    return;
  }
  submitting.value = true;
  try {
    await publicApi.post('/public/leads', {
      lot_id: selectedLot.value.id,
      ...lead.value,
      down_payment_percent: String(sim.value.downPct),
      installments: sim.value.months,
      simulated_installment_value: Math.round(sim.value.total * (1 - sim.value.downPct / 100) / sim.value.months * 100),
    });
    leadSent.value = true;
    toast.success('Solicitação enviada! Entraremos em contato em breve.');
  } catch (error) {
    toast.error(error?.response?.data?.error ?? 'Erro ao enviar. Tente novamente.');
  } finally {
    submitting.value = false;
  }
}

function destroyMap() {
  if (mapInstance) {
    mapInstance.remove();
    mapInstance = null;
  }
}

async function renderMap() {
  await nextTick();
  destroyMap();
  if (!mapContainer.value || !dev.value?.map_center || !Array.isArray(dev.value.map_center)) {
    return;
  }
  const L = (await import('leaflet')).default;
  await import('leaflet/dist/leaflet.css');

  const [lat, lng] = dev.value.map_center;
  const zoom = Number(dev.value.map_zoom) || 16;
  const map = L.map(mapContainer.value).setView([lat, lng], zoom);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap',
  }).addTo(map);

  lots.value.forEach((lot) => {
    const coords = lot.coordinates;
    if (!coords || !Array.isArray(coords) || coords.length < 3) {
      return;
    }
    const ring = coords.map((pair) => [pair[0], pair[1]]);
    const color = lot.zone?.color || '#c9a84c';
    L.polygon(ring, {
      color,
      weight: 2,
      fillColor: color,
      fillOpacity: 0.18,
    })
      .addTo(map)
      .on('click', () => openLot(lot));
  });

  mapInstance = map;
}

async function load() {
  loading.value = true;
  const developmentId = parseDevelopmentIdFromSlug(route.params.slug);
  if (!developmentId) {
    dev.value = null;
    loading.value = false;
    return;
  }

  try {
    const [detailRes, cfgRes] = await Promise.all([
      publicApi.get(`/public/developments/${developmentId}`),
      publicApi.get('/public/config').catch(() => ({ data: null })),
    ]);
    dev.value = detailRes.data.development;
    lots.value = detailRes.data.lots ?? [];
    if (cfgRes?.data?.whatsapp) {
      siteConfig.value.whatsapp = String(cfgRes.data.whatsapp);
    }

    const zoneMap = {};
    lots.value.forEach((lot) => {
      if (lot.zone) {
        zoneMap[lot.zone.id] = lot.zone;
      }
    });
    zones.value = Object.values(zoneMap);

    if (dev.value?.name) {
      document.title = `${dev.value.name} | Sid360`;
    }
  } catch {
    dev.value = null;
    lots.value = [];
    destroyMap();
  } finally {
    loading.value = false;
  }

  await nextTick();
  await renderMap();
}

watch(() => route.params.slug, () => load());

onMounted(() => load());

onUnmounted(() => {
  destroyMap();
});

function scrollToLotes() {
  document.getElementById('lotes')?.scrollIntoView({ behavior: 'smooth' });
}
</script>

<template>
  <div>
    <div v-if="loading" style="padding:120px 5% 80px;text-align:center;color:var(--text-secondary);">
      Carregando loteamento...
    </div>

    <template v-else-if="dev">
      <section class="dev-detail-hero">
        <div class="dev-detail-hero__banner">
          <img
            v-if="dev.cover_photo"
            :src="dev.cover_photo"
            :alt="dev.name"
            class="dev-detail-hero__photo"
          >
          <div v-else class="dev-detail-hero__photo dev-detail-hero__photo--empty" />
        </div>
        <div class="dev-detail-hero__content">
          <RouterLink
            :to="{ name: 'site.loteamentos' }"
            class="dev-detail-hero__back"
          >
            ← Todos os loteamentos
          </RouterLink>
          <div class="dev-detail-hero__badge">
            Loteamento em destaque
          </div>
          <h1 class="dev-detail-hero__title">
            {{ dev.name }}
          </h1>
          <p v-if="dev.location" class="dev-detail-hero__location">
            {{ dev.location }}
          </p>
          <p v-if="dev.description" class="dev-detail-hero__description">
            {{ dev.description }}
          </p>
          <div class="dev-detail-hero__stats">
            <span class="dev-detail-hero__stat dev-detail-hero__stat--available">
              {{ availableLots.length }} disponíveis
            </span>
            <span class="dev-detail-hero__stat">
              {{ lots.length }} lotes no total
            </span>
          </div>
          <div class="dev-detail-hero__actions">
            <button type="button" class="btn-primary dev-detail-hero__btn" @click="scrollToLotes">
              Ver lotes ↓
            </button>
            <a :href="waUrl" class="dev-detail-hero__wa" target="_blank" rel="noopener noreferrer">Falar no WhatsApp</a>
          </div>
        </div>
      </section>

      <section
        v-if="devPhotos.length"
        style="background:var(--bg-page);padding:40px 5% 24px;"
      >
        <div class="section-label" style="margin-bottom:12px;">
          Galeria
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px;max-width:900px;">
          <button
            v-for="(ph, i) in devPhotos"
            :key="ph.id"
            type="button"
            style="padding:0;border:none;border-radius:12px;overflow:hidden;cursor:pointer;aspect-ratio:4/3;background:#e8e0d4;"
            @click="openGallery(i)"
          >
            <img :src="ph.url" :alt="ph.caption || 'Foto'" style="width:100%;height:100%;object-fit:cover;">
          </button>
        </div>
      </section>

      <section id="lotes" class="dev-detail-lots">
        <span class="section-label">
          Lotes do empreendimento
        </span>
        <h2 class="dev-detail-lots__title">
          Escolha seu lote
        </h2>
        <p class="dev-detail-lots__sub">
          Filtre por quadra, valor e disponibilidade. Toque em um lote para ver detalhes e solicitar contato.
        </p>

        <div class="dev-detail-lots__filters">
          <select
            v-if="zones.length"
            v-model="filterZone"
            class="site-select"
          >
            <option value="">
              Todas as quadras
            </option>
            <option v-for="zone in zones" :key="zone.id" :value="String(zone.id)">
              {{ zone.name }}
            </option>
          </select>

          <select v-model="filterValue" class="site-select">
            <option value="">
              Qualquer valor
            </option>
            <option value="50000">
              Até R$ 50.000
            </option>
            <option value="100000">
              Até R$ 100.000
            </option>
            <option value="200000">
              Até R$ 200.000
            </option>
            <option value="500000">
              Até R$ 500.000
            </option>
          </select>

          <label class="dev-detail-lots__checkbox">
            <input v-model="onlyAvailable" type="checkbox">
            Somente disponíveis
          </label>
        </div>

        <div
          v-if="filteredLots.length"
          class="dev-detail-lots__grid"
        >
          <div
            v-for="lot in filteredLots"
            :key="lot.id"
            class="dev-detail-lot-card"
            @click="openLot(lot)"
          >
            <div class="dev-detail-lot-card__thumb">
              <img
                v-if="lot.cover_photo"
                :src="lot.cover_photo"
                :alt="`Lote ${lot.number}`"
                loading="lazy"
              >
              <div
                v-else
                class="dev-detail-lot-card__placeholder"
              >
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                </svg>
              </div>
              <span
                class="dev-detail-lot-card__status"
                :style="lotStatusStyle(lot.status)"
              >
                {{ lotStatusLabel(lot.status) }}
              </span>
            </div>

            <div class="dev-detail-lot-card__body">
              <span class="dev-detail-lot-card__zone">
                {{ lot.zone?.name ?? lot.block ?? 'Lote' }}
              </span>
              <p class="dev-detail-lot-card__number">
                Lote {{ lot.number }}
              </p>
              <p v-if="lot.area" class="dev-detail-lot-card__area">
                {{ lot.area }}m²
              </p>
              <div class="dev-detail-lot-card__price-wrap">
                <span class="dev-detail-lot-card__price-label">Valor</span>
                <span class="dev-detail-lot-card__price">
                  {{ lot.total_value ? formatCurrencyCents(lot.total_value) : 'Consulte' }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <div v-else class="dev-detail-lots__empty">
          Nenhum lote encontrado com esses filtros.
        </div>
      </section>

      <section
        v-if="dev.map_center && Array.isArray(dev.map_center)"
        style="background:var(--bg-page);padding:48px 5%;"
      >
        <div class="section-label" style="margin-bottom:8px;">
          Mapa
        </div>
        <h2 style="font-size:1.25rem;font-weight:800;color:var(--text-primary);margin-bottom:16px;">
          Localização dos lotes
        </h2>
        <div
          ref="mapContainer"
          class="lots-map-canvas"
          style="height:420px;border-radius:16px;overflow:hidden;border:1px solid var(--border-light);background:#e8e4d8;"
        />
      </section>

      <section id="simulador" class="simulador-section">
        <div class="simulador-header">
          <h2 class="simulador-title">
            Simulação de parcelamento
          </h2>
          <p class="simulador-sub">
            Calcule as parcelas do lote selecionado ou informe outro valor.
          </p>
          <div class="simulador-divider" />
        </div>

        <div class="simulador-card" style="max-width:640px;margin:0 auto;">
          <div style="margin-bottom:16px;">
            <label style="font-weight:600;font-size:0.85rem;display:block;margin-bottom:6px;color:var(--text-primary);">Valor do lote (R$)</label>
            <input
              v-model.number="sim.total"
              type="number"
              min="0"
              step="1000"
              class="site-input"
            >
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
            <div>
              <label style="font-weight:600;font-size:0.85rem;display:block;margin-bottom:6px;color:var(--text-primary);">Entrada (%)</label>
              <select v-model.number="sim.downPct" class="site-select" style="width:100%;">
                <option :value="20">
                  20%
                </option>
                <option :value="30">
                  30%
                </option>
                <option :value="40">
                  40%
                </option>
                <option :value="50">
                  50%
                </option>
              </select>
            </div>
            <div>
              <label style="font-weight:600;font-size:0.85rem;display:block;margin-bottom:6px;color:var(--text-primary);">Parcelas</label>
              <select v-model.number="sim.months" class="site-select" style="width:100%;">
                <option :value="12">
                  12x
                </option>
                <option :value="24">
                  24x
                </option>
                <option :value="36">
                  36x
                </option>
                <option :value="48">
                  48x
                </option>
                <option :value="60">
                  60x
                </option>
                <option :value="120">
                  120x
                </option>
              </select>
            </div>
          </div>

          <div
            v-if="sim.total > 0"
            class="sim-result-grid"
            style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;background:var(--bg-section);border-radius:12px;padding:16px;margin-bottom:16px;"
          >
            <div style="text-align:center;">
              <span style="font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-secondary);display:block;margin-bottom:4px;">Entrada</span>
              <strong style="font-size:1rem;color:var(--text-primary);">{{ formatCurrencyReais(sim.total * sim.downPct / 100) }}</strong>
            </div>
            <div style="text-align:center;">
              <span style="font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-secondary);display:block;margin-bottom:4px;">Saldo</span>
              <strong style="font-size:1rem;color:var(--text-primary);">{{ formatCurrencyReais(sim.total * (1 - sim.downPct / 100)) }}</strong>
            </div>
            <div style="text-align:center;background:var(--accent);border-radius:8px;padding:8px;">
              <span style="font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;color:rgba(255,255,255,0.75);display:block;margin-bottom:4px;">{{ sim.months }}x de</span>
              <strong style="font-size:1.1rem;color:#fff;">{{ formatCurrencyReais(sim.total * (1 - sim.downPct / 100) / sim.months) }}</strong>
            </div>
          </div>

          <a
            :href="`${waUrl}?text=${encodeURIComponent(simWaText)}`"
            target="_blank"
            rel="noopener noreferrer"
            class="btn-whatsapp"
            style="width:100%;justify-content:center;"
          >
            Enviar simulação no WhatsApp
          </a>
        </div>
      </section>

      <section style="background:var(--bg-section);padding:48px 5%;text-align:center;">
        <div class="section-label" style="margin-bottom:8px;">
          Avaliações
        </div>
        <p style="color:var(--text-secondary);font-size:0.95rem;max-width:480px;margin:0 auto;">
          Em breve: avaliações de clientes.
        </p>
        <!--
          GET /api/public/developments/{id}/reviews (futuro)
          { id, author, rating, comment, created_at }
        -->
      </section>

      <section class="dev-detail-cta">
        <div class="dev-detail-cta__inner">
          <h2 class="dev-detail-cta__title">
            Pronto para encontrar<br>seu <span>lote ideal</span>?
          </h2>
          <p class="dev-detail-cta__sub">
            Fale com o Sid sobre este empreendimento e tire todas as dúvidas.
          </p>
          <a :href="waUrl" class="btn-whatsapp" target="_blank" rel="noopener noreferrer">Falar no WhatsApp</a>
        </div>
      </section>

      <div
        v-if="galleryOpen && devPhotos.length"
        style="position:fixed;inset:0;z-index:300;background:rgba(12,6,4,0.92);display:flex;align-items:center;justify-content:center;padding:24px;"
        @click.self="closeGallery"
      >
        <button
          type="button"
          style="position:absolute;top:20px;right:20px;background:rgba(255,255,255,0.1);border:none;color:#fff;width:44px;height:44px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;"
          aria-label="Fechar galeria"
          @click="closeGallery"
        >
          <XMarkIcon style="width:24px;height:24px;" />
        </button>
        <button
          type="button"
          style="position:absolute;left:12px;top:50%;transform:translateY(-50%);background:rgba(255,255,255,0.1);border:none;color:#fff;width:44px;height:44px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;"
          aria-label="Anterior"
          @click.stop="galleryPrev"
        >
          <ChevronLeftIcon style="width:28px;height:28px;" />
        </button>
        <button
          type="button"
          style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:rgba(255,255,255,0.1);border:none;color:#fff;width:44px;height:44px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;"
          aria-label="Próxima"
          @click.stop="galleryNext"
        >
          <ChevronRightIcon style="width:28px;height:28px;" />
        </button>
        <img
          :src="devPhotos[galleryIndex]?.url"
          alt=""
          style="max-width:100%;max-height:85vh;object-fit:contain;border-radius:8px;"
          @click.stop
        >
      </div>

      <div
        v-if="selectedLot"
        class="site-lot-modal-backdrop"
        style="position:fixed;inset:0;z-index:200;background:rgba(28,10,6,0.75);display:flex;align-items:flex-end;justify-content:center;padding:0;"
        @click.self="closeModal"
      >
        <div
          class="site-modal-panel"
          style="width:100%;max-width:560px;max-height:90vh;overflow-y:auto;background:var(--bg-page);border-radius:20px 20px 0 0;margin:0 auto;padding-bottom:env(safe-area-inset-bottom);"
        >
          <div style="height:220px;background:#1C0A06;border-radius:20px 20px 0 0;overflow:hidden;position:relative;">
            <img
              v-if="selectedLot.cover_photo"
              :src="selectedLot.cover_photo"
              style="width:100%;height:100%;object-fit:cover;"
              alt="Foto do lote"
            >
            <button
              type="button"
              style="position:absolute;top:12px;right:12px;background:rgba(0,0,0,0.5);border:none;color:#fff;width:32px;height:32px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;"
              aria-label="Fechar"
              @click="closeModal"
            >
              <XMarkIcon style="width:20px;height:20px;" />
            </button>
          </div>

          <div style="padding:24px;">
            <span style="font-size:0.7rem;color:var(--accent-dark);font-weight:600;text-transform:uppercase;">
              {{ selectedLot.zone?.name ?? dev.name }}
            </span>
            <h3 style="font-size:1.3rem;font-weight:800;color:var(--text-primary);margin:4px 0 8px;">
              Lote {{ selectedLot.number }}
            </h3>
            <p style="font-size:0.85rem;color:var(--text-secondary);margin-bottom:16px;">
              {{ selectedLot.full_address }}
            </p>

            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:20px;">
              <div style="background:var(--bg-section);border-radius:10px;padding:12px;text-align:center;">
                <span style="font-size:0.65rem;color:var(--text-secondary);display:block;">Área</span>
                <strong style="color:var(--text-primary);">{{ selectedLot.area ? `${selectedLot.area}m²` : '–' }}</strong>
              </div>
              <div style="background:var(--bg-section);border-radius:10px;padding:12px;text-align:center;">
                <span style="font-size:0.65rem;color:var(--text-secondary);display:block;">Valor</span>
                <strong style="color:var(--accent);">{{ selectedLot.total_value ? formatCurrencyCents(selectedLot.total_value) : 'Consulte' }}</strong>
              </div>
              <div style="background:var(--bg-section);border-radius:10px;padding:12px;text-align:center;">
                <span style="font-size:0.65rem;color:var(--text-secondary);display:block;">Status</span>
                <strong :style="`color:${selectedLot.status === 'available' ? '#25d366' : '#f59e0b'}`">
                  {{ lotStatusLabel(selectedLot.status) }}
                </strong>
              </div>
            </div>

            <div v-if="selectedLot.status === 'available'">
              <div v-if="!leadSent">
                <p style="font-weight:600;font-size:0.9rem;color:var(--text-primary);margin-bottom:12px;">
                  Tenho interesse neste lote
                </p>
                <input
                  v-model="lead.name"
                  type="text"
                  placeholder="Seu nome *"
                  class="site-input"
                  style="margin-bottom:8px;"
                >
                <input
                  v-model="lead.phone"
                  type="tel"
                  placeholder="WhatsApp *"
                  class="site-input"
                  style="margin-bottom:8px;"
                >
                <input
                  v-model="lead.email"
                  type="email"
                  placeholder="E-mail (opcional)"
                  class="site-input"
                  style="margin-bottom:8px;"
                >
                <textarea
                  v-model="lead.message"
                  rows="2"
                  placeholder="Mensagem (opcional)"
                  class="site-input"
                  style="resize:none;margin-bottom:12px;"
                />
                <button
                  type="button"
                  :disabled="submitting || !lead.name || !lead.phone"
                  class="btn-primary"
                  style="width:100%;justify-content:center;border:none;cursor:pointer;"
                  :style="(submitting || !lead.name || !lead.phone) ? 'opacity:0.5;cursor:not-allowed;' : ''"
                  @click="submitLead"
                >
                  {{ submitting ? 'Enviando...' : 'Solicitar contato' }}
                </button>
              </div>
              <div
                v-else
                style="background:#f0faf4;border:1px solid #bbf7d0;border-radius:12px;padding:20px;text-align:center;"
              >
                <p style="font-weight:700;color:#166534;">
                  Solicitação enviada!
                </p>
                <p style="font-size:0.85rem;color:#15803d;margin-top:4px;">
                  Nossa equipe entrará em contato pelo seu WhatsApp em breve.
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <div v-else style="padding:120px 5% 80px;text-align:center;color:var(--text-secondary);">
      <p style="margin-bottom:16px;">
        Loteamento não encontrado.
      </p>
      <RouterLink :to="{ name: 'site.loteamentos' }" class="btn-primary">
        Ver todos os loteamentos
      </RouterLink>
    </div>

    <div
      v-if="loadingLot"
      style="position:fixed;inset:0;z-index:150;background:rgba(28,10,6,0.3);display:flex;align-items:center;justify-content:center;"
    >
      <div style="background:white;padding:12px 20px;border-radius:10px;font-size:0.9rem;color:var(--text-secondary);">
        Carregando detalhes...
      </div>
    </div>
  </div>
</template>

<style scoped>
.dev-detail-hero {
  padding-top: 88px;
  background: var(--bg-page);
}

.dev-detail-hero__banner {
  margin: 0 5%;
  border-radius: 16px;
  overflow: hidden;
  max-height: 360px;
  box-shadow: 0 8px 32px rgba(28, 10, 6, 0.08);
}

.dev-detail-hero__photo {
  display: block;
  width: 100%;
  height: 320px;
  object-fit: cover;
}

.dev-detail-hero__photo--empty {
  background: linear-gradient(135deg, var(--bg-section) 0%, #e8e0d4 100%);
}

.dev-detail-hero__content {
  max-width: 960px;
  padding: 32px 5% 48px;
}

.dev-detail-hero__back {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: var(--text-secondary);
  text-decoration: none;
  font-size: 0.82rem;
  margin-bottom: 16px;
}

.dev-detail-hero__back:hover {
  color: var(--accent);
}

.dev-detail-hero__badge {
  display: inline-block;
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 2px;
  color: var(--accent-dark);
  padding: 6px 14px;
  border-radius: 100px;
  border: 1px solid rgba(201, 168, 76, 0.35);
  background: rgba(201, 168, 76, 0.08);
  margin-bottom: 14px;
}

.dev-detail-hero__title {
  font-size: clamp(2rem, 5vw, 3.2rem);
  font-weight: 800;
  color: var(--text-primary);
  letter-spacing: -1px;
  line-height: 1.08;
  margin: 0 0 10px;
}

.dev-detail-hero__location {
  color: var(--text-secondary);
  font-size: 1rem;
  margin: 0 0 14px;
}

.dev-detail-hero__description {
  color: var(--text-secondary);
  font-size: 0.95rem;
  line-height: 1.75;
  max-width: 720px;
  margin: 0 0 18px;
}

.dev-detail-hero__stats {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 22px;
}

.dev-detail-hero__stat {
  background: var(--bg-section);
  border: 1px solid var(--border-light);
  color: var(--text-secondary);
  font-size: 0.78rem;
  font-weight: 600;
  padding: 6px 14px;
  border-radius: 100px;
}

.dev-detail-hero__stat--available {
  background: #ecfdf3;
  border-color: #bbf7d0;
  color: #166534;
}

.dev-detail-hero__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.dev-detail-hero__btn {
  border: none;
  cursor: pointer;
}

.dev-detail-hero__wa {
  display: inline-flex;
  align-items: center;
  padding: 13px 26px;
  border-radius: 10px;
  font-weight: 600;
  font-size: 0.9rem;
  text-decoration: none;
  color: var(--text-primary);
  border: 1px solid var(--border-light);
  background: white;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.dev-detail-hero__wa:hover {
  border-color: var(--accent-dark);
  box-shadow: 0 4px 16px rgba(28, 10, 6, 0.06);
}

.dev-detail-lots {
  background: white;
  padding: 56px 5% 64px;
  border-top: 1px solid var(--border-light);
}

.dev-detail-lots__title {
  font-size: clamp(1.4rem, 3vw, 2rem);
  font-weight: 800;
  color: var(--text-primary);
  margin: 0 0 8px;
}

.dev-detail-lots__sub {
  color: var(--text-secondary);
  font-size: 0.9rem;
  max-width: 520px;
  margin-bottom: 28px;
  line-height: 1.65;
}

.dev-detail-lots__filters {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-bottom: 28px;
  align-items: center;
}

.dev-detail-lots__checkbox {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.85rem;
  color: var(--text-secondary);
  cursor: pointer;
}

.dev-detail-lots__checkbox input {
  accent-color: var(--accent);
}

.dev-detail-lots__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 16px;
}

.dev-detail-lot-card {
  display: flex;
  flex-direction: column;
  background: var(--bg-page);
  border: 1px solid var(--border-light);
  border-radius: 14px;
  overflow: hidden;
  cursor: pointer;
  transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
}

.dev-detail-lot-card:hover {
  transform: translateY(-3px);
  border-color: rgba(201, 168, 76, 0.45);
  box-shadow: 0 12px 28px rgba(28, 10, 6, 0.08);
}

.dev-detail-lot-card__thumb {
  height: 160px;
  background: var(--bg-section);
  position: relative;
  overflow: hidden;
}

.dev-detail-lot-card__thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.dev-detail-lot-card__placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.dev-detail-lot-card__placeholder svg {
  width: 40px;
  height: 40px;
  color: rgba(201, 168, 76, 0.45);
}

.dev-detail-lot-card__status {
  position: absolute;
  top: 8px;
  right: 8px;
  font-size: 0.65rem;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 5px;
}

.dev-detail-lot-card__body {
  padding: 16px;
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.dev-detail-lot-card__zone {
  font-size: 0.65rem;
  color: var(--accent-dark);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.dev-detail-lot-card__number {
  font-size: 0.95rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0;
}

.dev-detail-lot-card__area {
  font-size: 0.8rem;
  color: var(--text-secondary);
  margin: 0;
}

.dev-detail-lot-card__price-wrap {
  margin-top: auto;
  padding-top: 10px;
  border-top: 1px solid var(--border-light);
}

.dev-detail-lot-card__price-label {
  font-size: 0.65rem;
  color: var(--text-secondary);
  display: block;
  margin-bottom: 2px;
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.dev-detail-lot-card__price {
  font-size: 1.15rem;
  font-weight: 800;
  color: var(--accent);
}

.dev-detail-lots__empty {
  text-align: center;
  padding: 48px;
  color: var(--text-secondary);
  background: var(--bg-section);
  border-radius: 12px;
}

.dev-detail-cta {
  background: var(--bg-section);
  padding: 56px 5%;
  text-align: center;
  border-top: 1px solid var(--border-light);
}

.dev-detail-cta__inner {
  max-width: 520px;
  margin: 0 auto;
}

.dev-detail-cta__title {
  font-size: clamp(1.5rem, 3vw, 2rem);
  font-weight: 800;
  color: var(--text-primary);
  letter-spacing: -0.5px;
  line-height: 1.2;
  margin-bottom: 12px;
}

.dev-detail-cta__title span {
  color: var(--accent);
}

.dev-detail-cta__sub {
  color: var(--text-secondary);
  font-size: 0.92rem;
  line-height: 1.65;
  margin-bottom: 20px;
}

@media (min-width: 640px) {
  .site-modal-panel {
    border-radius: 20px !important;
    margin: 16px auto !important;
  }

  .site-lot-modal-backdrop {
    align-items: center !important;
    padding: 16px !important;
  }
}
</style>
