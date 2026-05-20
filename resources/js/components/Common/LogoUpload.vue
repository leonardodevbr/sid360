<template>
  <div class="space-y-2">
    <label v-if="label" class="block text-sm font-medium text-slate-700">{{ label }}</label>
    <div
      :class="[
        'relative border-2 border-dashed rounded-lg overflow-hidden transition-colors',
        previewUrl ? 'border-slate-300' : 'border-slate-300 hover:border-blue-400',
        sizeClass,
        !entityId ? 'opacity-60 pointer-events-none' : 'cursor-pointer',
      ]"
      @click="entityId && triggerFileInput()"
    >
      <input
        ref="fileInput"
        type="file"
        accept="image/*"
        class="hidden"
        @change="handleFileSelect"
      />
      <div v-if="!previewUrl" class="flex flex-col items-center justify-center h-full p-4 text-slate-400">
        <PhotoIcon class="h-8 w-8 mb-2" />
        <span class="text-xs text-center">{{ entityId ? 'Clique para enviar imagem' : (type === 'user' ? 'Salve o usuário antes de enviar a assinatura' : 'Salve o registro antes de enviar o logo') }}</span>
      </div>
      <div v-else class="relative h-full w-full min-h-[120px]">
        <img
          :src="previewUrl"
          alt="Logo"
          class="h-full w-full object-contain max-h-40"
          @error="onImageError"
        />
        <button
          v-if="entityId"
          type="button"
          class="absolute top-1 right-1 bg-red-600 text-white rounded-full p-1 hover:bg-red-700 z-10"
          :title="type === 'user' ? 'Remover assinatura' : 'Remover logo'"
          @click.stop="remove"
        >
          <XMarkIcon class="h-4 w-4" />
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import api from '@/services/api';
import { useToast } from 'vue-toastification';
import { PhotoIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
  modelValue: { type: String, default: '' },
  type: { type: String, required: true, validator: (v) => ['department', 'municipality', 'user'].includes(v) },
  entityId: { type: [String, Number], default: '' },
  label: { type: String, default: 'Brasão / Logo' },
  sizeClass: { type: String, default: 'h-32 w-32' },
});

const emit = defineEmits(['update:modelValue']);

const toast = useToast();
const fileInput = ref(null);
const imageError = ref(false);

const previewUrl = computed(() => {
  if (imageError.value) return null;
  const v = props.modelValue;
  if (!v) return null;
  if (v.startsWith('http://') || v.startsWith('https://')) return v;
  if (v.startsWith('data:')) return v;
  return `/storage/${v}`;
});

watch(() => props.modelValue, () => { imageError.value = false; }, { immediate: true });

function triggerFileInput() {
  if (!props.entityId) return;
  fileInput.value?.click();
}

function onImageError() {
  imageError.value = true;
}

async function handleFileSelect(event) {
  const file = event.target.files?.[0];
  if (!file || !props.entityId) return;
  if (!file.type.startsWith('image/')) {
    toast.error('Selecione um arquivo de imagem.');
    event.target.value = '';
    return;
  }
  if (file.size > 2 * 1024 * 1024) {
    toast.error('A imagem deve ter no máximo 2MB.');
    event.target.value = '';
    return;
  }
  try {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('type', props.type);
    formData.append('id', String(props.entityId));
    const url = props.type === 'user' ? '/upload/signature' : '/upload/logo';
    const { data } = await api.post(url, formData);
    const path = data?.path ?? data?.data?.path ?? data;
    if (path) emit('update:modelValue', path);
    else toast.error('Resposta inválida do servidor.');
  } catch (err) {
    toast.error(err.response?.data?.message ?? (props.type === 'user' ? 'Erro ao enviar assinatura.' : 'Erro ao enviar logo.'));
  }
  event.target.value = '';
}

function remove() {
  imageError.value = false;
  emit('update:modelValue', '');
}
</script>
