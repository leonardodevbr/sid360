<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import api from '@/services/api';
import SelectInput from '@/components/Common/SelectInput.vue';
import { useAlert } from '@/composables/useAlert';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const { confirm } = useAlert();

const leads = ref([]);
const meta = ref(null);
const loading = ref(false);
const filterStatus = ref(route.query.status ? String(route.query.status) : '');

const statusOptions = [
  { value: '', label: 'Todos' },
  { value: 'pending', label: 'Pendentes' },
  { value: 'contacted', label: 'Contactados' },
  { value: 'converted', label: 'Convertidos' },
  { value: 'rejected', label: 'Rejeitados' },
];

async function load(page = 1) {
  loading.value = true;

  try {
    const { data } = await api.get('/leads', {
      params: {
        page,
        status: filterStatus.value || undefined,
      },
    });
    leads.value = data.data ?? [];
    meta.value = data.meta ?? null;
  } catch {
    toast.error('Erro ao carregar leads.');
    leads.value = [];
  } finally {
    loading.value = false;
  }
}

async function setStatus(lead, status) {
  if (status === 'rejected') {
    const ok = await confirm('Rejeitar lead', 'Deseja marcar este lead como rejeitado?');
    if (!ok) {
      return;
    }
  }

  try {
    await api.post(`/leads/${lead.id}/status`, { status });
    lead.status = status;
    toast.success('Status atualizado.');
    if (status === 'contacted') {
      toast.info('Mensagem WhatsApp enviada ao cliente.');
    }
    await load(meta.value?.current_page ?? 1);
  } catch {
    toast.error('Erro ao atualizar status.');
  }
}

async function openConvert(lead) {
  try {
    const { data } = await api.post(`/leads/${lead.id}/convert`);
    router.push({
      name: 'sales.create',
      query: { prefill: JSON.stringify(data.prefill) },
    });
  } catch (error) {
    toast.error(error?.response?.data?.error ?? 'Erro ao converter.');
  }
}

function whatsappUrl(phone) {
  const digits = String(phone ?? '').replace(/\D/g, '');
  return digits ? `https://wa.me/${digits}` : '#';
}

function statusClass(status) {
  return {
    pending: 'bg-amber-100 text-amber-700',
    contacted: 'bg-blue-100 text-blue-700',
    converted: 'bg-emerald-100 text-emerald-700',
    rejected: 'bg-red-100 text-red-400',
  }[status] ?? 'bg-slate-100 text-slate-500';
}

function statusLabel(status) {
  return {
    pending: 'Pendente',
    contacted: 'Contactado',
    converted: 'Convertido',
    rejected: 'Rejeitado',
  }[status] ?? status;
}

function formatDate(value) {
  return value ? new Date(value).toLocaleDateString('pt-BR') : '–';
}

