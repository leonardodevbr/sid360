<template>
  <div class="card p-5">
    <div class="mb-1 flex items-center justify-between gap-2">
      <h3 class="text-sm font-semibold text-slate-800">{{ title }}</h3>
      <span class="shrink-0 text-xs text-slate-400">{{ documents.length }} arquivo(s)</span>
    </div>
    <p v-if="description" class="mb-4 text-xs text-slate-500">{{ description }}</p>

    <div class="mb-5 rounded-lg border border-dashed border-slate-200 bg-slate-50 p-4">
      <div class="flex flex-wrap items-end gap-2">
        <div class="w-full sm:w-60">
          <SelectInput
            v-model="selectedType"
            :options="typeOptions"
            label="Tipo de documento"
            placeholder="Selecione o tipo"
            :searchable="false"
            :can-clear="false"
          />
        </div>
        <Button type="button" variant="outline" @click="openFilePicker">
          <ArrowUpTrayIcon class="mr-2 h-4 w-4" />
          Selecionar arquivo
        </Button>
        <Button
          v-if="selectedFile"
          type="button"
          variant="primary"
          :loading="uploading"
          :disabled="!selectedType"
          @click="handleUpload"
        >
          Enviar
        </Button>
      </div>
      <p v-if="selectedFileName" class="mt-2 text-xs text-slate-600">
        Arquivo selecionado: <span class="font-medium">{{ selectedFileName }}</span>
      </p>
      <p class="mt-2 text-xs text-slate-400">PDF, JPG, PNG ou WebP — máximo 10 MB</p>
      <input
        ref="fileInputRef"
        type="file"
        class="sr-only"
        accept=".pdf,image/jpeg,image/png,image/webp"
        @change="onFileSelected"
      />
    </div>

    <div v-if="loading" class="py-6 text-center text-xs text-slate-400">Carregando documentos...</div>
    <div v-else-if="!documents.length" class="py-6 text-center text-xs text-slate-400">
      Nenhum documento enviado ainda.
    </div>
    <template v-else>
      <div class="divide-y divide-slate-50">
        <div
          v-for="doc in visibleDocuments"
          :key="doc.id"
          class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between"
        >
          <div class="flex min-w-0 items-center gap-2">
            <DocumentTextIcon class="h-5 w-5 shrink-0 text-slate-400" />
            <div class="min-w-0">
              <p class="flex flex-wrap items-center gap-1.5 text-sm text-slate-700">
                <span class="font-medium">{{ doc.type_label }}</span>
                <span
                  v-if="isVersioned && !doc.is_current"
                  class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-500"
                >
                  versão {{ doc.version }}
                </span>
                <span
                  v-else-if="isVersioned"
                  class="rounded-full bg-emerald-50 px-1.5 py-0.5 text-[10px] font-medium text-emerald-700"
                >
                  atual
                </span>
              </p>
              <p class="truncate text-xs text-slate-500">{{ doc.original_filename }}</p>
            </div>
          </div>
          <div class="flex shrink-0 items-center gap-2">
            <span class="text-xs text-slate-400">{{ formatDate(doc.created_at) }}</span>
            <Button
              type="button"
              variant="outline"
              :loading="downloadingId === doc.id"
              @click="handleDownload(doc)"
            >
              Baixar
            </Button>
            <button
              v-if="allowDelete"
              type="button"
              class="rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-600"
              title="Excluir"
              @click="handleDelete(doc)"
            >
              <TrashIcon class="h-4 w-4" />
            </button>
          </div>
        </div>
      </div>

      <button
        v-if="isVersioned && hiddenHistoryCount > 0"
        type="button"
        class="mt-3 text-xs font-medium text-action hover:underline"
        @click="showHistory = !showHistory"
      >
        {{ showHistory ? 'Ocultar versões anteriores' : `Ver versões anteriores (${hiddenHistoryCount})` }}
      </button>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useToast } from 'vue-toastification';
