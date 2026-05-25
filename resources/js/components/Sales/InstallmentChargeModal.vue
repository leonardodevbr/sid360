<script setup>
import { computed, ref, watch } from 'vue';
import { useToast } from 'vue-toastification';
import Swal from 'sweetalert2';
import api from '@/services/api';
import Modal from '@/components/Common/Modal.vue';
import Flatpickr from '@/components/Common/Flatpickr.vue';
import Button from '@/components/Common/Button.vue';
import { formatCurrency, formatDate } from '@/utils/format';
import { buildPixPaymentMessage, buildBoletoPaymentMessage, buildWhatsAppUrl } from '@/utils/whatsapp';
import { prepareNewTab } from '@/utils/browser';

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false,
  },
  installment: {
    type: Object,
    default: null,
  },
  chargeType: {
    type: String,
    default: 'pix',
    validator: (value) => ['pix', 'boleto'].includes(value),
  },
  clientPhone: {
    type: String,
    default: '',
  },
  clientName: {
    type: String,
    default: '',
  },
  contractNo: {
    type: String,
    default: '',
  },
  carnetPdfUrl: {
    type: String,
    default: '',
  },
});

const emit = defineEmits(['close', 'updated']);

const toast = useToast();
const localInstallment = ref(null);
const waivePenalties = ref(false);
const dueDate = ref('');
const chargeBreakdown = ref(null);
const loadingPreview = ref(false);
const issuing = ref(false);
const sendingWhatsapp = ref(false);

const isPix = computed(() => props.chargeType === 'pix');
const isBoleto = computed(() => props.chargeType === 'boleto');
const isPaid = computed(() => localInstallment.value?.status === 'paid');
const isCarne = computed(() => localInstallment.value?.efi_payment_type === 'carne');

const hasIssuedPix = computed(() => Boolean(
  localInstallment.value?.efi_pix_copia_cola
  && localInstallment.value?.efi_payment_type === 'pix',
));

const hasIssuedBoleto = computed(() => Boolean(
  localInstallment.value?.efi_pdf_url
  && localInstallment.value?.efi_payment_type === 'boleto',
));

const hasIssued = computed(() => (isPix.value ? hasIssuedPix.value : hasIssuedBoleto.value));

const modalTitle = computed(() => {
  if (!localInstallment.value) {
    return isPix.value ? 'PIX' : 'Boleto';
  }

  const parcelLabel = localInstallment.value.type === 'down_payment'
    ? 'Entrada'
    : `Parcela ${localInstallment.value.number}`;

  return `${isPix.value ? 'PIX' : 'Boleto'} — ${parcelLabel}`;
});

const pixExpiryHours = computed(() => {
  const seconds = Number(chargeBreakdown.value?.expiry_seconds ?? import.meta.env.VITE_EFI_PIX_EXPIRY ?? 3600);
  return Math.max(1, Math.round(seconds / 3600));
});

function defaultDueDate(installment) {
  if (!installment?.due_date) {
    return '';
  }

  const due = new Date(`${installment.due_date}T00:00:00`);
  const today = new Date();
  today.setHours(0, 0, 0, 0);

  if (due >= today) {
    return installment.due_date;
  }

  const tomorrow = new Date(today);
  tomorrow.setDate(tomorrow.getDate() + 1);

  const year = tomorrow.getFullYear();
  const month = String(tomorrow.getMonth() + 1).padStart(2, '0');
  const day = String(tomorrow.getDate()).padStart(2, '0');

  return `${year}-${month}-${day}`;
}

async function loadChargePreview() {
  if (!localInstallment.value || isPaid.value || isCarne.value) {
    chargeBreakdown.value = null;
    return;
  }

  loadingPreview.value = true;

  try {
    const params = {
      waive_penalties: waivePenalties.value ? 1 : 0,
    };

    if (isBoleto.value && dueDate.value) {
      params.reference_date = dueDate.value;
    }

    const { data } = await api.get(`/installments/${localInstallment.value.id}/efi/charge-preview`, { params });
    chargeBreakdown.value = data;
  } catch {
    chargeBreakdown.value = null;
  } finally {
    loadingPreview.value = false;
  }
}

function resetState() {
  localInstallment.value = props.installment ? { ...props.installment } : null;
  waivePenalties.value = false;
  dueDate.value = defaultDueDate(props.installment);
  chargeBreakdown.value = null;
}

watch(
  () => [props.isOpen, props.installment, props.chargeType],
  ([open]) => {
    if (open) {
      resetState();
      loadChargePreview();
    }
  },
  { immediate: true },
);

watch(
  () => props.installment,
  (installment) => {
    if (props.isOpen && installment) {
      localInstallment.value = { ...installment };
    }
  },
);

watch([waivePenalties, dueDate], () => {
  if (props.isOpen) {
    loadChargePreview();
  }
});

function closeModal() {
  emit('close');
}

