<template>
  <div class="space-y-6 pb-10">
    <div class="flex items-center gap-4">
      <button type="button" class="rounded-lg p-2 hover:bg-slate-100" @click="goBack">
        <ArrowLeftIcon class="h-5 w-5 text-slate-600" />
      </button>
      <div>
        <h2 class="text-lg font-semibold text-slate-800">{{ isEdit ? 'Editar lote' : 'Novo lote' }}</h2>
        <p class="text-xs text-slate-500">
          {{ isEdit ? 'Atualize os dados e a demarcação' : 'Cadastre um novo lote' }}
        </p>
      </div>

      <div
        v-if="isOffline"
        class="ml-auto flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700"
      >
        <SignalSlashIcon class="h-3.5 w-3.5" />
        Modo offline — dados salvos localmente
      </div>

      <div
        v-else-if="hasPendingSync"
        class="ml-auto flex cursor-pointer items-center gap-1.5 rounded-full bg-blue-100 px-3 py-1 text-xs font-medium text-blue-700"
        @click="syncPending"
      >
        <ArrowPathIcon class="h-3.5 w-3.5" />
        {{ pendingCount }} pendente(s) — Sincronizar
      </div>
    </div>

    <form v-if="!loading" class="space-y-4" @submit.prevent="submit">
      <div class="card space-y-4 p-5">
        <p class="text-sm font-semibold text-slate-700">Dados do lote</p>

        <SelectInput
          v-model="form.development_id"
          label="Empreendimento"
          :options="developmentOptions"
          placeholder="Selecione o empreendimento"
          :searchable="true"
          required
          @update:model-value="handleDevelopmentChange"
        />

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <SelectInput
            v-if="selectableZones.length"
            v-model="form.zone_id"
            label="Quadra"
            :options="zoneOptions"
            placeholder="Selecione a quadra"
            :searchable="false"
            :can-clear="false"
            required
          />
          <Input
            v-else
            v-model="form.block"
            label="Bloco"
            placeholder="Ex: A"
          />
          <Input
            v-model="form.number"
            label="Número"
            required
            placeholder="Ex: QA-L01"
            :error="formErrors.number"
          />
        </div>

        <SelectInput
          v-if="streetOptions.length"
          v-model="form.street_id"
          label="Rua com frente para"
          :options="streetOptions"
          placeholder="Selecione a rua"
          :searchable="false"
        />

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <Input v-model="form.area" label="Área (m²)" type="number" step="0.01" min="0" />
            <p v-if="computedArea" class="mt-1 text-xs text-emerald-600">
              Área calculada pelo polígono: <strong>{{ computedArea }} m²</strong>
            </p>
          </div>
          <Input
            v-model="form.size_label"
            label="Medidas do lote"
            placeholder="Ex: 20×30"
          />
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <CurrencyInput v-model="form.total_value" label="Valor total" />
        </div>

        <div class="space-y-3 rounded-lg border border-slate-200 bg-slate-50 p-4">
          <p class="text-sm font-medium text-slate-700">Condições de venda</p>
          <label class="flex cursor-pointer select-none items-center gap-2">
            <input
              v-model="useDevelopmentPaymentTerms"
              type="checkbox"
              class="h-4 w-4 rounded border-slate-300 text-[#c23028] focus:ring-[#c23028]/30"
            />
            <span class="text-sm text-slate-600">Usar padrão do empreendimento</span>
          </label>
          <Input
            v-if="!useDevelopmentPaymentTerms"
            v-model="form.down_payment_percent"
            label="Entrada sugerida (%)"
            type="number"
            min="0"
            max="100"
            step="0.01"
          />
          <p v-else class="text-xs text-slate-500">
            Entrada: {{ developmentDownPaymentLabel }}% (definido no empreendimento)
          </p>
        </div>

        <SelectInput
          v-model="form.status"
          label="Status"
          :options="lotStatusFormOptions"
          :searchable="false"
        />
      </div>

      <div class="card space-y-4 overflow-hidden p-4 sm:p-5">
        <div class="flex items-center justify-between gap-2">
          <p class="text-sm font-semibold text-slate-700">Demarcação no mapa</p>
          <span v-if="hasLotDemarcation" class="text-xs font-medium text-emerald-600">
            {{ demarcationPointCount }} pontos demarcados
          </span>
        </div>

        <MapDrawingCanvas
          v-if="form.development_id && mapContextReady && lotDataReady"
          :key="mapInstanceKey"
          mode="lot"
          :coordinates="form.coordinates"
          :context-perimeter="developmentPerimeter"
          :context-streets="mappedStreets"
          :context-zones="mappedZones"
          :context-lots="mappedContextLots"
          :boundary-polygon="lotBoundaryPolygon"
          :map-center="developmentMapCenter"
          :map-zoom="developmentMapZoom"
          :demarcation-saving="savingDemarcation"
          :editing-lot-id="isEdit ? route.params.id : null"
          :feature-label="activeLotMapLabel"
          :saved-coordinates="editingLotSavedCoordinates"
          @update:coordinates="updateFormCoordinates"
          @update:area-computed="form.area_computed = $event"
          @update:gps-accuracy="gpsAccuracy = $event"
          @save-demarcation="saveLotDemarcation"
        />

        <div
          v-if="gpsAccuracy !== null"
          class="rounded-lg px-3 py-2 text-xs font-medium"
          :class="
            gpsAccuracy < 10
              ? 'bg-emerald-50 text-emerald-700'
              : gpsAccuracy < 30
                ? 'bg-amber-50 text-amber-700'
                : gpsAccuracy <= 50
                  ? 'bg-orange-50 text-orange-700'
                  : 'bg-red-50 text-red-700'
          "
        >
          Precisão GPS: ±{{ Math.round(gpsAccuracy) }}m
          {{
            gpsAccuracy < 10
              ? '— Excelente'
              : gpsAccuracy < 30
                ? '— Boa'
                : gpsAccuracy <= 50
                  ? '— Aceitável'
                  : '— Aguardando sinal melhor'
          }}
        </div>

        <p v-else-if="form.development_id && !mapContextReady" class="text-xs text-slate-400">
          Carregando mapa do empreendimento...
        </p>

        <p v-else-if="!form.development_id" class="text-xs text-slate-400">
          Selecione um empreendimento para exibir o mapa e demarcar o lote.
        </p>
      </div>

      <div v-if="isEdit" class="card space-y-3 p-5">
        <p class="text-sm font-semibold text-slate-700">Fotos do lote</p>
        <MediaGallery :endpoint="`/lots/${route.params.id}/media`" />
      </div>

      <div class="flex justify-end gap-3">
        <Button type="button" variant="outline" @click="goBack">Cancelar</Button>
        <Button type="submit" variant="primary" :disabled="saving">
          {{ saving ? 'Salvando...' : isOffline ? 'Salvar offline' : 'Salvar' }}
        </Button>
      </div>
    </form>

    <div v-else class="card p-12 text-center text-slate-500">Carregando...</div>
  </div>
