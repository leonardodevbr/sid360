<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import { useToast } from 'vue-toastification';
import { XMarkIcon, ChevronLeftIcon, ChevronRightIcon } from '@heroicons/vue/24/outline';
import publicApi from '@/services/publicApi';
import { setupMapBaseLayers } from '@/utils/mapLayers';
import { buildLotGroupsFromLots, lotMapStatusColor } from '@/site/utils/lotGroups';
import { parseDevelopmentIdFromSlug } from '@/site/utils/slug';
import SiteSelect from '@/site/components/SiteSelect.vue';
import { formatMoneyMaskFromCents, parseMoneyMaskToCents } from '@/utils/format';

const route = useRoute();
const toast = useToast();

const siteConfig = ref({ whatsapp: '5574988230151' });
const waUrl = computed(() => `https://wa.me/${String(siteConfig.value.whatsapp).replace(/\D/g, '')}`);

const dev = ref(null);
const lots = ref([]);
const lotGroups = ref([]);
const loading = ref(true);
const loadingLot = ref(false);
const selectedLot = ref(null);
const selectedGroupContext = ref(null);

// Modo do modal: 'group-picker' | 'lot-detail'
const modalMode = ref('lot-detail');

// Lotes do grupo selecionado para o picker
const pickerLots = computed(() => {
  if (!selectedGroupContext.value) return [];
  const ids = new Set(selectedGroupContext.value.lot_ids);
  return lots.value
    .filter((l) => ids.has(l.id))
    .sort((a, b) => {
      if (a.status === 'available' && b.status !== 'available') return -1;
      if (a.status !== 'available' && b.status === 'available') return 1;
      return String(a.number).localeCompare(String(b.number), 'pt-BR', { numeric: true });
    });
});

const leadSent = ref(false);
const submitting = ref(false);

const filterValue = ref('');
const onlyAvailable = ref(false);

const lead = ref({ name: '', phone: '', email: '', message: '' });

// sim.total is stored in REAIS (not cents)
const sim = ref({ total: 0, downPct: 20, months: 36, mode: 'parcelas' });
const simGroupKey = ref(null);
const simTotalDisplay = ref('');

const simGroupOptions = computed(() => lotGroups.value.map((g) => {
  const price = g.min_value ? formatCurrencyCents(g.min_value) : 'Consulte';
  const avail = g.available_count > 0 ? `${g.available_count} disp.` : 'Esgotado';
  return { value: g.key, label: `${g.label} — ${price} (${avail})` };
}));

watch(simGroupKey, (key) => {
  const group = lotGroups.value.find((g) => g.key === key);
  if (!group) return;
  const reais = group.min_value ? Math.round(group.min_value / 100) : 0;
  sim.value.total = reais;
  simTotalDisplay.value = reais > 0 ? formatMoneyMaskFromCents(reais * 100) : '';
  if (group.representative_lot_id) {
    const lot = lots.value.find((l) => l.id === group.representative_lot_id);
    if (lot?.down_payment_percent != null) {
      sim.value.downPct = Math.round(Number(lot.down_payment_percent));
    }
  }
});

function onSimTotalInput(e) {
  const cents = parseMoneyMaskToCents(e.target.value);
  const formatted = cents > 0 ? formatMoneyMaskFromCents(cents) : '';
  e.target.value = formatted;
  simTotalDisplay.value = formatted;
  sim.value.total = cents / 100;
}

function onSimTotalKeydown(e) {
  if (e.key !== 'Backspace') return;
  e.preventDefault();
  const digits = String(e.target.value).replace(/\D/g, '').slice(0, -1);
  const cents = digits ? parseInt(digits, 10) : 0;
  const formatted = cents > 0 ? formatMoneyMaskFromCents(cents) : '';
  e.target.value = formatted;
  simTotalDisplay.value = formatted;
  sim.value.total = cents / 100;
}

function onSimTotalFocus(e) {
  requestAnimationFrame(() => e.target.setSelectionRange(0, e.target.value.length));
}

const galleryOpen = ref(false);
const galleryIndex = ref(0);

const mapContainer = ref(null);
let mapInstance = null;
let mapLayersCleanup = null;

/**
 * @param {unknown} coords
 * @returns {Array<[number, number]> | null}
 */
function normalizePolygonRing(coords) {
  if (!Array.isArray(coords) || coords.length < 3) {
    return null;
  }
  const first = coords[0];
  if (Array.isArray(first) && first.length >= 2 && typeof first[0] === 'number') {
    return coords.map((pair) => [Number(pair[0]), Number(pair[1])]);
  }
  if (Array.isArray(first) && Array.isArray(first[0])) {
    return normalizePolygonRing(first);
  }
  return null;
}

const availableLots = computed(() => lots.value.filter((lot) => lot.status === 'available'));

const filteredLotGroups = computed(() => lotGroups.value.filter((group) => {
  if (onlyAvailable.value && group.available_count === 0) {
    return false;
  }
  if (filterValue.value != null && filterValue.value !== '' && group.min_value > Number(filterValue.value) * 100) {
    return false;
  }
  return true;
}));

const devPhotos = computed(() => {
  const p = dev.value?.photos;
  if (!Array.isArray(p)) {
    return [];
  }
  return p.filter((x) => Boolean(x?.url) && x?.type === 'photo');
});

const hasMapSection = computed(() => {
  if (!dev.value) {
    return false;
  }
  const c = dev.value.map_center;
  if (Array.isArray(c) && c.length === 2 && Number.isFinite(Number(c[0])) && Number.isFinite(Number(c[1]))) {
    return true;
  }
  if (normalizePolygonRing(dev.value.coordinates)) {
    return true;
  }
  return lots.value.some((lot) => Boolean(normalizePolygonRing(lot.coordinates)));
});

