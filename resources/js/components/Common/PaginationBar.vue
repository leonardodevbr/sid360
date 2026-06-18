<template>
  <div
    v-if="pagination"
    class="mt-4 flex flex-col gap-3 border-t border-slate-200 pt-4 sm:flex-row sm:items-center sm:justify-between"
  >
    <p class="text-sm text-slate-500">
      <template v-if="totalCount > 0">
        Mostrando {{ pagination.from }} a {{ pagination.to }} de {{ totalCount }} resultado{{ totalCount === 1 ? '' : 's' }}
      </template>
      <template v-else>
        Nenhum resultado
      </template>
    </p>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-4">
      <label
        v-if="showPerPageSelector"
        class="flex items-center gap-2 text-sm text-slate-600"
      >
        <span class="shrink-0 whitespace-nowrap">Itens por página:</span>
        <SelectInput
          :model-value="String(currentPerPage)"
          :options="perPageSelectOptions"
          :searchable="false"
          :can-clear="false"
          compact
          class="w-20 shrink-0"
          @update:model-value="onPerPageChange"
        />
      </label>

      <div class="flex items-center gap-2">
        <button
          type="button"
          class="rounded border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="currentPage <= 1"
          @click="goToPage(currentPage - 1)"
        >
          Anterior
        </button>
        <span class="whitespace-nowrap px-2 text-sm text-slate-500">
          Página {{ currentPage }} de {{ lastPage || 1 }}
        </span>
        <button
          type="button"
          class="rounded border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="currentPage >= lastPage"
          @click="goToPage(currentPage + 1)"
        >
          Próxima
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import SelectInput from '@/components/Common/SelectInput.vue';

const props = defineProps({
  pagination: {
    type: Object,
    default: null,
  },
  perPageOptions: {
    type: Array,
    default: () => [10, 15, 30, 50, 100],
  },
  showPerPageSelector: {
    type: Boolean,
    default: true,
  },
});

const emit = defineEmits(['page-change', 'per-page-change']);

const totalCount = computed(() => props.pagination?.total ?? 0);
const currentPage = computed(() => props.pagination?.current_page ?? 1);
const lastPage = computed(() => props.pagination?.last_page ?? 1);
const currentPerPage = computed(() => Number(props.pagination?.per_page ?? 15));

const perPageSelectOptions = computed(() => {
  const values = new Set(
    [...props.perPageOptions, currentPerPage.value]
      .map((value) => Number(value))
      .filter((value) => Number.isFinite(value) && value > 0),
  );

  return [...values]
    .sort((a, b) => a - b)
    .map((value) => ({ value: String(value), label: String(value) }));
});

function goToPage(page) {
  if (page >= 1 && page <= lastPage.value) {
    emit('page-change', page);
  }
}

function onPerPageChange(value) {
  const n = Number(value);
  if (n > 0) {
    emit('per-page-change', n);
  }
}
</script>
