<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-lg font-semibold text-slate-800">Vendas</h2>
        <p class="text-xs text-slate-500">Controle de vendas e contratos</p>
      </div>
      <router-link :to="{ name: 'sales.create' }">
        <Button variant="primary">+ Nova Venda</Button>
      </router-link>
    </div>

    <div class="card overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500">
          <tr>
            <th class="px-4 py-3 text-left">#</th>
            <th class="px-4 py-3 text-left">Cliente</th>
            <th class="px-4 py-3 text-left">Lote</th>
            <th class="px-4 py-3 text-left">Data</th>
            <th class="px-4 py-3 text-right">Valor Total</th>
            <th class="px-4 py-3 text-center">Status</th>
            <th class="px-4 py-3 text-right">Ações</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-if="loading">
            <td colspan="7" class="px-4 py-8 text-center text-slate-400">Carregando...</td>
          </tr>
          <tr v-else-if="!sales.length">
            <td colspan="7" class="px-4 py-8 text-center text-slate-400">Nenhuma venda encontrada.</td>
          </tr>
          <tr v-for="sale in sales" :key="sale.id" class="hover:bg-slate-50">
            <td class="px-4 py-3 text-slate-400">#{{ sale.id }}</td>
            <td class="px-4 py-3 font-medium text-slate-800">{{ sale.client?.name }}</td>
            <td class="px-4 py-3 text-slate-600">
              {{ sale.lot?.development?.name }} — Q{{ sale.lot?.block }} L{{ sale.lot?.number }}
            </td>
            <td class="px-4 py-3 text-slate-600">{{ formatDate(sale.sale_date) }}</td>
            <td class="px-4 py-3 text-right font-medium text-slate-800">{{ formatCurrency(sale.total_value) }}</td>
            <td class="px-4 py-3 text-center">
              <span :class="statusClass(sale.status)" class="rounded-full px-2 py-0.5 text-xs font-semibold">
                {{ statusLabel(sale.status) }}
              </span>
            </td>
            <td class="px-4 py-3 text-right">
              <div class="flex justify-end gap-2">
                <router-link :to="{ name: 'sales.show', params: { id: sale.id } }">
                  <button type="button" class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                    <EyeIcon class="h-4 w-4" />
                  </button>
                </router-link>
                <button
                  type="button"
                  class="rounded p-1 text-slate-400 hover:bg-primary-50 hover:text-sid-accent"
                  title="Baixar contrato PDF"
                  @click="handleDownloadContract(sale.id)"
                >
                  <DocumentArrowDownIcon class="h-4 w-4" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <PaginationBar v-if="pagination" :pagination="pagination" @page-change="fetchSales" />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useToast } from 'vue-toastification';
import api from '@/services/api';
import { downloadContract } from '@/services/sale.service';
import { formatCurrency } from '@/utils/format';
import Button from '@/components/Common/Button.vue';
import PaginationBar from '@/components/Common/PaginationBar.vue';
import { EyeIcon, DocumentArrowDownIcon } from '@heroicons/vue/24/outline';

const toast = useToast();
const sales = ref([]);
const pagination = ref(null);
const loading = ref(false);

const statusLabel = (s) => ({ active: 'Ativo', cancelled: 'Cancelado', completed: 'Concluído' }[s] ?? s);
const statusClass = (s) => ({
  active: 'bg-sid-cream-dark text-secondary-600',
  cancelled: 'bg-red-100 text-red-700',
  completed: 'bg-slate-100 text-slate-600',
}[s] ?? '');

const formatDate = (d) => (d ? new Date(`${d}T00:00:00`).toLocaleDateString('pt-BR') : '—');

async function fetchSales(page = 1) {
  loading.value = true;
  try {
    const { data } = await api.get('/sales', { params: { page } });
    sales.value = data.data ?? [];
    pagination.value = data.meta ?? null;
  } catch {
    toast.error('Erro ao carregar vendas');
  } finally {
    loading.value = false;
  }
}

async function handleDownloadContract(saleId) {
  try {
    await downloadContract(saleId);
  } catch {
    toast.error('Erro ao baixar contrato.');
  }
}

onMounted(() => fetchSales());
</script>
