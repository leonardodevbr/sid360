<script setup>
import { ref, onMounted } from 'vue';
import { useToast } from 'vue-toastification';
import api from '@/services/api';
import { useAlert } from '@/composables/useAlert';
import { PhotoIcon, StarIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
  endpoint: {
    type: String,
    required: true,
  },
});

const toast = useToast();
const { confirm } = useAlert();

const items = ref([]);
const uploading = ref(false);
const uploadQueue = ref([]);
const fileInput = ref(null);

async function load() {
  try {
    const { data } = await api.get(props.endpoint);
    items.value = data ?? [];
  } catch {
    items.value = [];
  }
}

async function onFileChange(event) {
  const files = Array.from(event.target.files ?? []);
  if (!files.length) {
    return;
  }

  if (fileInput.value) {
    fileInput.value.value = '';
  }

  uploading.value = true;
  uploadQueue.value = files.map((file) => ({
    name: file.name,
    progress: 0,
    done: false,
  }));

  for (let index = 0; index < files.length; index += 1) {
    const file = files[index];
    const formData = new FormData();
    formData.append('file', file);
    formData.append('is_cover', items.value.length === 0 && index === 0 ? '1' : '0');

    try {
      uploadQueue.value[index].progress = 30;
      await api.post(props.endpoint, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
        onUploadProgress: (progressEvent) => {
          if (progressEvent.total) {
            uploadQueue.value[index].progress = Math.round((progressEvent.loaded / progressEvent.total) * 90);
          }
        },
      });
      uploadQueue.value[index].progress = 100;
      uploadQueue.value[index].done = true;
    } catch {
      toast.error(`Erro ao enviar ${file.name}`);
    }
  }

  await load();
  setTimeout(() => {
    uploadQueue.value = [];
  }, 1500);
  uploading.value = false;
}

async function setCover(item) {
  try {
    await api.post(`/media/${item.id}/cover`);
    items.value.forEach((mediaItem) => {
      mediaItem.is_cover = mediaItem.id === item.id;
    });
    toast.success('Foto de capa definida.');
  } catch {
    toast.error('Erro ao definir capa.');
  }
}

async function confirmDelete(item) {
  const ok = await confirm('Excluir foto', 'Esta mídia será removida permanentemente.');
  if (!ok) {
    return;
  }

  try {
    await api.post(`/media/${item.id}/delete`);
    items.value = items.value.filter((mediaItem) => mediaItem.id !== item.id);
    toast.success('Foto excluída.');
  } catch {
    toast.error('Erro ao excluir.');
  }
}

async function updateCaption(item, caption) {
  if (caption === (item.caption ?? '')) {
    return;
  }

  try {
    await api.post(`/media/${item.id}/update`, { caption });
    item.caption = caption;
  } catch {
    toast.error('Erro ao salvar legenda.');
  }
}

onMounted(() => load());
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-wrap items-center gap-3">
      <label
        class="flex cursor-pointer items-center gap-2 rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-2.5 text-sm text-slate-600 transition-colors hover:bg-slate-100"
      >
        <PhotoIcon class="h-4 w-4" />
        {{ uploading ? 'Enviando...' : 'Adicionar fotos' }}
        <input
          ref="fileInput"
          type="file"
          multiple
          accept="image/jpeg,image/png,image/webp,video/mp4,video/quicktime"
          class="hidden"
          :disabled="uploading"
          @change="onFileChange"
        >
      </label>
      <p class="text-xs text-slate-400">
        JPG, PNG, WEBP ou MP4 — máx. 50MB por arquivo
      </p>
    </div>

    <div v-if="uploadQueue.length" class="space-y-1.5">
      <div
        v-for="item in uploadQueue"
        :key="item.name"
        class="flex items-center gap-3 rounded-lg bg-slate-50 px-3 py-2"
      >
        <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-200">
          <div
            class="h-2 rounded-full bg-emerald-500 transition-all"
            :style="{ width: `${item.progress}%` }"
          />
        </div>
        <p class="w-28 shrink-0 truncate text-xs text-slate-500">
          {{ item.name }}
        </p>
        <span class="text-xs" :class="item.done ? 'text-emerald-600' : 'text-slate-400'">
          {{ item.done ? 'Concluído' : `${item.progress}%` }}
        </span>
      </div>
    </div>

    <div v-if="items.length" class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
      <div
        v-for="item in items"
        :key="item.id"
        class="group relative aspect-square overflow-hidden rounded-lg border border-slate-200 bg-slate-100"
      >
        <img
          v-if="item.type === 'photo'"
          :src="item.url"
          :alt="item.caption ?? item.filename"
          class="h-full w-full object-cover"
          loading="lazy"
        >
        <video
          v-else-if="item.type === 'video'"
          :src="item.url"
          class="h-full w-full object-cover"
          muted
          preload="metadata"
        />

        <div
          v-if="item.is_cover"
          class="absolute left-1.5 top-1.5 rounded-full bg-emerald-600 px-2 py-0.5 text-xs font-bold text-white shadow"
        >
          Capa
        </div>

        <div class="absolute inset-0 flex flex-col justify-between bg-black/50 p-2 opacity-0 transition-opacity group-hover:opacity-100">
          <div class="flex justify-end gap-1">
            <button
              v-if="!item.is_cover && item.type === 'photo'"
              type="button"
              class="rounded bg-white/90 p-1 text-slate-700 hover:bg-white"
              title="Definir como capa"
              @click.stop="setCover(item)"
            >
              <StarIcon class="h-4 w-4" />
            </button>
            <button
              type="button"
              class="rounded bg-red-500/90 p-1 text-white hover:bg-red-600"
              title="Excluir"
              @click.stop="confirmDelete(item)"
            >
              <TrashIcon class="h-4 w-4" />
            </button>
          </div>

          <div class="mt-auto">
            <input
              type="text"
              :value="item.caption ?? ''"
              placeholder="Legenda..."
              class="w-full rounded bg-white/90 px-2 py-1 text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500"
              @blur="updateCaption(item, $event.target.value)"
            >
          </div>
        </div>
      </div>
    </div>

    <p v-else-if="!uploading" class="py-4 text-center text-xs text-slate-400">
      Nenhuma foto cadastrada. Adicione fotos acima.
    </p>
  </div>
</template>
