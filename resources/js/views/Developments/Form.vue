<template>
  <div class="space-y-6 pb-10">
    <div class="flex items-center gap-4">
      <button
        type="button"
        class="rounded-lg p-2 hover:bg-slate-100"
        @click="$router.push({ name: 'developments.index' })"
      >
        <ArrowLeftIcon class="h-5 w-5 text-slate-600" />
      </button>
      <div>
        <h2 class="text-lg font-semibold text-slate-800">
          {{ isEdit ? 'Editar empreendimento' : 'Novo empreendimento' }}
        </h2>
        <p class="text-xs text-slate-500">
          {{ isEdit ? 'Atualize os dados e o mapa' : 'Cadastre um novo empreendimento' }}
        </p>
      </div>
    </div>

    <form v-if="!loading" class="space-y-4" @submit.prevent="submit">
      <div class="card space-y-4 p-5">
        <p class="text-sm font-semibold text-slate-700">Dados básicos</p>
        <Input v-model="form.name" label="Nome" required placeholder="Ex: Parque Empresarial Sid360" />
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">Descrição</label>
          <textarea
            v-model="form.description"
            rows="3"
            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
            placeholder="Descrição do empreendimento"
          />
        </div>
        <Input v-model="form.location" label="Localização" placeholder="Endereço ou referência" />
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Input
            v-model="form.down_payment_percent"
            label="Entrada sugerida (%)"
            type="number"
            min="0"
            max="100"
            step="0.01"
            placeholder="20"
          />
          <SelectInput
            v-model="form.status"
            label="Status"
            :options="developmentStatusFormOptions"
            :searchable="false"
          />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">
            Padrão de numeração dos lotes
          </label>
          <input
            v-model="form.lot_number_pattern"
            type="text"
            placeholder="Ex: {zona}-L{numero2}"
            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
          />
          <p class="mt-1 text-xs text-slate-400">
            Variáveis:
            <code class="rounded bg-slate-100 px-1">{zona}</code>
            <code class="rounded bg-slate-100 px-1">{numero}</code>
            <code class="rounded bg-slate-100 px-1">{numero2}</code> (2 dígitos)
            <code class="rounded bg-slate-100 px-1">{numero3}</code> (3 dígitos)
            · Ex: QA-L01, Q1L001
          </p>
        </div>
      </div>

      <div class="card space-y-4 p-5">
        <div class="flex items-center justify-between">
          <p class="text-sm font-semibold text-slate-700">Mapa do empreendimento</p>
          <span class="text-xs text-slate-400">Desenhe o perímetro e depois as zonas</span>
        </div>

        <div
          ref="mapSectionRef"
          class="map-fullscreen-section space-y-4"
        >
          <div
            ref="mapContainer"
            class="map-fullscreen-canvas w-full overflow-hidden rounded-lg border border-slate-300"
            style="height: 420px"
          />

          <div class="map-fullscreen-toolbar flex flex-wrap gap-2">
          <button
            type="button"
            class="flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
            :class="{ 'border-blue-300 bg-blue-50 text-blue-700': drawingMode === 'perimeter' }"
            @click="startDrawPerimeter"
          >
            <MapIcon class="h-3.5 w-3.5" />
            {{ form.coordinates?.length ? 'Redesenhar perímetro' : 'Desenhar perímetro' }}
          </button>
          <button
            v-if="drawingMode"
            type="button"
            class="flex items-center gap-1.5 rounded-lg border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-100"
            @click="cancelDrawing"
          >
            <XMarkIcon class="h-3.5 w-3.5" />
            Cancelar desenho
          </button>
          <button
            type="button"
            class="flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50"
            :disabled="locatingUser"
            @click="goToMyLocation"
          >
            <MapPinIcon class="h-3.5 w-3.5" />
            {{ locatingUser ? 'Localizando...' : 'Minha localização' }}
          </button>
          <button
            v-if="form.coordinates?.length && !drawingMode"
            type="button"
            class="flex items-center gap-1.5 rounded-lg border border-red-200 bg-white px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50"
            @click="clearPerimeter"
          >
            Limpar perímetro
          </button>
          <button
            v-if="isMapFullscreen && isEdit"
            type="button"
            class="relative flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium"
            :class="showZoneMapPicker || drawingMode === 'zone'
              ? 'border-emerald-300 bg-emerald-50 text-emerald-700'
              : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'"
            @click="toggleZoneMapPicker"
          >
            <RectangleGroupIcon class="h-3.5 w-3.5" />
            Mapear zona
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
          <span
            v-if="drawingMode === 'perimeter'"
            class="self-center text-xs font-medium text-blue-600"
          >
            Clique no mapa para definir os pontos. Clique no primeiro ponto para fechar.
          </span>
          <span
            v-else-if="drawingMode === 'zone'"
            class="self-center text-xs font-medium text-emerald-600"
          >
            Desenhando área de {{ drawingZone?.name }}. Clique no primeiro ponto para fechar.
          </span>
          </div>

          <div
            v-if="showZoneMapPicker && isMapFullscreen && isEdit"
            class="map-zone-picker rounded-lg border border-slate-200 bg-white p-3 shadow-lg"
          >
            <div class="mb-2 flex items-center justify-between gap-2">
              <p class="text-xs font-semibold text-slate-700">Selecione a zona para demarcar</p>
              <button
                type="button"
                class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                @click="showZoneMapPicker = false"
              >
                <XMarkIcon class="h-4 w-4" />
              </button>
            </div>

            <button
              type="button"
              class="mb-2 flex w-full items-center gap-2 rounded-lg border border-dashed border-emerald-300 bg-emerald-50 px-3 py-2 text-left text-xs font-medium text-emerald-700 hover:bg-emerald-100"
              @click="openNewZoneFromMapPicker"
            >
              <PlusIcon class="h-4 w-4 shrink-0" />
              Nova zona
            </button>

            <p v-if="!zones.length" class="px-1 py-2 text-xs text-slate-400">
              Nenhuma zona cadastrada. Crie uma nova zona para demarcar no mapa.
            </p>

            <div v-else class="max-h-48 space-y-1 overflow-y-auto">
              <button
                v-for="zone in zones"
                :key="zone.id"
                type="button"
                class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-xs hover:bg-slate-50"
                :class="drawingZone?.id === zone.id ? 'bg-emerald-50 ring-1 ring-emerald-200' : ''"
                @click="pickZoneForMapping(zone)"
              >
                <span class="h-3 w-3 shrink-0 rounded-full" :style="{ background: zone.color }" />
                <span class="min-w-0 flex-1">
                  <span class="block font-medium text-slate-800">{{ zone.name }}</span>
                  <span class="block text-slate-400">
                    {{ zoneTypeLabel(zone.type) }}
                    · {{ zone.coordinates?.length ? 'área demarcada' : 'sem área' }}
                  </span>
                </span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <div v-if="isEdit" class="card space-y-4 p-5">
        <div class="flex items-center justify-between">
          <p class="text-sm font-semibold text-slate-700">
            Zonas (quadras / conjuntos / ruas)
          </p>
          <button
            type="button"
            class="flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-700"
            @click="openZoneForm"
          >
            <PlusIcon class="h-3.5 w-3.5" />
            Nova zona
          </button>
        </div>

        <div v-if="zones.length" class="space-y-2">
          <div
            v-for="zone in zones"
            :key="zone.id"
            class="flex items-center gap-3 rounded-lg border border-slate-200 px-3 py-2.5"
          >
            <div class="h-3 w-3 shrink-0 rounded-full" :style="{ background: zone.color }" />
            <div class="min-w-0 flex-1">
              <p class="text-sm font-medium text-slate-800">{{ zone.name }}</p>
              <p class="text-xs text-slate-400">
                {{ zoneTypeLabel(zone.type) }}
                · {{ zoneLotsCount(zone) }} lote(s)
                <span v-if="zone.coordinates?.length" class="text-emerald-600"> · perímetro definido</span>
              </p>
            </div>
            <div class="flex shrink-0 gap-2">
              <button
                type="button"
                class="rounded px-2 py-1 text-xs text-blue-600 hover:bg-blue-50"
                @click="startDrawZone(zone)"
              >
                {{ zone.coordinates?.length ? 'Redesenhar' : 'Desenhar área' }}
              </button>
              <button
                type="button"
                class="rounded px-2 py-1 text-xs"
                :class="canGenerateLotsInZone(zone)
                  ? 'text-emerald-600 hover:bg-emerald-50'
                  : 'cursor-not-allowed text-slate-300'"
                :disabled="!canGenerateLotsInZone(zone)"
                :title="generateLotsBlockedReason(zone) || undefined"
                @click="openGenerateLots(zone)"
              >
                Gerar lotes
              </button>
              <button
                type="button"
                class="rounded px-2 py-1 text-xs text-slate-500 hover:bg-slate-100"
                @click="editZone(zone)"
              >
                Editar
              </button>
              <button
                type="button"
                class="rounded px-2 py-1 text-xs text-red-500 hover:bg-red-50"
                @click="deleteZone(zone)"
              >
                Excluir
              </button>
            </div>
          </div>
        </div>
        <p v-else class="text-xs text-slate-400">Nenhuma zona cadastrada ainda.</p>
      </div>

      <div class="flex justify-end gap-3">
        <Button type="button" variant="outline" @click="$router.push({ name: 'developments.index' })">
          Cancelar
        </Button>
        <Button type="submit" variant="primary" :disabled="saving">
          {{ saving ? 'Salvando...' : 'Salvar' }}
        </Button>
      </div>
    </form>

    <div v-else class="card p-12 text-center text-slate-500">Carregando...</div>

    <Modal :is-open="showZoneForm" title="Zona" @close="closeZoneForm">
      <div class="space-y-3">
        <Input
          v-model="zoneForm.name"
          label="Nome da zona"
          required
          placeholder="Ex: Quadra A"
          :error="zoneFormErrors.name"
        />
        <SelectInput
          v-model="zoneForm.type"
          label="Tipo"
          :options="zoneTypeOptions"
          :searchable="false"
          :can-clear="false"
          placeholder="Selecione o tipo"
          :error="zoneFormErrors.type"
        />
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">Cor no mapa</label>
          <div class="flex flex-wrap gap-2">
            <button
              v-for="color in zoneColors"
              :key="color"
              type="button"
              class="h-7 w-7 rounded-full border-2 transition-transform"
              :style="{ background: color }"
              :class="zoneForm.color === color ? 'scale-110 border-slate-800' : 'border-transparent'"
              @click="zoneForm.color = color"
            />
          </div>
        </div>
      </div>
      <div class="mt-4 flex justify-end gap-2">
        <Button variant="outline" @click="closeZoneForm">Cancelar</Button>
        <Button variant="primary" :disabled="savingZone" @click="saveZone">
          {{ savingZone ? 'Salvando...' : 'Salvar zona' }}
        </Button>
      </div>
    </Modal>

    <Modal
      :is-open="!!generateLotsZone"
      :title="generateLotsZone ? `Gerar lotes — ${generateLotsZone.name}` : 'Gerar lotes'"
      @close="generateLotsZone = null"
    >
      <div class="space-y-3">
        <Input
          v-model="generateForm.quantity"
          label="Quantidade de lotes"
          type="number"
          min="1"
          max="500"
          required
        />
        <Input
          v-model="generateForm.start_from"
          label="Iniciar numeração em"
          type="number"
          min="1"
        />
        <Input
          v-model="generateForm.area"
          label="Área de cada lote (m²)"
          type="number"
          step="0.01"
        />
        <CurrencyInput v-model="generateForm.total_value" label="Valor de cada lote" />
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">Padrão de numeração</label>
          <input
            v-model="generateForm.pattern"
            type="text"
            :placeholder="form.lot_number_pattern || '{zona}-L{numero2}'"
            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
          />
          <p class="mt-1 text-xs text-slate-400">
            Deixe vazio para usar o padrão do empreendimento.
            Prévia: <strong>{{ previewLotNumber }}</strong>
          </p>
        </div>
      </div>
      <div class="mt-4 flex justify-end gap-2">
        <Button variant="outline" @click="generateLotsZone = null">Cancelar</Button>
        <Button variant="primary" :disabled="generating" @click="doGenerateLots">
          {{ generating ? 'Gerando...' : `Gerar ${generateForm.quantity || 0} lotes` }}
        </Button>
      </div>
    </Modal>
  </div>
