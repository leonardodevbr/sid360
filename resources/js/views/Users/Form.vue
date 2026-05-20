<template>
  <form class="space-y-4" @submit.prevent="submit">
    <Input v-model="form.name" label="Nome" required />
    <Input v-model="form.email" label="E-mail" type="email" required />
    <div v-if="!user">
      <Input v-model="form.password" label="Senha (login)" type="password" required autocomplete="new-password" />
      <Input v-model="form.password_confirmation" label="Confirmação da senha" type="password" required autocomplete="new-password" />
    </div>
    <div v-else>
      <Input v-model="form.password" label="Nova senha (deixe em branco para não alterar)" type="password" autocomplete="new-password" />
      <Input v-model="form.password_confirmation" label="Confirmação da nova senha" type="password" autocomplete="new-password" />
    </div>
    <Input v-model="form.operation_password" label="Senha de operação (opcional)" type="password" autocomplete="off" />
    <Input
      v-if="form.role === 'manager' || form.role === 'owner' || form.role === 'super-admin'"
      v-model="form.operation_pin"
      label="PIN de autorização (PDV – apenas números, máx. 10)"
      type="text"
      inputmode="numeric"
      pattern="[0-9]*"
      autocomplete="off"
      maxlength="10"
      placeholder="Ex.: 1234"
      @input="form.operation_pin = form.operation_pin.replace(/[^0-9]/g, '')"
    />
    <div v-if="authStore.user?.is_super_admin">
      <SelectInput
        v-model="form.branch_ids"
        label="Filiais"
        :options="branchOptions"
        placeholder="Selecione as filiais"
        mode="multiple"
        :searchable="true"
      />
      <div v-if="form.branch_ids && form.branch_ids.length > 1" class="mt-3">
        <label class="block text-sm font-medium text-slate-700 mb-2">Filial Primária</label>
        <div class="space-y-2">
          <label
            v-for="branchId in form.branch_ids"
            :key="branchId"
            class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-slate-50 transition-colors"
            :class="{ 'border-blue-500 bg-blue-50': form.primary_branch_id === branchId }"
          >
            <input
              v-model="form.primary_branch_id"
              type="radio"
              :value="branchId"
              class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-slate-300"
            >
            <span class="ml-3 text-sm font-medium text-slate-900">
              {{ branchOptions.find(b => b.value === branchId)?.label }}
            </span>
          </label>
        </div>
      </div>
    </div>
    <SelectInput v-model="form.role" label="Cargo" :options="roleOptions" placeholder="Selecione o cargo" />
    <div v-if="form.role === 'owner'" class="mt-2 p-3 bg-blue-50 border border-blue-200 rounded-lg">
      <div class="flex items-start gap-2">
        <svg class="h-5 w-5 text-blue-600 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <div class="text-xs text-blue-800">
          <strong>Dono da Loja:</strong> Tem acesso a todas as filiais automaticamente. Possui mesmas permissões de Gerente em todas as filiais, mas não pode excluir Super Admin.
        </div>
      </div>
    </div>
    <div class="flex justify-end gap-2 pt-4">
      <Button type="button" variant="outline" @click="$emit('close')">Cancelar</Button>
      <Button type="submit" :loading="saving">{{ user ? 'Atualizar' : 'Criar' }} usuário</Button>
    </div>
  </form>
</template>

<script setup>
import { ref, watch, computed, onMounted } from 'vue';
import { useToast } from 'vue-toastification';
import { useUserStore } from '@/stores/user';
import { useAuthStore } from '@/stores/auth';
import { useAppStore } from '@/stores/app';
import Input from '@/components/Common/Input.vue';
import Button from '@/components/Common/Button.vue';
import SelectInput from '@/components/Common/SelectInput.vue';

const roleOptions = [
  { value: 'seller', label: 'Vendedor(a)' },
  { value: 'stockist', label: 'Estoquista' },
  { value: 'manager', label: 'Gerente' },
  { value: 'owner', label: 'Gestor(a) Geral' },
  { value: 'super-admin', label: 'Super Admin' },
];

const props = defineProps({
  user: { type: Object, default: null },
});

const emit = defineEmits(['close', 'saved']);

const toast = useToast();
const userStore = useUserStore();
const authStore = useAuthStore();
const appStore = useAppStore();
const saving = ref(false);

