<template>
  <div class="space-y-6">
    <div>
      <h2 class="text-lg font-semibold text-slate-800">Meu Perfil</h2>
      <p class="text-xs text-slate-500">Gerencie suas informações pessoais</p>
    </div>

    <div v-if="loading" class="card p-12 text-center text-slate-500">Carregando...</div>

    <form v-else class="card p-6" @submit.prevent="submit">
      <div class="space-y-6">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
          <Input v-model="form.name" label="Nome completo" required />
          <Input v-model="form.email" label="E-mail" type="email" required />
        </div>

        <div class="border-t pt-6">
          <h3 class="mb-4 text-sm font-semibold text-slate-800">Alterar senha</h3>
          <p class="mb-4 text-xs text-slate-500">Deixe em branco se não quiser alterar</p>
          <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <Input v-model="form.password" label="Nova senha" type="password" autocomplete="new-password" />
            <Input v-model="form.password_confirmation" label="Confirmar nova senha" type="password" autocomplete="new-password" />
          </div>
        </div>

        <div class="border-t pt-6">
          <label class="mb-1 block text-sm font-medium text-slate-700">Perfil</label>
          <div class="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">{{ roleLabel }}</div>
        </div>

        <div class="flex justify-end border-t pt-6">
          <Button type="submit" :loading="saving">Salvar alterações</Button>
        </div>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useToast } from 'vue-toastification';
import api from '@/services/api';
import { useAuthStore } from '@/stores/auth';
import Input from '@/components/Common/Input.vue';
import Button from '@/components/Common/Button.vue';

const toast = useToast();
const authStore = useAuthStore();

const loading = ref(true);
const saving = ref(false);
const user = ref(null);
const form = ref({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
});

const roleLabel = computed(() => {
  const role = user.value?.role || user.value?.roles?.[0];
  if (role === 'super-admin') return 'Super Admin';
  if (role === 'admin') return 'Administrador';
  return role || '—';
});

async function loadProfile() {
  loading.value = true;
  try {
    const { data } = await api.get('/me');
    user.value = data.user ?? data;
    form.value.name = user.value.name ?? '';
    form.value.email = user.value.email ?? '';
  } catch {
    toast.error('Erro ao carregar perfil');
  } finally {
    loading.value = false;
  }
}

async function submit() {
  saving.value = true;
  const payload = { name: form.value.name, email: form.value.email };
  if (form.value.password) {
    payload.password = form.value.password;
    payload.password_confirmation = form.value.password_confirmation;
  }
  try {
    await api.put(`/users/${user.value.id}`, payload);
    await authStore.fetchMe();
    toast.success('Perfil atualizado.');
    form.value.password = '';
    form.value.password_confirmation = '';
  } catch (e) {
    toast.error(e.response?.data?.message || 'Erro ao salvar perfil');
  } finally {
    saving.value = false;
  }
}

onMounted(loadProfile);
</script>
