<script setup>
import { reactive, ref, watch } from 'vue';
import { useToast } from 'vue-toastification';
import Button from '@/components/Common/Button.vue';
import SelectInput from '@/components/Common/SelectInput.vue';
import { DEFAULT_TECHNICAL_MAP_OPTIONS, TECHNICAL_MAP_PAPER_SIZES } from '@/utils/technicalMapSvg';
import {
  buildTechnicalMapPayload,
  exportTechnicalMapClientPdf,
  exportTechnicalMapClientSvg,
} from '@/services/technicalMap.service';

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false,
  },
  developmentId: {
    type: [Number, String],
    default: null,
  },
  development: {
    type: Object,
    default: () => ({}),
  },
  zones: {
    type: Array,
    default: () => [],
  },
  streets: {
    type: Array,
    default: () => [],
  },
  lots: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(['close']);

const toast = useToast();
const exporting = ref(false);

const options = reactive({
  showPerimeter: true,
  showZones: true,
  showZoneNames: true,
  showStreets: true,
  showStreetNames: true,
  showLots: true,
  showLotNumbers: true,
  showLotDimensions: true,
  showLotEdgeDimensions: true,
  showScaleBar: true,
  showNorthArrow: true,
  showLegend: true,
  paperSize: DEFAULT_TECHNICAL_MAP_OPTIONS.paperSize,
  orientation: DEFAULT_TECHNICAL_MAP_OPTIONS.orientation,
  mapBearing: 0,
});

const paperSizeOptions = Object.keys(TECHNICAL_MAP_PAPER_SIZES).map((value) => ({
  value,
  label: value,
}));

const orientationOptions = [
  { value: 'landscape', label: 'Paisagem' },
  { value: 'portrait', label: 'Retrato' },
];

const toggleOptions = [
  { key: 'showPerimeter', label: 'Perímetro' },
  { key: 'showZones', label: 'Zonas (polígonos)' },
  { key: 'showZoneNames', label: 'Nomes das zonas' },
  { key: 'showStreets', label: 'Ruas (polígonos)' },
  { key: 'showStreetNames', label: 'Nomes das ruas' },
  { key: 'showLots', label: 'Lotes (polígonos)' },
  { key: 'showLotNumbers', label: 'Números dos lotes' },
  { key: 'showLotDimensions', label: 'Metragem dos lotes' },
  { key: 'showLotEdgeDimensions', label: 'Cotas nas arestas dos lotes' },
  { key: 'showScaleBar', label: 'Escala gráfica' },
  { key: 'showNorthArrow', label: 'Seta norte' },
  { key: 'showLegend', label: 'Legenda' },
];

watch(
  () => props.isOpen,
  (open) => {
    if (!open) {
      return;
    }

    options.mapBearing = Number(props.development?.map_bearing ?? 0);
  },
);

function buildPayload() {
  return buildTechnicalMapPayload({
    development: props.development,
    zones: props.zones,
    streets: props.streets,
    lots: props.lots,
  });
}

async function runExport(handler) {
  exporting.value = true;

  try {
    await handler();
    toast.success('Planta técnica gerada com sucesso.');
    emit('close');
  } catch {
    toast.error('Erro ao gerar planta técnica.');
  } finally {
    exporting.value = false;
  }
}

function exportPdf() {
  return runExport(() => exportTechnicalMapClientPdf(buildPayload(), { ...options }));
}

function exportSvg() {
  return runExport(() => exportTechnicalMapClientSvg(buildPayload(), { ...options }));
}
</script>

<template>
  <teleport to="body">
    <transition name="fade">
      <div
        v-if="isOpen"
        class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/50 px-4"
        @click.self.prevent
      >
        <div class="card max-h-[90vh] w-full max-w-2xl overflow-y-auto p-6" @click.stop>
          <div class="mb-4 flex items-center justify-between gap-3">
            <div>
              <h3 class="text-lg font-semibold text-slate-800">Exportar planta técnica</h3>
              <p class="mt-1 text-xs text-slate-500">
                Gera desenho vetorial com cotas, nomes e legenda para PDF ou SVG.
              </p>
            </div>
            <button
              type="button"
              class="text-slate-400 hover:text-slate-600"
              @click="emit('close')"
            >
              <span class="sr-only">Fechar</span>
              <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <div class="grid gap-4 sm:grid-cols-2">
            <SelectInput
              v-model="options.paperSize"
              label="Tamanho do papel"
              :options="paperSizeOptions"
            />
            <SelectInput
              v-model="options.orientation"
              label="Orientação"
              :options="orientationOptions"
            />
          </div>

          <div class="mt-4 grid gap-2 sm:grid-cols-2">
            <label
              v-for="toggle in toggleOptions"
              :key="toggle.key"
              class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"
            >
              <input
                v-model="options[toggle.key]"
                type="checkbox"
                class="rounded border-slate-300 text-emerald-700 focus:ring-emerald-600"
              >
              <span>{{ toggle.label }}</span>
            </label>
          </div>

          <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:justify-end">
            <Button variant="outline" :disabled="exporting" @click="emit('close')">
              Cancelar
            </Button>
            <Button variant="outline" :disabled="exporting" @click="exportSvg">
              Baixar SVG
            </Button>
            <Button variant="primary" :disabled="exporting" @click="exportPdf">
              {{ exporting ? 'Gerando...' : 'Baixar PDF' }}
            </Button>
          </div>
        </div>
      </div>
    </transition>
  </teleport>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
