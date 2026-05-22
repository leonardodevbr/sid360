<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-lg font-semibold text-slate-800">Clientes</h2>
        <p class="text-xs text-slate-500">Gerencie os clientes cadastrados</p>
      </div>
      <router-link :to="{ name: 'clients.create' }">
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
      <table class="w-full text-sm">
        <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500">
          <tr>
            <th class="px-4 py-3 text-left">Nome</th>
            <th class="px-4 py-3 text-left">CPF</th>
            <th class="px-4 py-3 text-left">Telefone</th>
            <th class="px-4 py-3 text-left">Cidade</th>
            <th class="px-4 py-3 text-right">Ações</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-if="loading">
            <td colspan="5" class="px-4 py-8 text-center text-slate-400">Carregando...</td>
          </tr>
          <tr v-else-if="!clients.length">
            <td colspan="5" class="px-4 py-8 text-center text-slate-400">Nenhum cliente encontrado.</td>
          </tr>
          <tr v-for="client in clients" :key="client.id" class="hover:bg-slate-50">
            <td class="px-4 py-3 font-medium text-slate-800">{{ client.name }}</td>
            <td class="px-4 py-3 text-slate-600">{{ client.cpf }}</td>
            <td class="px-4 py-3 text-slate-600">
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
            <td class="px-4 py-3 text-slate-600">{{ client.city ?? '—' }}</td>
            <td class="px-4 py-3 text-right">
              <div class="flex justify-end gap-2">
                <router-link :to="{ name: 'clients.edit', params: { id: client.id } }">
                  <button type="button" class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                    <PencilIcon class="h-4 w-4" />
                  </button>
                </router-link>
                <button type="button" class="rounded p-1 text-slate-400 hover:bg-red-50 hover:text-red-600" @click="confirmDelete(client)">
                  <TrashIcon class="h-4 w-4" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
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
import api from '@/services/api';
import Button from '@/components/Common/Button.vue';
import SelectInput from '@/components/Common/SelectInput.vue';
import Modal from '@/components/Common/Modal.vue';
import PaginationBar from '@/components/Common/PaginationBar.vue';
import { confirmationBadgeClass } from '@/utils/status';

const toast = useToast();
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
    await api.delete(`/clients/${deleteTarget.value.id}`);
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
