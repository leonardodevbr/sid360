<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-lg font-semibold text-slate-800">Clientes</h2>
        <p class="text-xs text-slate-500">Gerencie os clientes cadastrados</p>
      </div>
      <router-link v-if="authStore.can('clients.create')" :to="{ name: 'clients.create' }">
        <Button variant="primary">+ Novo Cliente</Button>
      </router-link>
    </div>

    <div class="card p-4">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
        <div class="min-w-0 flex-1">
          <label class="mb-1 block text-sm font-medium text-slate-700">Buscar</label>
          <input
            v-model="search"
            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sid-accent"
            placeholder="Buscar por nome, CPF ou telefone..."
            @input="debouncedFetch"
          />
        </div>
        <div class="w-full sm:w-52">
          <SelectInput
            v-model="filterWhatsapp"
            label="WhatsApp"
            :options="whatsappFilterOptions"
            placeholder="Todos"
            :searchable="false"
            @update:model-value="fetchClients(1)"
          />
        </div>
      </div>
    </div>

    <div class="card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full min-w-[640px] text-sm">
          <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500">
            <tr>
              <th class="px-4 py-3 text-left sm:px-6">Nome</th>
              <th class="px-4 py-3 text-left sm:px-6">CPF</th>
              <th class="px-4 py-3 text-left sm:px-6">Telefone</th>
              <th class="hidden px-4 py-3 text-left md:table-cell sm:px-6">Cidade</th>
              <th class="sticky right-0 z-10 border-l border-slate-200 bg-slate-50 px-4 py-3 text-right text-xs font-medium uppercase text-slate-500 sm:px-6">
                Ações
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-if="loading">
              <td colspan="5" class="px-4 py-8 text-center text-slate-400 sm:px-6">Carregando...</td>
            </tr>
            <tr v-else-if="!clients.length">
              <td colspan="5" class="px-4 py-8 text-center text-slate-400 sm:px-6">Nenhum cliente encontrado.</td>
            </tr>
            <tr v-for="client in clients" :key="client.id" class="hover:bg-slate-50">
              <td class="px-4 py-3 font-medium text-slate-800 sm:px-6">{{ client.name }}</td>
              <td class="px-4 py-3 text-slate-600 sm:px-6">{{ client.cpf }}</td>
              <td class="px-4 py-3 text-slate-600 sm:px-6">
                <div class="flex items-center gap-1.5">
                  <span>{{ client.phone ?? '—' }}</span>
                  <span
                    v-if="client.whatsapp_status === 'confirmed'"
                    :class="confirmationBadgeClass"
                    class="inline-flex items-center rounded-full px-1.5 py-0.5 text-xs font-medium"
                    title="WhatsApp confirmado"
                  >
                    WPP
                  </span>
                  <span
                    v-else-if="client.whatsapp_status === 'none'"
                    class="inline-flex items-center rounded-full bg-slate-100 px-1.5 py-0.5 text-xs text-slate-400"
                    title="Sem WhatsApp"
                  >
                    sem WPP
                  </span>
                </div>
              </td>
              <td class="hidden px-4 py-3 text-slate-600 md:table-cell sm:px-6">{{ client.city ?? '—' }}</td>
              <td class="sticky right-0 z-10 border-l border-slate-200 bg-white px-4 py-3 text-right sm:px-6">
                <div class="flex items-center justify-end gap-1">
                  <button
                    v-if="authStore.can('clients.edit')"
                    type="button"
                    class="rounded p-1.5 text-sid-accent hover:bg-primary-50"
                    title="Editar"
                    @click="$router.push({ name: 'clients.edit', params: { id: client.id } })"
                  >
                    <PencilSquareIcon class="h-5 w-5" />
                  </button>
                  <button
                    v-if="authStore.can('clients.delete')"
                    type="button"
                    class="rounded p-1.5 text-red-600 hover:bg-red-50"
                    title="Excluir"
                    @click="confirmDelete(client)"
                  >
                    <TrashIcon class="h-5 w-5" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <PaginationBar v-if="pagination" :pagination="pagination" @page-change="fetchClients" />

    <Modal :is-open="!!deleteTarget" title="Excluir cliente" @close="deleteTarget = null">
      <p class="text-sm text-slate-600">
        Deseja excluir <strong>{{ deleteTarget?.name }}</strong>? Esta ação não pode ser desfeita.
      </p>
      <div class="mt-4 flex justify-end gap-2">
        <Button variant="outline" @click="deleteTarget = null">Cancelar</Button>
        <Button variant="danger" :disabled="deleting" @click="doDelete">
          {{ deleting ? 'Excluindo...' : 'Excluir' }}
        </Button>
      </div>
    </Modal>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useToast } from 'vue-toastification';
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';
import api from '@/services/api';
import { useAuthStore } from '@/stores/auth';
import Button from '@/components/Common/Button.vue';
import SelectInput from '@/components/Common/SelectInput.vue';
import Modal from '@/components/Common/Modal.vue';
import PaginationBar from '@/components/Common/PaginationBar.vue';
import { confirmationBadgeClass } from '@/utils/status';

const toast = useToast();
const authStore = useAuthStore();
const clients = ref([]);
const pagination = ref(null);
const loading = ref(false);
const search = ref('');
const filterWhatsapp = ref('');
const deleteTarget = ref(null);
const deleting = ref(false);
let debounce = null;

const whatsappFilterOptions = [
  { value: 'confirmed', label: 'WhatsApp confirmado' },
  { value: 'none', label: 'Sem WhatsApp' },
  { value: 'pending', label: 'Não verificado' },
];

async function fetchClients(page = 1) {
  loading.value = true;
  try {
    const { data } = await api.get('/clients', {
      params: {
        search: search.value,
        whatsapp_status: filterWhatsapp.value || undefined,
        page,
      },
    });
    clients.value = data.data ?? [];
    pagination.value = data.meta ?? null;
  } catch {
    toast.error('Erro ao carregar clientes');
  } finally {
    loading.value = false;
  }
}

function debouncedFetch() {
  clearTimeout(debounce);
  debounce = setTimeout(() => fetchClients(1), 350);
}

function confirmDelete(client) {
  deleteTarget.value = client;
}

async function doDelete() {
  deleting.value = true;
  try {
    await api.post(`/clients/${deleteTarget.value.id}/delete`);
    toast.success('Cliente excluído.');
    deleteTarget.value = null;
    fetchClients();
  } catch {
    toast.error('Erro ao excluir cliente.');
  } finally {
    deleting.value = false;
  }
}

onMounted(() => fetchClients());
</script>
