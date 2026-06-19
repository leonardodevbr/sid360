<script setup>
defineProps({
  installment: {
    type: Object,
    required: true,
  },
  downloadingRecibo: {
    type: Boolean,
    default: false,
  },
  sendingRecibo: {
    type: Boolean,
    default: false,
  },
});

defineEmits(['pay', 'open-pix', 'open-boleto', 'download-recibo', 'send-recibo-whatsapp']);
</script>

<template>
  <div class="flex flex-wrap justify-end gap-1.5">
    <template v-if="installment.status === 'cancelled'">
      <span class="text-xs text-slate-400">Parcela cancelada</span>
    </template>
    <template v-else-if="installment.status !== 'paid'">
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
    <template v-else>
      <button
        type="button"
        :disabled="downloadingRecibo"
        class="rounded-lg px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50"
        @click="$emit('download-recibo', installment)"
      >
        {{ downloadingRecibo ? 'Baixando...' : 'Baixar recibo' }}
      </button>
      <button
        type="button"
        :disabled="sendingRecibo"
        class="rounded-lg px-2.5 py-1 text-xs font-medium text-emerald-700 hover:bg-emerald-50 disabled:opacity-50"
        @click="$emit('send-recibo-whatsapp', installment)"
      >
        {{ sendingRecibo ? 'Enviando...' : 'Enviar recibo' }}
      </button>
    </template>
  </div>
</template>
