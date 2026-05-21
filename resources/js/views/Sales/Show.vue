<template>
  <div class="space-y-6">
    <div class="flex items-center gap-4">
      <button type="button" class="rounded-lg p-2 hover:bg-slate-100" @click="$router.push({ name: 'sales.index' })">
        <ArrowLeftIcon class="h-5 w-5 text-slate-600" />
      </button>
      <div class="min-w-0 flex-1">
        <h2 class="text-lg font-semibold text-slate-800">Venda #{{ sale?.id }}</h2>
        <p class="text-xs text-slate-500">{{ sale?.client?.name }}</p>
      </div>
    </div>

    <div v-if="loading" class="card p-12 text-center text-slate-400">Carregando...</div>

    <template v-else-if="sale">
      <div
        v-if="showRegistrationSuccess"
        class="card border-[#e8dcc8] bg-[#faf5ee] p-5"
      >
        <p class="text-sm font-semibold text-[#1c0a06]">Venda registrada com sucesso</p>
        <p class="mt-1 text-xs text-[#7a4535]">
          Imprima o contrato para assinatura e, após assinado, envie o arquivo digitalizado abaixo.
        </p>
      </div>

      <div class="card p-5">
        <h3 class="mb-1 text-sm font-semibold text-slate-800">Contrato</h3>
        <p class="mb-4 text-xs text-slate-500">
          Gere o PDF para impressão e assinatura do comprador.
        </p>

        <div class="flex flex-wrap gap-2">
          <Button
            type="button"
            variant="primary"
            :loading="printingContract"
            @click="handlePrintContract"
          >
            <PrinterIcon class="mr-2 h-4 w-4" />
            Imprimir contrato
          </Button>
          <Button
            type="button"
            variant="outline"
            :loading="downloadingContract"
            @click="handleDownloadContract"
          >
            <DocumentArrowDownIcon class="mr-2 h-4 w-4" />
            Baixar PDF
          </Button>
          <Button
            v-if="financingInstallments.length"
            type="button"
            variant="outline"
            :loading="downloadingCarne"
            @click="handleDownloadCarne"
          >
            <DocumentTextIcon class="mr-2 h-4 w-4" />
            Imprimir Carnê
          </Button>
          <Button
            v-if="showCarnePreview && financingInstallments.length"
            type="button"
            variant="outline"
            @click="openCarnePreview"
          >
            <EyeIcon class="mr-2 h-4 w-4" />
            Preview HTML
          </Button>
        </div>

        <div class="mt-5 border-t border-slate-100 pt-5">
          <p class="mb-2 text-sm font-medium text-slate-700">Contrato assinado</p>
          <p class="mb-3 text-xs text-slate-500">
            Anexe o contrato assinado (PDF ou foto) para vincular a esta venda.
          </p>

          <div
            v-if="sale.has_signed_contract"
            class="flex flex-col gap-3 rounded-lg border border-[#e8dcc8] bg-[#faf5ee] px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
          >
            <div class="flex min-w-0 items-center gap-2">
              <DocumentCheckIcon class="h-5 w-5 shrink-0 text-[#2d6a45]" />
              <span class="truncate text-sm text-[#1c0a06]">
                {{ sale.signed_contract_original_name || 'Contrato assinado anexado' }}
              </span>
            </div>
            <div class="flex flex-wrap gap-2">
              <Button
                type="button"
                variant="outline"
                :loading="downloadingSigned"
                @click="handleDownloadSignedContract"
              >
                Baixar anexo
              </Button>
              <Button
                type="button"
                variant="outline"
                :loading="uploadingSigned"
                @click="openFilePicker"
              >
                Substituir arquivo
              </Button>
            </div>
          </div>

          <div
            v-else
            class="rounded-lg border border-dashed border-slate-200 bg-slate-50 p-4"
          >
            <p v-if="selectedFileName" class="mb-3 text-sm text-slate-700">
              Arquivo selecionado: <span class="font-medium">{{ selectedFileName }}</span>
            </p>
            <div class="flex flex-wrap gap-2">
              <Button type="button" variant="outline" @click="openFilePicker">
                <ArrowUpTrayIcon class="mr-2 h-4 w-4" />
                Selecionar arquivo
              </Button>
              <Button
                v-if="selectedFile"
                type="button"
                variant="primary"
                :loading="uploadingSigned"
                @click="handleUploadSignedContract"
              >
                Enviar contrato assinado
              </Button>
            </div>
            <p class="mt-2 text-xs text-slate-400">PDF, JPG, PNG ou WebP — máximo 10 MB</p>
          </div>

          <input
            ref="fileInputRef"
            type="file"
            class="sr-only"
            accept=".pdf,image/jpeg,image/png,image/webp"
            @change="onFileSelected"
          />
        </div>

        <div class="mt-5 border-t border-slate-100 pt-5">
          <p class="mb-3 text-sm font-medium text-slate-700">Notificações WhatsApp</p>
          <dl class="grid gap-3 sm:grid-cols-2">
            <div>
              <dt class="text-xs text-slate-500">Boas-vindas</dt>
              <dd class="mt-0.5 text-sm text-slate-800">
                {{ sale.whatsapp_welcome_sent_at ? formatDateTime(sale.whatsapp_welcome_sent_at) : 'Não enviada' }}
              </dd>
            </div>
            <div>
              <dt class="text-xs text-slate-500">Última notificação</dt>
              <dd class="mt-0.5 text-sm text-slate-800">
                {{ sale.whatsapp_last_notification_at ? formatDateTime(sale.whatsapp_last_notification_at) : '—' }}
              </dd>
            </div>
          </dl>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="card p-4">
          <p class="text-xs text-slate-500">Valor Total</p>
          <p class="text-lg font-bold text-slate-800">{{ formatCurrency(sale.total_value) }}</p>
        </div>
        <div class="card p-4">
          <p class="text-xs text-slate-500">Entrada</p>
          <p class="text-lg font-bold text-slate-800">{{ formatCurrency(sale.down_payment) }}</p>
        </div>
        <div class="card p-4">
          <p class="text-xs text-slate-500">Pagamento</p>
          <p class="text-lg font-bold text-slate-800">
            <template v-if="sale.installments_count > 0">
              {{ sale.installments_count }}x {{ formatCurrency(sale.installment_value) }}
            </template>
            <template v-else-if="sale.cash_value">
              À vista · {{ formatCurrency(sale.cash_value) }}
            </template>
            <template v-else>À vista</template>
          </p>
          <p
            v-if="sale.installments_count < 1 && sale.discount_amount > 0"
            class="mt-0.5 text-xs text-slate-500"
          >
            Desconto {{ formatCurrency(sale.discount_amount) }}
            <span v-if="sale.discount_percent"> ({{ formatDiscountPercent(sale.discount_percent) }})</span>
          </p>
        </div>
        <div class="card p-4">
          <p class="text-xs text-slate-500">Status</p>
          <span :class="saleStatusClass(sale.status)" class="rounded-full px-2 py-0.5 text-xs font-semibold">
            {{ saleStatusLabel(sale.status) }}
          </span>
        </div>
      </div>

      <div v-if="downPaymentInstallments.length" class="card overflow-hidden">
        <div class="border-b border-slate-100 px-4 py-3">
          <h3 class="text-sm font-semibold text-slate-700">Entrada negociada</h3>
          <p class="mt-0.5 text-xs text-slate-500">
            Total da entrada: {{ formatCurrency(sale.down_payment) }}
          </p>
        </div>
        <table class="w-full text-sm">
          <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500">
            <tr>
              <th class="px-4 py-3 text-left">Tipo</th>
              <th class="px-4 py-3 text-left">Vencimento</th>
              <th class="px-4 py-3 text-right">Valor</th>
              <th class="px-4 py-3 text-center">Status</th>
              <th class="px-4 py-3 text-center">Pago em</th>
              <th class="px-4 py-3 text-left">WhatsApp</th>
              <th class="px-4 py-3 text-right">Ação</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr
              v-for="inst in downPaymentInstallments"
              :key="inst.id"
              class="hover:bg-slate-50"
            >
              <td class="px-4 py-3 font-medium text-slate-700">
                {{ installmentTypeLabel(inst.type) }}
              </td>
              <td class="px-4 py-3 text-slate-700">{{ formatDate(inst.due_date) }}</td>
              <td class="px-4 py-3 text-right font-medium text-slate-800">{{ formatCurrency(inst.value) }}</td>
              <td class="px-4 py-3 text-center">
                <span :class="installStatusClass(installmentDisplayStatus(inst))" class="rounded-full px-2 py-0.5 text-xs font-semibold">
                  {{ installStatusLabel(installmentDisplayStatus(inst)) }}
                </span>
              </td>
              <td class="px-4 py-3 text-center text-slate-500">{{ inst.paid_at ? formatDate(inst.paid_at) : '—' }}</td>
              <td class="px-4 py-3 text-left text-xs">
                <InstallmentWhatsappCell :installment="inst" :sale="sale" />
              </td>
              <td class="px-4 py-3 text-right">
                <button
                  v-if="inst.status !== 'paid'"
                  type="button"
                  class="rounded px-2 py-1 text-xs font-medium text-action hover:text-action-hover hover:underline"
                  @click="payInstallment(inst)"
                >
                  Marcar pago
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="financingInstallments.length" class="card overflow-hidden">
        <button
          type="button"
          class="flex w-full items-center justify-between border-b border-slate-100 px-4 py-3 text-left hover:bg-slate-50"
          @click="installmentsExpanded = !installmentsExpanded"
        >
          <div class="flex min-w-0 items-center gap-2">
            <ChevronDownIcon
              class="h-4 w-4 shrink-0 text-slate-400 transition-transform duration-200"
              :class="{ '-rotate-90': !installmentsExpanded }"
            />
            <h3 class="text-sm font-semibold text-slate-700">Parcelas</h3>
            <span
              v-if="financingOverdueCount > 0"
              class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700"
            >
              {{ financingOverdueCount }} em atraso
            </span>
          </div>
          <span class="shrink-0 text-xs text-slate-400">
            {{ financingInstallments.length }} parcelas
          </span>
        </button>
        <table v-show="installmentsExpanded" class="w-full text-sm">
          <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500">
            <tr>
              <th class="px-4 py-3 text-left">#</th>
              <th class="px-4 py-3 text-left">Vencimento</th>
              <th class="px-4 py-3 text-right">Valor</th>
              <th class="px-4 py-3 text-center">Status</th>
              <th class="px-4 py-3 text-center">Pago em</th>
              <th class="px-4 py-3 text-left">WhatsApp</th>
              <th class="px-4 py-3 text-right">Ação</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="inst in financingInstallments" :key="inst.id" class="hover:bg-slate-50">
              <td class="px-4 py-3 text-slate-400">{{ inst.number }}</td>
              <td class="px-4 py-3 text-slate-700">{{ formatDate(inst.due_date) }}</td>
              <td class="px-4 py-3 text-right font-medium text-slate-800">{{ formatCurrency(inst.value) }}</td>
              <td class="px-4 py-3 text-center">
                <span :class="installStatusClass(installmentDisplayStatus(inst))" class="rounded-full px-2 py-0.5 text-xs font-semibold">
                  {{ installStatusLabel(installmentDisplayStatus(inst)) }}
                </span>
              </td>
              <td class="px-4 py-3 text-center text-slate-500">{{ inst.paid_at ? formatDate(inst.paid_at) : '—' }}</td>
              <td class="px-4 py-3 text-left text-xs">
                <InstallmentWhatsappCell :installment="inst" :sale="sale" />
              </td>
              <td class="px-4 py-3 text-right">
                <button
                  v-if="inst.status !== 'paid'"
                  type="button"
                  class="rounded px-2 py-1 text-xs font-medium text-action hover:text-action-hover hover:underline"
                  @click="payInstallment(inst)"
                >
                  Marcar pago
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="card mt-4 overflow-hidden">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <p class="text-sm font-semibold text-slate-700">Histórico WhatsApp</p>
          <span class="text-xs text-slate-400">{{ interactions.length }} registros</span>
        </div>

        <div v-if="!interactions.length" class="px-4 py-6 text-center text-xs text-slate-400">
          Nenhuma interação registrada ainda.
        </div>

        <div v-else class="divide-y divide-slate-50">
          <div
            v-for="inter in interactions"
            :key="inter.id"
            class="flex items-start gap-3 px-4 py-3"
          >
            <div class="mt-0.5 shrink-0">
              <span
                v-if="inter.direction === 'outbound'"
                class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-blue-100 text-xs text-blue-600"
                title="Sistema enviou"
              >↗</span>
              <span
                v-else
                class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-100 text-xs text-emerald-600"
                title="Cliente respondeu"
              >↙</span>
            </div>

            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex min-w-0 flex-wrap items-center gap-2">
                  <p class="text-xs font-semibold text-slate-700">{{ inter.type_label }}</p>
                  <span
                    v-if="inter.installments_label"
                    class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-600"
                  >
                    {{ inter.installments_label }}
                  </span>
                </div>
                <p class="shrink-0 text-xs text-slate-400">{{ fmtDate(inter.created_at) }}</p>
              </div>
              <div
                v-if="inter.message"
                class="mt-2 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-xs leading-relaxed text-slate-600 whitespace-pre-wrap break-words"
                v-html="formatWhatsappHtml(inter.message)"
              />
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import api from '@/services/api';
import {
  downloadContract,
  downloadCarne,
  downloadSignedContract,
  printContract,
  uploadSignedContract,
} from '@/services/sale.service';
import { getApiErrorMessage } from '@/utils/apiError';
import { formatCurrency } from '@/utils/format';
import {
  installmentStatusClass as installmentStatusClassHelper,
  installmentStatusLabel as installmentStatusLabelHelper,
  installmentTypeLabel as installmentTypeLabelHelper,
  saleStatusClass as saleStatusClassHelper,
  saleStatusLabel as saleStatusLabelHelper,
} from '@/utils/status';
import Button from '@/components/Common/Button.vue';
import InstallmentWhatsappCell from '@/components/Sales/InstallmentWhatsappCell.vue';
import { installmentDisplayStatus } from '@/utils/whatsapp';
import { formatWhatsappHtml } from '@/utils/whatsappFormat';
import {
  ArrowLeftIcon,
  ArrowUpTrayIcon,
  ChevronDownIcon,
  DocumentArrowDownIcon,
  DocumentCheckIcon,
  DocumentTextIcon,
  EyeIcon,
  PrinterIcon,
} from '@heroicons/vue/24/outline';