const simWaText = computed(() => {
  const devName = dev.value?.name ?? 'loteamento';
  if (!sim.value.total) {
    return `Olá! Tenho interesse em um lote no ${devName}. Pode me informar?`;
  }
  const totalFmt = formatCurrencyReais(sim.value.total);
  if (sim.value.mode === 'avista') {
    return `Olá! Simulei um lote no ${devName}: ${totalFmt} à vista. Tenho interesse!`;
  }
  const entrada = sim.value.total * sim.value.downPct / 100;
  const parcela = sim.value.total * (1 - sim.value.downPct / 100) / sim.value.months;
  return `Olá! Simulei um lote no ${devName}: ${totalFmt}, entrada ${sim.value.downPct}% (${formatCurrencyReais(Math.round(entrada))}) + ${sim.value.months}x de ${formatCurrencyReais(Math.round(parcela))}. Tenho interesse!`;
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

function lockScroll() {
  document.body.style.overflow = 'hidden';
  document.body.style.touchAction = 'none';
}

function unlockScroll() {
  document.body.style.overflow = '';
  document.body.style.touchAction = '';
}

function openGallery(i) {
  galleryIndex.value = i;
  galleryOpen.value = true;
  lockScroll();
}

function closeGallery() {
  galleryOpen.value = false;
  if (!selectedLot.value && !(selectedGroupContext.value && modalMode.value === 'group-picker')) {
    unlockScroll();
  }
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
  modalMode.value = 'lot-detail';
  lockScroll();
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
    const reais = Math.round(lot.total_value / 100);
    sim.value.total = reais;
    simTotalDisplay.value = reais > 0 ? formatMoneyMaskFromCents(reais * 100) : '';
  }
  if (lot.down_payment_percent != null) {
    sim.value.downPct = Math.round(Number(lot.down_payment_percent));
  } else if (dev.value?.down_payment_percent != null) {
    sim.value.downPct = Math.round(Number(dev.value.down_payment_percent));
  }
}

function formatGroupPrice(group) {
  if (!group.min_value) {
    return 'Consulte';
  }
  if (group.min_value === group.max_value) {
    return formatCurrencyCents(group.min_value);
  }
  return `A partir de ${formatCurrencyCents(group.min_value)}`;
}

function groupAvailabilityText(group) {
  const parts = [];
  if (group.available_count > 0) {
    parts.push(`${group.available_count} disponível${group.available_count !== 1 ? 'is' : ''}`);
  }
  if (group.reserved_count > 0) {
    parts.push(`${group.reserved_count} reservado${group.reserved_count !== 1 ? 's' : ''}`);
  }
  if (group.sold_count > 0) {
    parts.push(`${group.sold_count} vendido${group.sold_count !== 1 ? 's' : ''}`);
  }
  return parts.join(' · ') || 'Sem lotes';
}

function groupAreaSubtitle(group) {
  if (group.area && !String(group.label).includes('m²')) {
    return `${Number(group.area).toLocaleString('pt-BR', { maximumFractionDigits: 0 })} m²`;
  }
  return null;
}

async function openGroup(group) {
  selectedGroupContext.value = group;
  selectedLot.value = null;
  leadSent.value = false;
  lead.value = { name: '', phone: '', email: '', message: '' };

  if (group.lot_ids.length === 1) {
    const lot = lots.value.find((l) => l.id === group.lot_ids[0]);
    if (lot) {
      modalMode.value = 'lot-detail';
      await openLot(lot);
      return;
    }
  }

  modalMode.value = 'group-picker';
  lockScroll();
}

async function selectLotFromPicker(lot) {
  await openLot(lot);
}

function closeModal() {
  selectedLot.value = null;
  selectedGroupContext.value = null;
  modalMode.value = 'lot-detail';
  if (!galleryOpen.value) unlockScroll();
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
  mapLayersCleanup?.();
  mapLayersCleanup = null;
  if (mapInstance) {
    mapInstance.remove();
    mapInstance = null;
  }
}

async function renderMap() {
  await nextTick();
  destroyMap();
  if (!mapContainer.value || !dev.value) {
    return;
  }
  const L = (await import('leaflet')).default;
  await import('leaflet/dist/leaflet.css');

  const fallbackCenter = [-11.4667, -39.9833];
  const center = Array.isArray(dev.value.map_center) && dev.value.map_center.length === 2
    ? [Number(dev.value.map_center[0]), Number(dev.value.map_center[1])]
    : fallbackCenter;
  const zoom = Number(dev.value.map_zoom) || 16;
  const map = L.map(mapContainer.value, { scrollWheelZoom: false }).setView(center, zoom);

  const layers = await setupMapBaseLayers(map, L);
  mapLayersCleanup = layers.scrollZoomCleanup;

  const overlay = L.featureGroup().addTo(map);

  const perimeterRing = normalizePolygonRing(dev.value.coordinates);
  if (perimeterRing) {
    L.polygon(perimeterRing, {
      color: '#c9a84c',
      weight: 3,
      fillColor: '#c9a84c',
      fillOpacity: 0.07,
      dashArray: '8 6',
      interactive: false,
    }).addTo(overlay);
  }

  lots.value.forEach((lot) => {
    const ring = normalizePolygonRing(lot.coordinates);
    if (!ring) {
      return;
    }
    const color = lotMapStatusColor(lot.status);
    L.polygon(ring, {
      color,
      weight: 2,
      fillColor: color,
      fillOpacity: lot.status === 'available' ? 0.38 : 0.28,
    })
      .addTo(overlay)
      .on('click', () => {
        selectedGroupContext.value = null;
        openLot(lot);
      });
  });

  try {
    if (overlay.getLayers().length > 0) {
      const bounds = overlay.getBounds();
      if (bounds.isValid()) {
        map.fitBounds(bounds, { padding: [48, 48], maxZoom: 20, animate: false });
      }
    }
  } catch {
    /* invalid geometry */
  }

  mapInstance = map;
  requestAnimationFrame(() => {
    map.invalidateSize({ animate: false });
  });
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
    lotGroups.value = detailRes.data.lot_groups ?? buildLotGroupsFromLots(lots.value);
    if (cfgRes?.data?.whatsapp) {
      siteConfig.value.whatsapp = String(cfgRes.data.whatsapp);
    }

    if (dev.value?.name) {
      document.title = `${dev.value.name} | Sid360`;
    }
  } catch {
    dev.value = null;
    lots.value = [];
    lotGroups.value = [];
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
  unlockScroll();
});

function scrollToMap() {
  document.getElementById('mapa')?.scrollIntoView({ behavior: 'smooth' });
}
</script>

