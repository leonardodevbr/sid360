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
      <div class="mb-4 space-y-3">
        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
          <div class="w-full sm:max-w-xs">
            <SelectInput
              v-model="developmentId"
              label="Empreendimento"
              :options="developmentOptions"
              placeholder="Todos"
              :searchable="true"
              @update:model-value="onDevelopmentFilterChange"
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
            >
          </div>
          <div class="w-full sm:w-40">
            <SelectInput
              v-model="statusFilter"
              label="Status"
              :options="lotStatusOptions"
              placeholder="Todos"
              :searchable="false"
              @update:model-value="onFiltersChange"
            />
          </div>
          <button
            type="button"
            class="flex items-center gap-1.5 self-end rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50"
            @click="showAdvancedFilters = !showAdvancedFilters"
          >
            <FunnelIcon class="h-4 w-4" />
            {{ showAdvancedFilters ? 'Menos filtros' : 'Mais filtros' }}
            <span
              v-if="activeAdvancedFilterCount > 0"
              class="rounded-full bg-emerald-100 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-800"
            >
              {{ activeAdvancedFilterCount }}
            </span>
          </button>
        </div>

        <div
          v-if="showAdvancedFilters"
          class="grid grid-cols-1 gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
          <SelectInput
            v-model="zoneFilter"
            label="Zona / quadra"
            :options="zoneOptions"
            placeholder="Todas"
            mode="multiple"
            :searchable="true"
            :disabled="!developmentId"
            @update:model-value="onFiltersChange"
          />
          <Input
            v-model="blockFilter"
            label="Bloco"
            placeholder="Ex: A"
            @update:model-value="debouncedAdvancedFilter"
          />
          <Input
            v-model="sizeLabelFilter"
            label="Medidas (frente × fundo)"
            placeholder="Ex: 10×25"
            @update:model-value="debouncedAdvancedFilter"
          />
          <div class="grid grid-cols-2 gap-2">
            <Input
              v-model="areaMin"
              label="Área mín. (m²)"
              type="number"
              min="0"
              step="0.01"
              placeholder="0"
              @update:model-value="debouncedAdvancedFilter"
            />
            <Input
              v-model="areaMax"
              label="Área máx. (m²)"
              type="number"
              min="0"
              step="0.01"
              placeholder="—"
              @update:model-value="debouncedAdvancedFilter"
            />
          </div>
          <CurrencyInput
            v-model="valueMin"
            label="Valor mínimo"
            @update:model-value="debouncedAdvancedFilter"
          />
          <CurrencyInput
            v-model="valueMax"
            label="Valor máximo"
            @update:model-value="debouncedAdvancedFilter"
          />
          <div class="flex items-end sm:col-span-2 lg:col-span-1">
            <button
              type="button"
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50"
              @click="clearAdvancedFilters"
            >
              Limpar filtros avançados
            </button>
          </div>
        </div>
        <p v-if="showAdvancedFilters && !developmentId" class="text-xs text-amber-700">
          Selecione um empreendimento para filtrar por zona.
        </p>
      </div>

      <div
        v-if="selectedCount > 0"
        class="mb-4 flex flex-col gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-3 sm:flex-row sm:items-center sm:justify-between"
      >
        <p class="text-sm font-medium text-slate-700">
          {{ selectedCount }} lote(s) selecionado(s)
        </p>
        <div class="flex flex-wrap items-center gap-2">
          <Button
            v-if="authStore.can('lots.edit')"
            type="button"
            variant="primary"
            :disabled="bulkProcessing"
            @click="openBulkEditModal"
          >
            Editar em massa
          </Button>
          <Button
            v-if="authStore.can('lots.edit')"
            type="button"
            variant="outline"
            :disabled="bulkProcessing"
            @click="confirmBulkInactivate"
          >
            Inativar
          </Button>
          <Button
            v-if="authStore.can('lots.delete')"
            type="button"
            variant="outline"
            class="!border-red-200 !text-red-600 hover:!bg-red-50"
            :disabled="bulkProcessing"
            @click="confirmBulkDelete"
          >
            Excluir
          </Button>
          <button
            type="button"
            class="rounded-lg px-2 py-1.5 text-xs font-medium text-slate-500 hover:bg-white hover:text-slate-700"
            :disabled="bulkProcessing"
            @click="clearSelection"
          >
            Limpar seleção
          </button>
        </div>
      </div>

      <div v-if="loading" class="py-10 text-center text-slate-500">Carregando...</div>
      <div v-else-if="!items.length" class="py-10 text-center text-slate-500">Nenhum lote encontrado</div>
      <div v-else class="-mx-4 overflow-x-auto sm:-mx-6">
        <table class="min-w-full divide-y divide-slate-200">
          <thead class="bg-slate-50">
            <tr>
              <th class="w-10 px-4 py-3 sm:px-6">
                <input
                  type="checkbox"
                  class="h-4 w-4 rounded border-slate-300 accent-emerald-700"
                  :checked="allPageSelected"
                  :indeterminate.prop="somePageSelected && !allPageSelected"
                  aria-label="Selecionar todos os lotes da página"
                  @change="toggleSelectAllPage"
                >
              </th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500 sm:px-6">Empreendimento</th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500 sm:px-6">Zona</th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500 sm:px-6">Número</th>
              <th class="hidden px-4 py-3 text-left text-xs font-medium uppercase text-slate-500 sm:table-cell sm:px-6">Área (m²)</th>
              <th class="hidden px-4 py-3 text-left text-xs font-medium uppercase text-slate-500 md:table-cell sm:px-6">Medidas</th>
              <th class="hidden px-4 py-3 text-left text-xs font-medium uppercase text-slate-500 md:table-cell sm:px-6">Valor</th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500 sm:px-6">Status</th>
              <th class="sticky right-0 z-10 border-l border-slate-200 bg-slate-50 px-4 py-3 text-right text-xs font-medium uppercase text-slate-500 sm:px-6">Ações</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 bg-white">
            <tr
              v-for="item in items"
              :key="item.id"
              :class="selectedIds.has(item.id) ? 'bg-emerald-50/40' : ''"
            >
              <td class="px-4 py-4 sm:px-6">
                <input
                  type="checkbox"
                  class="h-4 w-4 rounded border-slate-300 accent-emerald-700"
                  :checked="selectedIds.has(item.id)"
                  :aria-label="`Selecionar lote ${item.number}`"
                  @change="toggleSelection(item.id)"
                >
              </td>
              <td class="max-w-[10rem] truncate px-4 py-4 text-sm text-slate-900 sm:max-w-none sm:px-6" :title="lotDevelopmentLabel(item)">
                {{ lotDevelopmentLabel(item) }}
              </td>
              <td class="px-4 py-4 text-sm text-slate-900 sm:px-6">{{ lotZoneLabel(item) }}</td>
              <td class="px-4 py-4 text-sm font-medium text-slate-900 sm:px-6">{{ item.number }}</td>
              <td class="hidden px-4 py-4 text-sm text-slate-600 sm:table-cell sm:px-6">{{ formatNumber(item.area ?? item.area_computed) }}</td>
              <td class="hidden px-4 py-4 text-sm text-slate-600 md:table-cell sm:px-6">{{ formatLotDimensionsLabel(item) || '—' }}</td>
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

    <Modal
      :is-open="bulkEditOpen"
      title="Editar lotes em massa"
      @close="closeBulkEditModal"
    >
      <p class="mb-4 text-sm text-slate-600">
        {{ selectedCount }} lote(s) selecionado(s). Marque os campos que deseja alterar.
      </p>

      <div class="space-y-4">
        <div class="rounded-lg border border-slate-200 p-3">
          <label class="flex cursor-pointer items-start gap-2">
            <input v-model="bulkEditFields.zone.enabled" type="checkbox" class="mt-1 h-4 w-4 rounded border-slate-300 accent-emerald-700">
            <span class="min-w-0 flex-1">
              <span class="block text-sm font-medium text-slate-800">Zona / quadra</span>
              <span class="mt-0.5 block text-xs text-slate-500">Altera a alocação do lote no empreendimento</span>
            </span>
          </label>
          <div v-if="bulkEditFields.zone.enabled" class="mt-3">
            <SelectInput
              v-model="bulkEditFields.zone.value"
              :options="bulkZoneOptions"
              placeholder="Selecione a zona"
              :searchable="true"
              :disabled="!canBulkEditZone"
            />
            <p v-if="!canBulkEditZone" class="mt-1 text-xs text-amber-700">
              Filtre por empreendimento para escolher a zona de destino.
            </p>
          </div>
        </div>

        <div class="rounded-lg border border-slate-200 p-3">
          <label class="flex cursor-pointer items-start gap-2">
            <input v-model="bulkEditFields.block.enabled" type="checkbox" class="mt-1 h-4 w-4 rounded border-slate-300 accent-emerald-700">
            <span class="block text-sm font-medium text-slate-800">Bloco</span>
          </label>
          <div v-if="bulkEditFields.block.enabled" class="mt-3">
            <Input v-model="bulkEditFields.block.value" placeholder="Ex: A" />
          </div>
        </div>

        <div class="rounded-lg border border-slate-200 p-3">
          <label class="flex cursor-pointer items-start gap-2">
            <input v-model="bulkEditFields.area.enabled" type="checkbox" class="mt-1 h-4 w-4 rounded border-slate-300 accent-emerald-700">
            <span class="block text-sm font-medium text-slate-800">Área (m²)</span>
          </label>
          <div v-if="bulkEditFields.area.enabled" class="mt-3">
            <Input v-model="bulkEditFields.area.value" type="number" min="0" step="0.01" placeholder="Ex: 500" />
          </div>
        </div>

        <div class="rounded-lg border border-slate-200 p-3">
          <label class="flex cursor-pointer items-start gap-2">
            <input v-model="bulkEditFields.sizeLabel.enabled" type="checkbox" class="mt-1 h-4 w-4 rounded border-slate-300 accent-emerald-700">
            <span class="block text-sm font-medium text-slate-800">Medidas (frente × fundo)</span>
          </label>
          <div v-if="bulkEditFields.sizeLabel.enabled" class="mt-3">
            <Input v-model="bulkEditFields.sizeLabel.value" placeholder="Ex: 10×25" />
          </div>
        </div>

        <div class="rounded-lg border border-slate-200 p-3">
          <label class="flex cursor-pointer items-start gap-2">
            <input v-model="bulkEditFields.totalValue.enabled" type="checkbox" class="mt-1 h-4 w-4 rounded border-slate-300 accent-emerald-700">
            <span class="block text-sm font-medium text-slate-800">Valor total</span>
          </label>
          <div v-if="bulkEditFields.totalValue.enabled" class="mt-3">
            <CurrencyInput v-model="bulkEditFields.totalValue.value" />
          </div>
        </div>

        <div class="rounded-lg border border-slate-200 p-3">
          <label class="flex cursor-pointer items-start gap-2">
            <input v-model="bulkEditFields.status.enabled" type="checkbox" class="mt-1 h-4 w-4 rounded border-slate-300 accent-emerald-700">
            <span class="block text-sm font-medium text-slate-800">Status</span>
          </label>
          <div v-if="bulkEditFields.status.enabled" class="mt-3">
            <SelectInput
              v-model="bulkEditFields.status.value"
              :options="lotStatusFormOptions"
              :searchable="false"
            />
          </div>
        </div>
      </div>

      <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <Button type="button" variant="outline" :disabled="bulkProcessing" @click="closeBulkEditModal">
          Cancelar
        </Button>
        <Button type="button" variant="primary" :disabled="bulkProcessing || !hasBulkEditSelection" @click="submitBulkEdit">
          {{ bulkProcessing ? 'Salvando...' : 'Aplicar alterações' }}
        </Button>
      </div>
    </Modal>
  </div>
