<template>
  <div class="relative">
    <div
      :class="[
        'relative border-2 border-dashed rounded-lg overflow-hidden cursor-pointer transition-colors',
        imagePreview ? 'border-slate-300' : 'border-slate-300 hover:border-blue-400',
        sizeClass,
      ]"
      @click="triggerFileInput"
    >
      <input
        ref="fileInput"
        type="file"
        accept="image/*"
        class="hidden"
        @change="handleFileSelect"
      />

      <div v-if="!imagePreview" class="flex flex-col items-center justify-center h-full p-4 text-slate-400">
        <CameraIcon class="h-8 w-8 mb-2" />
        <span class="text-xs text-center">Adicionar Foto</span>
      </div>

      <div v-else class="relative h-full w-full">
        <img
          :src="imagePreview"
          alt="Preview"
          class="h-full w-full object-cover"
          @error="handleImageError"
        />
        <button
          type="button"
          @click.stop="removeImage"
          class="absolute top-1 right-1 bg-red-600 text-white rounded-full p-1 hover:bg-red-700 transition-colors z-10"
          title="Remover imagem"
        >
          <XMarkIcon class="h-4 w-4" />
        </button>
        <div
          v-if="imageError"
          class="absolute inset-0 flex flex-col items-center justify-center bg-slate-100 text-slate-400"
        >
          <PhotoIcon class="h-8 w-8 mb-1" />
          <span class="text-xs text-center px-2">Imagem inválida</span>
        </div>
      </div>
    </div>

    <!-- Botão de câmera separado para captura via webcam -->
    <button
      type="button"
      class="mt-2 inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-2 py-1 text-xs text-slate-700 hover:bg-slate-50"
      @click.stop="openCamera"
    >
      <CameraIcon class="h-4 w-4" />
      <span>Usar câmera</span>
    </button>

    <!-- Modal simples para captura da câmera -->
    <div
      v-if="showCameraModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
    >
      <div class="bg-white rounded-xl shadow-lg w-full max-w-md mx-4 p-4">
        <div class="flex items-center justify-between mb-3">
          <h3 class="text-sm font-semibold text-slate-800">Capturar foto</h3>
          <button
            type="button"
            class="text-slate-400 hover:text-slate-600"
            @click="closeCamera"
          >
            <XMarkIcon class="h-5 w-5" />
          </button>
        </div>

        <div class="aspect-video bg-black rounded-lg overflow-hidden mb-3 flex items-center justify-center">
          <video
            ref="videoRef"
            autoplay
            playsinline
            class="w-full h-full object-contain bg-black"
          ></video>
        </div>

        <!-- Canvas oculto apenas para captura do frame -->
        <canvas ref="canvasRef" class="hidden"></canvas>

        <div class="flex items-center justify-end gap-2">
          <button
            type="button"
            class="px-3 py-1.5 text-xs rounded-md border border-slate-300 text-slate-700 hover:bg-slate-50"
            @click="closeCamera"
          >
            Cancelar
          </button>
          <button
            type="button"
            class="px-3 py-1.5 text-xs rounded-md bg-blue-600 text-white hover:bg-blue-700"
            @click="capturePhoto"
          >
            Capturar foto
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, watch } from 'vue';
import { useToast } from 'vue-toastification';
import { CameraIcon, XMarkIcon, PhotoIcon } from '@heroicons/vue/24/outline';