<template>
  <div>
    <div v-if="loading" style="padding:120px 5% 80px;text-align:center;color:var(--text-secondary);">
      Carregando loteamento...
    </div>

    <template v-else-if="dev">
      <section class="dev-detail-hero">
        <div class="dev-detail-hero__inner">
          <div class="dev-detail-hero__banner">
            <video
              v-if="dev.hero_video_url"
              class="dev-detail-hero__video"
              autoplay
              muted
              loop
              playsinline
              :poster="dev.cover_photo || undefined"
            >
              <source :src="dev.hero_video_url" :type="dev.hero_video_mime || 'video/mp4'">
            </video>
            <img
              v-else-if="dev.cover_photo"
              :src="dev.cover_photo"
              :alt="dev.name"
              class="dev-detail-hero__photo"
            >
            <div v-else class="dev-detail-hero__photo dev-detail-hero__photo--empty" />
          </div>
          <div class="dev-detail-hero__content">
            <div class="dev-detail-hero__meta">
              <RouterLink
                :to="{ name: 'site.loteamentos' }"
                class="dev-detail-hero__back"
              >
                Todos os loteamentos
              </RouterLink>
              <span class="dev-detail-hero__badge">Em destaque</span>
            </div>

            <h1 class="dev-detail-hero__title">
              {{ dev.name }}
            </h1>
            <p v-if="dev.location" class="dev-detail-hero__location">
              {{ dev.location }}
            </p>

            <p
              v-if="dev.description"
              class="dev-detail-hero__description"
            >
              {{ dev.description }}
            </p>

            <p
              v-if="lots.length"
              class="dev-detail-hero__summary"
            >
              <span>{{ lots.length }} lote{{ lots.length !== 1 ? 's' : '' }}</span>
              <span class="dev-detail-hero__summary-sep" aria-hidden="true">·</span>
              <span
                class="dev-detail-hero__summary-avail"
                :class="{ 'dev-detail-hero__summary-avail--zero': availableLots.length === 0 }"
              >{{ availableLots.length }} disponível{{ availableLots.length !== 1 ? 'eis' : '' }}</span>
            </p>

            <div class="dev-detail-hero__actions">
              <a
                :href="waUrl"
                class="btn-whatsapp dev-detail-hero__wa-primary"
                target="_blank"
                rel="noopener noreferrer"
              >Falar no WhatsApp</a>
              <button
                v-if="hasMapSection"
                type="button"
                class="dev-detail-hero__ghost"
                @click="scrollToMap"
              >
                Ver mapa
              </button>
            </div>
          </div>
        </div>
      </section>

      <section
        v-if="devPhotos.length"
        class="dev-detail-gallery"
      >
        <div class="dev-detail-gallery__inner">
          <div class="section-label dev-detail-gallery__label">
            Galeria
          </div>
          <div class="dev-detail-gallery__grid">
            <button
              v-for="(ph, i) in devPhotos"
              :key="ph.id"
              type="button"
              class="dev-detail-gallery__thumb"
              @click="openGallery(i)"
            >
              <img :src="ph.url" :alt="ph.caption || 'Foto'">
            </button>
          </div>
        </div>
      </section>

      <div class="dev-detail-lots-map-row">
        <section id="lotes" class="dev-detail-lots">
          <div class="dev-detail-lots__head">
            <div class="dev-detail-lots__head-text">
              <span class="section-label">Tipos de lote</span>
              <h2 class="dev-detail-lots__title">
                Escolha o tamanho
              </h2>
            </div>
            <button
              v-if="hasMapSection"
              type="button"
              class="dev-detail-lots__maplink"
              @click="scrollToMap"
            >
              Mapa
            </button>
          </div>
          <p class="dev-detail-lots__sub">
            Toque em um tipo para simular ou falar com o corretor. As quadras estão no mapa abaixo.
          </p>

          <div class="dev-detail-lots__filters">
            <SiteSelect
              v-model="filterValue"
              class="dev-detail-lots__filter-select"
              placeholder="Qualquer valor"
              :can-clear="true"
              :options="[
                { value: '50000', label: 'Até R$ 50.000' },
                { value: '100000', label: 'Até R$ 100.000' },
                { value: '200000', label: 'Até R$ 200.000' },
                { value: '500000', label: 'Até R$ 500.000' },
              ]"
            />

            <label class="dev-detail-lots__checkbox">
              <input v-model="onlyAvailable" type="checkbox">
              Só com disponíveis
            </label>
          </div>

          <div
            v-if="filteredLotGroups.length"
            class="dev-detail-lots__grid"
          >
            <button
              v-for="group in filteredLotGroups"
              :key="group.key"
              type="button"
              class="dev-detail-group-card"
              @click="openGroup(group)"
            >
              <div class="dev-detail-group-card__thumb">
                <img
                  v-if="group.cover_photo"
                  :src="group.cover_photo"
                  :alt="group.label"
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
                  class="dev-detail-group-card__badge"
                  :class="{
                    'dev-detail-group-card__badge--available': group.available_count > 0,
                    'dev-detail-group-card__badge--reserved': group.available_count === 0 && group.reserved_count > 0,
                    'dev-detail-group-card__badge--sold': group.available_count === 0 && group.reserved_count === 0,
                  }"
                >
                  {{ group.available_count > 0 ? `${group.available_count} disponível${group.available_count !== 1 ? 'is' : ''}` : (group.reserved_count > 0 ? 'Reservado' : 'Esgotado') }}
                </span>
              </div>

              <div class="dev-detail-group-card__body">
                <p class="dev-detail-group-card__label">
                  {{ group.label }}
                </p>
                <p v-if="groupAreaSubtitle(group)" class="dev-detail-group-card__area">
                  {{ groupAreaSubtitle(group) }}
                </p>
                <p class="dev-detail-group-card__counts">
                  {{ group.total_count }} lote{{ group.total_count !== 1 ? 's' : '' }} · {{ groupAvailabilityText(group) }}
                </p>
                <div class="dev-detail-group-card__price-wrap">
                  <span class="dev-detail-lot-card__price-label">Valor</span>
                  <span class="dev-detail-group-card__price">
                    {{ formatGroupPrice(group) }}
                  </span>
                </div>
                <span class="dev-detail-group-card__cta">Simular</span>
              </div>
            </button>
          </div>

          <div v-else class="dev-detail-lots__empty">
            Nenhum tipo de lote encontrado com esses filtros.
          </div>
        </section>

        <section
          v-if="hasMapSection"
          id="mapa"
          class="dev-detail-map"
        >
          <div class="section-label">
            Mapa
          </div>
          <h2 class="dev-detail-map__title">
            Onde ficam os lotes
          </h2>
          <p class="dev-detail-map__sub">
            Toque em um lote para ver detalhes. Zoom: Ctrl ou Cmd + rolagem.
          </p>
          <div class="dev-detail-map__legend">
            <span><i class="dev-detail-map__dot dev-detail-map__dot--available" /> Disponível</span>
            <span><i class="dev-detail-map__dot dev-detail-map__dot--reserved" /> Reservado</span>
            <span><i class="dev-detail-map__dot dev-detail-map__dot--sold" /> Vendido</span>
          </div>
          <div
            ref="mapContainer"
            class="lots-map-canvas dev-detail-map__canvas"
          />
        </section>
      </div>

      <section id="simulador" class="simulador-section">
        <div class="simulador-header">
          <h2 class="simulador-title">
            Simular parcelamento
          </h2>
          <p class="simulador-sub">
            Escolha o tipo de lote, a forma de pagamento e veja as condições.
          </p>
          <div class="simulador-divider" />
        </div>

        <div class="simulador-card dev-detail-sim__card">

          <!-- Passo 1: tipo de lote -->
          <div class="dev-detail-sim__field">
            <label class="dev-detail-sim__label">
              <span class="dev-detail-sim__step">1</span>
              Tipo de lote
            </label>
            <SiteSelect
              v-model="simGroupKey"
              placeholder="Selecione um tipo de lote"
              :searchable="false"
              :can-clear="true"
              :options="simGroupOptions"
            />
          </div>

          <!-- Valor manual com máscara -->
          <div class="dev-detail-sim__field">
            <label class="dev-detail-sim__label">Valor do lote (R$)</label>
            <input
              type="text"
              inputmode="numeric"
              autocomplete="off"
              :value="simTotalDisplay"
              placeholder="R$ 0,00"
              class="site-input dev-detail-sim__money-input"
              @input="onSimTotalInput"
              @keydown="onSimTotalKeydown"
              @focus="onSimTotalFocus"
            >
          </div>

          <!-- Passo 2: forma de pagamento -->
          <div class="dev-detail-sim__field">
            <label class="dev-detail-sim__label">
              <span class="dev-detail-sim__step">2</span>
              Forma de pagamento
            </label>
            <div class="dev-detail-sim__mode-group">
              <label class="dev-detail-sim__mode-item" :class="{ active: sim.mode === 'avista' }">
                <input v-model="sim.mode" type="radio" value="avista" class="sr-only">
                À vista
              </label>
              <label class="dev-detail-sim__mode-item" :class="{ active: sim.mode === 'parcelas' }">
                <input v-model="sim.mode" type="radio" value="parcelas" class="sr-only">
                Parcelado
              </label>
            </div>
          </div>

          <!-- Passo 3: condições do parcelamento -->
          <div v-if="sim.mode === 'parcelas'" class="dev-detail-sim__row">
            <div class="dev-detail-sim__field">
              <label class="dev-detail-sim__label">
                <span class="dev-detail-sim__step">3</span>
                Entrada
              </label>
              <SiteSelect
                v-model="sim.downPct"
                class="dev-detail-sim__select"
                :options="[
                  { value: 10, label: '10%' },
                  { value: 20, label: '20%' },
                  { value: 30, label: '30%' },
                  { value: 40, label: '40%' },
                  { value: 50, label: '50%' },
                ]"
              />
            </div>
            <div class="dev-detail-sim__field">
              <label class="dev-detail-sim__label">Parcelas</label>
              <SiteSelect
                v-model="sim.months"
                class="dev-detail-sim__select"
                :options="[
                  { value: 12, label: '12x' },
                  { value: 24, label: '24x' },
                  { value: 36, label: '36x' },
                  { value: 48, label: '48x' },
                  { value: 60, label: '60x' },
                  { value: 120, label: '120x' },
                ]"
              />
            </div>
          </div>

          <!-- Resultado -->
          <div v-if="sim.total > 0" class="dev-detail-sim__results">
            <template v-if="sim.mode === 'avista'">
              <div class="dev-detail-sim__result-cell dev-detail-sim__result-cell--highlight dev-detail-sim__result-cell--full">
                <span class="dev-detail-sim__result-label dev-detail-sim__result-label--on-accent">À vista</span>
                <strong class="dev-detail-sim__result-value dev-detail-sim__result-value--on-accent">{{ formatCurrencyReais(sim.total) }}</strong>
              </div>
            </template>
            <template v-else>
              <div class="dev-detail-sim__result-cell">
                <span class="dev-detail-sim__result-label">Entrada ({{ sim.downPct }}%)</span>
                <strong class="dev-detail-sim__result-value">{{ formatCurrencyReais(sim.total * sim.downPct / 100) }}</strong>
              </div>
              <div class="dev-detail-sim__result-cell">
                <span class="dev-detail-sim__result-label">Saldo</span>
                <strong class="dev-detail-sim__result-value">{{ formatCurrencyReais(sim.total * (1 - sim.downPct / 100)) }}</strong>
              </div>
              <div class="dev-detail-sim__result-cell dev-detail-sim__result-cell--highlight">
                <span class="dev-detail-sim__result-label dev-detail-sim__result-label--on-accent">{{ sim.months }}x de</span>
                <strong class="dev-detail-sim__result-value dev-detail-sim__result-value--on-accent">{{ formatCurrencyReais(sim.total * (1 - sim.downPct / 100) / sim.months) }}</strong>
              </div>
            </template>
          </div>

          <p class="dev-detail-sim__note">
            Valores estimados. Condições finais confirmadas com o corretor.
          </p>

          <a
            :href="`${waUrl}?text=${encodeURIComponent(simWaText)}`"
            target="_blank"
            rel="noopener noreferrer"
            class="btn-whatsapp dev-detail-sim__wa"
          >
            Enviar simulação no WhatsApp
          </a>
        </div>
      </section>

      <section class="dev-detail-cta">
        <div class="dev-detail-cta__inner">
          <p class="dev-detail-cta__line">
            Dúvidas sobre este loteamento?
          </p>
          <a :href="waUrl" class="btn-whatsapp dev-detail-cta__btn" target="_blank" rel="noopener noreferrer">WhatsApp</a>
        </div>
      </section>

      <Transition name="gallery-fade">
      <div
        v-if="galleryOpen && devPhotos.length"
        style="position:fixed;inset:0;z-index:1300;background:rgba(12,6,4,0.92);display:flex;align-items:center;justify-content:center;padding:24px;"
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
      </Transition>

      <Transition name="lot-modal">
        <div
          v-if="selectedLot || (selectedGroupContext && modalMode === 'group-picker')"
          class="site-lot-modal-backdrop"
          style="position:fixed;inset:0;z-index:1200;background:rgba(28,10,6,0.75);display:flex;align-items:flex-end;justify-content:center;padding:0;"
          @click.self="closeModal"
        >
        <div
          class="site-modal-panel"
          style="width:100%;max-width:560px;max-height:90vh;overflow-y:auto;background:var(--bg-page);border-radius:20px 20px 0 0;margin:0 auto;padding-bottom:env(safe-area-inset-bottom);"
        >
          <!-- MODO PICKER: lista de lotes do grupo -->
          <template v-if="modalMode === 'group-picker' && selectedGroupContext">
            <div style="padding:20px 24px 12px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border-light);">
              <div>
                <span style="font-size:0.65rem;color:var(--accent-dark);font-weight:700;text-transform:uppercase;letter-spacing:0.8px;">
                  {{ selectedGroupContext.label }}
                </span>
                <p style="font-size:0.82rem;color:var(--text-secondary);margin:4px 0 0;">
                  {{ groupAvailabilityText(selectedGroupContext) }}
                </p>
              </div>
              <button
                type="button"
                style="background:rgba(0,0,0,0.06);border:none;color:var(--text-primary);width:32px;height:32px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;"
                aria-label="Fechar"
                @click="closeModal"
              >
                <XMarkIcon style="width:18px;height:18px;" />
              </button>
            </div>

            <div style="overflow-y:auto;max-height:calc(90vh - 80px);padding:12px 16px 24px;">
              <p style="font-size:0.8rem;color:var(--text-secondary);margin:8px 0 16px;line-height:1.5;">
                Escolha o lote específico para ver detalhes, simular e enviar interesse:
              </p>

              <div style="display:flex;flex-direction:column;gap:10px;">
                <button
                  v-for="lot in pickerLots"
                  :key="lot.id"
                  type="button"
                  class="lot-picker-item"
                  :class="{
                    'lot-picker-item--available': lot.status === 'available',
                    'lot-picker-item--unavailable': lot.status !== 'available',
                  }"
                  @click="lot.status === 'available' ? selectLotFromPicker(lot) : null"
                >
                  <div class="lot-picker-item__thumb">
                    <img
                      v-if="lot.cover_photo"
                      :src="lot.cover_photo"
                      :alt="`Lote ${lot.number}`"
                      loading="lazy"
                    >
                    <div v-else class="lot-picker-item__thumb-empty">
                      <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" style="width:20px;height:20px;color:rgba(201,168,76,0.4);">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                      </svg>
                    </div>
                  </div>

                  <div class="lot-picker-item__info">
                    <div class="lot-picker-item__header">
                      <span class="lot-picker-item__number">Lote {{ lot.number }}</span>
                      <span
                        class="lot-picker-item__status"
                        :style="lotStatusStyle(lot.status)"
                      >{{ lotStatusLabel(lot.status) }}</span>
                    </div>
                    <p class="lot-picker-item__address">{{ lot.full_address }}</p>
                    <div class="lot-picker-item__footer">
                      <span v-if="lot.area" class="lot-picker-item__area">{{ lot.area }}m²</span>
                      <span class="lot-picker-item__price">
                        {{ lot.total_value ? formatCurrencyCents(lot.total_value) : 'Consulte' }}
                      </span>
                      <span v-if="lot.status === 'available'" class="lot-picker-item__cta">
                        Ver detalhes →
                      </span>
                    </div>
                  </div>
                </button>
              </div>

              <div
                v-if="pickerLots.every(l => l.status !== 'available')"
                style="margin-top:16px;padding:16px;background:var(--bg-section);border-radius:10px;text-align:center;"
              >
                <p style="font-size:0.85rem;color:var(--text-secondary);margin-bottom:12px;">
                  Todos os lotes deste tipo estão {{ pickerLots.every(l => l.status === 'sold') ? 'vendidos' : 'indisponíveis' }}.
                  Fale com o corretor para saber sobre novos lotes.
                </p>
                <a
                  :href="`${waUrl}?text=${encodeURIComponent(`Olá! Tenho interesse em lotes do tipo ${selectedGroupContext.label} no ${dev?.name}. Há previsão de novos lotes?`)}`"
                  class="btn-whatsapp"
                  style="display:inline-flex;justify-content:center;"
                  target="_blank"
                  rel="noopener noreferrer"
                >Falar no WhatsApp</a>
              </div>
            </div>
          </template>

          <!-- MODO DETALHE: simulador + lead form -->
          <template v-else-if="modalMode === 'lot-detail' && selectedLot">
          <div style="height:220px;background:#1C0A06;border-radius:20px 20px 0 0;overflow:hidden;position:relative;">
            <img
              v-if="selectedLot.cover_photo"
              :src="selectedLot.cover_photo"
              style="width:100%;height:100%;object-fit:cover;"
              alt="Foto do lote"
            >
            <button
              v-if="selectedGroupContext"
              type="button"
              style="position:absolute;top:12px;left:12px;background:rgba(0,0,0,0.4);border:none;color:#fff;padding:5px 10px;border-radius:8px;cursor:pointer;font-size:0.75rem;font-weight:600;display:flex;align-items:center;gap:4px;"
              @click="modalMode = 'group-picker'; selectedLot = null;"
            >
              ← Lotes
            </button>
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
            <template v-if="selectedGroupContext">
              <span style="font-size:0.7rem;color:var(--accent-dark);font-weight:600;text-transform:uppercase;">
                Tipo {{ selectedGroupContext.label }}
              </span>
              <p style="font-size:0.85rem;color:var(--text-secondary);margin:6px 0 12px;">
                {{ groupAvailabilityText(selectedGroupContext) }} · {{ formatGroupPrice(selectedGroupContext) }}
              </p>
            </template>
            <span v-else style="font-size:0.7rem;color:var(--accent-dark);font-weight:600;text-transform:uppercase;">
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

            <!-- Mini simulador inline -->
            <div class="modal-sim">
              <p class="modal-sim__title">
                Simular parcelamento
              </p>
              <div class="modal-sim__row">
                <div class="modal-sim__field">
                  <label class="modal-sim__label">Entrada</label>
                  <SiteSelect
                    v-model="sim.downPct"
                    :options="[
                      { value: 10, label: '10%' },
                      { value: 20, label: '20%' },
                      { value: 30, label: '30%' },
                      { value: 40, label: '40%' },
                      { value: 50, label: '50%' },
                    ]"
                  />
                </div>
                <div class="modal-sim__field">
                  <label class="modal-sim__label">Parcelas</label>
                  <SiteSelect
                    v-model="sim.months"
                    :options="[
                      { value: 12, label: '12x' },
                      { value: 24, label: '24x' },
                      { value: 36, label: '36x' },
                      { value: 48, label: '48x' },
                      { value: 60, label: '60x' },
                      { value: 120, label: '120x' },
                    ]"
                  />
                </div>
              </div>
              <div v-if="sim.total > 0" class="modal-sim__result">
                <span>Entrada: <strong>{{ formatCurrencyReais(sim.total * sim.downPct / 100) }}</strong></span>
                <span class="modal-sim__highlight">{{ sim.months }}x de <strong>{{ formatCurrencyReais(sim.total * (1 - sim.downPct / 100) / sim.months) }}</strong></span>
              </div>
            </div>

            <!-- Lote disponível: formulário de interesse -->
            <div v-if="selectedLot.status === 'available'">
              <div v-if="!leadSent">
                <p style="font-weight:600;font-size:0.9rem;color:var(--text-primary);margin:16px 0 12px;">
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

            <!-- Lote vendido/reservado: aviso + WhatsApp -->
            <div v-else style="margin-top:12px;">
              <p style="font-size:0.85rem;color:var(--text-secondary);margin-bottom:12px;line-height:1.5;">
                <span v-if="selectedGroupContext && selectedGroupContext.available_count > 0">
                  Este lote específico está {{ lotStatusLabel(selectedLot.status).toLowerCase() }}, mas há
                  <strong style="color:#25d366;">{{ selectedGroupContext.available_count }} disponível{{ selectedGroupContext.available_count !== 1 ? 'is' : '' }}</strong>
                  neste tipo. Fale com o corretor.
                </span>
                <span v-else>
                  Este lote está {{ lotStatusLabel(selectedLot.status).toLowerCase() }}. Entre em contato para verificar outros disponíveis.
                </span>
              </p>
              <a
                :href="`${waUrl}?text=${encodeURIComponent(simWaText)}`"
                class="btn-whatsapp"
                style="width:100%;justify-content:center;text-decoration:none;"
                target="_blank"
                rel="noopener noreferrer"
              >
                Falar no WhatsApp
              </a>
            </div>
          </div>
          </template>

        </div>
        </div>
      </Transition>
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
      style="position:fixed;inset:0;z-index:1100;background:rgba(28,10,6,0.3);display:flex;align-items:center;justify-content:center;"
    >
      <div style="background:white;padding:12px 20px;border-radius:10px;font-size:0.9rem;color:var(--text-secondary);">
        Carregando detalhes...
      </div>
    </div>
  </div>