function applyInstallmentUpdate(data) {
  if (!localInstallment.value) {
    return;
  }

  if (isPix.value) {
    localInstallment.value = {
      ...localInstallment.value,
      efi_pix_qrcode: data.qrcode,
      efi_pix_copia_cola: data.pix_copia_cola,
      efi_payment_type: 'pix',
    };
  } else {
    localInstallment.value = {
      ...localInstallment.value,
      efi_pdf_url: data.pdf,
      efi_barcode: data.barcode,
      efi_payment_type: 'boleto',
    };
  }

  emit('updated');
}

async function issueCharge({ reissue = false } = {}) {
  if (!localInstallment.value || issuing.value) {
    return;
  }

  if (reissue) {
    const label = isPix.value ? 'PIX' : 'boleto';
    const result = await Swal.fire({
      title: `Reemitir ${label}?`,
      text: isPix.value
        ? 'Um novo código PIX será gerado. O código anterior deixa de valer.'
        : 'Um novo boleto será gerado com os dados informados.',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Reemitir',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#1a3a28',
      cancelButtonColor: '#6b7280',
      reverseButtons: true,
    });

    if (!result.isConfirmed) {
      return;
    }
  }

  issuing.value = true;
  const previewTab = isBoleto.value ? prepareNewTab() : null;

  try {
    const payload = {
      waive_penalties: waivePenalties.value,
    };

    if (isBoleto.value && dueDate.value) {
      payload.due_date = dueDate.value;
    }

    const endpoint = isPix.value
      ? `/installments/${localInstallment.value.id}/efi/pix`
      : `/installments/${localInstallment.value.id}/efi/boleto`;

    const { data } = await api.post(endpoint, payload);

    applyInstallmentUpdate(data);
    chargeBreakdown.value = data.charge_breakdown ?? chargeBreakdown.value;

    if (isPix.value && data.expiry_seconds) {
      chargeBreakdown.value = {
        ...(chargeBreakdown.value ?? {}),
        expiry_seconds: data.expiry_seconds,
      };
    }

    if (isPix.value) {
      toast.success(reissue ? 'PIX reemitido!' : 'PIX gerado!');
    } else {
      if (previewTab && !previewTab.open(data.pdf)) {
        toast.warning('Boleto gerado. Use "Baixar PDF" se a aba não abriu.');
      } else {
        toast.success(reissue ? 'Boleto reemitido!' : 'Boleto gerado!');
      }
    }
  } catch (err) {
    previewTab?.close();
    toast.error(err?.response?.data?.error ?? `Erro ao ${reissue ? 'reemitir' : 'gerar'} cobrança.`);
  } finally {
    issuing.value = false;
  }
}

async function copyPixCode() {
  const code = localInstallment.value?.efi_pix_copia_cola;

  if (!code) {
    return;
  }

  await navigator.clipboard.writeText(code);
  toast.success('Código PIX copiado!');
}

function downloadBoletoPdf() {
  const url = localInstallment.value?.efi_pdf_url;

  if (!url) {
    toast.warning('PDF indisponível.');
    return;
  }

  window.open(url, '_blank', 'noopener,noreferrer');
}

async function sendWhatsApp() {
  if (!props.clientPhone) {
    await Swal.fire({
      title: 'Telefone não cadastrado',
      text: 'Este cliente não possui WhatsApp/telefone no cadastro.',
      icon: 'warning',
      confirmButtonText: 'OK',
      confirmButtonColor: '#1a3a28',
    });
    return;
  }

  sendingWhatsapp.value = true;

  try {
    const endpoint = isPix.value
      ? `/installments/${localInstallment.value.id}/efi/pix/whatsapp`
      : `/installments/${localInstallment.value.id}/efi/boleto/whatsapp`;

    const { data } = await api.post(endpoint);

    if (data?.pdf_sent === false) {
      toast.warning(data?.warning ?? 'Mensagem enviada, mas o PDF não pôde ser anexado.');
    } else {
      toast.success('Enviado no WhatsApp!');
    }

    emit('updated');
  } catch (err) {
    const shouldFallback = err?.response?.data?.fallback === true;

    if (shouldFallback) {
      const fallback = await Swal.fire({
        title: 'Envio automático indisponível',
        text: 'Deseja abrir a conversa manualmente no WhatsApp?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Abrir WhatsApp',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#1a3a28',
        cancelButtonColor: '#6b7280',
        reverseButtons: true,
      });

      if (fallback.isConfirmed) {
        openWhatsAppFallback();
      }
    } else {
      toast.error(err?.response?.data?.error ?? 'Erro ao enviar no WhatsApp.');
    }
  } finally {
    sendingWhatsapp.value = false;
  }
}

function openWhatsAppFallback() {
  const installment = localInstallment.value;

  if (!installment) {
    return;
  }

  const message = isPix.value
    ? buildPixPaymentMessage({
      clientName: props.clientName || 'cliente',
      contractNo: props.contractNo,
      installment,
      pixCopyPaste: installment.efi_pix_copia_cola,
      formatDate,
      formatCurrency,
    })
    : buildBoletoPaymentMessage({
      clientName: props.clientName || 'cliente',
      contractNo: props.contractNo,
      installment,
      formatDate,
      formatCurrency,
      barcode: installment.efi_barcode,
      pdfUrl: installment.efi_pdf_url,
    });

  const url = buildWhatsAppUrl(props.clientPhone, message);

  if (!url) {
    toast.error('Não foi possível montar o link do WhatsApp.');
    return;
  }

  window.open(url, '_blank', 'noopener,noreferrer');
}
</script>