import Button from './Button.vue';
import SelectInput from './SelectInput.vue';
import { DOCUMENT_TYPES } from '@/utils/documentTypes';
import { formatDate } from '@/utils/format';
import { getApiErrorMessage } from '@/utils/apiError';
import { useAlert } from '@/composables/useAlert';
import {
  listClientDocuments,
  uploadClientDocument,
  downloadClientDocument,
  deleteClientDocument,
  listSaleDocuments,
  uploadSaleDocument,
  downloadSaleDocument,
} from '@/services/document.service';
import { ArrowUpTrayIcon, DocumentTextIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
  // 'client' -> perfil geral versionado (clients_documents); 'sale' -> cópia congelada (sale_documents)
  entityType: {
    type: String,
    required: true,
    validator: (value) => ['client', 'sale'].includes(value),
  },
  entityId: {
    type: [Number, String],
    required: true,
  },
  title: {
    type: String,
    default: 'Documentos',
  },
  description: {
    type: String,
    default: '',
  },
});

const toast = useToast();
const { confirm } = useAlert();
const typeOptions = DOCUMENT_TYPES;

const isVersioned = computed(() => props.entityType === 'client');
const allowDelete = computed(() => props.entityType === 'client');

const documents = ref([]);
const loading = ref(false);
const uploading = ref(false);
const downloadingId = ref(null);
const showHistory = ref(false);
const selectedType = ref('');
const selectedFile = ref(null);
const selectedFileName = ref('');
const fileInputRef = ref(null);

const visibleDocuments = computed(() => {
  if (!isVersioned.value || showHistory.value) {
    return documents.value;
  }
  return documents.value.filter((doc) => doc.is_current);
});

const hiddenHistoryCount = computed(() => {
  if (!isVersioned.value) return 0;
  return documents.value.filter((doc) => !doc.is_current).length;
});

async function loadDocuments() {
  loading.value = true;
  try {
    documents.value = props.entityType === 'client'
      ? await listClientDocuments(props.entityId)
      : await listSaleDocuments(props.entityId);
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Erro ao carregar documentos.'));
  } finally {
    loading.value = false;
  }
}

function openFilePicker() {
  fileInputRef.value?.click();
}

function onFileSelected(event) {
  const file = event.target.files?.[0] ?? null;
  selectedFile.value = file;
  selectedFileName.value = file?.name ?? '';
}

async function handleUpload() {
  if (!selectedFile.value || !selectedType.value) return;

  uploading.value = true;
  try {
    if (props.entityType === 'client') {
      await uploadClientDocument(props.entityId, selectedFile.value, selectedType.value);
    } else {
      await uploadSaleDocument(props.entityId, selectedFile.value, selectedType.value);
    }
    toast.success('Documento enviado.');
    selectedFile.value = null;
    selectedFileName.value = '';
    selectedType.value = '';
    if (fileInputRef.value) fileInputRef.value.value = '';
    await loadDocuments();
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Erro ao enviar documento.'));
  } finally {
    uploading.value = false;
  }
}

async function handleDownload(doc) {
  downloadingId.value = doc.id;
  try {
    if (props.entityType === 'client') {
      await downloadClientDocument(props.entityId, doc.id, doc.original_filename);
    } else {
      await downloadSaleDocument(props.entityId, doc.id, doc.original_filename);
    }
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Erro ao baixar documento.'));
  } finally {
    downloadingId.value = null;
  }
}

async function handleDelete(doc) {
  const confirmed = await confirm(
    'Excluir documento?',
    `${doc.type_label} — ${doc.original_filename}. Essa ação não pode ser desfeita.`,
    'Sim, excluir',
  );
  if (!confirmed) return;

  try {
    await deleteClientDocument(props.entityId, doc.id);
    toast.success('Documento excluído.');
    await loadDocuments();
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Erro ao excluir documento.'));
  }
}

watch(() => props.entityId, () => loadDocuments());
onMounted(() => loadDocuments());
</script>