const saleStatusClass = (status) => saleStatusClassHelper(status);
const saleStatusLabel = (status) => saleStatusLabelHelper(status);
const showCarnePreview = import.meta.env.DEV;
const installStatusClass = (status) => installmentStatusClassHelper(status);
const installStatusLabel = (status) => installmentStatusLabelHelper(status);
const installmentTypeLabel = (type) => installmentTypeLabelHelper(type);

const downPaymentInstallments = computed(() =>
  (sale.value?.installments ?? []).filter((inst) => inst.type === 'down_payment'),
);

const financingInstallments = computed(() =>
  (sale.value?.installments ?? []).filter((inst) => inst.type !== 'down_payment'),
);

const financingOverdueCount = computed(() =>
  financingInstallments.value.filter(
    (inst) => installmentDisplayStatus(inst) === 'overdue',
  ).length,
);

const installmentsExpanded = ref(false);

const route = useRoute();
const router = useRouter();
const toast = useToast();
const sale = ref(null);
const interactions = ref([]);
const loading = ref(false);
const printingContract = ref(false);
const downloadingContract = ref(false);
const downloadingCarne = ref(false);
const downloadingSigned = ref(false);
const uploadingSigned = ref(false);
const fileInputRef = ref(null);
const selectedFile = ref(null);
const selectedFileName = ref('');

