<script setup>
const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false,
  },
  title: {
    type: String,
    default: 'Título',
  },
  closable: {
    type: Boolean,
    default: true,
  },
});

const emit = defineEmits(['close']);

function handleClose() {
  if (props.closable) {
    emit('close');
  }
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
        <div
          class="card w-full max-w-lg p-6"
          @click.stop
        >
          <div class="mb-4 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-slate-800">
              {{ title }}
            </h3>
            <button
              v-if="closable"
              type="button"
              class="text-slate-400 hover:text-slate-600"
              @click="handleClose"
            >
              <span class="sr-only">Fechar</span>
              <svg
                class="h-6 w-6"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M6 18L18 6M6 6l12 12"
                />
              </svg>
            </button>
          </div>

          <div class="text-sm text-slate-700">
            <slot />
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

