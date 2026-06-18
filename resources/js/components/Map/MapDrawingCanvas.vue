<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue';
import Swal from 'sweetalert2';
import { useMapDrawing } from '@/composables/useMapDrawing';
import { normalizePolygonCoordinates } from '@/utils/mapGeometry';
import { isCoarsePointerDevice } from '@/utils/mapGpsPreview';
import { ZONE_TYPE_OPTIONS } from '@/utils/zone';
import Button from '@/components/Common/Button.vue';
import Modal from '@/components/Common/Modal.vue';
import MapSnapControls from '@/components/Map/MapSnapControls.vue';
import {
  ArrowsPointingInIcon,
  ArrowsPointingOutIcon,
  ArrowUturnLeftIcon,
  LockClosedIcon,
  LockOpenIcon,
  MapIcon,
  MapPinIcon,
  TagIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
  mode: {
    type: String,
    default: 'lot',
    validator: (value) => ['lot', 'development'].includes(value),
  },
  coordinates: {
    type: Array,
    default: null,
  },
  contextPerimeter: {
    type: Array,
    default: null,
  },
  contextStreets: {
    type: Array,
    default: () => [],
  },
  contextZones: {
    type: Array,
    default: () => [],
  },
  contextLots: {
    type: Array,
    default: () => [],
  },
  boundaryPolygon: {
    type: Array,
    default: null,
  },
  mapCenter: {
    type: Array,
    default: null,
  },
  mapZoom: {
    type: Number,
    default: null,
  },
  fitContextOnLoad: {
    type: Boolean,
    default: true,
  },
  demarcationSaving: {
    type: Boolean,
    default: false,
  },
  editingLotId: {
    type: [String, Number],
    default: null,
  },
  featureLabel: {
    type: String,
    default: null,
  },
  savedCoordinates: {
    type: Array,
    default: null,
  },
});

const emit = defineEmits([
  'update:coordinates',
  'update:areaComputed',
  'update:gpsAccuracy',
  'save-demarcation',
]);

const coordinatesModel = ref(
  normalizePolygonCoordinates(props.savedCoordinates ?? props.coordinates),
);

watch(
  () => [props.savedCoordinates, props.coordinates],
  () => {
    const normalized =
      normalizePolygonCoordinates(props.savedCoordinates)
      ?? normalizePolygonCoordinates(props.coordinates);

    if (!normalized?.length) {
      return;
    }

    if (JSON.stringify(normalized) === JSON.stringify(coordinatesModel.value)) {
      return;
    }

    coordinatesModel.value = normalized;
  },
  { immediate: true, deep: true },
);

const contextPerimeterRef = computed(() => props.contextPerimeter);
const contextStreetsRef = computed(() => props.contextStreets);
const contextZonesRef = computed(() => props.contextZones);
const contextLotsRef = computed(() => props.contextLots);
const savedCoordinatesRef = computed(() => props.savedCoordinates);
const featureLabelRef = computed(() => props.featureLabel);
const boundaryPolygonRef = computed(() => props.boundaryPolygon);
const mapCenterRef = computed(() => props.mapCenter);
const mapZoomRef = computed(() => props.mapZoom);
const editingLotIdRef = computed(() => props.editingLotId);

const {
  mapContainer,
  mapSectionRef,
  mapFooterRef,
  mapReady,
  isMapFullscreen,
  toggleMapFullscreen,
  drawingMode,
  isDrawing,
  boundaryHint,
  canSaveDrawing,
  hasSavedDemarcation,
  startedFromExistingPolygon,
  locatingUser,
  capturingGps,
  gpsAccuracy,
  mapPanLocked,
  initMap,
  startDrawLot,
  cancelDrawing,
  finishDrawing,
  undoLastPoint,
  clearSavedFeature,
  captureGpsPoint,
  goToMyLocation,
  toggleMapPanLock,
  rotateMapBy,
  zoomMapIn,
  zoomMapOut,
  visibleZoneNameTypes,
  hasMappedZones,
  mappedZonesCountByType,
  syncZoneNameLabels,
  computedArea,
} = useMapDrawing({
  mode: props.mode,
  coordinates: coordinatesModel,
  contextPerimeter: contextPerimeterRef,
  contextStreets: contextStreetsRef,
  contextZones: contextZonesRef,
  contextLots: contextLotsRef,
  boundaryPolygon: boundaryPolygonRef,
  mapCenter: mapCenterRef,
  mapZoom: mapZoomRef,
  savedCoordinates: savedCoordinatesRef,
  featureLabel: featureLabelRef,
  editingLotId: editingLotIdRef,
  fitContextOnLoad: props.fitContextOnLoad,
  onDemarcationSaved: (coords) => {
    emit('save-demarcation', coords);
  },
  onCoordinatesChange: (coords) => {
    emit('update:coordinates', normalizePolygonCoordinates(coords));
  },
});

