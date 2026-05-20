<template>
  <div class="space-y-6">
    <div class="flex items-center gap-4">
      <button type="button" class="rounded-lg p-2 hover:bg-slate-100" @click="goBack">
        <ArrowLeftIcon class="h-5 w-5 text-slate-600" />
      </button>
      <div>
        <h2 class="text-lg font-semibold text-slate-800">{{ isEdit ? 'Editar lote' : 'Novo lote' }}</h2>
        <p class="text-xs text-slate-500">{{ isEdit ? 'Atualize os dados do lote' : 'Cadastre um novo lote' }}</p>
      </div>
    </div>

    <form v-if="!loading" class="card space-y-4 p-4 sm:p-6" @submit.prevent="submit">
      <SelectInput
        v-model="form.development_id"
        label="Empreendimento"
        :options="developmentOptions"
        placeholder="Selecione o empreendimento"
        :searchable="true"
        required
      />
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Input v-model="form.block" label="Quadra" placeholder="Ex: A" />
        <Input v-model="form.number" label="Número" required placeholder="Ex: 01" />
      </div>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Input v-model="form.area" label="Área (m²)" type="number" step="0.01" min="0" />
        <Input v-model="form.total_value" label="Valor total (R$)" type="number" step="0.01" min="0" />
      </div>
      <SelectInput v-model="form.status" label="Status" :options="lotStatusFormOptions" :searchable="false" />
      <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end">
        <Button type="button" variant="outline" @click="goBack">Cancelar</Button>
        <Button type="submit" variant="primary" :disabled="saving">{{ saving ? 'Salvando...' : 'Salvar' }}</Button>
      </div>
    </form>
    <div v-else class="card p-12 text-center text-slate-500">Carregando...</div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import api from '@/services/api';
import { lotStatusFormOptions } from '@/utils/labels';
import Input from '@/components/Common/Input.vue';
import SelectInput from '@/components/Common/SelectInput.vue';
import Button from '@/components/Common/Button.vue';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

const route = useRoute();
const router = useRouter();
const toast = useToast();

const loading = ref(false);
const saving = ref(false);
const developments = ref([]);
const form = ref({
  development_id: route.query.development_id ? String(route.query.development_id) : '',
  number: '',
  block: '',
  area: '',
  total_value: '',
  status: 'available',
});

const isEdit = computed(() => Boolean(route.params.id));

const developmentOptions = computed(() =>
  developments.value.map((d) => ({ value: String(d.id), label: d.name }))
);

function goBack() {
  const query = form.value.development_id ? { development_id: form.value.development_id } : {};
  router.push({ name: 'lots.index', query });
}

async function loadDevelopments() {
  try {
    const { data } = await api.get('/developments', { params: { all: 1 } });
    developments.value = data.data ?? data ?? [];
  } catch {
    developments.value = [];
  }
}

async function loadItem() {
  if (!isEdit.value) return;
  loading.value = true;
  try {
    const { data } = await api.get(`/lots/${route.params.id}`);
    const item = data.data ?? data;
    form.value = {
      development_id: String(item.development_id ?? ''),
      number: item.number ?? '',
      block: item.block ?? '',
      area: item.area ?? '',
      total_value: item.total_value ?? '',
      status: item.status ?? 'available',
    };
  } catch {
    toast.error('Erro ao carregar lote');
    goBack();
  } finally {
    loading.value = false;
  }
}

async function submit() {
  saving.value = true;
  const payload = {
    ...form.value,
    development_id: Number(form.value.development_id),
    area: form.value.area === '' ? null : Number(form.value.area),
    total_value: form.value.total_value === '' ? null : Number(form.value.total_value),
  };
  try {
    if (isEdit.value) {
      await api.put(`/lots/${route.params.id}`, payload);
      toast.success('Lote atualizado.');
    } else {
      await api.post('/lots', payload);
      toast.success('Lote criado.');
    }
    goBack();
  } catch (e) {
    toast.error(e.response?.data?.message || 'Erro ao salvar lote');
  } finally {
    saving.value = false;
  }
}

onMounted(async () => {
  await loadDevelopments();
  await loadItem();
});
</script>
