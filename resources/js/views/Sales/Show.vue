<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-4">
        <button type="button" class="rounded-lg p-2 hover:bg-slate-100" @click="$router.push({ name: 'sales.index' })">
          <ArrowLeftIcon class="h-5 w-5 text-slate-600" />
        </button>
        <div>
          <h2 class="text-lg font-semibold text-slate-800">Venda #{{ sale?.id }}</h2>
          <p class="text-xs text-slate-500">{{ sale?.client?.name }}</p>
        </div>
      </div>
      <Button v-if="sale" variant="outline" @click="downloadContract(sale.id)">
        <DocumentArrowDownIcon class="mr-2 h-4 w-4" />
        Baixar Contrato PDF
      </Button>
    </div>

    <div v-if="loading" class="card p-12 text-center text-slate-400">Carregando...</div>

    <template v-else-if="sale">
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
          <p class="text-xs text-slate-500">Parcelas</p>
          <p class="text-lg font-bold text-slate-800">{{ sale.installments_count }}x {{ formatCurrency(sale.installment_value) }}</p>
        </div>
        <div class="card p-4">
          <p class="text-xs text-slate-500">Status</p>
          <span :class="statusClass(sale.status)" class="rounded-full px-2 py-0.5 text-xs font-semibold">
            {{ statusLabel(sale.status) }}
          </span>
        </div>
      </div>

      <div class="card overflow-hidden">
        <div class="border-b border-slate-100 px-4 py-3">
          <h3 class="text-sm font-semibold text-slate-700">Parcelas</h3>
        </div>
        <table class="w-full text-sm">
          <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500">
            <tr>
              <th class="px-4 py-3 text-left">#</th>
              <th class="px-4 py-3 text-left">Vencimento</th>
              <th class="px-4 py-3 text-right">Valor</th>
              <th class="px-4 py-3 text-center">Status</th>
              <th class="px-4 py-3 text-center">Pago em</th>
              <th class="px-4 py-3 text-right">Ação</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="inst in sale.installments" :key="inst.id" class="hover:bg-slate-50">
              <td class="px-4 py-3 text-slate-400">{{ inst.number }}</td>
              <td class="px-4 py-3 text-slate-700">{{ formatDate(inst.due_date) }}</td>
              <td class="px-4 py-3 text-right font-medium text-slate-800">{{ formatCurrency(inst.value) }}</td>
              <td class="px-4 py-3 text-center">
                <span :class="installStatusClass(inst.status)" class="rounded-full px-2 py-0.5 text-xs font-semibold">
                  {{ installStatusLabel(inst.status) }}
                </span>
              </td>
              <td class="px-4 py-3 text-center text-slate-500">{{ inst.paid_at ? formatDate(inst.paid_at) : '—' }}</td>
              <td class="px-4 py-3 text-right">
                <button
                  v-if="inst.status !== 'paid'"
                  type="button"
                  class="rounded px-2 py-1 text-xs font-medium text-sid-accent hover:bg-primary-50"
                  @click="payInstallment(inst)"
                >
                  Marcar pago
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import api from '@/services/api';
import { formatCurrency } from '@/utils/format';
import Button from '@/components/Common/Button.vue';
import { ArrowLeftIcon, DocumentArrowDownIcon } from '@heroicons/vue/24/outline';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const sale = ref(null);
const loading = ref(false);

const formatDate = (d) => (d ? new Date(`${d}T00:00:00`).toLocaleDateString('pt-BR') : '—');
const statusLabel = (s) => ({ active: 'Ativo', cancelled: 'Cancelado', completed: 'Concluído' }[s] ?? s);
const statusClass = (s) => ({ active: 'bg-sid-cream-dark text-secondary-600', cancelled: 'bg-red-100 text-red-700', completed: 'bg-slate-100 text-slate-600' }[s] ?? '');
const installStatusLabel = (s) => ({ pending: 'Pendente', paid: 'Pago', overdue: 'Atrasado' }[s] ?? s);
const installStatusClass = (s) => ({ pending: 'bg-yellow-100 text-yellow-700', paid: 'bg-sid-cream-dark text-secondary-600', overdue: 'bg-red-100 text-red-700' }[s] ?? '');

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

async function downloadContract(saleId) {
  try {
    const { data } = await api.get(`/sales/${saleId}/contract`, { responseType: 'blob' });
    const url = window.URL.createObjectURL(new Blob([data], { type: 'application/pdf' }));
    const link = document.createElement('a');
    link.href = url;
    link.download = `contrato-venda-${saleId}.pdf`;
    link.click();
    window.URL.revokeObjectURL(url);
  } catch {
    toast.error('Erro ao baixar contrato.');
  }
}

async function payInstallment(inst) {
  try {
    await api.post(`/installments/${inst.id}/pay`);
    toast.success(`Parcela ${inst.number} marcada como paga.`);
    loadSale();
  } catch {
    toast.error('Erro ao marcar parcela como paga.');
  }
}

onMounted(() => loadSale());
</script>