</template>

<style scoped>
.dev-detail-hero {
  padding-top: 76px;
  background: var(--bg-page);
  padding-bottom: 4px;
}

@media (min-width: 640px) {
  .dev-detail-hero {
    padding-top: 88px;
  }
}

.dev-detail-hero__banner {
  margin: 0;
  border-radius: 0;
  overflow: hidden;
  box-shadow: none;
}

@media (min-width: 640px) {
  .dev-detail-hero__banner {
    margin: 0 5%;
    border-radius: 16px;
    box-shadow: 0 8px 32px rgba(28, 10, 6, 0.08);
    max-height: 360px;
  }
}

@media (min-width: 1024px) {
  .dev-detail-hero {
    padding-top: 88px;
    padding-bottom: 0;
  }

  .dev-detail-hero__inner {
    max-width: 1200px;
    margin: 0 auto;
    padding: 40px 5% 48px;
    display: grid;
    grid-template-columns: 55% 1fr;
    gap: 40px;
    align-items: start;
  }

  .dev-detail-hero__banner {
    margin: 0;
    border-radius: 16px;
    max-height: none;
    box-shadow: 0 8px 32px rgba(28, 10, 6, 0.08);
  }

  .dev-detail-hero__photo,
  .dev-detail-hero__video {
    height: 380px;
  }

  .dev-detail-hero__content {
    max-width: none;
    padding: 0;
    position: sticky;
    top: 100px;
  }

  .dev-detail-hero__wa-primary {
    width: auto;
  }
}

