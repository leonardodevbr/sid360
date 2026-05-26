<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import { useToast } from 'vue-toastification';
import { XMarkIcon } from '@heroicons/vue/24/outline';
import publicApi from '@/services/publicApi';
import { parseDevelopmentIdFromSlug } from '@/site/utils/slug';

const route = useRoute();
const toast = useToast();

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

const availableLots = computed(() => lots.value.filter((lot) => lot.status === 'available'));

const filteredLots = computed(() => lots.value.filter((lot) => {
  if (onlyAvailable.value && lot.status !== 'available') {
    return false;
  }
  if (filterZone.value && lot.zone?.id !== Number(filterZone.value)) {
    return false;
  }
  if (filterValue.value && lot.total_value > Number(filterValue.value) * 100) {
    return false;
  }
  return true;
}));

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

async function load() {
  loading.value = true;
  const developmentId = parseDevelopmentIdFromSlug(route.params.slug);

  if (!developmentId) {
    dev.value = null;
    loading.value = false;
    return;
  }

  try {
    const { data } = await publicApi.get(`/public/developments/${developmentId}`);
    dev.value = data.development;
    lots.value = data.lots ?? [];

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
  } finally {
    loading.value = false;
  }
}

watch(() => route.params.slug, () => load());

onMounted(() => load());
</script>

