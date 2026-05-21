<script setup>
import { computed } from 'vue';

const props = defineProps({
  label: {
    type: String,
    default: '',
  },
  value: {
    type: [Number, String],
    default: 0,
  },
  color: {
    type: String,
    default: 'slate',
  },
});

const textColor = computed(() => ({
  emerald: 'text-emerald-700',
  red: 'text-red-600',
  blue: 'text-blue-700',
  amber: 'text-amber-600',
  slate: 'text-slate-700',
}[props.color] ?? 'text-slate-700'));
</script>

<template>
  <div class="card p-4">
    <p class="mb-2 text-xs text-slate-500">{{ label }}</p>
    <p class="text-3xl font-bold" :class="textColor">{{ value }}</p>
  </div>
</template>