.dev-detail-hero__photo,
.dev-detail-hero__video {
  display: block;
  width: 100%;
  height: 200px;
  object-fit: cover;
}

.dev-detail-hero__video {
  background: #1c0a06;
}

@media (min-width: 640px) {
  .dev-detail-hero__photo,
  .dev-detail-hero__video {
    height: 260px;
  }
}

.dev-detail-hero__photo--empty {
  background: linear-gradient(135deg, var(--bg-section) 0%, #e8e0d4 100%);
}

.dev-detail-hero__content {
  max-width: 40rem;
  margin: 0 auto;
  padding: 24px 1.25rem 28px;
}

@media (min-width: 640px) {
  .dev-detail-hero__content {
    padding: 28px 5% 36px;
  }
}

.dev-detail-hero__meta {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 10px;
  margin-bottom: 18px;
}

@media (min-width: 480px) {
  .dev-detail-hero__meta {
    flex-direction: row;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px 14px;
  }
}

.dev-detail-hero__back {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  color: var(--text-secondary);
  text-decoration: none;
  font-size: 0.8125rem;
  font-weight: 500;
}

.dev-detail-hero__back:hover {
  color: var(--accent);
}

.dev-detail-hero__badge {
  display: inline-block;
  font-size: 0.625rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  color: var(--accent-dark);
  padding: 5px 12px;
  border-radius: 100px;
  border: 1px solid rgba(201, 168, 76, 0.35);
  background: rgba(201, 168, 76, 0.08);
  margin: 0;
  flex-shrink: 0;
}

.dev-detail-hero__title {
  font-size: clamp(1.5rem, 6vw, 2.75rem);
  font-weight: 800;
  color: var(--text-primary);
  letter-spacing: -0.03em;
  line-height: 1.1;
  margin: 0 0 8px;
}

.dev-detail-hero__location {
  color: var(--text-secondary);
  font-size: 0.9375rem;
  margin: 0 0 14px;
  line-height: 1.45;
}

.dev-detail-hero__description {
  color: var(--text-secondary);
  font-size: 0.875rem;
  line-height: 1.65;
  margin: 0 0 16px;
  display: -webkit-box;
  -webkit-line-clamp: 5;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

@media (min-width: 768px) {
  .dev-detail-hero__description {
    display: block;
    -webkit-line-clamp: unset;
    overflow: visible;
    font-size: 0.9375rem;
  }
}

.dev-detail-hero__summary {
  font-size: 0.8125rem;
  color: var(--text-secondary);
  margin: 0 0 20px;
  line-height: 1.5;
}

.dev-detail-hero__summary-sep {
  margin: 0 0.35em;
  opacity: 0.45;
}

.dev-detail-hero__summary-avail {
  font-weight: 600;
  color: #166534;
}

.dev-detail-hero__summary-avail--zero {
  color: var(--text-secondary);
  font-weight: 500;
}

.dev-detail-hero__actions {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: 12px;
}

@media (min-width: 640px) {
  .dev-detail-hero__actions {
    flex-direction: row;
    flex-wrap: wrap;
    align-items: center;
    gap: 14px 18px;
  }
}

.dev-detail-hero__wa-primary {
  text-decoration: none;
  width: 100%;
  justify-content: center;
}

@media (min-width: 640px) {
  .dev-detail-hero__wa-primary {
    width: auto;
    min-width: 200px;
  }
}

.dev-detail-hero__ghost {
  border: 1px solid rgba(201, 168, 76, 0.35);
  background: transparent;
  color: var(--accent-dark);
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  text-align: center;
  padding: 10px 12px;
  border-radius: 10px;
}

.dev-detail-hero__ghost:hover {
  background: rgba(201, 168, 76, 0.1);
  color: var(--text-primary);
}

.dev-detail-gallery {
  background: var(--bg-page);
  padding: 26px 1.25rem 30px;
  border-top: 1px solid var(--border-light);
}

@media (min-width: 640px) {
  .dev-detail-gallery {
    padding: 34px 5% 32px;
  }
}

.dev-detail-gallery__label {
  margin-bottom: 12px;
}

.dev-detail-gallery__grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
  max-width: 42rem;
  margin: 0 auto;
}

@media (min-width: 640px) {
  .dev-detail-gallery__grid {
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    max-width: 56rem;
  }
}

@media (min-width: 1024px) {
  .dev-detail-gallery {
    padding: 40px 5% 44px;
  }

  .dev-detail-gallery__inner {
    max-width: 1200px;
    margin: 0 auto;
  }

  .dev-detail-gallery__grid {
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    max-width: none;
  }
}

.dev-detail-gallery__thumb {
  padding: 0;
  border: none;
  border-radius: 12px;
  overflow: hidden;
  cursor: pointer;
  aspect-ratio: 4 / 3;
  background: #e8e0d4;
}

.dev-detail-gallery__thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.dev-detail-lots {
  background: white;
  padding: 32px 1.25rem 40px;
  border-top: 1px solid var(--border-light);
}

/* ── Lots + Map two-column layout (desktop) ─────────────────────────────── */
.dev-detail-lots-map-row {
  display: contents;
}

@media (min-width: 1024px) {
  .dev-detail-lots-map-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    align-items: start;
    max-width: 1200px;
    margin: 0 auto;
    gap: 0;
  }

  .dev-detail-lots-map-row .dev-detail-lots {
    padding: 48px 40px 56px 5%;
    border-right: 1px solid var(--border-light);
  }

  .dev-detail-lots-map-row .dev-detail-map {
    padding: 48px 5% 56px 40px;
    position: sticky;
    top: 88px;
    max-height: calc(100vh - 88px);
    overflow-y: auto;
  }

  .dev-detail-lots-map-row .dev-detail-map__canvas {
    height: min(60vh, 480px);
  }

  .dev-detail-lots__maplink {
    display: none;
  }

  .dev-detail-lots-map-row:not(:has(.dev-detail-map)) {
    display: block;
    max-width: 1200px;
    margin: 0 auto;
  }

  .dev-detail-lots-map-row:not(:has(.dev-detail-map)) .dev-detail-lots {
    padding: 48px 5% 56px;
    border-right: none;
  }

  .dev-detail-lots-map-row:not(:has(.dev-detail-map)) .dev-detail-lots__grid {
    grid-template-columns: repeat(3, 1fr);
  }
}