onMounted(() => load());
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between gap-3">
      <div>
        <h2 class="text-lg font-semibold text-slate-800">
          Pré-reservas / Leads
        </h2>
        <p class="text-xs text-slate-500">
          Interessados em lotes pelo site
        </p>
      </div>
      <span
        v-if="meta?.pending"
        class="rounded-full bg-amber-100 px-3 py-1 text-sm font-semibold text-amber-700"
      >
        {{ meta.pending }} pendente(s)
      </span>
    </div>

    <div class="max-w-xs">
      <SelectInput
        v-model="filterStatus"
        label="Status"
        :options="statusOptions"
        :searchable="false"
        :can-clear="true"
        placeholder="Todos"
        @update:model-value="load(1)"
      />
    </div>

    <div class="card overflow-hidden">
      <div v-if="loading" class="py-12 text-center text-sm text-slate-400">
        Carregando...
      </div>

      <div v-else class="-mx-4 overflow-x-auto sm:-mx-6">
        <table class="min-w-full text-sm">
          <thead class="border-b border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
            <tr>
              <th class="px-4 py-3 text-left sm:px-6">
                Cliente
              </th>
              <th class="px-4 py-3 text-left sm:px-6">
                Lote
              </th>
              <th class="hidden px-4 py-3 text-left md:table-cell sm:px-6">
                Simulação
              </th>
              <th class="px-4 py-3 text-left sm:px-6">
                Status
              </th>
              <th class="hidden px-4 py-3 text-left sm:table-cell sm:px-6">
                Data
              </th>
              <th class="sticky right-0 z-10 border-l border-slate-200 bg-slate-50 px-4 py-3 text-right sm:px-6">
                Ações
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50 bg-white">
            <tr
              v-for="lead in leads"
              :key="lead.id"
              class="transition-colors hover:bg-slate-50"
            >
              <td class="px-4 py-3 sm:px-6">
                <p class="font-medium text-slate-800">
                  {{ lead.name }}
                </p>
                <p class="text-xs text-slate-400">
                  {{ lead.phone }}
                </p>
                <p v-if="lead.email" class="text-xs text-slate-400">
                  {{ lead.email }}
                </p>
              </td>
              <td class="px-4 py-3 sm:px-6">
                <p class="font-medium text-slate-700">
                  {{ lead.lot?.number ?? '–' }}
                </p>
                <p class="text-xs text-slate-400">
                  {{ lead.development?.name ?? '–' }}
                </p>
                <p v-if="lead.lot?.value" class="text-xs font-medium text-emerald-600">
                  {{ lead.lot.value }}
                </p>
              </td>
              <td class="hidden px-4 py-3 text-xs text-slate-500 md:table-cell sm:px-6">
                <span v-if="lead.installments">
                  {{ lead.down_payment_percent }}% entrada · {{ lead.installments }}x de {{ lead.simulated_installment_value }}
                </span>
                <span v-else class="text-slate-300">—</span>
              </td>
              <td class="px-4 py-3 sm:px-6">
                <span
                  class="rounded-full px-2 py-0.5 text-xs font-semibold"
                  :class="statusClass(lead.status)"
                >
                  {{ statusLabel(lead.status) }}
                </span>
              </td>
              <td class="hidden px-4 py-3 text-xs text-slate-400 sm:table-cell sm:px-6">
                {{ formatDate(lead.created_at) }}
              </td>
              <td class="sticky right-0 z-10 border-l border-slate-200 bg-white px-4 py-3 text-right sm:px-6">
                <div class="flex flex-wrap justify-end gap-2">
                  <button
                    v-if="lead.status === 'pending'"
                    type="button"
                    class="rounded px-2 py-1 text-xs font-medium text-blue-600 hover:bg-blue-50"
                    @click="setStatus(lead, 'contacted')"
                  >
                    Contactar
                  </button>
                  <a
                    v-if="lead.phone"
                    :href="whatsappUrl(lead.phone)"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="rounded px-2 py-1 text-xs font-medium text-emerald-600 hover:bg-emerald-50"
                  >
                    WhatsApp
                  </a>
                  <button
                    v-if="lead.status !== 'converted'"
                    type="button"
                    class="rounded px-2 py-1 text-xs font-medium text-sid-accent hover:bg-primary-50"
                    @click="openConvert(lead)"
                  >
                    Converter
                  </button>
                  <button
                    v-if="lead.status === 'pending'"
                    type="button"
                    class="rounded px-2 py-1 text-xs text-red-400 hover:bg-red-50"
                    @click="setStatus(lead, 'rejected')"
                  >
                    Rejeitar
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="!loading && !leads.length" class="py-12 text-center text-sm text-slate-400">
        Nenhum lead encontrado.
      </div>
    </div>

    <div
      v-if="meta && meta.last_page > 1"
      class="flex flex-wrap justify-center gap-2"
    >
      <button
        v-for="page in meta.last_page"
        :key="page"
        type="button"
        class="rounded-lg px-3 py-1.5 text-sm font-medium"
        :class="page === meta.current_page
          ? 'bg-sid-accent text-white'
          : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
        @click="load(page)"
      >
        {{ page }}
      </button>
    </div>
  </div>
</template>