const branchOptions = computed(() =>
  (appStore.branches || []).map((b) => ({ value: b.id, label: b.name }))
);

const form = ref({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  operation_password: '',
  operation_pin: '',
  branch_id: null,
  branch_ids: [],
  primary_branch_id: null,
  role: null,
});

onMounted(() => {
  if (authStore.user?.is_super_admin) {
    appStore.fetchBranches();
  }
});

watch(
  () => props.user,
  (u) => {
    if (u) {
      form.value = {
        name: u.name ?? '',
        email: u.email ?? '',
        password: '',
        password_confirmation: '',
        operation_password: '',
        operation_pin: '',
        branch_id: u.branch_id ?? u.branch?.id ?? null,
        branch_ids: u.branch_ids ?? [],
        primary_branch_id: u.primary_branch_id ?? u.branch_id ?? null,
        role: u.role ?? null,
      };
    } else {
      form.value = {
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        operation_password: '',
        operation_pin: '',
        branch_id: authStore.user?.is_super_admin ? (appStore.currentBranch?.id ?? null) : null,
        branch_ids: [],
        primary_branch_id: null,
        role: null,
      };
    }
  },
  { immediate: true }
);

// Watch para auto-selecionar todas as filiais quando escolher "Dono"
watch(() => form.value.role, (newRole) => {
  if (newRole === 'owner' && authStore.user?.is_super_admin) {
    // Auto-seleciona todas as filiais
    form.value.branch_ids = branchOptions.value.map(b => b.value);
    // Define a primeira como primária
    if (form.value.branch_ids.length > 0) {
      form.value.primary_branch_id = form.value.branch_ids[0];
    }
  }
});

async function submit() {
  if (!form.value.name || !form.value.email) {
    toast.error('Nome e e-mail são obrigatórios.');
    return;
  }
  if (!props.user && (!form.value.password || form.value.password !== form.value.password_confirmation)) {
    toast.error('Senha e confirmação devem ser iguais.');
    return;
  }
  if (props.user && form.value.password && form.value.password !== form.value.password_confirmation) {
    toast.error('Nova senha e confirmação devem ser iguais.');
    return;
  }
  if (!form.value.role) {
    toast.error('Selecione o cargo.');
    return;
  }
  if (authStore.user?.is_super_admin && form.value.role !== 'owner' && (!form.value.branch_ids || form.value.branch_ids.length === 0)) {
    toast.error('Selecione pelo menos uma filial.');
    return;
  }

  saving.value = true;
  try {
    const payload = {
      name: form.value.name,
      email: form.value.email,
      role: form.value.role,
    };
    
    // Envia múltiplas filiais se Super Admin
    if (authStore.user?.is_super_admin) {
      payload.branch_ids = form.value.branch_ids;
      payload.primary_branch_id = form.value.primary_branch_id || form.value.branch_ids[0];
      // Mantém branch_id para compatibilidade
      payload.branch_id = payload.primary_branch_id;
    }
    if (form.value.password) {
      payload.password = form.value.password;
      payload.password_confirmation = form.value.password_confirmation;
    }
    if (form.value.operation_password && !props.user) {
      payload.operation_password = form.value.operation_password;
    }
    const isManagerOrSuperAdmin = form.value.role === 'manager' || form.value.role === 'super-admin';
    if (isManagerOrSuperAdmin) {
      payload.operation_pin = form.value.operation_pin?.trim() || null;
    }
    if (props.user) {
      payload.operation_password = form.value.operation_password === '' ? null : (form.value.operation_password || null);
      if (Object.prototype.hasOwnProperty.call(payload, 'password') && !payload.password) {
        delete payload.password;
        delete payload.password_confirmation;
      }
      await userStore.updateUser(props.user.id, payload);
      toast.success('Usuário atualizado com sucesso.');
    } else {
      await userStore.createUser(payload);
      toast.success('Usuário criado com sucesso.');
    }
    emit('saved');
  } catch (err) {
    const msg = err.response?.data?.message ?? err.response?.data?.errors
      ? Object.values(err.response.data.errors).flat().join(' ')
      : 'Erro ao salvar usuário.';
    toast.error(msg);
  } finally {
    saving.value = false;
  }
}
</script>