@media (min-width: 640px) {
  .dev-detail-lots {
    padding: 48px 5% 56px;
  }
}

.dev-detail-lots__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 8px;
}

.dev-detail-lots__head-text .section-label {
  margin-bottom: 4px;
}

.dev-detail-lots__title {
  font-size: clamp(1.25rem, 4.5vw, 1.75rem);
  font-weight: 800;
  color: var(--text-primary);
  margin: 0;
  line-height: 1.15;
  letter-spacing: -0.02em;
}

.dev-detail-lots__maplink {
  flex-shrink: 0;
  margin-top: 2px;
  border: 1px solid rgba(201, 168, 76, 0.4);
  background: transparent;
  color: var(--accent-dark);
  font-size: 0.8125rem;
  font-weight: 700;
  cursor: pointer;
  padding: 8px 12px;
  border-radius: 10px;
}

.dev-detail-lots__maplink:hover {
  background: rgba(201, 168, 76, 0.08);
}

.dev-detail-lots__sub {
  color: var(--text-secondary);
  font-size: 0.8125rem;
  max-width: 36rem;
  margin: 0 0 20px;
  line-height: 1.55;
}

@media (min-width: 640px) {
  .dev-detail-lots__sub {
    font-size: 0.875rem;
    margin-bottom: 24px;
  }
}