const showRegistrationSuccess = computed(() => route.query.registered === '1');

const formatDate = (d) => (d ? new Date(`${d}T00:00:00`).toLocaleDateString('pt-BR') : '—');
const formatDiscountPercent = (value) => `${String(value).replace('.', ',')}%`;

function formatDateTime(value) {
  if (!value) return '—';
  return new Date(value).toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

const fmtDate = formatDateTime;

async function loadSale() {
  loading.value = true;
  try {
    const { data } = await api.get(`/sales/${route.params.id}`);
    sale.value = data.data ?? data;
  } catch {
    toast.error('Erro ao carregar venda');
    router.push({ name: 'sales.index' });
  } finally {
    loading.value = false;
  }
}

async function loadInteractions() {
  try {
    const { data } = await api.get(`/sales/${route.params.id}/interactions`);
    interactions.value = data.data ?? data;
  } catch {
    interactions.value = [];
  }
}

async function handlePrintContract() {
  printingContract.value = true;
  try {
    await printContract(sale.value.id);
  } catch (err) {
    if (err?.code === 'popup_blocked') {
      toast.warning('Permita pop-ups para imprimir ou use "Baixar PDF".');
    } else {
      toast.error('Erro ao abrir contrato para impressão.');
    }
  } finally {
    printingContract.value = false;
  }
}

async function handleDownloadContract() {
  downloadingContract.value = true;
  try {
    await downloadContract(sale.value.id);
  } catch {
    toast.error('Erro ao baixar contrato.');
  } finally {
    downloadingContract.value = false;
  }
}

async function handleDownloadCarne() {
  downloadingCarne.value = true;
  try {
    await downloadCarne(sale.value.id);
  } catch {
    toast.error('Erro ao baixar carnê.');
  } finally {
    downloadingCarne.value = false;
  }
}

function openCarnePreview() {
  const previewRoute = router.resolve({
    name: 'sales.carne.preview',
    params: { id: sale.value.id },
  });

  window.open(previewRoute.href, '_blank');
}

function openFilePicker() {
  fileInputRef.value?.click();
}

function onFileSelected(event) {
  const file = event.target.files?.[0];
  if (!file) {
    return;
  }
  selectedFile.value = file;
  selectedFileName.value = file.name;
  if (sale.value?.has_signed_contract) {
    handleUploadSignedContract();
  }
}

async function handleUploadSignedContract() {
  if (!selectedFile.value) {
    toast.warning('Selecione o arquivo do contrato assinado.');
    return;
  }

  uploadingSigned.value = true;
  try {
    const updated = await uploadSignedContract(sale.value.id, selectedFile.value);
    sale.value = updated;
    selectedFile.value = null;
    selectedFileName.value = '';
    if (fileInputRef.value) {
      fileInputRef.value.value = '';
    }
    toast.success('Contrato assinado anexado com sucesso.');
    clearRegistrationQuery();
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Erro ao enviar contrato assinado.'));
  } finally {
    uploadingSigned.value = false;
  }
}

async function handleDownloadSignedContract() {
  downloadingSigned.value = true;
  try {
    await downloadSignedContract(
      sale.value.id,
      sale.value.signed_contract_original_name,
    );
  } catch {
    toast.error('Erro ao baixar contrato assinado.');
  } finally {
    downloadingSigned.value = false;
  }
}

function clearRegistrationQuery() {
  if (route.query.registered === '1') {
    router.replace({ name: 'sales.show', params: { id: route.params.id } });
  }
}

async function payInstallment(inst) {
  try {
    await api.post(`/installments/${inst.id}/pay`);
    const label = inst.type === 'down_payment' ? 'Entrada' : `Parcela ${inst.number}`;
    toast.success(`${label} marcada como paga.`);
    loadSale();
  } catch {
    toast.error('Erro ao marcar pagamento como pago.');
  }
}

onMounted(() => {
  loadSale();
  loadInteractions();
});
</script>
