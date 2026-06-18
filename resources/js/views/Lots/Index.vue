<template>
  <div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 class="text-lg font-semibold text-slate-800">Lotes</h2>
        <p class="text-xs text-slate-500">Listagem de lotes disponíveis</p>
      </div>
      <Button v-if="authStore.can('lots.create')" type="button" variant="primary" @click="goToCreate">
        Novo lote
      </Button>
    </div>

    <div class="card p-4 sm:p-6">
      <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end">
        <div class="w-full sm:max-w-xs">
          <SelectInput
            v-model="developmentId"
            label="Empreendimento"
            :options="developmentOptions"
            placeholder="Todos"
            :searchable="true"
            @update:model-value="loadItems(1)"
          />
        </div>
        <div class="min-w-0 flex-1 sm:max-w-xs">
          <label class="mb-1 block text-sm font-medium text-slate-700">Buscar</label>
          <input
            v-model="searchQuery"
            type="search"
            placeholder="Número ou zona..."
            class="w-full rounded border border-slate-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-sid-accent"
            @input="debouncedSearch"
          />
        </div>
        <div class="w-full sm:w-40">
          <SelectInput
            v-model="statusFilter"
            label="Status"
            :options="lotStatusOptions"
            placeholder="Todos"
            :searchable="false"
            @update:model-value="loadItems(1)"
          />
        </div>
      </div>

      <div v-if="loading" class="py-10 text-center text-slate-500">Carregando...</div>
      <div v-else-if="!items.length" class="py-10 text-center text-slate-500">Nenhum lote encontrado</div>
      <div v-else class="-mx-4 overflow-x-auto sm:-mx-6">
        <table class="min-w-full divide-y divide-slate-200">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500 sm:px-6">Empreendimento</th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500 sm:px-6">Zona</th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500 sm:px-6">Número</th>
              <th class="hidden px-4 py-3 text-left text-xs font-medium uppercase text-slate-500 sm:table-cell sm:px-6">Área (m²)</th>
              <th class="hidden px-4 py-3 text-left text-xs font-medium uppercase text-slate-500 md:table-cell sm:px-6">Valor</th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500 sm:px-6">Status</th>
              <th class="sticky right-0 z-10 border-l border-slate-200 bg-slate-50 px-4 py-3 text-right text-xs font-medium uppercase text-slate-500 sm:px-6">Ações</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 bg-white">
            <tr v-for="item in items" :key="item.id">
              <td class="max-w-[10rem] truncate px-4 py-4 text-sm text-slate-900 sm:max-w-none sm:px-6" :title="lotDevelopmentLabel(item)">
                {{ lotDevelopmentLabel(item) }}
              </td>
              <td class="px-4 py-4 text-sm text-slate-900 sm:px-6">{{ lotZoneLabel(item) }}</td>
              <td class="px-4 py-4 text-sm font-medium text-slate-900 sm:px-6">{{ item.number }}</td>
              <td class="hidden px-4 py-4 text-sm text-slate-600 sm:table-cell sm:px-6">{{ formatNumber(item.area) }}</td>
              <td class="hidden px-4 py-4 text-sm text-slate-600 md:table-cell sm:px-6">{{ formatCurrency(item.total_value) }}</td>
              <td class="px-4 py-4 sm:px-6">
                <span :class="lotStatusClass(item.status)" class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium">
                  {{ lotStatusLabels[item.status] || item.status }}
                </span>
              </td>
              <td class="sticky right-0 z-10 border-l border-slate-200 bg-white px-4 py-4 text-right sm:px-6">
                <div class="flex items-center justify-end gap-1">
                  <button
                    v-if="authStore.can('lots.edit')"
                    type="button"
                    class="rounded p-1.5 text-sid-accent hover:bg-primary-50"
                    title="Editar"
                    @click="$router.push({ name: 'lots.edit', params: { id: item.id } })"
                  >
                    <PencilSquareIcon class="h-5 w-5" />
                  </button>
                  <button
                    v-if="authStore.can('lots.delete')"
                    type="button"
                    class="rounded p-1.5 text-red-600 hover:bg-red-50"
                    title="Excluir"
                    @click="confirmDelete(item)"
                  >
                    <TrashIcon class="h-5 w-5" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <PaginationBar
        v-if="!loading && pagination"
        :pagination="pagination"
        @page-change="(page) => loadItems(page)"
        @per-page-change="onPerPageChange"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import api from '@/services/api';
import { useAuthStore } from '@/stores/auth';
import { useAlert } from '@/composables/useAlert';
import { formatCurrency } from '@/utils/format';
import { lotStatusLabels, lotStatusOptions } from '@/utils/labels';
import { buildZoneTitleLabel } from '@/utils/zone';
import { lotStatusClass } from '@/utils/status';
import { buildLotDeleteConfirmMessage } from '@/utils/mapLots';
import Button from '@/components/Common/Button.vue';
import SelectInput from '@/components/Common/SelectInput.vue';
import PaginationBar from '@/components/Common/PaginationBar.vue';
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const toast = useToast();
const { confirm } = useAlert();

const items = ref([]);
const developments = ref([]);
const loading = ref(false);
const searchQuery = ref('');
const statusFilter = ref('available');
const developmentId = ref(route.query.development_id ? String(route.query.development_id) : '');
const perPage = ref(15);
const pagination = ref(null);
let searchTimeout = null;

const developmentOptions = computed(() =>
  developments.value.map((d) => ({ value: String(d.id), label: d.name }))
);

function formatNumber(value) {
  if (value == null || value === '') return '—';
  return Number(value).toLocaleString('pt-BR');
}

function lotDevelopmentLabel(lot) {
  return lot.development?.name ?? '—';
}

function lotZoneLabel(lot) {
  if (lot.zone?.name) {
    return buildZoneTitleLabel(lot.zone);
  }

  if (lot.block) {
    return lot.block;
  }

  return '—';
}

function goToCreate() {
  router.push({
    name: 'lots.create',
    query: developmentId.value ? { development_id: developmentId.value } : {},
  });
}

async function loadDevelopments() {
  try {
    const { data } = await api.get('/developments', { params: { all: 1 } });
    developments.value = data.data ?? data ?? [];
  } catch {
    developments.value = [];
  }
}

async function loadItems(page = 1) {
  loading.value = true;
  try {
    const params = { page, per_page: perPage.value };
    if (developmentId.value) params.development_id = developmentId.value;
    if (searchQuery.value) params.search = searchQuery.value;
    if (statusFilter.value) params.status = statusFilter.value;
    const { data } = await api.get('/lots', { params });
    items.value = data.data ?? [];
    pagination.value = data.meta ?? null;
  } catch {
    toast.error('Erro ao carregar lotes');
  } finally {
    loading.value = false;
  }
}

function debouncedSearch() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => loadItems(1), 400);
}

function onPerPageChange(value) {
  perPage.value = value;
  loadItems(1);
}

async function confirmDelete(item) {
  const ok = await confirm('Excluir lote', buildLotDeleteConfirmMessage(item));
  if (!ok) return;
  try {
    await api.post(`/lots/${item.id}/delete`);
    toast.success('Lote excluído.');
    loadItems(pagination.value?.current_page || 1);
  } catch (e) {
    toast.error(getApiErrorMessage(e, 'Não foi possível excluir.'));
  }
}

watch(developmentId, (id) => {
  const query = { ...route.query };
  if (id) {
    query.development_id = id;
  } else {
    delete query.development_id;
  }
  router.replace({ query });
});

onMounted(async () => {
  await loadDevelopments();
  loadItems();
});
</script>
