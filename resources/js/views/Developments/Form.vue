<template>
  <div class="space-y-6">
    <div class="flex items-center gap-4">
      <button type="button" class="rounded-lg p-2 hover:bg-slate-100" @click="$router.push({ name: 'developments.index' })">
        <ArrowLeftIcon class="h-5 w-5 text-slate-600" />
      </button>
      <div>
        <h2 class="text-lg font-semibold text-slate-800">{{ isEdit ? 'Editar empreendimento' : 'Novo empreendimento' }}</h2>
        <p class="text-xs text-slate-500">{{ isEdit ? 'Atualize os dados do empreendimento' : 'Cadastre um novo empreendimento' }}</p>
      </div>
    </div>

    <form v-if="!loading" class="card space-y-4 p-4 sm:p-6" @submit.prevent="submit">
      <Input v-model="form.name" label="Nome" required placeholder="Nome do empreendimento" />
      <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Descrição</label>
        <textarea
          v-model="form.description"
          rows="4"
          class="w-full rounded border border-slate-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-sid-accent"
          placeholder="Descrição do empreendimento"
        />
      </div>
      <Input v-model="form.location" label="Localização" placeholder="Endereço ou referência" />
      <SelectInput v-model="form.status" label="Status" :options="developmentStatusFormOptions" :searchable="false" />
      <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end">
        <Button type="button" variant="outline" @click="$router.push({ name: 'developments.index' })">Cancelar</Button>
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
import { developmentStatusFormOptions } from '@/utils/labels';
import Input from '@/components/Common/Input.vue';
import SelectInput from '@/components/Common/SelectInput.vue';
import Button from '@/components/Common/Button.vue';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

const route = useRoute();
const router = useRouter();
const toast = useToast();

const loading = ref(false);
const saving = ref(false);
const form = ref({
  name: '',
  description: '',
  location: '',
  status: 'active',
});

const isEdit = computed(() => Boolean(route.params.id));

async function loadItem() {
  if (!isEdit.value) return;
  loading.value = true;
  try {
    const { data } = await api.get(`/developments/${route.params.id}`);
    const item = data.data ?? data;
    form.value = {
      name: item.name ?? '',
      description: item.description ?? '',
      location: item.location ?? '',
      status: item.status ?? 'active',
    };
  } catch {
    toast.error('Erro ao carregar empreendimento');
    router.push({ name: 'developments.index' });
  } finally {
    loading.value = false;
  }
}

async function submit() {
  saving.value = true;
  try {
    if (isEdit.value) {
      await api.put(`/developments/${route.params.id}`, form.value);
      toast.success('Empreendimento atualizado.');
    } else {
      await api.post('/developments', form.value);
      toast.success('Empreendimento criado.');
    }
    router.push({ name: 'developments.index' });
  } catch (e) {
    toast.error(e.response?.data?.message || 'Erro ao salvar empreendimento');
  } finally {
    saving.value = false;
  }
}

onMounted(loadItem);
</script>