</template>

<script setup>
import { ref, computed, reactive, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import api from '@/services/api';
import { useAuthStore } from '@/stores/auth';
import { useAlert } from '@/composables/useAlert';
import { formatCurrency } from '@/utils/format';
import { lotStatusLabels, lotStatusOptions, lotStatusFormOptions } from '@/utils/labels';
import { buildZoneTitleLabel } from '@/utils/zone';
import { lotStatusClass } from '@/utils/status';
import { buildLotDeleteConfirmMessage, formatLotDimensionsLabel } from '@/utils/mapLots';
import { getApiErrorMessage } from '@/utils/apiError';
import Button from '@/components/Common/Button.vue';
import Input from '@/components/Common/Input.vue';
import SelectInput from '@/components/Common/SelectInput.vue';
import CurrencyInput from '@/components/Common/CurrencyInput.vue';
import Modal from '@/components/Common/Modal.vue';
import PaginationBar from '@/components/Common/PaginationBar.vue';
import { FunnelIcon, PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const toast = useToast();
const { confirm } = useAlert();

const items = ref([]);
const developments = ref([]);
const zones = ref([]);
const loading = ref(false);
const bulkProcessing = ref(false);
const bulkEditOpen = ref(false);
const showAdvancedFilters = ref(false);
const searchQuery = ref('');
const statusFilter = ref('available');
const developmentId = ref(route.query.development_id ? String(route.query.development_id) : '');
const zoneFilter = ref([]);
const blockFilter = ref('');
const sizeLabelFilter = ref('');
const areaMin = ref('');
const areaMax = ref('');
const valueMin = ref(0);
const valueMax = ref(0);
const perPage = ref(15);
const pagination = ref(null);
const selectedIds = ref(new Set());
let searchTimeout = null;
let advancedFilterTimeout = null;

const bulkEditFields = reactive({
  zone: { enabled: false, value: '' },
  block: { enabled: false, value: '' },
  area: { enabled: false, value: '' },
  sizeLabel: { enabled: false, value: '' },
  totalValue: { enabled: false, value: 0 },
  status: { enabled: false, value: 'available' },
});

const developmentOptions = computed(() =>
  developments.value.map((d) => ({ value: String(d.id), label: d.name }))
);

const zoneOptions = computed(() =>
  zones.value.map((zone) => ({
    value: String(zone.id),
    label: buildZoneTitleLabel(zone),
  }))
);

const bulkZoneOptions = computed(() => zoneOptions.value);

const canBulkEditZone = computed(() => Boolean(developmentId.value) && zoneOptions.value.length > 0);

const selectedCount = computed(() => selectedIds.value.size);

const allPageSelected = computed(() =>
  items.value.length > 0 && items.value.every((item) => selectedIds.value.has(item.id))
);

const somePageSelected = computed(() =>
  items.value.some((item) => selectedIds.value.has(item.id))
);

const activeAdvancedFilterCount = computed(() => {
  let count = 0;
  if (zoneFilter.value.length) count += 1;
  if (blockFilter.value.trim()) count += 1;
  if (sizeLabelFilter.value.trim()) count += 1;
  if (areaMin.value !== '' && areaMin.value != null) count += 1;
  if (areaMax.value !== '' && areaMax.value != null) count += 1;
  if (valueMin.value > 0) count += 1;
  if (valueMax.value > 0) count += 1;
  return count;
});

const hasBulkEditSelection = computed(() =>
  Object.values(bulkEditFields).some((field) => field.enabled)
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

function buildListParams(page = 1) {
  const params = { page, per_page: perPage.value };

  if (developmentId.value) params.development_id = developmentId.value;
  if (searchQuery.value.trim()) params.search = searchQuery.value.trim();
  if (statusFilter.value) params.status = statusFilter.value;
  if (zoneFilter.value.length) params.zone_ids = zoneFilter.value;
  if (blockFilter.value.trim()) params.block = blockFilter.value.trim();
  if (sizeLabelFilter.value.trim()) params.size_label = sizeLabelFilter.value.trim();
  if (areaMin.value !== '' && areaMin.value != null) params.area_min = areaMin.value;
  if (areaMax.value !== '' && areaMax.value != null) params.area_max = areaMax.value;
  if (valueMin.value > 0) params.value_min = valueMin.value;
  if (valueMax.value > 0) params.value_max = valueMax.value;

  return params;
}

function toggleSelection(id) {
  const next = new Set(selectedIds.value);

  if (next.has(id)) {
    next.delete(id);
  } else {
    next.add(id);
  }

  selectedIds.value = next;
}

function toggleSelectAllPage(event) {
  const next = new Set(selectedIds.value);

  if (event.target.checked) {
    items.value.forEach((item) => next.add(item.id));
  } else {
    items.value.forEach((item) => next.delete(item.id));
  }

  selectedIds.value = next;
}

function clearSelection() {
  selectedIds.value = new Set();
}

function resetBulkEditFields() {
  bulkEditFields.zone.enabled = false;
  bulkEditFields.zone.value = '';
  bulkEditFields.block.enabled = false;
  bulkEditFields.block.value = '';
  bulkEditFields.area.enabled = false;
  bulkEditFields.area.value = '';
  bulkEditFields.sizeLabel.enabled = false;
  bulkEditFields.sizeLabel.value = '';
  bulkEditFields.totalValue.enabled = false;
  bulkEditFields.totalValue.value = 0;
  bulkEditFields.status.enabled = false;
  bulkEditFields.status.value = 'available';
}

function openBulkEditModal() {
  if (selectedCount.value === 0) return;
  resetBulkEditFields();
  bulkEditOpen.value = true;
}

function closeBulkEditModal() {
  if (bulkProcessing.value) return;
  bulkEditOpen.value = false;
}

function buildBulkEditPayload() {
  const payload = { ids: [...selectedIds.value] };

  if (bulkEditFields.zone.enabled) {
    payload.zone_id = bulkEditFields.zone.value ? Number(bulkEditFields.zone.value) : null;
  }

  if (bulkEditFields.block.enabled) {
    payload.block = bulkEditFields.block.value?.trim() || null;
  }

  if (bulkEditFields.area.enabled) {
    const area = bulkEditFields.area.value;
    payload.area = area === '' || area == null ? null : Number(area);
  }

  if (bulkEditFields.sizeLabel.enabled) {
    payload.size_label = bulkEditFields.sizeLabel.value?.trim() || null;
  }

  if (bulkEditFields.totalValue.enabled) {
    payload.total_value = bulkEditFields.totalValue.value ?? 0;
  }

  if (bulkEditFields.status.enabled) {
    payload.status = bulkEditFields.status.value;
  }

  return payload;
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

async function loadZones(developmentIdValue = developmentId.value) {
  if (!developmentIdValue) {
    zones.value = [];
    return;
  }

  try {
    const { data } = await api.get(`/developments/${developmentIdValue}/zones`);
    zones.value = data.data ?? data ?? [];
  } catch {
    zones.value = [];
  }
}

async function loadItems(page = 1) {
  loading.value = true;
  try {
    const { data } = await api.get('/lots', { params: buildListParams(page) });
    items.value = data.data ?? [];
    pagination.value = data.meta ?? null;
  } catch {
    toast.error('Erro ao carregar lotes');
  } finally {
    loading.value = false;
  }
}

function onFiltersChange() {
  clearSelection();
  loadItems(1);
}

function onDevelopmentFilterChange() {
  zoneFilter.value = [];
  clearSelection();
  loadZones();
  loadItems(1);
}

function debouncedSearch() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    clearSelection();
    loadItems(1);
  }, 400);
}

function debouncedAdvancedFilter() {
  clearTimeout(advancedFilterTimeout);
  advancedFilterTimeout = setTimeout(() => {
    clearSelection();
    loadItems(1);
  }, 400);
}

function clearAdvancedFilters() {
  zoneFilter.value = [];
  blockFilter.value = '';
  sizeLabelFilter.value = '';
  areaMin.value = '';
  areaMax.value = '';
  valueMin.value = 0;
  valueMax.value = 0;
  onFiltersChange();
}

function onPerPageChange(value) {
  perPage.value = value;
  clearSelection();
  loadItems(1);
}

function reportBulkSkipped(skipped, actionLabel) {
  if (!skipped?.length) {
    return;
  }

  const preview = skipped
    .slice(0, 3)
    .map((item) => `Lote ${item.number ?? item.id}`)
    .join(', ');

  const suffix = skipped.length > 3 ? ` e mais ${skipped.length - 3}` : '';
  toast.warning(`${skipped.length} lote(s) não ${actionLabel}: ${preview}${suffix}.`);
}

async function confirmDelete(item) {
  const ok = await confirm('Excluir lote', buildLotDeleteConfirmMessage(item));
  if (!ok) return;
  try {
    await api.post(`/lots/${item.id}/delete`);
    toast.success('Lote excluído.');
    selectedIds.value.delete(item.id);
    selectedIds.value = new Set(selectedIds.value);
    loadItems(pagination.value?.current_page || 1);
  } catch (e) {
    toast.error(getApiErrorMessage(e, 'Não foi possível excluir.'));
  }
}

async function confirmBulkDelete() {
  const count = selectedCount.value;
  if (count === 0) return;

  const ok = await confirm(
    'Excluir lotes',
    `Excluir ${count} lote(s) selecionado(s)? Esta ação não pode ser desfeita.`,
  );

  if (!ok) return;

  bulkProcessing.value = true;
  try {
    const { data } = await api.post('/lots/bulk-delete', {
      ids: [...selectedIds.value],
    });

    if (data.deleted > 0) {
      toast.success(data.message || `${data.deleted} lote(s) excluído(s).`);
    } else {
      toast.warning(data.message || 'Nenhum lote foi excluído.');
    }

    reportBulkSkipped(data.skipped, 'foram excluídos');
    clearSelection();
    await loadItems(pagination.value?.current_page || 1);
  } catch (e) {
    toast.error(getApiErrorMessage(e, 'Não foi possível excluir os lotes.'));
  } finally {
    bulkProcessing.value = false;
  }
}

async function confirmBulkInactivate() {
  const count = selectedCount.value;
  if (count === 0) return;

  const ok = await confirm(
    'Inativar lotes',
    `Inativar ${count} lote(s) selecionado(s)? Eles deixarão de aparecer como disponíveis.`,
  );

  if (!ok) return;

  bulkProcessing.value = true;
  try {
    const { data } = await api.post('/lots/bulk-update-status', {
      ids: [...selectedIds.value],
      status: 'inactive',
    });

    if (data.updated > 0) {
      toast.success(data.message || `${data.updated} lote(s) inativado(s).`);
    } else {
      toast.warning(data.message || 'Nenhum lote foi inativado.');
    }

    reportBulkSkipped(data.skipped, 'foram inativados');
    clearSelection();
    await loadItems(pagination.value?.current_page || 1);
  } catch (e) {
    toast.error(getApiErrorMessage(e, 'Não foi possível inativar os lotes.'));
  } finally {
    bulkProcessing.value = false;
  }
}

async function submitBulkEdit() {
  if (!hasBulkEditSelection.value || selectedCount.value === 0) {
    toast.warning('Marque ao menos um campo para alterar.');
    return;
  }

  if (bulkEditFields.zone.enabled && !canBulkEditZone.value) {
    toast.warning('Selecione um empreendimento nos filtros para alterar a zona.');
    return;
  }

  const payload = buildBulkEditPayload();

  bulkProcessing.value = true;
  try {
    const { data } = await api.post('/lots/bulk-update', payload);

    if (data.updated > 0) {
      toast.success(data.message || `${data.updated} lote(s) atualizado(s).`);
    } else {
      toast.warning(data.message || 'Nenhum lote foi atualizado.');
    }

    reportBulkSkipped(data.skipped, 'foram atualizados');
    closeBulkEditModal();
    clearSelection();
    await loadItems(pagination.value?.current_page || 1);
  } catch (e) {
    toast.error(getApiErrorMessage(e, 'Não foi possível atualizar os lotes.'));
  } finally {
    bulkProcessing.value = false;
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
  if (developmentId.value) {
    await loadZones();
  }
  loadItems();
});
</script>
