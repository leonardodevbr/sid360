<script setup>
import { useAppStore } from '@/stores/app';
import { computed } from 'vue';

defineProps({
  heightClass: {
    type: String,
    default: 'h-10',
  },
  textClass: {
    type: String,
    default: 'text-base',
  },
  showText: {
    type: Boolean,
    default: false,
  },
  light: {
    type: Boolean,
    default: false,
  },
});

const appStore = useAppStore();
const appName = computed(() => {
  if (appStore.appName) return appStore.appName;
  return document.querySelector('meta[name="apple-mobile-web-app-title"]')?.getAttribute('content') || 'Sid360';
});
</script>

<template>
  <div class="inline-flex items-center gap-3">
    <img
      src="/img/logo-systema.png"
      :alt="appName"
      :class="[heightClass, 'w-auto max-w-[240px] shrink-0 object-contain']"
    />
    <div v-if="showText && appName" class="flex flex-col leading-tight">
      <span
        class="font-bold tracking-tight"
        :class="[textClass, light ? 'text-white' : 'text-sid-dark']"
      >
        {{ appName }}
      </span>
    </div>
  </div>
</template>
