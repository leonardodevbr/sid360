<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '@/services/api';
import { useToast } from 'vue-toastification';
import { useAppStore } from '@/stores/app';
import {
  BuildingOffice2Icon,
  Squares2X2Icon,
  CheckCircleIcon,
  ClockIcon,
} from '@heroicons/vue/24/outline';

const router = useRouter();
const toast = useToast();
const appStore = useAppStore();

const stats = ref({
  total_developments: 0,
  total_lots: 0,
  lots_by_status: { available: 0, reserved: 0, sold: 0 },
  recent_developments: [],
});

const loading = ref(true);

async function fetchStats() {
  try {
    loading.value = true;
    const { data } = await api.get('/dashboard');
    stats.value = data;
  } catch {
    toast.error('Erro ao carregar dados do painel');
  } finally {
    loading.value = false;
  }
}

onMounted(fetchStats);
</script>

<template>
  <div class="space-y-6">
    <div>
      <h2 class="text-lg font-semibold text-slate-800">Dashboard</h2>
      <p class="text-xs text-slate-500">{{ appStore.appName || 'Sid360' }}</p>
    </div>

    <div v-if="loading" class="flex justify-center py-12">
      <svg class="h-8 w-8 animate-spin text-sid-accent" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
      </svg>
    </div>

    <template v-else>
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="card flex items-center gap-4 p-4">
          <div class="rounded-lg bg-primary-50 p-3">
            <BuildingOffice2Icon class="h-6 w-6 text-sid-accent" />
          </div>
          <div>
            <p class="text-xs text-slate-500">Empreendimentos</p>
            <p class="text-2xl font-bold text-slate-800">{{ stats.total_developments }}</p>
          </div>
        </div>
        <div class="card flex items-center gap-4 p-4">
          <div class="rounded-lg bg-sid-cream-dark p-3">
            <Squares2X2Icon class="h-6 w-6 text-secondary-600" />
          </div>
          <div>
            <p class="text-xs text-slate-500">Total de lotes</p>
            <p class="text-2xl font-bold text-slate-800">{{ stats.total_lots }}</p>
          </div>
        </div>
        <div class="card flex items-center gap-4 p-4">
          <div class="rounded-lg bg-sid-cream-dark p-3">
            <CheckCircleIcon class="h-6 w-6 text-sid-gold" />
          </div>
          <div>
            <p class="text-xs text-slate-500">Disponíveis</p>
            <p class="text-2xl font-bold text-slate-800">{{ stats.lots_by_status?.available ?? 0 }}</p>
          </div>
        </div>
        <div class="card flex items-center gap-4 p-4">
          <div class="rounded-lg bg-[rgba(122,69,53,0.08)] p-3">
            <ClockIcon class="h-6 w-6 text-sid-secondary" />
          </div>
          <div>
            <p class="text-xs text-slate-500">Reservados</p>
            <p class="text-2xl font-bold text-slate-800">{{ stats.lots_by_status?.reserved ?? 0 }}</p>
          </div>
        </div>
      </div>

      <div class="card p-5">
        <div class="mb-4 flex items-center justify-between">
          <h3 class="text-base font-semibold text-slate-800">Empreendimentos recentes</h3>
          <button
            type="button"
            class="text-sm font-medium text-sid-accent hover:text-sid-accent-light"
            @click="router.push({ name: 'developments.index' })"
          >
            Ver todos
          </button>
        </div>
        <div v-if="stats.recent_developments?.length" class="space-y-2">
          <div
            v-for="dev in stats.recent_developments"
            :key="dev.id"
            class="flex items-center justify-between rounded bg-slate-50 p-3"
          >
            <div>
              <p class="text-sm font-medium text-slate-900">{{ dev.name }}</p>
              <p class="text-xs text-slate-500">{{ dev.location || 'Sem localização' }}</p>
            </div>
            <span class="text-xs text-slate-500">{{ dev.lots_count ?? 0 }} lotes</span>
          </div>
        </div>
        <p v-else class="py-6 text-center text-slate-500">Nenhum empreendimento cadastrado</p>
      </div>
    </template>
  </div>
</template>
