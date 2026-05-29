<template>
  <div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 class="text-lg font-semibold text-slate-800">Empreendimentos</h2>
        <p class="text-xs text-slate-500">Gerencie os empreendimentos e seus lotes</p>
      </div>
      <Button
        v-if="authStore.can('developments.create')"
        type="button"
        variant="primary"
        @click="$router.push({ name: 'developments.create' })"
      >
        Novo empreendimento
      </Button>
    </div>

    <div class="card p-4 sm:p-6">
      <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end">
        <div class="min-w-0 flex-1 sm:max-w-md">
          <label class="mb-1 block text-sm font-medium text-slate-700">Buscar</label>
          <input
            v-model="searchQuery"
            type="search"
            placeholder="Nome ou localização..."
            class="w-full rounded border border-slate-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-sid-accent"
            @input="debouncedSearch"
          />
        </div>
        <div class="w-full sm:w-40">
          <SelectInput
            v-model="statusFilter"
            label="Status"
            :options="developmentStatusOptions"
            placeholder="Todos"
            :searchable="false"
            @update:model-value="loadItems(1)"
          />
        </div>
      </div>

      <div v-if="loading" class="py-10 text-center text-slate-500">Carregando...</div>
      <div v-else-if="!items.length" class="py-10 text-center text-slate-500">Nenhum empreendimento encontrado</div>
      <div v-else class="-mx-4 overflow-x-auto sm:-mx-6">
        <table class="min-w-full divide-y divide-slate-200">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500 sm:px-6">Nome</th>
              <th class="hidden px-4 py-3 text-left text-xs font-medium uppercase text-slate-500 sm:table-cell sm:px-6">Localização</th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500 sm:px-6">Status</th>
              <th class="hidden px-4 py-3 text-left text-xs font-medium uppercase text-slate-500 md:table-cell sm:px-6">Lotes</th>
              <th class="sticky right-0 z-10 border-l border-slate-200 bg-slate-50 px-4 py-3 text-right text-xs font-medium uppercase text-slate-500 sm:px-6">Ações</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 bg-white">
            <tr v-for="item in items" :key="item.id">
              <td class="px-4 py-4 text-sm font-medium text-slate-900 sm:px-6">{{ item.name }}</td>
              <td class="hidden px-4 py-4 text-sm text-slate-600 sm:table-cell sm:px-6">{{ item.location || '—' }}</td>
              <td class="px-4 py-4 sm:px-6">
                <span :class="developmentStatusClass(item.status)" class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium">
                  {{ developmentStatusLabels[item.status] || item.status }}
                </span>
              </td>
              <td class="hidden px-4 py-4 text-sm text-slate-600 md:table-cell sm:px-6">{{ item.lots_count ?? 0 }}</td>
              <td class="sticky right-0 z-10 border-l border-slate-200 bg-white px-4 py-4 text-right sm:px-6">
                <div class="flex items-center justify-end gap-1">
                  <button
                    v-if="authStore.can('lots.view')"
                    type="button"
                    class="rounded p-1.5 text-slate-600 hover:bg-slate-50"
                    title="Ver lotes"
                    @click="$router.push({ name: 'lots.index', query: { development_id: item.id } })"
                  >
                    <Squares2X2Icon class="h-5 w-5" />
                  </button>
                  <button
                    v-if="authStore.can('developments.edit')"
                    type="button"
                    class="rounded p-1.5 text-sid-accent hover:bg-primary-50"
                    title="Editar"
                    @click="$router.push({ name: 'developments.edit', params: { id: item.id } })"
                  >
                    <PencilSquareIcon class="h-5 w-5" />
                  </button>
                  <button
                    v-if="authStore.can('developments.delete')"
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
import { ref, onMounted } from 'vue';
import { useToast } from 'vue-toastification';
import api from '@/services/api';
import { useAuthStore } from '@/stores/auth';
import { useAlert } from '@/composables/useAlert';
import { developmentStatusLabels, developmentStatusOptions } from '@/utils/labels';
import { developmentStatusClass } from '@/utils/status';
import { getApiErrorMessage } from '@/utils/apiError';
import Button from '@/components/Common/Button.vue';
import SelectInput from '@/components/Common/SelectInput.vue';
import PaginationBar from '@/components/Common/PaginationBar.vue';
import { PencilSquareIcon, TrashIcon, Squares2X2Icon } from '@heroicons/vue/24/outline';

const authStore = useAuthStore();
const toast = useToast();
const { confirm } = useAlert();

const items = ref([]);
const loading = ref(false);
const searchQuery = ref('');
const statusFilter = ref('');
const perPage = ref(15);
const pagination = ref(null);
let searchTimeout = null;

async function loadItems(page = 1) {
  loading.value = true;
  try {
    const params = { page, per_page: perPage.value };
    if (searchQuery.value) params.search = searchQuery.value;
    if (statusFilter.value) params.status = statusFilter.value;
    const { data } = await api.get('/developments', { params });
    items.value = data.data ?? [];
    pagination.value = data.meta ?? null;
  } catch {
    toast.error('Erro ao carregar empreendimentos');
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
  const ok = await confirm('Excluir empreendimento', `Excluir "${item.name}"?`);
  if (!ok) return;
  try {
    await api.post(`/developments/${item.id}/delete`);
    toast.success('Empreendimento excluído.');
    loadItems(pagination.value?.current_page || 1);
  } catch (e) {
    toast.error(getApiErrorMessage(e, 'Não foi possível excluir.'));
  }
}

onMounted(() => loadItems());
</script>