export default {
  name: 'ImageUpload',
  components: {
    CameraIcon,
    XMarkIcon,
    PhotoIcon,
  },
  props: {
    modelValue: {
      type: [File, String, null],
      default: null,
    },
    size: {
      type: String,
      default: 'md',
      validator: (value) => ['sm', 'md', 'lg'].includes(value),
    },
  },
  emits: ['update:modelValue'],
  setup(props, { emit }) {
    const toast = useToast();
    const fileInput = ref(null);
    const imagePreview = ref(null);
    const imageError = ref(false);
    const showCameraModal = ref(false);
    const videoRef = ref(null);
    const canvasRef = ref(null);
    const mediaStream = ref(null);

    const sizeClass = computed(() => {
      const sizes = {
        sm: 'h-24 w-24',
        md: 'h-32 w-32',
        lg: 'h-40 w-40',
      };
      return sizes[props.size] || sizes.md;
    });

    const isValidImageUrl = (url) => {
      if (!url || typeof url !== 'string') return false;
      
      if (url.includes('via.placeholder.com')) {
        return false;
      }
      
      if (url.startsWith('http://') || url.startsWith('https://')) {
        try {
          new URL(url);
          return true;
        } catch {
          return false;
        }
      }
      
      return true;
    };

    const updatePreview = (value) => {
      imageError.value = false;
      
      if (value instanceof File) {
        const reader = new FileReader();
        reader.onload = (e) => {
          imagePreview.value = e.target.result;
        };
        reader.readAsDataURL(value);
      } else if (typeof value === 'string' && value) {
        if (!isValidImageUrl(value)) {
          imagePreview.value = null;
          return;
        }
        
        if (value.startsWith('http://') || value.startsWith('https://')) {
          imagePreview.value = value;
        } else if (value.startsWith('products/') || value.startsWith('product-variants/')) {
          imagePreview.value = `/storage/${value}`;
        } else {
          imagePreview.value = value;
        }
      } else {
        imagePreview.value = null;
      }
    };

    const handleImageError = () => {
      imageError.value = true;
    };

    watch(
      () => props.modelValue,
      (newValue) => {
        updatePreview(newValue);
      },
      { immediate: true }
    );

    const triggerFileInput = () => {
      fileInput.value?.click();
    };

    const handleFileSelect = (event) => {
      const file = event.target.files?.[0];
      
      if (!file) {
        return;
      }

      // Validação de tipo
      if (!file.type.startsWith('image/')) {
        toast.error('Por favor, selecione um arquivo de imagem válido.');
        if (event.target) {
          event.target.value = '';
        }
        return;
      }

      // Validação de tamanho (2MB máximo)
      const maxSize = 2 * 1024 * 1024; // 2MB em bytes
      if (file.size > maxSize) {
        toast.error('A imagem deve ter no máximo 2MB.');
        if (event.target) {
          event.target.value = '';
        }
        return;
      }

      emit('update:modelValue', file);
    };

    const removeImage = () => {
      imageError.value = false;
      emit('update:modelValue', null);
      if (fileInput.value) {
        fileInput.value.value = '';
      }
    };

    const stopStream = () => {
      if (mediaStream.value) {
        mediaStream.value.getTracks().forEach((track) => track.stop());
        mediaStream.value = null;
      }
    };

    const closeCamera = () => {
      stopStream();
      showCameraModal.value = false;
    };

    const openCamera = async () => {
      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        // Aqui idealmente usamos um toast global; por enquanto, fallback simples
        // eslint-disable-next-line no-alert
        alert('Captura de câmera não é suportada neste dispositivo/navegador.');
        return;
      }

      try {
        const stream = await navigator.mediaDevices.getUserMedia({ video: true });
        mediaStream.value = stream;
        showCameraModal.value = true;

        // Aguarda o modal montar o vídeo
        requestAnimationFrame(() => {
          if (videoRef.value) {
            videoRef.value.srcObject = stream;
          }
        });
      } catch (error) {
        // eslint-disable-next-line no-console
        console.error('Erro ao acessar a câmera', error);
        // eslint-disable-next-line no-alert
        alert('Não foi possível acessar a câmera. Verifique as permissões do navegador.');
      }
    };

    const capturePhoto = () => {
      const video = videoRef.value;
      const canvas = canvasRef.value;
      if (!video || !canvas) return;

      const width = video.videoWidth || 640;
      const height = video.videoHeight || 480;

      canvas.width = width;
      canvas.height = height;

      const ctx = canvas.getContext('2d');
      if (!ctx) return;

      ctx.drawImage(video, 0, 0, width, height);

      canvas.toBlob(
        (blob) => {
          if (!blob) return;
          
          // Validação de tamanho para foto da câmera
          const maxSize = 2 * 1024 * 1024; // 2MB
          if (blob.size > maxSize) {
            toast.error('A imagem capturada é muito grande. Tente novamente com menor qualidade.');
            stopStream();
            showCameraModal.value = false;
            return;
          }
          
          const file = new File([blob], `camera-${Date.now()}.jpg`, { type: 'image/jpeg' });
          emit('update:modelValue', file);
          stopStream();
          showCameraModal.value = false;
        },
        'image/jpeg',
        0.9,
      );
    };

    return {
      fileInput,
      imagePreview,
      imageError,
      sizeClass,
      triggerFileInput,
      handleFileSelect,
      removeImage,
      handleImageError,
      showCameraModal,
      videoRef,
      canvasRef,
      openCamera,
      closeCamera,
      capturePhoto,
    };
  },
};
</script>
