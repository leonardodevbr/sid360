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
          <Input v-model="form.number" label="Número" required placeholder="Ex: QA-L01" />
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <Input v-model="form.area" label="Área (m²)" type="number" step="0.01" min="0" />
            <p v-if="computedArea" class="mt-1 text-xs text-emerald-600">
              Área calculada pelo polígono: <strong>{{ computedArea }} m²</strong>
            </p>
          </div>
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

      <div class="card space-y-4 p-5">
        <div class="flex items-center justify-between">
          <p class="text-sm font-semibold text-slate-700">Demarcação no mapa</p>
          <span v-if="form.coordinates?.length" class="text-xs font-medium text-emerald-600">
            {{ form.coordinates.length }} pontos demarcados
          </span>
        </div>

        <div
          ref="mapSectionRef"
          class="map-fullscreen-section space-y-4"
          :class="{ 'map-fullscreen-section--overlay': isMapFullscreen }"
        >
          <div
            ref="mapContainer"
            class="map-fullscreen-canvas w-full overflow-hidden rounded-lg border border-slate-300"
            :class="{ '!h-full min-h-0': isMapFullscreen }"
            :style="isMapFullscreen ? null : { height: '380px' }"
          />

          <div class="map-fullscreen-toolbar flex flex-wrap gap-2">
          <button
            type="button"
            class="flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium hover:bg-slate-50"
            :class="drawing ? 'border-blue-300 bg-blue-50 text-blue-700' : 'border-slate-300 bg-white text-slate-700'"
            @click="startDrawing"
          >
            <PencilSquareIcon class="h-3.5 w-3.5" />
            {{
              drawing
                ? 'Clique no mapa para marcar pontos'
                : form.coordinates?.length
                  ? 'Redesenhar'
                  : 'Marcar no mapa'
            }}
          </button>

          <button
            type="button"
            class="flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-100 disabled:opacity-50"
            :disabled="capturingGPS"
            @click="captureGPS"
          >
            <MapPinIcon class="h-3.5 w-3.5" />
            {{ capturingGPS ? 'Capturando GPS...' : 'Capturar ponto GPS' }}
          </button>

          <button
            v-if="form.coordinates?.length"
            type="button"
            class="flex items-center gap-1.5 rounded-lg border border-amber-200 bg-white px-3 py-1.5 text-xs font-medium text-amber-600 hover:bg-amber-50"
            @click="undoLastPoint"
          >
            <ArrowUturnLeftIcon class="h-3.5 w-3.5" />
            Desfazer último ponto
          </button>

          <button
            v-if="form.coordinates?.length"
            type="button"
            class="flex items-center gap-1.5 rounded-lg border border-red-200 bg-white px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50"
            @click="clearPolygon"
          >
            Limpar
          </button>

          <button
            type="button"
            class="flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
            @click="toggleMapFullscreen"
          >
            <ArrowsPointingOutIcon v-if="!isMapFullscreen" class="h-3.5 w-3.5" />
            <ArrowsPointingInIcon v-else class="h-3.5 w-3.5" />
            {{ isMapFullscreen ? 'Sair da tela cheia' : 'Tela cheia' }}
          </button>
          </div>
        </div>

        <div
          v-if="gpsAccuracy !== null"
          class="rounded-lg px-3 py-2 text-xs font-medium"
          :class="
            gpsAccuracy < 10
              ? 'bg-emerald-50 text-emerald-700'
              : gpsAccuracy < 30
                ? 'bg-amber-50 text-amber-700'
                : 'bg-red-50 text-red-700'
          "
        >
          Precisão GPS: ±{{ Math.round(gpsAccuracy) }}m
          {{
            gpsAccuracy < 10
              ? '— Excelente'
              : gpsAccuracy < 30
                ? '— Boa'
                : '— Ruim, aguarde melhorar'
          }}
        </div>

        <p class="text-xs text-slate-400">
          <strong>Desktop:</strong> clique "Marcar no mapa" e clique nos vértices do lote.
          <strong>Campo:</strong> vá a cada vértice do lote e clique "Capturar ponto GPS".
          Desfaça pontos errados com "Desfazer". Quando terminar, salve.
        </p>
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
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import api from '@/services/api';
import { lotStatusFormOptions } from '@/utils/labels';
import { setupMapBaseLayers } from '@/utils/mapLayers';
import { buildZoneTitleLabel, isLotSelectableZone } from '@/utils/zone';
import { useMapFullscreen } from '@/composables/useMapFullscreen';
import Input from '@/components/Common/Input.vue';
import SelectInput from '@/components/Common/SelectInput.vue';
import Button from '@/components/Common/Button.vue';
import CurrencyInput from '@/components/Common/CurrencyInput.vue';
import {
  ArrowLeftIcon,
  ArrowPathIcon,
  ArrowsPointingInIcon,
  ArrowsPointingOutIcon,
  ArrowUturnLeftIcon,
  MapPinIcon,
  PencilSquareIcon,
  SignalSlashIcon,
} from '@heroicons/vue/24/outline';