.dev-detail-lots__filters {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: 12px;
  margin-bottom: 22px;
}

@media (min-width: 640px) {
  .dev-detail-lots__filters {
    flex-direction: row;
    flex-wrap: wrap;
    align-items: center;
    gap: 14px;
    margin-bottom: 26px;
  }
}

.dev-detail-lots__filter-select {
  width: 100%;
}

@media (min-width: 640px) {
  .dev-detail-lots__filter-select {
    width: auto;
    min-width: 200px;
  }
}

.dev-detail-lots__checkbox {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.8125rem;
  color: var(--text-secondary);
  cursor: pointer;
}

.dev-detail-lots__checkbox input {
  accent-color: var(--accent);
}

.dev-detail-lots__grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 14px;
}

@media (min-width: 480px) {
  .dev-detail-lots__grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (min-width: 992px) {
  .dev-detail-lots__grid {
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
  }
}

.dev-detail-group-card {
  display: flex;
  flex-direction: column;
  text-align: left;
  background: var(--bg-page);
  border: 1px solid var(--border-light);
  border-radius: 14px;
  overflow: hidden;
  cursor: pointer;
  padding: 0;
  transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
}

.dev-detail-group-card:hover {
  transform: translateY(-3px);
  border-color: rgba(201, 168, 76, 0.45);
  box-shadow: 0 12px 28px rgba(28, 10, 6, 0.08);
}

.dev-detail-group-card__thumb {
  height: 128px;
  background: var(--bg-section);
  position: relative;
  overflow: hidden;
}

@media (min-width: 640px) {
  .dev-detail-group-card__thumb {
    height: 148px;
  }
}

@media (min-width: 992px) {
  .dev-detail-group-card__thumb {
    height: 160px;
  }
}

.dev-detail-group-card__thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.dev-detail-group-card__badge {
  position: absolute;
  top: 8px;
  right: 8px;
  font-size: 0.65rem;
  font-weight: 700;
  padding: 4px 9px;
  border-radius: 5px;
  color: #fff;
}

.dev-detail-group-card__badge--available {
  background: #25d366;
}

.dev-detail-group-card__badge--reserved {
  background: #f59e0b;
}

.dev-detail-group-card__badge--sold {
  background: #dc2626;
}

.dev-detail-group-card__body {
  padding: 12px 14px 14px;
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

@media (min-width: 640px) {
  .dev-detail-group-card__body {
    padding: 16px;
    gap: 6px;
  }
}

.dev-detail-group-card__label {
  font-size: 0.9375rem;
  font-weight: 800;
  color: var(--text-primary);
  margin: 0;
}

@media (min-width: 640px) {
  .dev-detail-group-card__label {
    font-size: 1.05rem;
  }
}

.dev-detail-group-card__area {
  font-size: 0.82rem;
  color: var(--text-secondary);
  margin: 0;
}

.dev-detail-group-card__counts {
  font-size: 0.78rem;
  color: var(--text-secondary);
  margin: 0;
  line-height: 1.45;
}

.dev-detail-group-card__price-wrap {
  margin-top: auto;
  padding-top: 10px;
  border-top: 1px solid var(--border-light);
}

.dev-detail-group-card__price {
  font-size: 1.15rem;
  font-weight: 800;
  color: var(--accent);
}

.dev-detail-group-card__cta {
  margin-top: 8px;
  font-size: 0.72rem;
  font-weight: 600;
  color: var(--accent-dark);
}

@media (min-width: 640px) {
  .dev-detail-group-card__cta {
    margin-top: 10px;
    font-size: 0.78rem;
  }
}

.dev-detail-map {
  background: var(--bg-page);
  padding: 32px 1.25rem 40px;
  /* Isolate Leaflet's internal z-index stack so it doesn't bleed above fixed modals */
  isolation: isolate;
  position: relative;
  z-index: 0;
}

@media (min-width: 640px) {
  .dev-detail-map {
    padding: 44px 5% 48px;
  }
}

.dev-detail-map__title {
  font-size: clamp(1.1rem, 3.5vw, 1.35rem);
  font-weight: 800;
  color: var(--text-primary);
  margin: 0 0 6px;
}

.dev-detail-map__sub {
  font-size: 0.8125rem;
  color: var(--text-secondary);
  margin: 0 0 12px;
  max-width: 36rem;
  line-height: 1.5;
}

.dev-detail-map__legend {
  display: flex;
  flex-wrap: wrap;
  gap: 10px 14px;
  margin-bottom: 12px;
  font-size: 0.75rem;
  color: var(--text-secondary);
}

.dev-detail-map__legend span {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.dev-detail-map__dot {
  display: inline-block;
  width: 12px;
  height: 12px;
  border-radius: 3px;
  flex-shrink: 0;
}

.dev-detail-map__dot--available {
  background: #25d366;
}

.dev-detail-map__dot--reserved {
  background: #f59e0b;
}

.dev-detail-map__dot--sold {
  background: #dc2626;
}

.dev-detail-map__canvas {
  height: min(52vh, 320px);
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid var(--border-light);
  background: #e8e4d8;
}

@media (min-width: 640px) {
  .dev-detail-map__canvas {
    height: 400px;
    border-radius: 16px;
  }
}

@media (min-width: 1024px) {
  .dev-detail-map__canvas {
    height: 420px;
  }
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

.dev-detail-sim__card {
  max-width: 640px;
  margin: 0 auto;
}

.dev-detail-sim__card > .dev-detail-sim__field:first-child {
  margin-bottom: 14px;
}

.dev-detail-sim__row {
  display: grid;
  grid-template-columns: 1fr;
  gap: 14px;
  margin-bottom: 16px;
}

@media (min-width: 640px) {
  .dev-detail-sim__row {
    grid-template-columns: 1fr 1fr;
  }
}

.dev-detail-sim__field {
  margin-bottom: 14px;
}

.dev-detail-sim__label {
  display: flex;
  align-items: center;
  gap: 6px;
  font-weight: 600;
  font-size: 0.8125rem;
  margin-bottom: 6px;
  color: var(--text-primary);
}

.dev-detail-sim__step {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: var(--accent-dark);
  color: #fff;
  font-size: 0.65rem;
  font-weight: 800;
  flex-shrink: 0;
}

.dev-detail-sim__select {
  width: 100%;
}

.dev-detail-sim__money-input {
  width: 100%;
  font-weight: 600;
  font-size: 1rem;
  color: var(--text-primary);
}

/* Payment mode toggle */
.dev-detail-sim__mode-group {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}

.dev-detail-sim__mode-item {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 10px 12px;
  border: 1.5px solid var(--border-accent);
  border-radius: 10px;
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--text-secondary);
  cursor: pointer;
  transition: all 0.18s;
  text-align: center;
  background: var(--bg-section);
}

.dev-detail-sim__mode-item.active {
  border-color: var(--accent-dark);
  background: rgba(201, 168, 76, 0.12);
  color: var(--text-primary);
}

.dev-detail-sim__note {
  font-size: 0.75rem;
  color: var(--text-secondary);
  text-align: center;
  margin: 0 0 14px;
  line-height: 1.5;
}

.dev-detail-sim__results {
  display: grid;
  grid-template-columns: 1fr;
  gap: 10px;
  background: var(--bg-section);
  border-radius: 12px;
  padding: 14px;
  margin-bottom: 16px;
}

@media (min-width: 480px) {
  .dev-detail-sim__results {
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    padding: 16px;
  }
}

.dev-detail-sim__result-cell {
  text-align: center;
}

.dev-detail-sim__result-cell--highlight {
  background: var(--accent);
  border-radius: 10px;
  padding: 10px 8px;
}

.dev-detail-sim__result-cell--full {
  grid-column: 1 / -1;
  padding: 16px;
}

.dev-detail-sim__result-label {
  font-size: 0.625rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--text-secondary);
  display: block;
  margin-bottom: 4px;
}

.dev-detail-sim__result-label--on-accent {
  color: rgba(255, 255, 255, 0.75);
}

.dev-detail-sim__result-value {
  font-size: 0.9375rem;
  color: var(--text-primary);
}

.dev-detail-sim__result-value--on-accent {
  font-size: 1rem;
  color: #fff;
}

.dev-detail-sim__wa {
  width: 100%;
  justify-content: center;
}

@media (min-width: 1024px) {
  .simulador-section {
    padding: 80px 5%;
  }

  .simulador-header {
    max-width: 640px;
    margin-left: auto;
    margin-right: auto;
    margin-bottom: 44px;
  }

  .dev-detail-sim__card {
    max-width: 800px;
  }

  .dev-detail-sim__row {
    grid-template-columns: 1fr 1fr;
  }

  .dev-detail-sim__results {
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
  }
}

/* ── Modal inline simulator ───────────────────────────────────────────────── */
.modal-sim {
  background: var(--bg-section);
  border-radius: 12px;
  padding: 14px;
  margin-bottom: 16px;
}

.modal-sim__title {
  font-size: 0.8rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--text-secondary);
  margin: 0 0 10px;
}

.modal-sim__row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  margin-bottom: 12px;
}

