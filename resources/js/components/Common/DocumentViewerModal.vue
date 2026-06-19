<template>
  <teleport to="body">
    <transition name="fade">
      <div
        v-if="isOpen"
        class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/70 px-4 py-6"
        @click.self="handleClose"
      >
        <div class="card flex max-h-full w-full max-w-4xl flex-col p-0" @click.stop>
          <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-5 py-3">
            <div class="min-w-0">
              <p class="flex flex-wrap items-center gap-1.5 text-sm font-semibold text-slate-800">
                {{ currentDoc?.type_label }}
                <span
                  v-if="currentDoc?.side_label"
                  class="rounded-full bg-indigo-50 px-1.5 py-0.5 text-[10px] font-medium text-indigo-700"
                >
                  {{ currentDoc?.side_label }}
                </span>
              </p>
              <p class="truncate text-xs text-slate-500">{{ currentDoc?.original_filename }}</p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
              <span v-if="documents.length > 1" class="text-xs text-slate-400">
                {{ activeIndex + 1 }} / {{ documents.length }}
              </span>
              <Button type="button" variant="outline" @click="$emit('download', currentDoc)">
                <ArrowDownTrayIcon class="mr-1.5 h-4 w-4" />
                Baixar
              </Button>
              <button
                type="button"
                class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                title="Fechar"
                @click="handleClose"
              >
                <span class="sr-only">Fechar</span>
                <XMarkIcon class="h-5 w-5" />
              </button>
            </div>
          </div>

          <div class="relative flex min-h-[60vh] flex-1 items-center justify-center overflow-auto bg-slate-100 p-4">
            <button
              v-if="documents.length > 1"
              type="button"
              class="absolute left-3 top-1/2 z-10 -translate-y-1/2 rounded-full bg-white/90 p-2 text-slate-500 shadow hover:bg-white hover:text-slate-800 disabled:cursor-not-allowed disabled:opacity-30"
              :disabled="activeIndex === 0"
              title="Anterior"
              @click="goTo(activeIndex - 1)"
            >
              <ChevronLeftIcon class="h-5 w-5" />
            </button>

            <div v-if="loading" class="text-xs text-slate-400">Carregando documento...</div>
            <div v-else-if="loadError" class="px-6 text-center text-xs text-red-500">{{ loadError }}</div>
            <img
              v-else-if="isImage"
              :src="previewUrl"
              :alt="currentDoc?.original_filename"
              class="max-h-[75vh] max-w-full rounded shadow-sm"
            />
            <iframe
              v-else-if="isPdf"
              :src="previewUrl"
              class="h-[75vh] w-full rounded border border-slate-200 bg-white"
              title="Visualização do documento"
            />
            <div v-else class="px-6 text-center text-xs text-slate-500">
              Visualização não suportada para este tipo de arquivo.<br />
              Use o botão "Baixar" para abri-lo.
            </div>

            <button
              v-if="documents.length > 1"
              type="button"
              class="absolute right-3 top-1/2 z-10 -translate-y-1/2 rounded-full bg-white/90 p-2 text-slate-500 shadow hover:bg-white hover:text-slate-800 disabled:cursor-not-allowed disabled:opacity-30"
              :disabled="activeIndex === documents.length - 1"
              title="Próximo"
              @click="goTo(activeIndex + 1)"
            >
              <ChevronRightIcon class="h-5 w-5" />
            </button>
          </div>
        </div>
      </div>
    </transition>
  </teleport>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import Button from './Button.vue';
import { ArrowDownTrayIcon, XMarkIcon, ChevronLeftIcon, ChevronRightIcon } from '@heroicons/vue/24/outline';
import { previewClientDocument, previewSaleDocument } from '@/services/document.service';
import { getApiErrorMessage } from '@/utils/apiError';

// Galeria de visualização inline (sem download) para documentos de cliente/venda.
// Busca cada documento como blob autenticado (nunca uma URL pública do R2) e
// navega entre os itens da lista recebida via setas/teclado, estilo "galeria".
const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false,
  },
  documents: {
    type: Array,
    default: () => [],
  },
  initialIndex: {
    type: Number,
    default: 0,
  },
  entityType: {
    type: String,
    required: true,
    validator: (value) => ['client', 'sale'].includes(value),
  },
  entityId: {
    type: [Number, String],
    required: true,
  },
});

const emit = defineEmits(['close', 'download']);

const activeIndex = ref(props.initialIndex);
const previewUrl = ref('');
const loading = ref(false);
const loadError = ref('');

const currentDoc = computed(() => props.documents[activeIndex.value] ?? null);
const isImage = computed(() => (currentDoc.value?.mime_type ?? '').startsWith('image/'));
const isPdf = computed(() => currentDoc.value?.mime_type === 'application/pdf');

function revokeCurrentUrl() {
  if (previewUrl.value) {
    window.URL.revokeObjectURL(previewUrl.value);
    previewUrl.value = '';
  }
}

function goTo(index) {
  if (index < 0 || index >= props.documents.length) return;
  activeIndex.value = index;
}

function handleClose() {
  emit('close');
}

function handleKeydown(event) {
  if (!props.isOpen) return;
  if (event.key === 'Escape') handleClose();
  if (event.key === 'ArrowLeft') goTo(activeIndex.value - 1);
  if (event.key === 'ArrowRight') goTo(activeIndex.value + 1);
}

async function loadPreview() {
  revokeCurrentUrl();
  loadError.value = '';

  const doc = currentDoc.value;
  if (!doc || (!isImage.value && !isPdf.value)) {
    return;
  }

  loading.value = true;
  try {
    previewUrl.value = props.entityType === 'client'
      ? await previewClientDocument(props.entityId, doc.id, doc.mime_type)
      : await previewSaleDocument(props.entityId, doc.id, doc.mime_type);
  } catch (err) {
    loadError.value = getApiErrorMessage(err, 'Erro ao carregar a visualização do documento.');
  } finally {
    loading.value = false;
  }
}

watch(() => props.isOpen, (open) => {
  if (open) {
    activeIndex.value = props.initialIndex;
    loadPreview();
  } else {
    revokeCurrentUrl();
  }
});

watch(activeIndex, () => {
  if (props.isOpen) loadPreview();
});

onMounted(() => window.addEventListener('keydown', handleKeydown));
onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown);
  revokeCurrentUrl();
});
</script>

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