const route = useRoute();
const router = useRouter();
const toast = useToast();

const isEdit = computed(() => Boolean(route.params.id));
const loading = ref(false);
const saving = ref(false);
const useDevelopmentPaymentTerms = ref(true);

const form = ref({
  development_id: route.query.development_id ? String(route.query.development_id) : '',
  zone_id: '',
  block: '',
  number: '',
  area: '',
  total_value: 0,
  down_payment_percent: '',
  status: 'available',
  coordinates: null,
  area_computed: null,
});

const developments = ref([]);
const zones = ref([]);

const developmentOptions = computed(() =>
  developments.value.map((d) => ({ value: String(d.id), label: d.name })),
);

const selectableZones = computed(() => zones.value.filter(isLotSelectableZone));

const zoneOptions = computed(() =>
  selectableZones.value.map((z) => ({
    value: String(z.id),
    label: buildZoneTitleLabel(z),
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

async function loadDevelopmentMapContext() {
  zones.value = [];

  if (!form.value.development_id || !map || !L) return;

  try {
    const { data } = await api.get(`/developments/${form.value.development_id}/zones`);
    zones.value = Array.isArray(data) ? data : data.data ?? [];
  } catch {
    zones.value = [];
  }

  if (
    form.value.zone_id
    && !selectableZones.value.some((zone) => String(zone.id) === String(form.value.zone_id))
  ) {
    form.value.zone_id = '';
  }

  resolveZoneIdFromLegacyBlock();

  const dev =
    (await fetchDevelopmentMapData(form.value.development_id))
    ?? developments.value.find((d) => String(d.id) === String(form.value.development_id));

  if (dev?.map_center?.length === 2) {
    map.setView(dev.map_center, dev.map_zoom ?? 17);
  }

  if (dev?.coordinates?.length) {
    drawDevelopmentPerimeter(dev.coordinates);
  }

  if (selectableZones.value.length) {
    drawZonesOnMap();
  }
}

const mapContainer = ref(null);
const mapSectionRef = ref(null);
let map = null;
let L = null;
let polygonLayer = null;
let devPerimLayer = null;
let zoneLayers = [];
let pointMarkers = [];
const drawing = ref(false);

const { isFullscreen: isMapFullscreen, toggleFullscreen: toggleMapFullscreen } = useMapFullscreen(
  mapSectionRef,
  () => map?.invalidateSize(),
);

async function initMap() {
  if (!mapContainer.value) return;

  L = (await import('leaflet')).default;
  await import('leaflet/dist/leaflet.css');

  map = L.map(mapContainer.value, { scrollWheelZoom: false }).setView([-11.4667, -39.9833], 16);

  await setupMapBaseLayers(map, L, { maxZoom: 22 });

  map.on('click', onMapClick);
  map.invalidateSize();

  if (form.value.coordinates?.length) {
    restorePointMarkers(form.value.coordinates);
    renderPolygon(form.value.coordinates);
  }
}

function onMapClick(e) {
  if (!drawing.value) return;
  addPoint([e.latlng.lat, e.latlng.lng]);
}

function addPoint(coords) {
  if (!L || !map) return;

  if (!form.value.coordinates) {
    form.value.coordinates = [];
  }

  form.value.coordinates.push(coords);

  const marker = L.circleMarker(coords, {
    radius: 5,
    color: '#1E5F8E',
    fillColor: '#fff',
    fillOpacity: 1,
    weight: 2,
  }).addTo(map);

  pointMarkers.push(marker);
  renderPolygon(form.value.coordinates);
}

function restorePointMarkers(coords) {
  if (!L || !map) return;

  pointMarkers.forEach((marker) => map.removeLayer(marker));
  pointMarkers = [];

  coords.forEach((coordsPoint) => {
    const marker = L.circleMarker(coordsPoint, {
      radius: 5,
      color: '#1E5F8E',
      fillColor: '#fff',
      fillOpacity: 1,
      weight: 2,
    }).addTo(map);
    pointMarkers.push(marker);
  });
}

function renderPolygon(coords) {
  if (!L || !map) return;

  if (polygonLayer) {
    map.removeLayer(polygonLayer);
    polygonLayer = null;
  }

  if (coords.length < 2) {
    if (coords.length === 1) {
      map.setView(coords[0], 18);
    }
    return;
  }

  polygonLayer = (coords.length >= 3 ? L.polygon : L.polyline)(coords, {
    color: '#1E5F8E',
    weight: 2,
    fillColor: '#1E5F8E',
    fillOpacity: 0.15,
  }).addTo(map);

  if (coords.length >= 2 && polygonLayer.getBounds) {
    map.fitBounds(polygonLayer.getBounds(), { padding: [20, 20] });
  }

  if (coords.length >= 3) {
    form.value.area_computed = computeGeodesicArea(coords);
  }
}

function startDrawing() {
  drawing.value = !drawing.value;
  if (drawing.value) {
    map?.getContainer()?.style.setProperty('cursor', 'crosshair');
  } else {
    map?.getContainer()?.style.removeProperty('cursor');
  }
}

function undoLastPoint() {
  if (!form.value.coordinates?.length) return;

  form.value.coordinates.pop();

  const marker = pointMarkers.pop();
  if (marker) {
    map?.removeLayer(marker);
  }

  if (polygonLayer) {
    map?.removeLayer(polygonLayer);
    polygonLayer = null;
  }

  if (form.value.coordinates.length >= 2) {
    renderPolygon(form.value.coordinates);
  }

  form.value.area_computed =
    form.value.coordinates.length >= 3
      ? computeGeodesicArea(form.value.coordinates)
      : null;
}

function clearPolygon() {
  form.value.coordinates = null;
  form.value.area_computed = null;

  if (polygonLayer) {
    map?.removeLayer(polygonLayer);
    polygonLayer = null;
  }

  pointMarkers.forEach((marker) => map?.removeLayer(marker));
  pointMarkers = [];
}

function drawDevelopmentPerimeter(coords) {
  if (!L || !map) return;

  if (devPerimLayer) {
    map.removeLayer(devPerimLayer);
  }

  devPerimLayer = L.polygon(coords, {
    color: '#94A3B8',
    weight: 1.5,
    dashArray: '6',
    fillColor: '#94A3B8',
    fillOpacity: 0.05,
  }).addTo(map);

  map.fitBounds(devPerimLayer.getBounds(), { padding: [30, 30] });
}

function drawZonesOnMap() {
  if (!L || !map) return;

  zoneLayers.forEach((layer) => map.removeLayer(layer));
  zoneLayers = [];

  zones.value.forEach((zone) => {
    if (!isLotSelectableZone(zone) || !zone.coordinates?.length) return;

    const layer = L.polygon(zone.coordinates, {
      color: zone.color,
      weight: 1.5,
      fillColor: zone.color,
      fillOpacity: 0.1,
    })
      .bindTooltip(zone.name)
      .addTo(map);

    zoneLayers.push(layer);
  });
}

function computeGeodesicArea(coords) {
  if (coords.length < 3) return null;

  const earthRadius = 6371000;
  let area = 0;
  const pointCount = coords.length;

  for (let i = 0; i < pointCount; i++) {
    const [lat1, lng1] = coords[i];
    const [lat2, lng2] = coords[(i + 1) % pointCount];
    area +=
      ((lng2 - lng1) * Math.PI) / 180
      * (2 + Math.sin((lat1 * Math.PI) / 180) + Math.sin((lat2 * Math.PI) / 180));
  }

  return Math.round(Math.abs((area * earthRadius * earthRadius) / 2));
}

const computedArea = computed(() =>
  form.value.area_computed ? form.value.area_computed.toLocaleString('pt-BR') : null,
);

const capturingGPS = ref(false);
const gpsAccuracy = ref(null);

async function captureGPS() {
  if (!navigator.geolocation) {
    toast.error('GPS não disponível neste dispositivo.');
    return;
  }

  capturingGPS.value = true;
  gpsAccuracy.value = null;

  navigator.geolocation.getCurrentPosition(
    (pos) => {
      gpsAccuracy.value = pos.coords.accuracy;
      const coords = [pos.coords.latitude, pos.coords.longitude];
      addPoint(coords);
      map?.setView(coords, 20);
      toast.success(`Ponto capturado! Precisão: ±${Math.round(pos.coords.accuracy)}m`);
      capturingGPS.value = false;
    },
    (err) => {
      toast.error(`Erro ao capturar GPS: ${err.message}`);
      capturingGPS.value = false;
    },
    { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
  );
}

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
        await api.put(`/lots/${record.lot_id}`, record.payload);
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
  if (!isEdit.value) return;

  loading.value = true;
  try {
    const { data } = await api.get(`/lots/${route.params.id}`);
    const item = data.data ?? data;

    form.value = {
      development_id: String(item.development_id ?? ''),
      zone_id: item.zone_id ? String(item.zone_id) : '',
      block: item.block ?? '',
      number: item.number ?? '',
      area: item.area ?? '',
      total_value: item.total_value ?? 0,
      down_payment_percent: item.down_payment_percent != null ? String(item.down_payment_percent) : '',
      status: item.status ?? 'available',
      coordinates: item.coordinates ?? null,
      area_computed: item.area_computed ?? null,
    };

    useDevelopmentPaymentTerms.value = item.down_payment_percent == null;
  } catch {
    toast.error('Erro ao carregar lote');
    goBack();
  } finally {
    loading.value = false;
  }
}

async function submit() {
  if (form.value.area_computed && !form.value.area) {
    form.value.area = form.value.area_computed;
  }

  if (selectableZones.value.length && !form.value.zone_id) {
    toast.warning('Selecione a quadra do lote.');
    return;
  }

  const selectedZone = getSelectedZone();

  const payload = {
    ...form.value,
    development_id: Number(form.value.development_id),
    zone_id: selectedZone ? selectedZone.id : null,
    block: selectedZone ? selectedZone.name : (form.value.block?.trim() || null),
    area: form.value.area === '' ? null : Number(form.value.area),
    area_computed: form.value.area_computed ?? null,
    total_value: form.value.total_value > 0 ? Number(form.value.total_value) : null,
    down_payment_percent: useDevelopmentPaymentTerms.value
      ? null
      : form.value.down_payment_percent === ''
        ? null
        : Number(form.value.down_payment_percent),
  };

  if (isOffline.value) {
    try {
      await savePending({
        lot_id: isEdit.value ? route.params.id : null,
        payload,
      });
      await checkPending();
      toast.success('Lote salvo offline. Será sincronizado quando houver conexão.');
      goBack();
    } catch {
      toast.error('Erro ao salvar lote offline.');
    }
    return;
  }

  saving.value = true;
  try {
    if (isEdit.value) {
      await api.put(`/lots/${route.params.id}`, payload);
      toast.success('Lote atualizado.');
    } else {
      await api.post('/lots', payload);
      toast.success('Lote cadastrado.');
    }
    goBack();
  } catch (err) {
    toast.error(err?.response?.data?.message ?? 'Erro ao salvar lote.');
  } finally {
    saving.value = false;
  }
}

onMounted(async () => {
  window.addEventListener('online', handleOnline);
  window.addEventListener('offline', handleOffline);

  await loadDevelopments();
  await loadItem();
  await nextTick();
  await initMap();

  if (form.value.development_id) {
    await loadDevelopmentMapContext();
    if (form.value.coordinates?.length) {
      restorePointMarkers(form.value.coordinates);
      renderPolygon(form.value.coordinates);
    }
  }

  await checkPending();
});

onUnmounted(() => {
  window.removeEventListener('online', handleOnline);
  window.removeEventListener('offline', handleOffline);
  map?.remove();
  map = null;
});
</script>
