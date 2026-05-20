<template>
  <div class="space-y-6">
    <div class="flex items-center gap-4">
      <button type="button" class="rounded-lg p-2 hover:bg-slate-100" @click="$router.push({ name: 'sales.index' })">
        <ArrowLeftIcon class="h-5 w-5 text-slate-600" />
      </button>
      <div>
        <h2 class="text-lg font-semibold text-slate-800">Nova Venda</h2>
        <p class="text-xs text-slate-500">Registre uma nova venda e gere as parcelas automaticamente</p>
      </div>
    </div>

    <form class="space-y-4" @submit.prevent="submit">
      <div class="card space-y-4 p-4 sm:p-6">
        <h3 class="text-sm font-semibold text-slate-700">Dados da Venda</h3>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <SearchableSelect
            v-model="form.client_id"
            label="Cliente"
            :options="clientOptions"
            placeholder="Selecione o cliente"
          />
          <SearchableSelect
            v-model="form.lot_id"
            label="Lote"
            :options="lotOptions"
            placeholder="Selecione o lote"
          />
        </div>
        <Input v-model="form.sale_date" label="Data da venda" type="date" />
        <Input v-model="form.notes" label="Observações" placeholder="Anotações internas..." />
      </div>

      <div class="card space-y-4 p-4 sm:p-6">
        <h3 class="text-sm font-semibold text-slate-700">Valores</h3>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Input v-model="form.total_value" label="Valor total (R$)" type="number" />
          <Input v-model="form.cash_value" label="Valor à vista (R$)" type="number" />
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Input v-model="form.down_payment" label="Entrada (R$)" type="number" />
          <Input v-model="form.financed_value" label="Saldo a financiar (R$)" type="number" />
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <Input v-model="form.installments_count" label="Nº de parcelas" type="number" />
          <Input v-model="form.installment_value" label="Valor da parcela (R$)" type="number" />
          <Input v-model="form.payment_day" label="Dia do vencimento" type="number" />
        </div>
        <Input v-model="form.first_due_date" label="Vencimento da 1ª parcela" type="date" />
      </div>

      <div class="flex justify-end gap-2">
        <Button type="button" variant="outline" @click="$router.push({ name: 'sales.index' })">Cancelar</Button>
        <Button type="submit" variant="primary" :disabled="saving">{{ saving ? 'Salvando...' : 'Registrar Venda' }}</Button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import api from '@/services/api';
import Input from '@/components/Common/Input.vue';
import Button from '@/components/Common/Button.vue';
import SearchableSelect from '@/components/Common/SearchableSelect.vue';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

const router = useRouter();
const toast = useToast();
const saving = ref(false);

const form = ref({
  client_id: '', lot_id: '', sale_date: '',
  total_value: '', cash_value: '', down_payment: '0',
  financed_value: '', installments_count: '',
  installment_value: '', payment_day: '10',
  first_due_date: '', notes: '',
});

const clientOptions = ref([]);
const lotOptions = ref([]);

function recalculate() {
  const total = parseFloat(form.value.total_value) || 0;
  const down = parseFloat(form.value.down_payment) || 0;
  const n = parseInt(form.value.installments_count, 10) || 1;
  const financed = Math.max(0, total - down);
  form.value.financed_value = financed.toFixed(2);
  form.value.installment_value = n > 0 ? (financed / n).toFixed(2) : '';
}

watch(
  () => [form.value.total_value, form.value.down_payment, form.value.installments_count],
  recalculate,
);

async function loadOptions() {
  try {
    const [c, l] = await Promise.all([
      api.get('/clients', { params: { all: 1 } }),
      api.get('/lots', { params: { all: 1, status: 'available' } }),
    ]);
    clientOptions.value = (c.data.data ?? c.data).map((x) => ({ value: String(x.id), label: x.name }));
    lotOptions.value = (l.data.data ?? l.data).map((x) => ({
      value: String(x.id),
      label: `${x.development?.name ?? ''} — Q${x.block ?? '?'} L${x.number}`,
    }));
  } catch {
    toast.error('Erro ao carregar opções do formulário.');
  }
}

async function submit() {
  saving.value = true;
  try {
    const payload = {
      ...form.value,
      client_id: Number(form.value.client_id),
      lot_id: Number(form.value.lot_id),
      total_value: Number(form.value.total_value),
      cash_value: form.value.cash_value ? Number(form.value.cash_value) : null,
      down_payment: Number(form.value.down_payment),
      financed_value: Number(form.value.financed_value),
      installments_count: Number(form.value.installments_count),
      installment_value: Number(form.value.installment_value),
      payment_day: Number(form.value.payment_day),
    };
    await api.post('/sales', payload);
    toast.success('Venda registrada. Parcelas geradas automaticamente.');
    router.push({ name: 'sales.index' });
  } catch (err) {
    toast.error(err?.response?.data?.message ?? 'Erro ao registrar venda.');
  } finally {
    saving.value = false;
  }
}

onMounted(() => loadOptions());
</script>
