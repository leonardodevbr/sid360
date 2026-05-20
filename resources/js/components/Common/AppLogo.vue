<script setup>
import { useAppStore } from '@/stores/app';
import { computed } from 'vue';

defineProps({
  iconClass: {
    type: String,
    default: 'h-8 w-8',
  },
  textClass: {
    type: String,
    default: 'text-base',
  },
  light: {
    type: Boolean,
    default: false,
  },
});

const appStore = useAppStore();
const appName = computed(() => {
  if (appStore.appName) return appStore.appName;
  return document.querySelector('meta[name="apple-mobile-web-app-title"]')?.getAttribute('content') || '';
});
</script>

<template>
  <div class="inline-flex items-center gap-3">
    <img
      src="/logo.png"
      :alt="appName || 'Sistema'"
      :class="iconClass"
      class="shrink-0 object-contain"
    />
    <div v-if="appName" class="flex flex-col leading-tight">
      <span
        class="font-bold tracking-tight"
        :class="[textClass, light ? 'text-white' : 'text-slate-800']"
      >
        {{ appName }}
      </span>
    </div>
  </div>
</template>