.modal-sim__field {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.modal-sim__label {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--text-secondary);
}

.modal-sim__result {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.85rem;
  color: var(--text-primary);
  flex-wrap: wrap;
  gap: 6px;
}

.modal-sim__highlight {
  background: var(--accent);
  color: #fff;
  padding: 4px 12px;
  border-radius: 8px;
  font-size: 0.875rem;
}

.modal-sim__highlight strong {
  font-size: 1rem;
}

.dev-detail-lots__empty {
  text-align: center;
  padding: 28px 1.25rem;
  color: var(--text-secondary);
  background: var(--bg-section);
  border-radius: 12px;
  font-size: 0.875rem;
}

.dev-detail-cta {
  background: var(--bg-section);
  padding: 28px 1.25rem 32px;
  text-align: center;
  border-top: 1px solid var(--border-light);
}

@media (min-width: 640px) {
  .dev-detail-cta {
    padding: 36px 5% 40px;
  }
}

.dev-detail-cta__inner {
  max-width: 22rem;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 14px;
}

.dev-detail-cta__line {
  margin: 0;
  font-size: 0.875rem;
  color: var(--text-secondary);
  line-height: 1.45;
}

.dev-detail-cta__btn {
  width: 100%;
  max-width: 260px;
  justify-content: center;
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

/* ── Gallery fade ─────────────────────────────────────────────────────────── */
.gallery-fade-enter-active,
.gallery-fade-leave-active {
  transition: opacity 0.22s ease;
}
.gallery-fade-enter-from,
.gallery-fade-leave-to {
  opacity: 0;
}

/* ── Bottom-sheet slide-up transition ─────────────────────────────────────── */
.lot-modal-enter-active {
  transition: background-color 0.3s ease;
}
.lot-modal-leave-active {
  transition: background-color 0.25s ease;
}
.lot-modal-enter-from {
  background-color: transparent !important;
}
.lot-modal-leave-to {
  background-color: transparent !important;
}

/* Panel itself slides */
.lot-modal-enter-active .site-modal-panel {
  transition: transform 0.38s cubic-bezier(0.32, 0.72, 0, 1);
}
.lot-modal-leave-active .site-modal-panel {
  transition: transform 0.26s cubic-bezier(0.4, 0, 1, 1);
}
.lot-modal-enter-from .site-modal-panel,
.lot-modal-leave-to .site-modal-panel {
  transform: translateY(100%);
}

/* ── Lot picker ───────────────────────────────────────────────────────────── */
.lot-picker-item {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  text-align: left;
  background: var(--bg-page);
  border: 1.5px solid var(--border-light);
  border-radius: 12px;
  padding: 10px 12px;
  cursor: pointer;
  transition: border-color 0.18s, background 0.18s, transform 0.15s;
}

.lot-picker-item--available:hover {
  border-color: rgba(201, 168, 76, 0.55);
  background: rgba(201, 168, 76, 0.04);
  transform: translateY(-1px);
}

.lot-picker-item--unavailable {
  opacity: 0.55;
  cursor: default;
}

.lot-picker-item__thumb {
  width: 60px;
  height: 60px;
  flex-shrink: 0;
  border-radius: 8px;
  overflow: hidden;
  background: var(--bg-section);
}

.lot-picker-item__thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.lot-picker-item__thumb-empty {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--bg-section);
}

.lot-picker-item__info {
  flex: 1;
  min-width: 0;
}

.lot-picker-item__header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 3px;
}

.lot-picker-item__number {
  font-size: 0.9rem;
  font-weight: 700;
  color: var(--text-primary);
}

.lot-picker-item__status {
  font-size: 0.6rem;
  font-weight: 700;
  padding: 2px 7px;
  border-radius: 4px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.lot-picker-item__address {
  font-size: 0.76rem;
  color: var(--text-secondary);
  margin: 0 0 5px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  line-height: 1.4;
}

.lot-picker-item__footer {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.lot-picker-item__area {
  font-size: 0.72rem;
  color: var(--text-secondary);
}

.lot-picker-item__price {
  font-size: 0.88rem;
  font-weight: 700;
  color: var(--accent);
}

.lot-picker-item__cta {
  margin-left: auto;
  font-size: 0.72rem;
  font-weight: 600;
  color: var(--accent-dark);
}

/* ── Desktop containers ─────────────────────────────────────────────────── */
@media (min-width: 1024px) {
  .dev-detail-cta {
    padding: 48px 5%;
  }

  .dev-detail-lots__head {
    align-items: center;
  }

  .dev-detail-lots__title {
    font-size: 1.5rem;
  }

  .dev-detail-map__title {
    font-size: 1.2rem;
  }

  .dev-detail-lots-map-row .dev-detail-lots__grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (min-width: 1280px) {
  .dev-detail-lots-map-row {
    max-width: 1320px;
  }

  .dev-detail-hero__inner {
    max-width: 1320px;
  }

  .dev-detail-gallery__inner {
    max-width: 1320px;
  }
}
</style>