watch(computedArea, (value) => {
  emit('update:areaComputed', value);
}, { immediate: true });

watch(gpsAccuracy, (value) => {
  emit('update:gpsAccuracy', value);
});

const showZoneNamePicker = ref(false);
const zoneNamePickerDraft = ref([]);

function openZoneNamePicker() {
  zoneNamePickerDraft.value = [...visibleZoneNameTypes.value];
  showZoneNamePicker.value = true;
}

function closeZoneNamePicker() {
  showZoneNamePicker.value = false;
}

function toggleZoneNameTypeDraft(type) {
  const index = zoneNamePickerDraft.value.indexOf(type);
  if (index >= 0) {
    zoneNamePickerDraft.value.splice(index, 1);
    return;
  }

  zoneNamePickerDraft.value.push(type);
}

function selectAllZoneNameTypesInDraft() {
  zoneNamePickerDraft.value = ZONE_TYPE_OPTIONS.map((option) => option.value);
}

function clearAllZoneNameTypesInDraft() {
  zoneNamePickerDraft.value = [];
}

function applyZoneNamePicker() {
  visibleZoneNameTypes.value = [...zoneNamePickerDraft.value];
  closeZoneNamePicker();
  syncZoneNameLabels();
}

async function confirmClearFeature() {
  const result = await Swal.fire({
    title: 'Limpar demarcação',
    text: 'A área demarcada do lote será removida. Deseja continuar?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sim, limpar',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: '#1a3a28',
    cancelButtonColor: '#6b7280',
  });

  if (result.isConfirmed) {
    clearSavedFeature();
  }
}

onMounted(async () => {
  await nextTick();
  await initMap();
});
</script>

