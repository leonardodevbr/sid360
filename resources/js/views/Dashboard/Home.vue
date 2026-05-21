<template>
  <div class="space-y-6">
    <div>
      <h2 class="text-lg font-semibold text-slate-800">Dashboard</h2>
      <p class="text-xs text-slate-500">Sid360 Imóveis · Visão geral do sistema</p>
    </div>

    <div v-if="loading" class="flex justify-center py-12">
      <svg class="h-8 w-8 animate-spin text-action" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
      </svg>
    </div>

    <template v-else>
      <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <StatCard label="Lotes disponíveis" :value="data?.lots_by_status?.available ?? 0" color="emerald" />
        <StatCard label="Lotes vendidos" :value="data?.lots_by_status?.sold ?? 0" color="red" />
        <StatCard label="Vendas ativas" :value="data?.active_sales ?? 0" color="blue" />
        <StatCard label="Clientes" :value="data?.total_clients ?? 0" color="amber" />
      </div>

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="card p-4">
          <p class="mb-1 text-xs text-slate-500">Receita total gerada</p>
          <p class="text-2xl font-bold text-slate-800">{{ fmt(data?.total_revenue) }}</p>
          <p class="mt-1 text-xs text-slate-400">em {{ data?.total_sales ?? 0 }} vendas</p>
        </div>
        <div class="card p-4">
          <p class="mb-1 text-xs text-slate-500">Total recebido</p>
          <p class="text-2xl font-bold text-emerald-700">{{ fmt(data?.total_received) }}</p>
          <p class="mt-1 text-xs text-slate-400">parcelas pagas</p>
        </div>
        <div class="card p-4">
          <p class="mb-1 text-xs text-slate-500">A receber</p>
          <p class="text-2xl font-bold text-blue-700">{{ fmt(data?.total_pending) }}</p>
          <p class="mt-1 text-xs text-slate-400">parcelas pendentes</p>
        </div>
      </div>

      <div class="card p-4">
        <p class="mb-3 text-sm font-semibold text-slate-700">
          Parcelas de {{ mesAtual }}
        </p>
        <div class="grid grid-cols-3 gap-3">
          <div class="rounded-lg border border-emerald-100 bg-emerald-50 p-3 text-center">
            <p class="mb-1 text-xs text-emerald-600">Pagas</p>
            <p class="text-xl font-bold text-emerald-700">{{ data?.month_installments?.paid?.count ?? 0 }}</p>
            <p class="text-xs text-emerald-500">{{ fmt(data?.month_installments?.paid?.total) }}</p>
          </div>
          <div class="rounded-lg border border-amber-100 bg-amber-50 p-3 text-center">
            <p class="mb-1 text-xs text-amber-600">Pendentes</p>
            <p class="text-xl font-bold text-amber-700">{{ data?.month_installments?.pending?.count ?? 0 }}</p>
            <p class="text-xs text-amber-500">{{ fmt(data?.month_installments?.pending?.total) }}</p>
          </div>
          <div class="rounded-lg border border-red-100 bg-red-50 p-3 text-center">
            <p class="mb-1 text-xs text-red-600">Atrasadas</p>
            <p class="text-xl font-bold text-red-700">{{ data?.month_installments?.overdue?.count ?? 0 }}</p>
            <p class="text-xs text-red-500">{{ fmt(data?.month_installments?.overdue?.total) }}</p>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="card overflow-hidden">
          <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
            <div class="flex items-center gap-2">
              <ExclamationTriangleIcon class="h-4 w-4 text-red-600" />
              <p class="text-sm font-semibold text-slate-700">Parcelas atrasadas</p>
            </div>
            <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">
              {{ data?.overdue_installments?.length ?? 0 }}
            </span>
          </div>
          <div v-if="!data?.overdue_installments?.length" class="px-4 py-6 text-center text-xs text-slate-400">
            Nenhuma parcela atrasada
          </div>
          <div v-else class="divide-y divide-slate-50">
            <router-link
              v-for="inst in data.overdue_installments"
              :key="inst.id"
              :to="{ name: 'sales.show', params: { id: inst.sale_id } }"
              class="flex items-center justify-between px-4 py-2.5 transition-colors hover:bg-red-50"
            >
              <div class="min-w-0">
                <p class="truncate text-xs font-medium text-slate-800">{{ inst.client }}</p>
                <p class="text-xs text-slate-400">{{ inst.lote }} · {{ inst.label }}</p>
              </div>
              <div class="ml-3 shrink-0 text-right">
                <p class="text-xs font-semibold text-red-600">{{ fmt(inst.value) }}</p>
                <p class="text-xs text-slate-400">{{ fmtDate(inst.due_date) }}</p>
              </div>
            </router-link>
          </div>
        </div>

        <div class="card overflow-hidden">
          <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
            <div class="flex items-center gap-2">
              <CalendarDaysIcon class="h-4 w-4 text-amber-600" />
              <p class="text-sm font-semibold text-slate-700">Vencendo em 7 dias</p>
            </div>
            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700">
              {{ data?.upcoming_installments?.length ?? 0 }}
            </span>
          </div>
          <div v-if="!data?.upcoming_installments?.length" class="px-4 py-6 text-center text-xs text-slate-400">
            Nenhuma parcela vencendo esta semana
          </div>
          <div v-else class="divide-y divide-slate-50">
            <router-link
              v-for="inst in data.upcoming_installments"
              :key="inst.id"
              :to="{ name: 'sales.show', params: { id: inst.sale_id } }"
              class="flex items-center justify-between px-4 py-2.5 transition-colors hover:bg-amber-50"
            >
              <div class="min-w-0">
                <p class="truncate text-xs font-medium text-slate-800">{{ inst.client }}</p>
                <p class="text-xs text-slate-400">{{ inst.lote }} · {{ inst.label }}</p>
              </div>
              <div class="ml-3 shrink-0 text-right">
                <p class="text-xs font-semibold text-amber-700">{{ fmt(inst.value) }}</p>
                <p class="text-xs text-slate-400">{{ fmtDate(inst.due_date) }}</p>
              </div>
            </router-link>
          </div>
        </div>
      </div>

      <div class="card overflow-hidden">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <p class="text-sm font-semibold text-slate-700">Empreendimentos</p>
          <router-link :to="{ name: 'developments.index' }" class="text-xs text-blue-600 hover:underline">
            Ver todos
          </router-link>
        </div>
        <div v-if="!data?.recent_developments?.length" class="px-4 py-6 text-center text-xs text-slate-400">
          Nenhum empreendimento cadastrado
        </div>
        <div v-else class="divide-y divide-slate-50">
          <div
            v-for="dev in data.recent_developments"
            :key="dev.id"
            class="flex items-center justify-between px-4 py-3"
          >
            <div>
              <p class="text-sm font-medium text-slate-800">{{ dev.name }}</p>
              <p class="text-xs text-slate-400">{{ dev.location || 'Sem localização' }}</p>
            </div>
            <span class="text-xs text-slate-500">{{ dev.lots_count }} lotes</span>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useToast } from 'vue-toastification';
import api from '@/services/api';
import StatCard from '@/components/Common/StatCard.vue';
import { CalendarDaysIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';

const toast = useToast();
const data = ref(null);
const loading = ref(false);

const mesAtual = computed(() =>
  new Date().toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' })
);

function fmt(v) {
  return v != null
    ? new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format((Number(v) || 0) / 100)
    : 'R$ 0,00';
}

function fmtDate(d) {
  return d ? new Date(`${d}T00:00:00`).toLocaleDateString('pt-BR') : '–';
}

async function loadDashboard() {
  loading.value = true;
  try {
    const { data: res } = await api.get('/dashboard');
    data.value = res;
  } catch {
    toast.error('Erro ao carregar dados do painel');
  } finally {
    loading.value = false;
  }
}

onMounted(() => loadDashboard());
</script>