</template>

<script setup>
import { ref, computed, watch, reactive, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import api from '@/services/api';
import { lotStatusFormOptions } from '@/utils/labels';
import { buildZoneTitleLabel, compareZonesByName, isLotSelectableZone } from '@/utils/zone';
import { getPolygonCentroid, normalizePolygonCoordinates } from '@/utils/mapGeometry';
import { getMappedStreets } from '@/utils/mapStreets';
import { buildLotMapLabel } from '@/utils/mapLots';
import { getApiErrorMessage } from '@/utils/apiError';
import Input from '@/components/Common/Input.vue';
import SelectInput from '@/components/Common/SelectInput.vue';
import Button from '@/components/Common/Button.vue';
import CurrencyInput from '@/components/Common/CurrencyInput.vue';
import MapDrawingCanvas from '@/components/Map/MapDrawingCanvas.vue';
import MediaGallery from '@/components/Common/MediaGallery.vue';
import {
  ArrowLeftIcon,
  ArrowPathIcon,
  SignalSlashIcon,
} from '@heroicons/vue/24/outline';

const route = useRoute();
const router = useRouter();
const toast = useToast();

const isEdit = computed(() => Boolean(route.params.id));
const loading = ref(false);
const saving = ref(false);
const savingDemarcation = ref(false);
const useDevelopmentPaymentTerms = ref(true);
const formErrors = reactive({ number: '' });

const form = ref({
  development_id: route.query.development_id ? String(route.query.development_id) : '',
  zone_id: '',
  street_id: '',
  block: '',
  number: '',
  area: '',
  size_label: '',
  total_value: 0,
  down_payment_percent: '',
  status: 'available',
  coordinates: null,
  area_computed: null,
});

const developments = ref([]);
const zones = ref([]);
const streets = ref([]);
const developmentLots = ref([]);
const loadedLotCoordinates = ref(null);
const developmentPerimeter = ref(null);
const developmentMapCenter = ref(null);
const developmentMapZoom = ref(null);
const mapContextReady = ref(false);
const lotDataReady = ref(false);
const gpsAccuracy = ref(null);

const mapInstanceKey = computed(() => {
  const lotId = isEdit.value ? String(route.params.id ?? 'edit') : 'new';
  return `${form.value.development_id}-${lotId}`;
});

const developmentOptions = computed(() =>
  developments.value.map((d) => ({ value: String(d.id), label: d.name })),
);

const selectableZones = computed(() => zones.value.filter(isLotSelectableZone));

const mappedZones = computed(() =>
  zones.value.filter((zone) => Array.isArray(zone.coordinates) && zone.coordinates.length >= 3),
);

const mappedStreets = computed(() => getMappedStreets(streets.value));

const mappedContextLots = computed(() => {
  const lots = developmentLots.value
    .map((lot) => ({
      ...lot,
      coordinates: normalizePolygonCoordinates(lot.coordinates),
    }))
    .filter((lot) => Array.isArray(lot.coordinates) && lot.coordinates.length >= 3);

  if (isEdit.value) {
    return lots.filter((lot) => String(lot.id) !== String(route.params.id));
  }

  return lots;
});

const editingLotSavedCoordinates = computed(() => {
  const fromForm = normalizePolygonCoordinates(form.value.coordinates);
  if (fromForm?.length >= 3) {
    return fromForm;
  }

  const fromLoaded = normalizePolygonCoordinates(loadedLotCoordinates.value);
  if (fromLoaded?.length >= 3) {
    return fromLoaded;
  }

  if (!isEdit.value) {
    return null;
  }

  const currentLot = developmentLots.value.find(
    (lot) => String(lot.id) === String(route.params.id),
  );

  return normalizePolygonCoordinates(currentLot?.coordinates);
});

function coordinatesEqual(a, b) {
  if (!a && !b) return true;
  if (!a || !b) return false;
  return JSON.stringify(a) === JSON.stringify(b);
}

function updateFormCoordinates(coords) {
  const normalized = normalizePolygonCoordinates(coords);

  if (normalized?.length >= 3) {
    if (coordinatesEqual(normalized, form.value.coordinates)) {
      return;
    }

    form.value.coordinates = normalized;
    loadedLotCoordinates.value = normalized;
    return;
  }

  if (coords === null) {
    if (form.value.coordinates === null && loadedLotCoordinates.value === null) {
      return;
    }

    form.value.coordinates = null;
    loadedLotCoordinates.value = null;
  }
}

const hasLotDemarcation = computed(
  () => (editingLotSavedCoordinates.value?.length ?? 0) >= 3,
);

const demarcationPointCount = computed(
  () => editingLotSavedCoordinates.value?.length ?? 0,
);

const zoneOptions = computed(() =>
  [...selectableZones.value]
    .sort(compareZonesByName)
    .map((z) => ({
      value: String(z.id),
      label: buildZoneTitleLabel(z),
    })),
);

const streetOptions = computed(() =>
  streets.value.map((street) => ({
    value: String(street.id),
    label: street.name,
  })),
);

const developmentDownPaymentLabel = computed(() => {
  const dev = developments.value.find((d) => String(d.id) === String(form.value.development_id));
  return dev?.down_payment_percent ?? 20;
});

async function loadDevelopments() {
  try {
    const { data } = await api.get('/developments', { params: { all: 1 } });
    developments.value = data.data ?? data ?? [];
  } catch {
    developments.value = [];
  }
}

async function fetchDevelopmentMapData(developmentId) {
  try {
    const { data } = await api.get(`/developments/${developmentId}`);
    return data.data ?? data;
  } catch {
    return null;
  }
}

async function handleDevelopmentChange() {
  form.value.zone_id = '';
  form.value.street_id = '';
  await loadDevelopmentMapContext();
}

function resolveZoneIdFromLegacyBlock() {
  if (form.value.zone_id || !form.value.block?.trim() || !selectableZones.value.length) {
    return;
  }

  const legacyBlock = form.value.block.trim().toLocaleLowerCase('pt-BR');

  const matchedZone = selectableZones.value.find((zone) => {
    const zoneName = String(zone.name ?? '').trim().toLocaleLowerCase('pt-BR');

    return zoneName === legacyBlock
      || zoneName.endsWith(` ${legacyBlock}`)
      || legacyBlock.endsWith(zoneName);
  });

  if (matchedZone) {
    form.value.zone_id = String(matchedZone.id);
  }
}

function getSelectedZone() {
  return selectableZones.value.find(
    (zone) => String(zone.id) === String(form.value.zone_id),
  ) ?? null;
}

const activeLotMapLabel = computed(() => {
  const number = String(form.value.number ?? '').trim();
  if (!number) {
    return null;
  }

  const selectedZone = getSelectedZone();

  return buildLotMapLabel({
    number,
    block: selectedZone?.name ?? form.value.block?.trim() ?? null,
  });
});

const lotBoundaryPolygon = computed(() => {
  const selectedZone = getSelectedZone();
  if (selectedZone?.coordinates?.length >= 3) {
    return selectedZone.coordinates;
  }

  return developmentPerimeter.value;
});

const computedArea = computed(() =>
  form.value.area_computed ? form.value.area_computed.toLocaleString('pt-BR') : null,
);

function resolveDevelopmentMapCenter(dev) {
  if (dev?.map_center?.length === 2) {
    return dev.map_center;
  }

  return getPolygonCentroid(dev?.coordinates);
}

async function loadDevelopmentLots() {
  if (!form.value.development_id) {
    developmentLots.value = [];
    return;
  }

  try {
    const { data } = await api.get(`/developments/${form.value.development_id}/lots`, {
      params: { all: 1 },
    });
    developmentLots.value = Array.isArray(data) ? data : data.data ?? [];
  } catch {
    developmentLots.value = [];
  }
}

async function loadDevelopmentMapContext() {
  mapContextReady.value = false;
  zones.value = [];
  streets.value = [];
  developmentLots.value = [];
  developmentPerimeter.value = null;
  developmentMapCenter.value = null;
  developmentMapZoom.value = null;

  if (!form.value.development_id) return;

  try {
    const [zonesRes, streetsRes] = await Promise.all([
      api.get(`/developments/${form.value.development_id}/zones`),
      api.get(`/developments/${form.value.development_id}/streets`),
      loadDevelopmentLots(),
    ]);
    zones.value = Array.isArray(zonesRes.data) ? zonesRes.data : zonesRes.data.data ?? [];
    streets.value = Array.isArray(streetsRes.data) ? streetsRes.data : streetsRes.data.data ?? [];
  } catch {
    zones.value = [];
    streets.value = [];
  }

  if (
    form.value.zone_id
    && !selectableZones.value.some((zone) => String(zone.id) === String(form.value.zone_id))
  ) {
    form.value.zone_id = '';
  }

  if (
    form.value.street_id
    && !streets.value.some((street) => String(street.id) === String(form.value.street_id))
  ) {
    form.value.street_id = '';
  }

  resolveZoneIdFromLegacyBlock();

  const dev =
    (await fetchDevelopmentMapData(form.value.development_id))
    ?? developments.value.find((d) => String(d.id) === String(form.value.development_id));

  developmentPerimeter.value = dev?.coordinates?.length ? dev.coordinates : null;
  developmentMapCenter.value = resolveDevelopmentMapCenter(dev);
  developmentMapZoom.value = dev?.map_zoom ?? 17;
  syncCoordinatesFromDevelopmentLots();
  mapContextReady.value = true;
}

function syncCoordinatesFromDevelopmentLots() {
  const coords = editingLotSavedCoordinates.value;
  if (!coords?.length) {
    return;
  }

  if ((form.value.coordinates?.length ?? 0) >= 3) {
    return;
  }

  form.value.coordinates = coords;

  if (coords?.length >= 3) {
    loadedLotCoordinates.value = coords;
  }

  const currentLot = developmentLots.value.find(
    (lot) => String(lot.id) === String(route.params.id),
  );

  if (!form.value.area_computed && currentLot?.area_computed != null) {
    form.value.area_computed = currentLot.area_computed;
  }
}

watch(
  editingLotSavedCoordinates,
  (coords) => {
    syncCoordinatesFromDevelopmentLots();
  },
  { immediate: true },
);

const isOffline = ref(typeof navigator !== 'undefined' ? !navigator.onLine : false);
const hasPendingSync = ref(false);
const pendingCount = ref(0);
const DB_NAME = 'sid360_lots';
const DB_VERSION = 1;
const STORE_NAME = 'pending_lots';

function openDB() {
  return new Promise((resolve, reject) => {
    const req = indexedDB.open(DB_NAME, DB_VERSION);
    req.onupgradeneeded = (event) => {
      const database = event.target.result;
      if (!database.objectStoreNames.contains(STORE_NAME)) {
        database.createObjectStore(STORE_NAME, { keyPath: 'local_id', autoIncrement: true });
      }
    };
    req.onsuccess = (event) => resolve(event.target.result);
    req.onerror = (event) => reject(event.target.error);
  });
}

async function savePending(data) {
  const database = await openDB();
  return new Promise((resolve, reject) => {
    const tx = database.transaction(STORE_NAME, 'readwrite');
    const store = tx.objectStore(STORE_NAME);
    const req = store.add({
      ...data,
      synced: false,
      created_at: new Date().toISOString(),
    });
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
}

async function getPending() {
  const database = await openDB();
  return new Promise((resolve, reject) => {
    const tx = database.transaction(STORE_NAME, 'readonly');
    const store = tx.objectStore(STORE_NAME);
    const req = store.getAll();
    req.onsuccess = () => resolve(req.result.filter((record) => !record.synced));
    req.onerror = () => reject(req.error);
  });
}

async function markSynced(localId) {
  const database = await openDB();
  return new Promise((resolve) => {
    const tx = database.transaction(STORE_NAME, 'readwrite');
    const store = tx.objectStore(STORE_NAME);
    const req = store.get(localId);
    req.onsuccess = () => {
      const record = req.result;
      if (record) {
        record.synced = true;
        store.put(record);
      }
      resolve();
    };
  });
}

async function checkPending() {
  try {
    const pending = await getPending();
    pendingCount.value = pending.length;
    hasPendingSync.value = pending.length > 0;
  } catch {
    hasPendingSync.value = false;
    pendingCount.value = 0;
  }
}

async function syncPending() {
  const pending = await getPending();
  let synced = 0;

  for (const record of pending) {
    try {
      if (record.lot_id) {
        await api.post(`/lots/${record.lot_id}/update`, record.payload);
      } else {
        await api.post('/lots', record.payload);
      }
      await markSynced(record.local_id);
      synced++;
    } catch {
      // keep unsynced records for next attempt
    }
  }

  if (synced > 0) {
    toast.success(`${synced} lote(s) sincronizado(s)!`);
  } else if (pending.length > 0) {
    toast.error('Não foi possível sincronizar os lotes pendentes.');
  }

  await checkPending();
}

function handleOnline() {
  isOffline.value = false;
  checkPending();
}

function handleOffline() {
  isOffline.value = true;
}

function goBack() {
  const query = form.value.development_id ? { development_id: form.value.development_id } : {};
  router.push({ name: 'lots.index', query });
}

async function loadItem() {
  if (!isEdit.value) {
    lotDataReady.value = true;
    return;
  }

  loading.value = true;
  try {
    const { data } = await api.get(`/lots/${route.params.id}`);
    const item = data.data ?? data;
    const normalizedCoordinates = normalizePolygonCoordinates(item.coordinates);

    loadedLotCoordinates.value = normalizedCoordinates;

    form.value = {
      development_id: String(item.development_id ?? ''),
      zone_id: item.zone_id ? String(item.zone_id) : '',
      street_id: item.street_id ? String(item.street_id) : '',
      block: item.block ?? '',
      number: item.number ?? '',
      area: item.area ?? '',
      size_label: item.size_label ?? '',
      total_value: item.total_value ?? 0,
      down_payment_percent: item.down_payment_percent != null ? String(item.down_payment_percent) : '',
      status: item.status ?? 'available',
      coordinates: normalizedCoordinates,
      area_computed: item.area_computed ?? null,
    };

    if (!normalizedCoordinates?.length && item.coordinates) {
      toast.warning('Não foi possível interpretar a demarcação salva. Redesenhe o lote no mapa.');
    }

    useDevelopmentPaymentTerms.value = item.down_payment_percent == null;
  } catch {
    toast.error('Erro ao carregar lote');
    goBack();
  } finally {
    loading.value = false;
    lotDataReady.value = true;
  }
}

function buildLotPayload() {
  if (form.value.area_computed && !form.value.area) {
    form.value.area = form.value.area_computed;
  }

  const selectedZone = getSelectedZone();

  return {
    ...form.value,
    development_id: Number(form.value.development_id),
    zone_id: selectedZone ? selectedZone.id : null,
    street_id: form.value.street_id ? Number(form.value.street_id) : null,
    block: selectedZone ? selectedZone.name : (form.value.block?.trim() || null),
    area: form.value.area === '' ? null : Number(form.value.area),
    area_computed: form.value.area_computed ?? null,
    size_label: form.value.size_label?.trim() || null,
    total_value: form.value.total_value > 0 ? Number(form.value.total_value) : null,
    down_payment_percent: useDevelopmentPaymentTerms.value
      ? null
      : form.value.down_payment_percent === ''
        ? null
        : Number(form.value.down_payment_percent),
  };
}

async function saveLotDemarcation(coords) {
  const normalized = normalizePolygonCoordinates(coords);
  updateFormCoordinates(normalized);

  if (!isEdit.value) {
    toast.success('Demarcação registrada. Clique em Salvar para persistir o lote.');
    return;
  }

  if (isOffline.value) {
    toast.warning('Sem conexão. A demarcação foi aplicada localmente — sincronize ao voltar online.');
    return;
  }

  savingDemarcation.value = true;
  try {
    await api.post(`/lots/${route.params.id}/update`, buildLotPayload());
    await loadDevelopmentLots();
    toast.success('Demarcação do lote salva.');
  } catch (err) {
    toast.error(err?.response?.data?.message ?? 'Erro ao salvar demarcação do lote.');
  } finally {
    savingDemarcation.value = false;
  }
}

function clearFormErrors() {
  formErrors.number = '';
}

function applyLotFormApiErrors(err) {
  clearFormErrors();

  const apiErrors = err?.response?.data?.errors;
  if (!apiErrors || typeof apiErrors !== 'object') {
    return;
  }

  if (apiErrors.number?.[0]) {
    formErrors.number = apiErrors.number[0];
  }
}

async function submit() {
  if (selectableZones.value.length && !form.value.zone_id) {
    toast.warning('Selecione a quadra do lote.');
    return;
  }

  const payload = buildLotPayload();

  if (isOffline.value) {
    try {
      await savePending({
        lot_id: isEdit.value ? route.params.id : null,
        payload,
      });
      await checkPending();
      toast.success('Lote salvo offline. Será sincronizado quando houver conexão.');
      if (!isEdit.value) {
        goBack();
      }
    } catch {
      toast.error('Erro ao salvar lote offline.');
    }
    return;
  }

  saving.value = true;
  clearFormErrors();

  try {
    if (isEdit.value) {
      await api.post(`/lots/${route.params.id}/update`, payload);
      toast.success('Lote atualizado.');
    } else {
      await api.post('/lots', payload);
      toast.success('Lote cadastrado.');
      goBack();
    }
  } catch (err) {
    applyLotFormApiErrors(err);
    toast.error(getApiErrorMessage(err, 'Erro ao salvar lote.'));
  } finally {
    saving.value = false;
  }
}

onMounted(async () => {
  window.addEventListener('online', handleOnline);
  window.addEventListener('offline', handleOffline);

  await loadDevelopments();
  await loadItem();

  if (form.value.development_id) {
    await loadDevelopmentMapContext();
  }

  await checkPending();
});

onUnmounted(() => {
  window.removeEventListener('online', handleOnline);
  window.removeEventListener('offline', handleOffline);
});
</script>
