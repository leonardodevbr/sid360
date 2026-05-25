<script setup>
defineProps({
  installment: {
    type: Object,
    required: true,
  },
});

defineEmits(['pay', 'open-pix', 'open-boleto']);
</script>

<template>
  <div class="flex flex-wrap justify-end gap-1.5">
    <template v-if="installment.status !== 'paid'">
      <button
        type="button"
        class="rounded-lg px-2.5 py-1 text-xs font-medium text-emerald-700 hover:bg-emerald-50"
        @click="$emit('pay', installment)"
      >
        Marcar paga
      </button>
      <button
        type="button"
        class="rounded-lg px-2.5 py-1 text-xs font-medium text-blue-600 hover:bg-blue-50"
        @click="$emit('open-pix', installment)"
      >
        PIX
      </button>
      <button
        type="button"
        class="rounded-lg px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50"
        @click="$emit('open-boleto', installment)"
      >
        Boleto
      </button>
    </template>
    <span v-else class="text-xs text-slate-400">Paga</span>
  </div>
</template>