<template>
  <div>
    <div v-if="loading" style="padding:80px;text-align:center;color:var(--text-secondary);">
      Carregando loteamento...
    </div>

    <template v-else-if="dev">
      <section style="position:relative;min-height:380px;background:var(--bg-dark);overflow:hidden;display:flex;align-items:flex-end;">
        <img
          v-if="dev.cover_photo"
          :src="dev.cover_photo"
          :alt="dev.name"
          style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:0.4;"
        >
        <div style="position:absolute;inset:0;background:linear-gradient(to bottom,transparent 30%,rgba(28,10,6,0.95));" />
        <div style="position:relative;z-index:2;padding:40px 5%;width:100%;">
          <RouterLink
            :to="{ name: 'site.loteamentos' }"
            style="display:inline-flex;align-items:center;gap:6px;color:rgba(250,245,238,0.6);text-decoration:none;font-size:0.8rem;margin-bottom:16px;"
          >
            ← Todos os loteamentos
          </RouterLink>
          <h1 style="font-size:clamp(1.8rem,4vw,2.8rem);font-weight:800;color:#FAF5EE;letter-spacing:-1px;margin-bottom:8px;">
            {{ dev.name }}
          </h1>
          <p v-if="dev.location" style="color:rgba(250,245,238,0.6);font-size:0.9rem;">
            {{ dev.location }}
          </p>
          <p v-if="dev.description" style="color:rgba(250,245,238,0.55);font-size:0.85rem;margin-top:12px;max-width:640px;line-height:1.6;">
            {{ dev.description }}
          </p>
          <div style="display:flex;gap:12px;margin-top:16px;flex-wrap:wrap;">
            <span style="background:rgba(201,168,76,0.15);border:1px solid rgba(201,168,76,0.3);color:#EDD882;font-size:0.75rem;font-weight:600;padding:5px 12px;border-radius:100px;">
              {{ availableLots.length }} lotes disponíveis
            </span>
            <span style="background:rgba(255,255,255,0.08);color:rgba(250,245,238,0.6);font-size:0.75rem;padding:5px 12px;border-radius:100px;">
              {{ lots.length }} lotes no total
            </span>
          </div>
        </div>
      </section>

      <section style="background:var(--bg-page);padding:48px 5%;">
        <div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:32px;align-items:center;">
          <h2 style="font-size:1.1rem;font-weight:700;color:var(--text-primary);margin:0;flex:1;min-width:200px;">
            Lotes disponíveis
          </h2>

          <select
            v-if="zones.length"
            v-model="filterZone"
            class="site-select"
          >
            <option value="">
              Todas as quadras
            </option>
            <option v-for="zone in zones" :key="zone.id" :value="zone.id">
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

          <label style="display:flex;align-items:center;gap:6px;font-size:0.85rem;color:var(--text-secondary);cursor:pointer;">
            <input v-model="onlyAvailable" type="checkbox" style="accent-color:var(--accent);">
            Somente disponíveis
          </label>
        </div>

        <div
          v-if="filteredLots.length"
          style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;"
        >
          <div
            v-for="lot in filteredLots"
            :key="lot.id"
            class="lot-card-site"
            @click="openLot(lot)"
          >
            <div style="height:160px;background:#1C0A06;position:relative;overflow:hidden;">
              <img
                v-if="lot.cover_photo"
                :src="lot.cover_photo"
                :alt="`Lote ${lot.number}`"
                style="width:100%;height:100%;object-fit:cover;"
                loading="lazy"
              >
              <div
                v-else
                style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;"
              >
                <svg style="width:40px;height:40px;color:rgba(201,168,76,0.35);" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                </svg>
              </div>
              <span
                :style="`position:absolute;top:8px;right:8px;font-size:0.65rem;font-weight:700;padding:3px 8px;border-radius:5px;${lotStatusStyle(lot.status)}`"
              >
                {{ lotStatusLabel(lot.status) }}
              </span>
            </div>

            <div style="padding:16px;flex:1;display:flex;flex-direction:column;gap:6px;">
              <span style="font-size:0.65rem;color:var(--accent-dark);font-weight:600;text-transform:uppercase;letter-spacing:0.5px;">
                {{ lot.zone?.name ?? lot.block ?? 'Lote' }}
              </span>
              <p style="font-size:0.95rem;font-weight:700;color:#FAF5EE;margin:0;">
                Lote {{ lot.number }}
              </p>
              <p v-if="lot.area" style="font-size:0.8rem;color:rgba(250,245,238,0.5);margin:0;">
                {{ lot.area }}m²
              </p>
              <div style="margin-top:auto;padding-top:10px;border-top:1px solid rgba(201,168,76,0.12);">
                <span style="font-size:0.65rem;color:rgba(237,216,130,0.7);display:block;margin-bottom:2px;">A partir de</span>
                <span style="font-size:1.2rem;font-weight:800;color:#EDD882;">
                  {{ lot.total_value ? formatCurrencyCents(lot.total_value) : 'Consulte' }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <div v-else style="text-align:center;padding:48px;color:var(--text-secondary);">
          Nenhum lote encontrado com esses filtros.
        </div>
      </section>

      <section id="simulador" style="background:white;padding:72px 5%;text-align:center;">
        <div style="max-width:560px;margin:0 auto 36px;">
          <h2 style="font-size:clamp(1.5rem,3vw,2rem);font-weight:800;color:var(--text-primary);letter-spacing:-0.5px;margin-bottom:10px;">
            Simule seu parcelamento
          </h2>
          <p style="font-size:0.9rem;color:var(--text-secondary);line-height:1.7;">
            Calcule as parcelas do lote que você escolheu.
          </p>
        </div>

        <div style="max-width:640px;margin:0 auto;background:white;border:1px solid var(--border-light);border-radius:20px;padding:32px;text-align:left;box-shadow:0 12px 40px rgba(26,7,7,0.07);">
          <div style="margin-bottom:16px;">
            <label style="font-weight:600;font-size:0.85rem;display:block;margin-bottom:6px;">Valor do lote (R$)</label>
            <input
              v-model.number="sim.total"
              type="number"
              min="0"
              step="1000"
              class="site-input"
              :placeholder="selectedLot ? String(Math.round(selectedLot.total_value / 100)) : '0'"
            >
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
            <div>
              <label style="font-weight:600;font-size:0.85rem;display:block;margin-bottom:6px;">Entrada (%)</label>
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
              <label style="font-weight:600;font-size:0.85rem;display:block;margin-bottom:6px;">Parcelas</label>
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
            :href="`https://wa.me/5574988230151?text=${encodeURIComponent(simWaText)}`"
            target="_blank"
            rel="noopener noreferrer"
            class="btn-whatsapp"
            style="width:100%;justify-content:center;"
          >
            Enviar simulação no WhatsApp
          </a>
        </div>
      </section>

      <div
        v-if="selectedLot"
        style="position:fixed;inset:0;z-index:200;background:rgba(28,10,6,0.75);display:flex;align-items:flex-end;padding:0;"
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
                  style="width:100%;justify-content:center;"
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

    <div v-else style="padding:80px 5%;text-align:center;color:var(--text-secondary);">
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
@media (min-width: 640px) {
  .site-modal-panel {
    border-radius: 20px !important;
    margin: 16px auto !important;
  }
}
</style>
