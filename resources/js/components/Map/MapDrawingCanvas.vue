<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue';
import Swal from 'sweetalert2';
import { useMapDrawing } from '@/composables/useMapDrawing';
import { ZONE_TYPE_OPTIONS } from '@/utils/zone';
import Button from '@/components/Common/Button.vue';
import Modal from '@/components/Common/Modal.vue';
import {
  ArrowsPointingInIcon,
  ArrowsPointingOutIcon,
  ArrowUturnLeftIcon,
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
  contextZones: {
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
});

const emit = defineEmits([
  'update:coordinates',
  'update:areaComputed',
  'update:gpsAccuracy',
]);

const coordinatesModel = ref(props.coordinates);

watch(
  () => props.coordinates,
  (value) => {
    coordinatesModel.value = value;
  },
);

watch(
  coordinatesModel,
  (value) => {
    emit('update:coordinates', value);
  },
  { deep: true },
);

const contextPerimeterRef = computed(() => props.contextPerimeter);
const contextZonesRef = computed(() => props.contextZones);
const boundaryPolygonRef = computed(() => props.boundaryPolygon);
const mapCenterRef = computed(() => props.mapCenter);
const mapZoomRef = computed(() => props.mapZoom);

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
  locatingUser,
  capturingGps,
  gpsAccuracy,
  initMap,
  startDrawLot,
  cancelDrawing,
  finishDrawing,
  undoLastPoint,
  clearSavedFeature,
  captureGpsPoint,
  goToMyLocation,
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
  contextZones: contextZonesRef,
  boundaryPolygon: boundaryPolygonRef,
  mapCenter: mapCenterRef,
  mapZoom: mapZoomRef,
  fitContextOnLoad: props.fitContextOnLoad,
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
    class="map-fullscreen-section space-y-4"
    :class="{ 'map-fullscreen-section--overlay': isMapFullscreen }"
  >
    <div class="map-canvas-wrap relative">
      <div
        ref="mapContainer"
        class="map-fullscreen-canvas h-[560px] w-full overflow-hidden rounded-lg border border-slate-300 sm:h-[600px]"
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
      </div>

      <div
        v-if="boundaryHint"
        class="map-zone-invalid-hint"
      >
        {{ boundaryHint }}
      </div>
    </div>

    <div
      ref="mapFooterRef"
      class="map-fullscreen-footer"
      :class="{ 'map-fullscreen-footer--dedicated': isMapFullscreen }"
    >
      <div class="map-fullscreen-toolbar flex flex-wrap items-center justify-between gap-x-2 gap-y-2">
        <div class="map-toolbar-group map-toolbar-group--primary flex min-w-0 flex-1 flex-wrap items-center gap-2">
          <button
            v-if="!isDrawing"
            type="button"
            class="flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
            @click="startDrawLot"
          >
            <MapIcon class="h-3.5 w-3.5" />
            {{ coordinatesModel?.length ? 'Redesenhar lote' : 'Demarcar lote' }}
          </button>

          <button
            v-if="coordinatesModel?.length && !isDrawing"
            type="button"
            class="flex items-center gap-1.5 rounded-lg border border-red-200 bg-white px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50"
            @click="confirmClearFeature"
          >
            Limpar demarcação
          </button>

          <button
            v-if="isDrawing"
            type="button"
            class="flex items-center gap-1.5 rounded-lg border border-amber-200 bg-white px-3 py-1.5 text-xs font-medium text-amber-600 hover:bg-amber-50"
            @click="undoLastPoint"
          >
            <ArrowUturnLeftIcon class="h-3.5 w-3.5" />
            Desfazer último ponto
          </button>

          <button
            v-if="isDrawing"
            type="button"
            class="flex items-center gap-1.5 rounded-lg border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-100"
            @click="cancelDrawing"
          >
            <XMarkIcon class="h-3.5 w-3.5" />
            Cancelar desenho
          </button>

          <button
            v-if="isDrawing"
            type="button"
            class="map-toolbar-btn map-toolbar-btn--save flex items-center gap-1.5 rounded-lg px-3 py-1.5 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="!canSaveDrawing"
            @click="finishDrawing"
          >
            Salvar demarcação
          </button>

          <button
            type="button"
            class="flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-100 disabled:opacity-50"
            :disabled="capturingGps"
            @click="captureGpsPoint"
          >
            <MapPinIcon class="h-3.5 w-3.5" />
            {{ capturingGps ? 'Capturando GPS...' : 'Capturar ponto GPS' }}
          </button>

          <button
            v-if="!isDrawing"
            type="button"
            class="flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50"
            :disabled="locatingUser"
            @click="goToMyLocation"
          >
            <MapPinIcon class="h-3.5 w-3.5" />
            {{ locatingUser ? 'Localizando...' : 'Minha localização' }}
          </button>

          <span
            v-if="isDrawing"
            class="self-center text-xs font-medium text-blue-600"
          >
            Clique no mapa para adicionar pontos. Arraste as bolinhas para ajustar. Salve quando tiver pelo menos 3 pontos.
          </span>
        </div>

        <div class="map-toolbar-group map-toolbar-group--map flex shrink-0 flex-wrap items-center justify-end gap-2">
          <button
            v-if="!isDrawing && hasMappedZones"
            type="button"
            class="map-toolbar-btn map-toolbar-btn--map flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium"
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
            class="map-toolbar-btn map-toolbar-btn--map flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium"
            @click="rotateMapBy(-15)"
          >
            Girar pra esquerda
          </button>
          <button
            v-if="!isDrawing"
            type="button"
            class="map-toolbar-btn map-toolbar-btn--map flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium"
            @click="rotateMapBy(15)"
          >
            Girar pra direita
          </button>
          <button
            type="button"
            class="map-toolbar-btn map-toolbar-btn--map flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium"
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