<template>
  <Modal
    :is-open="isOpen"
    :title="modalTitle"
    @close="closeModal"
  >
    <div v-if="localInstallment" class="space-y-4">
      <div
        v-if="isCarne && isBoleto"
        class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800"
      >
        Esta parcela faz parte do carnê bancário da venda.
        <a
          v-if="carnetPdfUrl"
          :href="carnetPdfUrl"
          target="_blank"
          rel="noopener noreferrer"
          class="font-semibold underline"
        >
          Abrir carnê bancário
        </a>
      </div>

      <div class="grid grid-cols-2 gap-3 text-xs">
        <div class="rounded-lg bg-slate-50 px-3 py-2">
          <p class="text-slate-500">Vencimento original</p>
          <p class="font-medium text-slate-800">{{ formatDate(localInstallment.due_date) }}</p>
        </div>
        <div class="rounded-lg bg-slate-50 px-3 py-2">
          <p class="text-slate-500">Valor da parcela</p>
          <p class="font-medium text-slate-800">{{ formatCurrency(localInstallment.value) }}</p>
        </div>
      </div>

      <template v-if="!isCarne || isPix">
        <div
          v-if="chargeBreakdown?.is_overdue && !waivePenalties"
          class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900"
        >
          Parcela em atraso há {{ chargeBreakdown.days_overdue }} dia(s).
          Multa: {{ formatCurrency(chargeBreakdown.fine_cents) }}
          · Juros: {{ formatCurrency(chargeBreakdown.interest_cents) }}
          · Total: {{ formatCurrency(chargeBreakdown.total_value) }}
        </div>

        <label class="flex cursor-pointer items-start gap-2 text-xs text-slate-600">
          <input
            v-model="waivePenalties"
            type="checkbox"
            class="mt-0.5 rounded border-slate-300 text-emerald-700 focus:ring-emerald-600"
          >
          <span>Dispensar multa e juros (cobrar apenas o valor original da parcela)</span>
        </label>

        <Flatpickr
          v-if="isBoleto"
          v-model="dueDate"
          label="Vencimento do boleto"
          :min-date="new Date().toISOString().slice(0, 10)"
        />

        <div
          v-if="chargeBreakdown && !loadingPreview"
          class="rounded-lg border border-slate-100 bg-white px-3 py-2 text-xs text-slate-600"
        >
          Valor a cobrar:
          <strong class="text-slate-900">{{ formatCurrency(chargeBreakdown.total_value) }}</strong>
        </div>

        <template v-if="hasIssued">
          <div v-if="isPix && localInstallment.efi_pix_qrcode" class="text-center">
            <img
              :src="localInstallment.efi_pix_qrcode"
              alt="QR Code PIX"
              class="mx-auto h-40 w-40"
            >
          </div>

          <div
            v-if="isPix && localInstallment.efi_pix_copia_cola"
            class="rounded-lg bg-slate-50 p-3"
          >
            <p class="mb-1 text-xs text-slate-500">PIX Copia e Cola</p>
            <p class="break-all font-mono text-xs text-slate-700">{{ localInstallment.efi_pix_copia_cola }}</p>
          </div>

          <div
            v-if="isBoleto && localInstallment.efi_barcode"
            class="rounded-lg bg-slate-50 p-3"
          >
            <p class="mb-1 text-xs text-slate-500">Linha digitável</p>
            <p class="break-all font-mono text-xs text-slate-700">{{ localInstallment.efi_barcode }}</p>
          </div>

          <p v-if="isPix" class="text-xs text-amber-700">
            O código PIX expira em aproximadamente {{ pixExpiryHours }} hora(s). Reemita se necessário.
          </p>

          <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
            <Button
              v-if="isBoleto"
              type="button"
              variant="outline"
              class="w-full sm:w-auto"
              @click="downloadBoletoPdf"
            >
              Baixar PDF
            </Button>
            <Button
              v-if="isPix"
              type="button"
              variant="outline"
              class="w-full sm:w-auto"
              @click="copyPixCode"
            >
              Copiar PIX
            </Button>
            <Button
              type="button"
              variant="outline"
              class="w-full sm:w-auto"
              :loading="issuing"
              @click="issueCharge({ reissue: true })"
            >
              Reemitir
            </Button>
            <Button
              type="button"
              variant="primary"
              class="w-full sm:w-auto"
              :loading="sendingWhatsapp"
              @click="sendWhatsApp"
            >
              Enviar WhatsApp
            </Button>
          </div>
        </template>

        <template v-else>
          <Button
            type="button"
            variant="primary"
            class="w-full"
            :loading="issuing"
            @click="issueCharge()"
          >
            {{ isPix ? 'Gerar PIX' : 'Gerar boleto' }}
          </Button>
        </template>
      </template>
    </div>
  </Modal>
</template>
