<script setup>
import { computed, ref, watch } from 'vue';
import { useToast } from 'vue-toastification';
import api from '@/services/api';
import Modal from '@/components/Common/Modal.vue';
import Button from '@/components/Common/Button.vue';
import Flatpickr from '@/components/Common/Flatpickr.vue';
import { formatCurrency, formatDate } from '@/utils/format';
import { getApiErrorMessage } from '@/utils/apiError';

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false,
  },
  installment: {
    type: Object,
    default: null,
  },
  installmentsCount: {
    type: [Number, String],
    default: null,
  },
});

const emit = defineEmits(['close', 'updated']);

const toast = useToast();
const dueDate = ref('');
const saving = ref(false);

const parcelLabel = computed(() => {
  if (!props.installment) {
    return '';
  }

  if (props.installment.type === 'down_payment') {
    return 'Entrada';
  }

  return props.installmentsCount
    ? `Parcela ${props.installment.number}/${props.installmentsCount}`
    : `Parcela ${props.installment.number}`;
});

watch(
  () => [props.isOpen, props.installment],
  ([open]) => {
    if (open) {
      dueDate.value = props.installment?.due_date ?? '';
    }
  },
);

function closeModal() {
  if (saving.value) {
    return;
  }
  emit('close');
}

async function confirmUpdate() {
  if (!props.installment || !dueDate.value || saving.value) {
    return;
  }

  saving.value = true;
  try {
    const { data } = await api.post(`/installments/${props.installment.id}/due-date`, {
      due_date: dueDate.value,
    });

    toast.success(`Vencimento da ${parcelLabel.value} atualizado.`);
    emit('updated', data.data ?? data);
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Erro ao alterar a data de vencimento.'));
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <Modal
    :is-open="isOpen"
    title="Alterar vencimento"
    :closable="!saving"
    @close="closeModal"
  >
    <div v-if="installment" class="space-y-4">
      <div class="grid grid-cols-2 gap-3 text-xs">
        <div class="rounded-lg bg-slate-50 px-3 py-2">
          <p class="text-slate-500">Parcela</p>
          <p class="font-medium text-slate-800">{{ parcelLabel }}</p>
        </div>
        <div class="rounded-lg bg-slate-50 px-3 py-2">
          <p class="text-slate-500">Valor</p>
          <p class="font-medium text-slate-800">{{ formatCurrency(installment.value) }}</p>
        </div>
      </div>

      <p class="text-xs text-slate-500">
        Vencimento atual: <span class="font-medium text-slate-700">{{ formatDate(installment.due_date) }}</span>
      </p>

      <Flatpickr
        v-model="dueDate"
        label="Novo vencimento"
        placeholder="DD/MM/AAAA"
      />

      <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <Button type="button" variant="outline" :disabled="saving" @click="closeModal">
          Cancelar
        </Button>
        <Button
          type="button"
          variant="primary"
          :loading="saving"
          :disabled="!dueDate"
          @click="confirmUpdate"
        >
          Salvar
        </Button>
      </div>
    </div>
  </Modal>
</template>
