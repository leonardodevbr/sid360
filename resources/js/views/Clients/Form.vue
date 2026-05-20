<template>
  <div class="space-y-6">
    <div class="flex items-center gap-4">
      <button type="button" class="rounded-lg p-2 hover:bg-slate-100" @click="$router.push({ name: 'clients.index' })">
        <ArrowLeftIcon class="h-5 w-5 text-slate-600" />
      </button>
      <div>
        <h2 class="text-lg font-semibold text-slate-800">{{ isEdit ? 'Editar cliente' : 'Novo cliente' }}</h2>
        <p class="text-xs text-slate-500">{{ isEdit ? 'Atualize os dados do cliente' : 'Cadastre um novo cliente' }}</p>
      </div>
    </div>

    <form v-if="!loading" class="card space-y-4 p-4 sm:p-6" @submit.prevent="submit">
      <Input v-model="form.name" label="Nome completo" placeholder="Nome do cliente" />
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Input v-model="form.cpf" label="CPF" placeholder="000.000.000-00" />
        <Input v-model="form.rg" label="RG" placeholder="0000000" />
      </div>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Input v-model="form.rg_issuer" label="Órgão emissor" placeholder="SSP/BA" />
        <Input v-model="form.phone" label="Telefone" placeholder="(74) 9 0000-0000" />
      </div>
      <Input v-model="form.email" label="E-mail" type="email" placeholder="email@exemplo.com" />
      <Input v-model="form.address" label="Endereço" placeholder="Rua, número, bairro" />
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Input v-model="form.city" label="Cidade" placeholder="Cafarnaum" />
        <Input v-model="form.state" label="Estado" placeholder="BA" />
      </div>
      <div>
        <label class="mb-1 block text-xs font-medium text-slate-600">Observações</label>
        <textarea
          v-model="form.notes"
          rows="3"
          class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sid-accent"
          placeholder="Anotações internas..."
        />
      </div>
      <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end">
        <Button type="button" variant="outline" @click="$router.push({ name: 'clients.index' })">Cancelar</Button>
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
import Input from '@/components/Common/Input.vue';
import Button from '@/components/Common/Button.vue';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const loading = ref(false);
const saving = ref(false);
const isEdit = computed(() => Boolean(route.params.id));

const form = ref({
  name: '', cpf: '', rg: '', rg_issuer: '',
  phone: '', email: '', address: '',
  city: 'Cafarnaum', state: 'BA', notes: '',
});

async function loadItem() {
  if (!isEdit.value) return;
  loading.value = true;
  try {
    const { data } = await api.get(`/clients/${route.params.id}`);
    const item = data.data ?? data;
    Object.keys(form.value).forEach((k) => { form.value[k] = item[k] ?? ''; });
  } catch {
    toast.error('Erro ao carregar cliente');
    router.push({ name: 'clients.index' });
  } finally {
    loading.value = false;
  }
}

async function submit() {
  saving.value = true;
  try {
    if (isEdit.value) {
      await api.put(`/clients/${route.params.id}`, form.value);
      toast.success('Cliente atualizado.');
    } else {
      await api.post('/clients', form.value);
      toast.success('Cliente cadastrado.');
    }
    router.push({ name: 'clients.index' });
  } catch (err) {
    const msg = err?.response?.data?.message ?? 'Erro ao salvar cliente.';
    toast.error(msg);
  } finally {
    saving.value = false;
  }
}

onMounted(() => loadItem());
</script>
