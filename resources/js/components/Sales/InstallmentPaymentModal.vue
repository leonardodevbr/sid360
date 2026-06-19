<script setup>
import { computed, ref, watch } from 'vue';
import { useToast } from 'vue-toastification';
import api from '@/services/api';
import Modal from '@/components/Common/Modal.vue';
import Button from '@/components/Common/Button.vue';
import SelectInput from '@/components/Common/SelectInput.vue';
import { formatCurrency, formatDate } from '@/utils/format';
import { getApiErrorMessage } from '@/utils/apiError';
import { PAYMENT_METHODS, paymentMethodRequiresDescription } from '@/utils/paymentMethods';

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

const emit = defineEmits(['close', 'paid']);

const toast = useToast();
const paymentMethod = ref('');
const paymentMethodDescription = ref('');
const paying = ref(false);

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

const requiresDescription = computed(() => paymentMethodRequiresDescription(paymentMethod.value));

const canConfirm = computed(() => {
  if (!paymentMethod.value) {
    return false;
  }

  return requiresDescription.value ? paymentMethodDescription.value.trim() !== '' : true;
});

function resetState() {
  paymentMethod.value = '';
  paymentMethodDescription.value = '';
}

watch(
  () => [props.isOpen, props.installment],
  ([open]) => {
    if (open) {
      resetState();
    }
  },
);

function closeModal() {
  if (paying.value) {
    return;
  }
  emit('close');
}

async function confirmPay() {
  if (!props.installment || !canConfirm.value || paying.value) {
    return;
  }

  paying.value = true;
  try {
    const { data } = await api.post(`/installments/${props.installment.id}/pay`, {
      payment_method: paymentMethod.value,
      payment_method_description: requiresDescription.value ? paymentMethodDescription.value.trim() : null,
    });

    toast.success(`${parcelLabel.value} marcada como paga.`);
    emit('paid', data.data ?? data);
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Erro ao marcar parcela como paga.'));
  } finally {
    paying.value = false;
  }
}
</script>

<template>
  <Modal
    :is-open="isOpen"
    title="Confirmar pagamento"
    :closable="!paying"
    @close="closeModal"
  >
    <div v-if="installment" class="space-y-4">
      <div class="grid grid-cols-2 gap-3 text-xs">
        <div class="rounded-lg bg-slate-50 px-3 py-2">
          <p class="text-slate-500">Parcela</p>
          <p class="font-medium text-slate-800">{{ parcelLabel }}</p>
        </div>
        <div class="rounded-lg bg-slate-50 px-3 py-2">
          <p class="text-slate-500">Vencimento</p>
          <p class="font-medium text-slate-800">{{ formatDate(installment.due_date) }}</p>
        </div>
        <div class="col-span-2 rounded-lg bg-slate-50 px-3 py-2">
          <p class="text-slate-500">Valor</p>
          <p class="font-medium text-slate-800">{{ formatCurrency(installment.value) }}</p>
        </div>
      </div>

      <SelectInput
        v-model="paymentMethod"
        :options="PAYMENT_METHODS"
        label="Meio de pagamento"
        placeholder="Selecione o meio de pagamento"
        :searchable="false"
        :can-clear="false"
      />

      <div v-if="requiresDescription">
        <label class="mb-1 block text-xs font-medium text-slate-600">
          Descrição do pagamento
        </label>
        <textarea
          v-model="paymentMethodDescription"
          rows="2"
          placeholder="Ex.: Veículo Fiat Uno 2018, placa ABC1234"
          class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:border-action focus:outline-none focus:ring-1 focus:ring-action"
        />
      </div>

      <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <Button type="button" variant="outline" :disabled="paying" @click="closeModal">
          Cancelar
        </Button>
        <Button
          type="button"
          variant="primary"
          :loading="paying"
          :disabled="!canConfirm"
          @click="confirmPay"
        >
          Confirmar pagamento
        </Button>
      </div>
    </div>
  </Modal>
</template>
