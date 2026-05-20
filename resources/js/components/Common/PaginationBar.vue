<template>
  <div v-if="pagination" class="mt-4 flex flex-col sm:flex-row items-center justify-between gap-4">
    <div class="flex flex-wrap items-center gap-3">
      <span class="text-sm text-slate-500">
        <template v-if="totalCount > 0">
          Mostrando {{ pagination.from }} a {{ pagination.to }} de {{ totalCount }} resultado{{ totalCount === 1 ? '' : 's' }}
        </template>
        <template v-else>
          Nenhum resultado
        </template>
      </span>
      <label class="flex items-center gap-2 text-sm text-slate-600">
        <span>Itens por página:</span>
        <select
          :value="currentPerPage"
          class="rounded border border-slate-300 px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          @change="onPerPageChange"
        >
          <option v-for="n in perPageOptions" :key="n" :value="n">{{ n }}</option>
        </select>
      </label>
    </div>
    <div class="flex items-center gap-2">
      <button
        type="button"
        class="px-3 py-1.5 border rounded text-sm font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed border-slate-300 hover:bg-slate-50 text-slate-700"
        :disabled="currentPage <= 1"
        @click="goToPage(currentPage - 1)"
      >
        Anterior
      </button>
      <span class="text-sm text-slate-500 px-2">
        Página {{ currentPage }} de {{ lastPage || 1 }}
      </span>
      <button
        type="button"
        class="px-3 py-1.5 border rounded text-sm font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed border-slate-300 hover:bg-slate-50 text-slate-700"
        :disabled="currentPage >= lastPage"
        @click="goToPage(currentPage + 1)"
      >
        Próxima
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  pagination: {
    type: Object,
    default: null,
  },
  perPageOptions: {
    type: Array,
    default: () => [15, 30, 50],
  },
})

const emit = defineEmits(['page-change', 'per-page-change'])

const totalCount = computed(() => props.pagination?.total ?? 0)
const currentPage = computed(() => props.pagination?.current_page ?? 1)
const lastPage = computed(() => props.pagination?.last_page ?? 1)
const currentPerPage = computed(() => props.pagination?.per_page ?? 15)

function goToPage(page) {
  if (page >= 1 && page <= lastPage.value) {
    emit('page-change', page)
  }
}

function onPerPageChange(event) {
  const value = Number(event.target.value)
  if (value > 0) {
    emit('per-page-change', value)
  }
}
</script>
