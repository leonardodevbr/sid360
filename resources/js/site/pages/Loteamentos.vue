<script setup>
import { ref, computed, onMounted } from 'vue';
import publicApi from '@/services/publicApi';
import SelectInput from '@/components/Common/SelectInput.vue';
import LotCard from '@/site/components/LotCard.vue';
import LotModal from '@/site/components/LotModal.vue';

const lots = ref([]);
const developments = ref([]);
const meta = ref(null);
const loading = ref(false);
const loadingLot = ref(false);
const selectedLot = ref(null);

const filters = ref({
  development_id: '',
  area_min: '',
  value_max: '',
});

const developmentOptions = computed(() => [
  { value: '', label: 'Todos os loteamentos' },
  ...developments.value.map((development) => ({
    value: String(development.id),
    label: development.name,
  })),
]);

const areaMinOptions = [
  { value: '', label: 'Área mínima' },
  { value: '100', label: '100m²+' },
  { value: '200', label: '200m²+' },
  { value: '500', label: '500m²+' },
  { value: '1000', label: '1000m²+' },
];

const valueMaxOptions = [
  { value: '', label: 'Qualquer valor' },
  { value: '50000', label: 'Até R$ 50.000' },
  { value: '100000', label: 'Até R$ 100.000' },
  { value: '200000', label: 'Até R$ 200.000' },
  { value: '500000', label: 'Até R$ 500.000' },
];

async function load(page = 1) {
  loading.value = true;

  try {
    const params = { page };
    Object.entries(filters.value).forEach(([key, value]) => {
      if (value !== '') {
        params[key] = value;
      }
    });

    const { data } = await publicApi.get('/public/lots/available', { params });
    lots.value = data.data ?? [];
    meta.value = data.meta ?? null;
  } finally {
    loading.value = false;
  }
}

async function loadDevelopments() {
  const { data } = await publicApi.get('/public/developments');
  developments.value = data ?? [];
}

async function openLot(lot) {
  loadingLot.value = true;

  try {
    const developmentId = lot.development?.id;
    if (developmentId) {
      const { data } = await publicApi.get(`/public/developments/${developmentId}/lots/${lot.id}`);
      selectedLot.value = data;
    } else {
      selectedLot.value = lot;
    }
  } catch {
    selectedLot.value = lot;
  } finally {
    loadingLot.value = false;
  }
}

onMounted(() => {
  loadDevelopments();
  load();
});
</script>

<template>
  <div>
    <section class="bg-slate-900 py-16 text-center text-white">
      <h1 class="mb-2 text-3xl font-bold">
        Loteamentos Disponíveis
      </h1>
      <p class="text-slate-400">
        Encontre o lote ideal para sua família ou negócio
      </p>
    </section>

    <section class="sticky top-0 z-20 border-b border-slate-200 bg-white shadow-sm">
      <div class="mx-auto flex max-w-6xl flex-wrap items-end gap-3 px-4 py-3">
        <div class="min-w-[180px] flex-1 sm:max-w-xs">
          <SelectInput
            v-model="filters.development_id"
            label="Loteamento"
            :options="developmentOptions"
            placeholder="Todos os loteamentos"
            :searchable="true"
            :can-clear="true"
            @update:model-value="load(1)"
          />
        </div>

        <div class="min-w-[140px] flex-1 sm:max-w-[160px]">
          <SelectInput
            v-model="filters.area_min"
            label="Área"
            :options="areaMinOptions"
            :searchable="false"
            :can-clear="true"
            @update:model-value="load(1)"
          />
        </div>

        <div class="min-w-[140px] flex-1 sm:max-w-[180px]">
          <SelectInput
            v-model="filters.value_max"
            label="Valor máximo"
            :options="valueMaxOptions"
            :searchable="false"
            :can-clear="true"
            @update:model-value="load(1)"
          />
        </div>

        <span class="pb-2 text-xs text-slate-400 sm:ml-auto">
          {{ meta?.total ?? 0 }} lotes encontrados
        </span>
      </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 py-8">
      <div
        v-if="loading"
        class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3"
      >
        <div
          v-for="index in 6"
          :key="index"
          class="h-72 animate-pulse rounded-xl bg-slate-100"
        />
      </div>

      <div
        v-else-if="lots.length"
        class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3"
      >
        <LotCard
          v-for="lot in lots"
          :key="lot.id"
          :lot="lot"
          @click="openLot"
        />
      </div>

      <div
        v-else
        class="py-20 text-center text-slate-400"
      >
        Nenhum lote disponível com esses filtros.
      </div>

      <div
        v-if="meta && meta.last_page > 1"
        class="mt-8 flex flex-wrap justify-center gap-2"
      >
        <button
          v-for="page in meta.last_page"
          :key="page"
          type="button"
          class="rounded-lg px-3 py-1.5 text-sm font-medium"
          :class="page === meta.current_page
            ? 'bg-emerald-600 text-white'
            : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
          @click="load(page)"
        >
          {{ page }}
        </button>
      </div>
    </section>

    <LotModal
      v-if="selectedLot"
      :lot="selectedLot"
      @close="selectedLot = null"
    />

    <div
      v-if="loadingLot"
      class="fixed inset-0 z-40 flex items-center justify-center bg-black/30"
    >
      <div class="rounded-lg bg-white px-4 py-3 text-sm text-slate-600">
        Carregando detalhes...
      </div>
    </div>
  </div>
</template>
