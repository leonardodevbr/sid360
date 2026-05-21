<script setup>
import { computed, onMounted, ref } from 'vue';
import { useToast } from 'vue-toastification';
import Button from '@/components/Common/Button.vue';
import Input from '@/components/Common/Input.vue';
import { formatCurrency, formatCpf } from '@/utils/format';
import { getApiErrorMessage } from '@/utils/apiError';
import {
  installmentStatusClass,
  installmentStatusLabel,
  installmentTypeLabel,
  saleStatusLabel,
} from '@/utils/status';
import {
  buildWhatsAppUrl,
  clearPortalSession,
  getStoredPortalClient,
  portalAccess,
  portalDashboard,
  portalLogout,
  storePortalSession,
} from '@/services/portal.service';
import { ChevronLeftIcon, ChevronRightIcon } from '@heroicons/vue/24/outline';

const toast = useToast();

const loading = ref(false);
const submitting = ref(false);
const dashboard = ref(null);
const selectedSaleId = ref(null);
const clientName = ref(getStoredPortalClient()?.name ?? '');
const cpf = ref('');
const phone = ref('');
const whatsappNumber = ref('5574988230151');

const isAuthenticated = computed(() => Boolean(clientName.value) && Boolean(dashboard.value));

const sales = computed(() => dashboard.value?.sales ?? []);

const selectedSale = computed(() =>
  sales.value.find((sale) => sale.id === selectedSaleId.value) ?? null,
);

const showContractList = computed(() => isAuthenticated.value && selectedSaleId.value === null);

const formatDate = (value) => (value ? new Date(`${value}T00:00:00`).toLocaleDateString('pt-BR') : '—');

function onCpfInput(value) {
  cpf.value = formatCpf(value);
}

function onPhoneInput(value) {
  const digits = String(value ?? '').replace(/\D/g, '').slice(0, 11);
  if (digits.length <= 2) {
    phone.value = digits;
    return;
  }
  if (digits.length <= 7) {
    phone.value = `(${digits.slice(0, 2)}) ${digits.slice(2)}`;
    return;
  }
  phone.value = `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`;
}

async function loadDashboard() {
  loading.value = true;
  try {
    dashboard.value = await portalDashboard();
    clientName.value = dashboard.value.client?.name ?? clientName.value;
    whatsappNumber.value = dashboard.value.whatsapp_number ?? whatsappNumber.value;
    selectedSaleId.value = null;
  } catch (err) {
    clearPortalSession();
    dashboard.value = null;
    clientName.value = '';
    if (err?.response?.status !== 401) {
      toast.error(getApiErrorMessage(err, 'Erro ao carregar seus pagamentos.'));
    }
  } finally {
    loading.value = false;
  }
}

async function handleAccess() {
  submitting.value = true;
  try {
    const result = await portalAccess(cpf.value, phone.value);
    storePortalSession(result.portal_token, result.client);
    clientName.value = result.client.name;
    await loadDashboard();
    toast.success('Acesso liberado.');
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Não foi possível validar seus dados.'));
  } finally {
    submitting.value = false;
  }
}

async function handleLogout() {
  try {
    await portalLogout();
  } catch {
    clearPortalSession();
  }
  dashboard.value = null;
  clientName.value = '';
  cpf.value = '';
  phone.value = '';
  selectedSaleId.value = null;
}

function openSale(saleId) {
  selectedSaleId.value = saleId;
}

function backToList() {
  selectedSaleId.value = null;
}

function lotLabel(sale) {
  return `Quadra ${sale.lot?.block ?? '–'} · Lote ${sale.lot?.number ?? '–'}`;
}

function installmentLabel(inst) {
  return inst.type === 'down_payment' ? 'Entrada' : `Parcela ${inst.number}`;
}

function buildRequestMessage(inst, kind) {
  const typeLabel = installmentLabel(inst);
  const prefix = kind === 'pix'
    ? 'Gostaria do código PIX'
    : kind === 'boleto'
      ? 'Gostaria da 2ª via do boleto'
      : 'Gostaria da 2ª via de pagamento';

  return [
    `Olá! Sou ${clientName.value}.`,
    `${prefix} da ${typeLabel} do contrato nº ${inst.contract_no}.`,
    `Vencimento: ${formatDate(inst.due_date)} · Valor: ${formatCurrency(inst.value)}.`,
    'Aguardo retorno. Obrigado!',
  ].join(' ');
}

function openWhatsApp(inst, kind) {
  const url = buildWhatsAppUrl(whatsappNumber.value, buildRequestMessage(inst, kind));
  window.open(url, '_blank', 'noopener,noreferrer');
}

