<script setup>
defineProps({
  lot: {
    type: Object,
    required: true,
  },
});

defineEmits(['click']);

function formatCurrency(value) {
  if (!value) {
    return '–';
  }

  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value / 100);
}
</script>

<template>
  <div
    class="cursor-pointer overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition-shadow hover:shadow-md"
    @click="$emit('click', lot)"
  >
    <div class="relative aspect-video overflow-hidden bg-slate-100">
      <img
        v-if="lot.cover_photo"
        :src="lot.cover_photo"
        :alt="`Lote ${lot.number}`"
        class="h-full w-full object-cover"
        loading="lazy"
      >
      <div
        v-else
        class="flex h-full items-center justify-center bg-slate-200 text-slate-400"
      >
        <svg class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
        </svg>
      </div>

      <span class="absolute right-2 top-2 rounded-full bg-emerald-500 px-2.5 py-0.5 text-xs font-bold text-white">
        Disponível
      </span>
    </div>

    <div class="space-y-2 p-4">
      <div class="flex items-start justify-between gap-2">
        <div>
          <p class="font-semibold text-slate-800">
            {{ lot.development?.name }}
          </p>
          <p class="text-xs text-slate-500">
            {{ lot.full_address }}
          </p>
        </div>
        <p v-if="lot.area" class="shrink-0 text-xs text-slate-400">
          {{ lot.area }}m²
        </p>
      </div>

      <div class="flex items-center justify-between border-t border-slate-100 pt-2">
        <div>
          <p class="text-xs text-slate-400">
            A partir de
          </p>
          <p class="text-lg font-bold text-emerald-700">
            {{ formatCurrency(lot.total_value) }}
          </p>
        </div>
        <span class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white">
          Ver detalhes
        </span>
      </div>
    </div>
  </div>
</template>
