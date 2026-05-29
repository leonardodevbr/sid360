<template>
  <div class="space-y-6">
    <div class="flex items-center gap-4">
      <button type="button" class="rounded-lg p-2 hover:bg-slate-100" @click="$router.push({ name: 'users.index' })">
        <ArrowLeftIcon class="h-5 w-5 text-slate-600" />
      </button>
      <div>
        <h2 class="text-lg font-semibold text-slate-800">{{ userId ? 'Editar Usuário' : 'Novo Usuário' }}</h2>
        <p class="text-xs text-slate-500">{{ userId ? 'Atualize os dados' : 'Preencha os dados do novo usuário' }}</p>
      </div>
    </div>

    <form v-if="!loading" class="card p-6" @submit.prevent="submit">
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <Input v-model="form.name" label="Nome completo" required />
        <Input v-model="form.email" label="E-mail" type="email" required />
        <Input v-model="form.username" label="Nome de usuário (login)" />
        <Input v-model="form.matricula" label="Matrícula (login)" />
        <Input
          v-model="form.password"
          :label="userId ? 'Nova senha (opcional)' : 'Senha'"
          type="password"
          :required="!userId"
        />
        <Input
          v-model="form.password_confirmation"
          label="Confirmar senha"
          type="password"
          :required="!userId || !!form.password"
        />
        <div class="lg:col-span-2">
          <SelectInput
            v-model="form.roles"
            label="Perfis"
            :options="roleOptions"
            mode="multiple"
            :searchable="false"
          />
        </div>
      </div>
      <div class="mt-8 flex flex-col-reverse gap-2 border-t pt-6 sm:flex-row sm:justify-end">
        <Button type="button" variant="outline" @click="$router.push({ name: 'users.index' })">Cancelar</Button>
        <Button type="submit" :loading="saving">{{ userId ? 'Atualizar' : 'Criar' }}</Button>
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
import SelectInput from '@/components/Common/SelectInput.vue';
import Button from '@/components/Common/Button.vue';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

const route = useRoute();
const router = useRouter();
const toast = useToast();

const loading = ref(false);
const saving = ref(false);
const roleOptions = ref([]);
const form = ref({
  name: '',
  email: '',
  username: '',
  matricula: '',
  password: '',
  password_confirmation: '',
  roles: ['admin'],
});

const userId = computed(() => route.params.id || null);

async function loadRoles() {
  try {
    const { data } = await api.get('/config/roles');
    const roles = data.data ?? data ?? [];
    roleOptions.value = roles.map((r) => ({ value: r.name, label: r.name === 'super-admin' ? 'Super Admin' : 'Administrador' }));
  } catch {
    roleOptions.value = [
      { value: 'admin', label: 'Administrador' },
      { value: 'super-admin', label: 'Super Admin' },
    ];
  }
}

async function loadUser() {
  if (!userId.value) return;
  loading.value = true;
  try {
    const { data } = await api.get(`/users/${userId.value}`);
    const user = data.data ?? data;
    form.value = {
      name: user.name ?? '',
      email: user.email ?? '',
      username: user.username ?? '',
      matricula: user.matricula ?? '',
      password: '',
      password_confirmation: '',
      roles: user.roles?.length ? user.roles : (user.role ? [user.role] : ['admin']),
    };
  } catch {
    toast.error('Erro ao carregar usuário');
    router.push({ name: 'users.index' });
  } finally {
    loading.value = false;
  }
}

async function submit() {
  saving.value = true;
  const payload = {
    name: form.value.name,
    email: form.value.email,
    username: form.value.username || null,
    matricula: form.value.matricula || null,
    roles: form.value.roles,
  };
  if (form.value.password) {
    payload.password = form.value.password;
    payload.password_confirmation = form.value.password_confirmation;
  }
  try {
    if (userId.value) {
      await api.post(`/users/${userId.value}/update`, payload);
      toast.success('Usuário atualizado.');
    } else {
      payload.password = form.value.password;
      payload.password_confirmation = form.value.password_confirmation;
      await api.post('/users', payload);
      toast.success('Usuário criado.');
    }
    router.push({ name: 'users.index' });
  } catch (e) {
    const errors = e.response?.data?.errors;
    const first = errors ? Object.values(errors).flat()[0] : null;
    toast.error(first || e.response?.data?.message || 'Erro ao salvar usuário');
  } finally {
    saving.value = false;
  }
}

onMounted(async () => {
  await loadRoles();
  await loadUser();
});
</script>