onMounted(async () => {
  if (getStoredPortalClient()) {
    clientName.value = getStoredPortalClient().name;
    await loadDashboard();
  }
});
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="font-display text-2xl font-bold text-sid-primary sm:text-3xl">
        Meus pagamentos
      </h1>
      <p class="mt-1 text-sm text-slate-600">
        Acompanhe entrada, parcelas e solicite PIX, boleto ou segunda via.
      </p>
    </div>

    <div v-if="!isAuthenticated && !loading" class="card p-5 sm:p-6">
      <h2 class="text-sm font-semibold text-slate-800">Acessar com seus dados</h2>
      <p class="mt-1 text-xs text-slate-500">
        Informe o CPF e o WhatsApp cadastrados na compra do lote.
      </p>

      <form class="mt-5 space-y-4" @submit.prevent="handleAccess">
        <Input
          :model-value="cpf"
          label="CPF"
          inputmode="numeric"
          autocomplete="off"
          placeholder="000.000.000-00"
          required
          @update:model-value="onCpfInput"
        />
        <Input
          v-model="phone"
          label="WhatsApp cadastrado"
          inputmode="tel"
          autocomplete="tel"
          placeholder="(00) 00000-0000"
          required
          @update:model-value="onPhoneInput"
        />
        <Button type="submit" variant="primary" class="w-full sm:w-auto" :loading="submitting">
          Entrar
        </Button>
      </form>
    </div>

    <div v-if="loading" class="card p-12 text-center text-slate-400">
      Carregando...
    </div>

    <template v-else-if="isAuthenticated">
      <div class="card flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <p class="text-xs text-slate-500">Comprador</p>
          <p class="font-semibold text-slate-800">{{ clientName }}</p>
        </div>
        <Button type="button" variant="outline" @click="handleLogout">
          Sair
        </Button>
      </div>

      <div v-if="!sales.length" class="card p-8 text-center">
        <p class="text-sm text-slate-600">Nenhum contrato encontrado para este CPF.</p>
        <a
          href="https://wa.me/5574988230151"
          target="_blank"
          rel="noopener noreferrer"
          class="mt-4 inline-block text-sm font-medium text-sid-accent hover:underline"
        >
          Falar com a corretora
        </a>
      </div>

      <!-- Lista de contratos -->
      <template v-else-if="showContractList">
        <div>
          <h2 class="text-sm font-semibold text-slate-800">Seus contratos</h2>
          <p class="mt-0.5 text-xs text-slate-500">
            Selecione um contrato para ver o extrato completo e solicitar pagamentos.
          </p>
        </div>

        <div class="grid gap-4">
          <button
            v-for="sale in sales"
            :key="sale.id"
            type="button"
            class="card overflow-hidden text-left transition hover:border-sid-accent/30 hover:shadow-md"
            @click="openSale(sale.id)"
          >
            <div class="border-b border-slate-100 bg-slate-50 px-4 py-4">
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                  <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Contrato nº {{ sale.contract_no }}
                  </p>
                  <p class="mt-1 truncate text-sm font-semibold text-slate-800">
                    {{ sale.development?.name ?? 'Empreendimento' }}
                  </p>
                  <p class="mt-0.5 text-xs text-slate-500">
                    {{ lotLabel(sale) }} · {{ saleStatusLabel(sale.status) }}
                  </p>
                </div>
                <ChevronRightIcon class="h-5 w-5 shrink-0 text-slate-400" />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3 p-4 sm:grid-cols-4">
              <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                <p class="text-[11px] text-slate-500">Valor total</p>
                <p class="text-sm font-semibold text-slate-800">{{ formatCurrency(sale.total_value) }}</p>
              </div>
              <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                <p class="text-[11px] text-slate-500">Pagas</p>
                <p class="text-sm font-semibold text-emerald-700">{{ sale.summary.paid_count }}</p>
              </div>
              <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                <p class="text-[11px] text-slate-500">Pendentes</p>
                <p class="text-sm font-semibold text-amber-700">{{ sale.summary.pending_count }}</p>
              </div>
              <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                <p class="text-[11px] text-slate-500">Em aberto</p>
                <p class="text-sm font-semibold text-slate-800">{{ formatCurrency(sale.summary.pending_value) }}</p>
              </div>
            </div>

            <p
              v-if="sale.summary.overdue_count > 0"
              class="border-t border-red-100 bg-red-50 px-4 py-2 text-xs font-medium text-red-700"
            >
              {{ sale.summary.overdue_count }} parcela(s) em atraso
            </p>
          </button>
        </div>
      </template>

      <!-- Detalhe do contrato -->
      <template v-else-if="selectedSale">
        <button
          type="button"
          class="inline-flex items-center gap-1 text-sm font-medium text-sid-accent hover:underline"
          @click="backToList"
        >
          <ChevronLeftIcon class="h-4 w-4" />
          Voltar aos contratos
        </button>

        <div class="card overflow-hidden">
          <div class="border-b border-slate-100 bg-slate-50 px-4 py-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                  Contrato nº {{ selectedSale.contract_no }}
                </p>
                <p class="mt-1 text-sm font-semibold text-slate-800">
                  {{ selectedSale.development?.name ?? 'Empreendimento' }}
                  · {{ lotLabel(selectedSale) }}
                </p>
                <p class="mt-0.5 text-xs text-slate-500">
                  Venda em {{ formatDate(selectedSale.sale_date) }} · {{ saleStatusLabel(selectedSale.status) }}
                </p>
              </div>
              <div class="text-right">
                <p class="text-xs text-slate-500">Valor total</p>
                <p class="text-lg font-bold text-slate-800">{{ formatCurrency(selectedSale.total_value) }}</p>
              </div>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
              <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                <p class="text-[11px] text-slate-500">Entrada</p>
                <p class="text-sm font-semibold text-slate-800">{{ formatCurrency(selectedSale.down_payment) }}</p>
              </div>
              <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                <p class="text-[11px] text-slate-500">Pagas</p>
                <p class="text-sm font-semibold text-emerald-700">{{ selectedSale.summary.paid_count }}</p>
              </div>
              <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                <p class="text-[11px] text-slate-500">Pendentes</p>
                <p class="text-sm font-semibold text-amber-700">{{ selectedSale.summary.pending_count }}</p>
              </div>
              <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                <p class="text-[11px] text-slate-500">Em aberto</p>
                <p class="text-sm font-semibold text-slate-800">{{ formatCurrency(selectedSale.summary.pending_value) }}</p>
              </div>
            </div>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full min-w-[640px] text-sm">
              <thead class="bg-white text-xs font-semibold uppercase text-slate-500">
                <tr>
                  <th class="px-4 py-3 text-left">Item</th>
                  <th class="px-4 py-3 text-left">Vencimento</th>
                  <th class="px-4 py-3 text-right">Valor</th>
                  <th class="px-4 py-3 text-center">Status</th>
                  <th class="px-4 py-3 text-right">Ações</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr
                  v-for="inst in selectedSale.installments"
                  :key="inst.id"
                  class="hover:bg-slate-50"
                >
                  <td class="px-4 py-3 font-medium text-slate-700">
                    {{ installmentTypeLabel(inst.type) }}
                    <span v-if="inst.type !== 'down_payment'" class="text-slate-400">#{{ inst.number }}</span>
                  </td>
                  <td class="px-4 py-3 text-slate-700">{{ formatDate(inst.due_date) }}</td>
                  <td class="px-4 py-3 text-right font-medium text-slate-800">
                    {{ formatCurrency(inst.value) }}
                  </td>
                  <td class="px-4 py-3 text-center">
                    <span
                      :class="installmentStatusClass(inst.status)"
                      class="rounded-full px-2 py-0.5 text-xs font-semibold"
                    >
                      {{ installmentStatusLabel(inst.status) }}
                    </span>
                  </td>
                  <td class="px-4 py-3">
                    <div v-if="inst.status !== 'paid'" class="flex flex-wrap justify-end gap-1">
                      <button
                        type="button"
                        class="rounded px-2 py-1 text-xs font-medium text-sid-accent hover:bg-primary-50"
                        @click="openWhatsApp(inst, 'pix')"
                      >
                        PIX
                      </button>
                      <button
                        type="button"
                        class="rounded px-2 py-1 text-xs font-medium text-sid-accent hover:bg-primary-50"
                        @click="openWhatsApp(inst, 'boleto')"
                      >
                        Boleto
                      </button>
                      <button
                        type="button"
                        class="rounded px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-100"
                        @click="openWhatsApp(inst, 'via')"
                      >
                        2ª via
                      </button>
                    </div>
                    <p v-else class="text-right text-xs text-slate-400">
                      Pago em {{ formatDate(inst.paid_at) }}
                    </p>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="rounded-lg border border-[#e8dcc8] bg-[#faf5ee] px-4 py-3 text-xs text-[#7a4535]">
          Pagamento automático via PIX estará disponível em breve. Por enquanto, use os botões acima
          para solicitar PIX, boleto ou segunda via pelo WhatsApp da corretora.
        </div>
      </template>
    </template>
  </div>
</template>
