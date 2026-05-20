<template>
  <div class="space-y-4">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
      <div>
        <h2 class="text-lg font-semibold text-slate-800">Usuários</h2>
        <p class="text-xs text-slate-500">Gerencie os usuários do sistema</p>
      </div>
      <Button v-if="authStore.can('users.create')" type="button" variant="primary" @click="$router.push({ name: 'users.create' })">
        Novo Usuário
      </Button>
    </div>

    <div class="card p-4 sm:p-6">
      <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end">
        <div class="min-w-0 flex-1 sm:max-w-xs">
          <label class="mb-1 block text-sm font-medium text-slate-700">Nome ou e-mail</label>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Digite para buscar..."
            class="w-full rounded border border-slate-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-sid-accent"
            @input="debouncedSearch"
          />
        </div>
        <div class="w-full sm:w-40">
          <SelectInput
            v-model="filters.role"
            label="Perfil"
            :options="roleOptionsForSelect"
            placeholder="Todos"
            :searchable="false"
            @update:model-value="applyFilters"
          />
        </div>
      </div>

      <div v-if="userStore.loading" class="py-8 text-center text-slate-500">Carregando usuários...</div>
      <div v-else-if="!userStore.users.length" class="py-8 text-center text-slate-500">Nenhum usuário encontrado</div>
      <div v-else class="-mx-4 overflow-x-auto sm:-mx-6">
        <table class="min-w-full divide-y divide-slate-200">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500 sm:px-6">Nome</th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500 sm:px-6">E-mail</th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500 sm:px-6">Perfil</th>
              <th class="sticky right-0 z-10 border-l border-slate-200 bg-slate-50 px-4 py-3 text-right text-xs font-medium uppercase text-slate-500 sm:px-6">Ações</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 bg-white">
            <tr v-for="u in userStore.users" :key="u.id">
              <td class="px-4 py-4 text-sm font-medium text-slate-900 sm:px-6">{{ u.name }}</td>
              <td class="px-4 py-4 text-sm text-slate-900 sm:px-6">{{ u.email }}</td>
              <td class="px-4 py-4 text-sm sm:px-6">
                <span v-if="u.role" class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-800">
                  {{ roleLabel(u.role) }}
                </span>
                <span v-else>—</span>
              </td>
              <td class="sticky right-0 z-10 border-l border-slate-200 bg-white px-4 py-4 text-right sm:px-6">
                <div class="flex items-center justify-end gap-1">
                  <button
                    v-if="u.id !== authStore.user?.id && authStore.can('users.delete')"
                    type="button"
                    class="rounded p-1.5 text-red-600 hover:bg-red-50"
                    title="Excluir"
                    @click="confirmDelete(u)"
                  >
                    <TrashIcon class="h-5 w-5" />
                  </button>
                  <button
                    v-if="authStore.can('users.edit')"
                    type="button"
                    class="rounded p-1.5 text-sid-accent hover:bg-primary-50"
                    title="Editar"
                    @click="$router.push({ name: 'users.edit', params: { id: u.id } })"
                  >
                    <PencilSquareIcon class="h-5 w-5" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <PaginationBar
        v-if="!userStore.loading && (userStore.pagination || userStore.users.length > 0)"
        :pagination="userStore.pagination || defaultPagination"
        @page-change="(page) => loadUsers({ page })"
        @per-page-change="onPerPageChange"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useToast } from 'vue-toastification';
import { useUserStore } from '@/stores/user';
import { useAuthStore } from '@/stores/auth';
import { useAlert } from '@/composables/useAlert';
import Button from '@/components/Common/Button.vue';
import SelectInput from '@/components/Common/SelectInput.vue';
import PaginationBar from '@/components/Common/PaginationBar.vue';
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';

const ROLE_LABELS = {
  admin: 'Administrador',
  'super-admin': 'Super Admin',
};

const toast = useToast();
const { confirm } = useAlert();
const authStore = useAuthStore();
const userStore = useUserStore();
const searchQuery = ref('');
const perPageRef = ref(15);
const filters = ref({ role: '' });
let searchTimeout = null;

const roleOptionsForSelect = [
  { value: '', label: 'Todos' },
  { value: 'admin', label: 'Administrador' },
  { value: 'super-admin', label: 'Super Admin' },
];

const defaultPagination = {
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0,
  from: null,
  to: null,
};

function roleLabel(role) {
  return role ? (ROLE_LABELS[role] ?? role) : '—';
}

function onPerPageChange(perPage) {
  perPageRef.value = perPage;
  loadUsers({ page: 1, per_page: perPage });
}

async function loadUsers(params = {}) {
  try {
    const p = { per_page: perPageRef.value, ...params };
    if (searchQuery.value) p.search = searchQuery.value;
    if (filters.value.role) p.role = filters.value.role;
    await userStore.fetchUsers(p);
    if (userStore.pagination?.per_page) perPageRef.value = userStore.pagination.per_page;
  } catch {
    toast.error('Erro ao carregar usuários');
  }
}

function applyFilters() {
  loadUsers({ page: 1 });
}

function debouncedSearch() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => loadUsers(), 500);
}

async function confirmDelete(user) {
  const ok = await confirm('Excluir usuário', `Tem certeza que deseja excluir "${user.name}"?`);
  if (!ok) return;
  try {
    await userStore.deleteUser(user.id);
    toast.success('Usuário excluído com sucesso.');
    loadUsers();
  } catch {
    toast.error(userStore.error ?? 'Erro ao excluir usuário');
  }
}

onMounted(() => loadUsers());
</script>