<template>
  <div
    ref="mapSectionRef"
    class="map-fullscreen-section map-drawing-section space-y-3 sm:space-y-4"
    :class="{ 'map-fullscreen-section--overlay': isMapFullscreen }"
  >
    <div class="map-canvas-wrap relative min-w-0">
      <div
        ref="mapContainer"
        class="map-fullscreen-canvas map-drawing-canvas h-[min(42vh,380px)] min-h-[240px] w-full overflow-hidden rounded-lg border border-slate-300 sm:h-[560px] md:h-[600px]"
      />

      <div
        v-if="mapReady"
        class="map-floating-controls"
      >
        <div class="map-floating-controls-group">
          <button
            type="button"
            class="map-floating-controls-btn"
            title="Aumentar zoom"
            aria-label="Aumentar zoom"
            @click="zoomMapIn"
          >
            +
          </button>
          <button
            type="button"
            class="map-floating-controls-btn"
            title="Diminuir zoom"
            aria-label="Diminuir zoom"
            @click="zoomMapOut"
          >
            −
          </button>
        </div>
        <div
          v-if="isDrawing"
          class="map-floating-controls-group"
        >
          <button
            type="button"
            class="map-floating-controls-btn"
            :class="{ 'map-floating-controls-btn--active': mapPanLocked }"
            :title="mapPanLocked ? 'Destravar movimento do mapa' : 'Travar movimento do mapa'"
            :aria-label="mapPanLocked ? 'Destravar movimento do mapa' : 'Travar movimento do mapa'"
            @click="toggleMapPanLock"
          >
            <LockClosedIcon v-if="mapPanLocked" class="h-4 w-4" />
            <LockOpenIcon v-else class="h-4 w-4" />
          </button>
        </div>
      </div>

      <div
        v-if="boundaryHint"
        class="map-zone-invalid-hint"
      >
        {{ boundaryHint }}
      </div>
    </div>

    <p
      v-if="isDrawing && isCoarsePointerDevice()"
      class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs leading-relaxed text-blue-900"
    >
      Para máxima precisão no GPS: ative <strong>Alta precisão</strong> nas configurações do celular,
      use em área aberta e aguarde o sinal estabilizar. Depois de capturar, <strong>arraste cada ponto</strong> no mapa para a posição correta.
      <strong>Alt+clique</strong> (Option no Mac) em um vértice remove o ponto.
      Com o mapa <strong>travado</strong> (cadeado), o arraste move só o ponto — destrave para mover a visualização.
    </p>

    <div
      ref="mapFooterRef"
      class="map-fullscreen-footer"
      :class="{ 'map-fullscreen-footer--dedicated': isMapFullscreen }"
    >
      <div class="map-fullscreen-toolbar map-drawing-toolbar flex flex-col gap-2">
        <div class="flex flex-col gap-2 sm:flex-row sm:flex-nowrap sm:items-center sm:justify-between sm:gap-x-2">
          <div class="map-toolbar-group map-toolbar-group--primary flex min-w-0 w-full flex-wrap items-center gap-2 sm:w-auto sm:flex-1 sm:flex-nowrap">
            <button
              v-if="!isDrawing"
              type="button"
              class="map-toolbar-action-btn flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-[11px] font-medium text-slate-700 hover:bg-slate-50 sm:px-3 sm:text-xs"
              @click="startDrawLot"
            >
              <MapIcon class="h-3.5 w-3.5" />
              {{ hasSavedDemarcation ? 'Editar demarcação' : 'Demarcar lote' }}
            </button>

            <button
              v-if="hasSavedDemarcation && !isDrawing"
              type="button"
              class="map-toolbar-action-btn flex items-center gap-1.5 rounded-lg border border-red-200 bg-white px-2.5 py-1.5 text-[11px] font-medium text-red-600 hover:bg-red-50 sm:px-3 sm:text-xs"
              @click="confirmClearFeature"
            >
              Limpar demarcação
            </button>

            <button
              v-if="isDrawing"
              type="button"
              class="map-toolbar-action-btn flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-[11px] font-medium sm:px-3 sm:text-xs"
              :class="mapPanLocked
                ? 'border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100'
                : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'"
              @click="toggleMapPanLock"
            >
              <LockClosedIcon v-if="mapPanLocked" class="h-3.5 w-3.5" />
              <LockOpenIcon v-else class="h-3.5 w-3.5" />
              {{ mapPanLocked ? 'Mapa travado' : 'Travar mapa' }}
            </button>

            <MapSnapControls v-if="isDrawing" />

            <button
              v-if="isDrawing"
              type="button"
              class="map-toolbar-action-btn flex items-center gap-1.5 rounded-lg border border-amber-200 bg-white px-2.5 py-1.5 text-[11px] font-medium text-amber-600 hover:bg-amber-50 sm:px-3 sm:text-xs"
              @click="undoLastPoint"
            >
              <ArrowUturnLeftIcon class="h-3.5 w-3.5" />
              Desfazer último ponto
            </button>

            <button
              v-if="isDrawing"
              type="button"
              class="map-toolbar-action-btn flex items-center gap-1.5 rounded-lg border border-amber-300 bg-amber-50 px-2.5 py-1.5 text-[11px] font-medium text-amber-700 hover:bg-amber-100 sm:px-3 sm:text-xs"
              @click="cancelDrawing"
            >
              <XMarkIcon class="h-3.5 w-3.5" />
              Cancelar desenho
            </button>

            <button
              v-if="isDrawing && canSaveDrawing"
              type="button"
              class="map-toolbar-btn map-toolbar-btn--save map-toolbar-action-btn flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-[11px] disabled:cursor-not-allowed disabled:opacity-50 sm:px-3 sm:text-xs"
              :disabled="demarcationSaving"
              @click="finishDrawing()"
            >
              {{ demarcationSaving ? 'Salvando...' : 'Salvar demarcação' }}
            </button>

            <button
              type="button"
              class="map-toolbar-action-btn flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-emerald-50 px-2.5 py-1.5 text-[11px] font-medium text-emerald-700 hover:bg-emerald-100 disabled:opacity-50 sm:hidden"
              :disabled="capturingGps"
              @click="captureGpsPoint"
            >
              <MapPinIcon class="h-3.5 w-3.5" />
              {{ capturingGps ? 'Refinando GPS...' : 'Capturar ponto GPS' }}
            </button>

            <button
              v-if="!isDrawing"
              type="button"
              class="map-toolbar-action-btn flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-[11px] font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50 sm:px-3 sm:text-xs"
              :disabled="locatingUser"
              @click="goToMyLocation"
            >
              <MapPinIcon class="h-3.5 w-3.5" />
              {{ locatingUser ? 'Localizando...' : 'Minha localização' }}
            </button>
          </div>

          <div class="map-toolbar-group map-toolbar-group--map grid w-full min-w-0 grid-cols-2 gap-2 sm:flex sm:w-auto sm:shrink-0 sm:flex-nowrap sm:items-center sm:justify-end sm:gap-2">
            <button
              v-if="!isDrawing && hasMappedZones"
              type="button"
              class="map-toolbar-btn map-toolbar-btn--map map-toolbar-action-btn col-span-2 flex items-center justify-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-[11px] font-medium sm:col-span-1 sm:justify-start sm:px-3 sm:text-xs"
              :class="visibleZoneNameTypes.length
                ? 'border-emerald-300 bg-emerald-50 text-emerald-700'
                : ''"
              @click="openZoneNamePicker"
            >
              <TagIcon class="h-3.5 w-3.5" />
              Exibir nomes
            </button>
            <button
              v-if="!isDrawing"
              type="button"
              class="map-toolbar-btn map-toolbar-btn--map map-toolbar-action-btn flex items-center justify-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-[11px] font-medium sm:justify-start sm:px-3 sm:text-xs"
              @click="rotateMapBy(-15)"
            >
              <span class="sm:hidden">Girar esq.</span>
              <span class="hidden sm:inline">Girar pra esquerda</span>
            </button>
            <button
              v-if="!isDrawing"
              type="button"
              class="map-toolbar-btn map-toolbar-btn--map map-toolbar-action-btn flex items-center justify-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-[11px] font-medium sm:justify-start sm:px-3 sm:text-xs"
              @click="rotateMapBy(15)"
            >
              <span class="sm:hidden">Girar dir.</span>
              <span class="hidden sm:inline">Girar pra direita</span>
            </button>
            <button
              type="button"
              class="map-toolbar-btn map-toolbar-btn--map map-toolbar-action-btn col-span-2 flex items-center justify-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-[11px] font-medium sm:col-span-1 sm:justify-start sm:px-3 sm:text-xs"
              @click="toggleMapFullscreen"
            >
              <ArrowsPointingOutIcon v-if="!isMapFullscreen" class="h-3.5 w-3.5" />
              <ArrowsPointingInIcon v-else class="h-3.5 w-3.5" />
              {{ isMapFullscreen ? 'Sair da tela cheia' : 'Tela cheia' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <Modal
    :is-open="showZoneNamePicker"
    title="Exibir nomes no mapa"
    @close="closeZoneNamePicker"
  >
    <p class="text-xs text-slate-500">
      Selecione os tipos de zona cujos nomes devem aparecer no mapa.
    </p>

    <div class="mt-3 flex gap-2">
      <button
        type="button"
        class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50"
        @click="selectAllZoneNameTypesInDraft"
      >
        Marcar todos
      </button>
      <button
        type="button"
        class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50"
        @click="clearAllZoneNameTypesInDraft"
      >
        Limpar seleção
      </button>
    </div>

    <div class="mt-3 space-y-2">
      <button
        v-for="option in ZONE_TYPE_OPTIONS"
        :key="option.value"
        type="button"
        class="flex w-full items-center justify-between rounded-lg border px-3 py-2.5 text-left transition-colors"
        :class="zoneNamePickerDraft.includes(option.value)
          ? 'border-emerald-300 bg-emerald-50'
          : 'border-slate-200 bg-white hover:bg-slate-50'"
        @click="toggleZoneNameTypeDraft(option.value)"
      >
        <span>
          <span class="block text-sm font-medium text-slate-800">{{ option.label }}</span>
          <span class="block text-xs text-slate-400">
            {{ mappedZonesCountByType(option.value) }} no mapa
          </span>
        </span>
        <span
          class="flex h-5 w-5 shrink-0 items-center justify-center rounded border"
          :class="zoneNamePickerDraft.includes(option.value)
            ? 'border-emerald-600 bg-emerald-600 text-white'
            : 'border-slate-300 bg-white text-transparent'"
        >
          <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
          </svg>
        </span>
      </button>
    </div>

    <div class="mt-4 flex justify-end gap-2">
      <Button variant="outline" @click="closeZoneNamePicker">Cancelar</Button>
      <Button variant="primary" @click="applyZoneNamePicker">Aplicar</Button>
    </div>
  </Modal>
</template>