</template>

<script setup>
import { ref, computed, reactive, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import api from '@/services/api';
import { getApiErrorMessage } from '@/utils/apiError';
import { useAlert } from '@/composables/useAlert';
import { useMapFullscreen } from '@/composables/useMapFullscreen';
import { developmentStatusFormOptions } from '@/utils/labels';
import { setupMapBaseLayers } from '@/utils/mapLayers';
import {
  canGenerateLotsInZone,
  generateLotsBlockedReason,
  zoneTypeLabel as zoneTypeLabelHelper,
} from '@/utils/zone';
import Input from '@/components/Common/Input.vue';
import SelectInput from '@/components/Common/SelectInput.vue';
import Button from '@/components/Common/Button.vue';
import Modal from '@/components/Common/Modal.vue';
import CurrencyInput from '@/components/Common/CurrencyInput.vue';
import { ArrowLeftIcon, ArrowsPointingInIcon, ArrowsPointingOutIcon, MapIcon, MapPinIcon, PlusIcon, RectangleGroupIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const { confirm } = useAlert();

const isEdit = computed(() => Boolean(route.params.id));
const loading = ref(false);
const saving = ref(false);

const form = ref({
  name: '',
  description: '',
  location: '',
  status: 'active',
  down_payment_percent: '20',
  lot_number_pattern: '{zona}-L{numero2}',
  coordinates: null,
  map_center: null,
  map_zoom: 17,
});

const mapContainer = ref(null);
const mapSectionRef = ref(null);
let map = null;
let L = null;
let perimeterLayer = null;
let perimeterPoints = [];
let tempMarkers = [];
let zoneLayers = {};
let locationMarker = null;
const drawingMode = ref(null);
const drawingZone = ref(null);
const locatingUser = ref(false);

const { isFullscreen: isMapFullscreen, toggleFullscreen: toggleMapFullscreen } = useMapFullscreen(
  mapSectionRef,
  () => map?.invalidateSize(),
);

async function initMap() {
  if (!mapContainer.value) return;

  L = (await import('leaflet')).default;
  await import('leaflet/dist/leaflet.css');

  const center = form.value.map_center ?? [-11.4667, -39.9833];
  const zoom = form.value.map_zoom ?? 17;

  map = L.map(mapContainer.value, { zoomControl: true, scrollWheelZoom: false }).setView(center, zoom);

  await setupMapBaseLayers(map, L, { maxZoom: 22 });

  if (form.value.coordinates?.length) {
    drawPerimeterOnMap(form.value.coordinates);
  }

  map.on('click', onMapClick);
  map.on('moveend zoomend', () => {
    const c = map.getCenter();
    form.value.map_center = [c.lat, c.lng];
    form.value.map_zoom = map.getZoom();
  });

  map.invalidateSize();
}

function resetMapCursor() {
  map?.getContainer()?.style.removeProperty('cursor');
}

function onMapClick(e) {
  if (!drawingMode.value || !L) return;

  const { lat, lng } = e.latlng;
  perimeterPoints.push([lat, lng]);

  const markerColor = drawingMode.value === 'perimeter'
    ? '#1E5F8E'
    : drawingZone.value?.color ?? '#10B981';

  const marker = L.circleMarker([lat, lng], {
    radius: 5,
    color: markerColor,
    fillColor: '#fff',
    fillOpacity: 1,
    weight: 2,
  }).addTo(map);

  if (perimeterPoints.length > 2 && isNearFirst(e.latlng)) {
    finishDrawing();
    return;
  }

  marker.on('click', () => {
    if (perimeterPoints.length > 2) finishDrawing();
  });
  tempMarkers.push(marker);

  refreshTempPolyline();
}

function isNearFirst(latlng) {
  if (perimeterPoints.length < 3 || !L) return false;
  const first = L.latLng(perimeterPoints[0][0], perimeterPoints[0][1]);
  return latlng.distanceTo(first) < 15;
}

function refreshTempPolyline() {
  if (!L || perimeterPoints.length < 2) return;
  if (map._tempLine) map.removeLayer(map._tempLine);

  const lineColor = drawingMode.value === 'perimeter'
    ? '#1E5F8E'
    : drawingZone.value?.color ?? '#10B981';

  map._tempLine = L.polyline(perimeterPoints, {
    color: lineColor,
    weight: 2,
    dashArray: '4',
  }).addTo(map);
}

function finishDrawing() {
  clearTempLayers();
  resetMapCursor();

  if (drawingMode.value === 'perimeter') {
    form.value.coordinates = [...perimeterPoints];
    drawPerimeterOnMap(form.value.coordinates);
  } else if (drawingMode.value === 'zone' && drawingZone.value) {
    saveZoneCoordinates(drawingZone.value, [...perimeterPoints]);
  }

  perimeterPoints = [];
  drawingMode.value = null;
  drawingZone.value = null;
}

function clearTempLayers() {
  tempMarkers.forEach((m) => map?.removeLayer(m));
  tempMarkers = [];
  if (map?._tempLine) {
    map.removeLayer(map._tempLine);
    delete map._tempLine;
  }
}

function drawPerimeterOnMap(coords) {
  if (!L || !map) return;
  if (perimeterLayer) map.removeLayer(perimeterLayer);

  perimeterLayer = L.polygon(coords, {
    color: '#1E5F8E',
    weight: 2.5,
    fillColor: '#1E5F8E',
    fillOpacity: 0.08,
  }).addTo(map);

  map.fitBounds(perimeterLayer.getBounds(), { padding: [20, 20] });
}

function drawZonesOnMap() {
  if (!L || !map) return;

  Object.values(zoneLayers).forEach((layer) => map.removeLayer(layer));
  zoneLayers = {};

  zones.value.forEach((zone) => {
    if (!zone.coordinates?.length) return;

    const layer = L.polygon(zone.coordinates, {
      color: zone.color,
      weight: 2,
      fillColor: zone.color,
      fillOpacity: 0.15,
    })
      .bindTooltip(zone.name, { permanent: false })
      .addTo(map);

    zoneLayers[zone.id] = layer;
  });
}

function startDrawPerimeter() {
  clearTempLayers();
  perimeterPoints = [];
  drawingMode.value = 'perimeter';
  drawingZone.value = null;
  map?.getContainer()?.style.setProperty('cursor', 'crosshair');
}

function startDrawZone(zone) {
  clearTempLayers();
  perimeterPoints = [];
  drawingMode.value = 'zone';
  drawingZone.value = zone;
  showZoneMapPicker.value = false;
  map?.getContainer()?.style.setProperty('cursor', 'crosshair');
}

function toggleZoneMapPicker() {
  if (!isEdit.value) return;

  showZoneMapPicker.value = !showZoneMapPicker.value;
}

function pickZoneForMapping(zone) {
  if (drawingMode.value === 'perimeter') {
    cancelDrawing();
  }

  startDrawZone(zone);
  toast.info(`Desenhando área de "${zone.name}". Clique no mapa para marcar os vértices.`);
}

function openNewZoneFromMapPicker() {
  showZoneMapPicker.value = false;
  openZoneForm();
}

function cancelDrawing() {
  clearTempLayers();
  resetMapCursor();
  perimeterPoints = [];
  drawingMode.value = null;
  drawingZone.value = null;
  showZoneMapPicker.value = false;
}

function goToMyLocation() {
  if (!navigator.geolocation) {
    toast.error('GPS não disponível neste dispositivo.');
    return;
  }

  locatingUser.value = true;

  navigator.geolocation.getCurrentPosition(
    (pos) => {
      const coords = [pos.coords.latitude, pos.coords.longitude];

      if (map && L) {
        map.setView(coords, Math.max(map.getZoom(), 17));

        if (locationMarker) {
          map.removeLayer(locationMarker);
        }

        locationMarker = L.circleMarker(coords, {
          radius: 8,
          color: '#2563EB',
          fillColor: '#3B82F6',
          fillOpacity: 0.85,
          weight: 2,
        }).addTo(map);
      }

      locatingUser.value = false;
    },
    (err) => {
      toast.error(`Erro ao obter localização: ${err.message}`);
      locatingUser.value = false;
    },
    { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
  );
}

function clearPerimeter() {
  form.value.coordinates = null;
  if (perimeterLayer) {
    map?.removeLayer(perimeterLayer);
    perimeterLayer = null;
  }
}

async function saveZoneCoordinates(zone, coords) {
  try {
    await api.put(`/developments/${route.params.id}/zones/${zone.id}`, {
      name: zone.name,
      type: zone.type,
      color: zone.color,
      order: zone.order,
      coordinates: coords,
    });
    toast.success('Área da zona salva.');
    await loadZones();
    drawZonesOnMap();
  } catch {
    toast.error('Erro ao salvar área da zona.');
  }
}

const zones = ref([]);
const showZoneForm = ref(false);
const showZoneMapPicker = ref(false);
const savingZone = ref(false);
const editingZone = ref(null);

const zoneColors = [
  '#3B82F6',
  '#10B981',
  '#F59E0B',
  '#EF4444',
  '#8B5CF6',
  '#EC4899',
  '#06B6D4',
  '#84CC16',
];

const zoneTypeOptions = [
  { value: 'quadra', label: 'Quadra' },
  { value: 'conjunto', label: 'Conjunto' },
  { value: 'setor', label: 'Setor' },
  { value: 'rua', label: 'Rua' },
  { value: 'outro', label: 'Outro' },
];

const zoneForm = reactive({ name: '', type: 'quadra', color: '#3B82F6' });
const zoneFormErrors = reactive({ name: '', type: '' });

function zoneTypeLabel(type) {
  return zoneTypeLabelHelper(type);
}

function zoneLotsCount(zone) {
  return zone.lots_count ?? zone.lots?.length ?? 0;
}

async function loadZones() {
  if (!route.params.id) return;

  try {
    const { data } = await api.get(`/developments/${route.params.id}/zones`);
    zones.value = Array.isArray(data) ? data : data.data ?? [];
  } catch {
    zones.value = [];
  }
}

function clearZoneFormErrors() {
  zoneFormErrors.name = '';
  zoneFormErrors.type = '';
}

function resetZoneForm() {
  zoneForm.name = '';
  zoneForm.type = 'quadra';
  zoneForm.color = '#3B82F6';
  clearZoneFormErrors();
}

function openZoneForm() {
  editingZone.value = null;
  resetZoneForm();
  showZoneForm.value = true;
}

function editZone(zone) {
  editingZone.value = zone;
  zoneForm.name = zone.name ?? '';
  zoneForm.type = zone.type ?? 'quadra';
  zoneForm.color = zone.color ?? '#3B82F6';
  clearZoneFormErrors();
  showZoneForm.value = true;
}

function closeZoneForm() {
  showZoneForm.value = false;
  editingZone.value = null;
  resetZoneForm();
}

function validateZoneForm() {
  clearZoneFormErrors();

  const name = zoneForm.name.trim();
  const type = zoneForm.type || 'quadra';

  if (!name) {
    zoneFormErrors.name = 'Informe o nome da zona.';
  }

  if (!type) {
    zoneFormErrors.type = 'Selecione o tipo da zona.';
  }

  return !zoneFormErrors.name && !zoneFormErrors.type;
}

function applyZoneFormApiErrors(err) {
  const apiErrors = err?.response?.data?.errors;
  if (!apiErrors || typeof apiErrors !== 'object') return;

  if (apiErrors.name?.[0]) zoneFormErrors.name = apiErrors.name[0];
  if (apiErrors.type?.[0]) zoneFormErrors.type = apiErrors.type[0];
}

function buildZonePayload() {
  return {
    name: zoneForm.name.trim(),
    type: zoneForm.type || 'quadra',
    color: zoneForm.color || '#3B82F6',
  };
}

async function saveZone() {
  if (!validateZoneForm()) {
    toast.warning('Verifique os campos da zona.');
    return;
  }

  const payload = buildZonePayload();
  savingZone.value = true;

  try {
    let createdZone = null;

    if (editingZone.value) {
      await api.put(`/developments/${route.params.id}/zones/${editingZone.value.id}`, payload);
      toast.success('Zona atualizada.');
    } else {
      const { data } = await api.post(`/developments/${route.params.id}/zones`, payload);
      createdZone = data;
      toast.success('Zona criada.');
    }

    closeZoneForm();
    await loadZones();
    drawZonesOnMap();

    if (isMapFullscreen.value && createdZone) {
      const zone = zones.value.find((z) => z.id === createdZone.id) ?? createdZone;
      pickZoneForMapping(zone);
    }
  } catch (err) {
    applyZoneFormApiErrors(err);
    toast.error(getApiErrorMessage(err, 'Erro ao salvar zona.'));
  } finally {
    savingZone.value = false;
  }
}

async function deleteZone(zone) {
  const ok = await confirm(
    'Excluir zona',
    `Excluir "${zone.name}"? Os lotes dentro dela não serão excluídos.`,
    'Sim, excluir',
  );
  if (!ok) return;

  try {
    await api.delete(`/developments/${route.params.id}/zones/${zone.id}`);
    toast.success('Zona excluída.');
    await loadZones();
    if (zoneLayers[zone.id]) {
      map?.removeLayer(zoneLayers[zone.id]);
      delete zoneLayers[zone.id];
    }
  } catch {
    toast.error('Erro ao excluir zona.');
  }
}

const generateLotsZone = ref(null);
const generating = ref(false);
const generateForm = ref({
  quantity: 10,
  start_from: 1,
  area: '',
  total_value: 0,
  pattern: '',
});

const previewLotNumber = computed(() => {
  const zone = generateLotsZone.value;
  const pattern = generateForm.value.pattern || form.value.lot_number_pattern || '{zona}-L{numero2}';
  if (!zone) return pattern;

  const num = parseInt(generateForm.value.start_from, 10) || 1;
  return pattern
    .replace('{zona}', zone.name)
    .replace('{numero}', String(num))
    .replace('{numero2}', String(num).padStart(2, '0'))
    .replace('{numero3}', String(num).padStart(3, '0'));
});

function openGenerateLots(zone) {
  if (!canGenerateLotsInZone(zone)) {
    toast.warning(generateLotsBlockedReason(zone));
    return;
  }

  generateLotsZone.value = zone;
  generateForm.value = {
    quantity: 10,
    start_from: 1,
    area: '',
    total_value: 0,
    pattern: '',
  };
}

async function doGenerateLots() {
  generating.value = true;
  try {
    const { data } = await api.post(
      `/developments/${route.params.id}/zones/${generateLotsZone.value.id}/generate-lots`,
      {
        quantity: parseInt(generateForm.value.quantity, 10),
        start_from: parseInt(generateForm.value.start_from, 10) || 1,
        area: generateForm.value.area ? parseFloat(generateForm.value.area) : null,
        total_value: generateForm.value.total_value || null,
        pattern: generateForm.value.pattern || null,
      },
    );
    toast.success(`${data.created} lotes gerados com sucesso!`);
    generateLotsZone.value = null;
    await loadZones();
  } catch (err) {
    toast.error(err?.response?.data?.message ?? 'Erro ao gerar lotes.');
  } finally {
    generating.value = false;
  }
}

async function loadItem() {
  if (!isEdit.value) return;

  loading.value = true;
  try {
    const { data } = await api.get(`/developments/${route.params.id}`);
    const item = data.data ?? data;

    form.value = {
      name: item.name ?? '',
      description: item.description ?? '',
      location: item.location ?? '',
      status: item.status ?? 'active',
      down_payment_percent: String(item.down_payment_percent ?? 20),
      lot_number_pattern: item.lot_number_pattern ?? '{zona}-L{numero2}',
      coordinates: item.coordinates ?? null,
      map_center: item.map_center ?? null,
      map_zoom: item.map_zoom ?? 17,
    };
  } catch {
    toast.error('Erro ao carregar empreendimento');
    router.push({ name: 'developments.index' });
  } finally {
    loading.value = false;
  }
}

async function submit() {
  saving.value = true;
  try {
    if (isEdit.value) {
      await api.put(`/developments/${route.params.id}`, form.value);
      toast.success('Empreendimento atualizado.');
    } else {
      const { data } = await api.post('/developments', form.value);
      const id = (data.data ?? data).id;
      toast.success('Empreendimento criado.');
      router.push({ name: 'developments.edit', params: { id } });
    }
  } catch {
    toast.error('Erro ao salvar empreendimento.');
  } finally {
    saving.value = false;
  }
}

onMounted(async () => {
  await loadItem();
  await loadZones();
  await nextTick();
  await initMap();
  if (zones.value.length) drawZonesOnMap();
});

watch(isMapFullscreen, (active) => {
  if (!active) {
    showZoneMapPicker.value = false;
  }
});

onUnmounted(() => {
  if (locationMarker && map) {
    map.removeLayer(locationMarker);
    locationMarker = null;
  }
  map?.remove();
  map = null;
});
</script>
