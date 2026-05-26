<script setup>
import { ref, computed, watch } from 'vue';
import { useToast } from 'vue-toastification';
import publicApi from '@/services/publicApi';
import SelectInput from '@/components/Common/SelectInput.vue';
import { XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
  lot: {
    type: Object,
    required: true,
  },
});

defineEmits(['close']);

const toast = useToast();

const photoIndex = ref(0);
const form = ref({ name: '', phone: '', cpf: '', email: '', message: '' });
const submitting = ref(false);
const submitted = ref(false);

const sim = ref({
  downPercent: '20',
  installments: '36',
  downValue: 0,
  balance: 0,
  installmentValue: 0,
});

const currentPhoto = computed(() =>
  props.lot.photos?.[photoIndex.value]?.url ?? props.lot.cover_photo ?? null,
);

const downPaymentOptions = [
  { value: '20', label: '20%' },
  { value: '30', label: '30%' },
  { value: '40', label: '40%' },
  { value: '50', label: '50%' },
  { value: '60', label: '60%' },
];

const installmentOptions = [
  { value: '12', label: '12 meses' },
  { value: '24', label: '24 meses' },
  { value: '36', label: '36 meses' },
  { value: '48', label: '48 meses' },
  { value: '60', label: '60 meses' },
  { value: '120', label: '120 meses' },
];

function formatCurrency(value) {
  if (!value) {
    return '–';
  }

  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value / 100);
}

function calcSim() {
  const total = props.lot.total_value ?? 0;
  const down = Math.round(total * (parseInt(sim.value.downPercent, 10) / 100));
  const balance = total - down;
  const installment = Math.round(balance / parseInt(sim.value.installments, 10));

  sim.value.downValue = down;
  sim.value.balance = balance;
  sim.value.installmentValue = installment;
}

watch(
  () => props.lot,
  (lot) => {
    photoIndex.value = 0;
    submitted.value = false;
    form.value = { name: '', phone: '', cpf: '', email: '', message: '' };
    sim.value.downPercent = String(Math.round(lot.down_payment_percent ?? 20));
    calcSim();
  },
  { immediate: true },
);

async function submitLead() {
  if (!form.value.name || !form.value.phone) {
    return;
  }

  submitting.value = true;

  try {
    await publicApi.post('/public/leads', {
      lot_id: props.lot.id,
      ...form.value,
      down_payment_percent: sim.value.downPercent,
      installments: parseInt(sim.value.installments, 10),
      simulated_installment_value: sim.value.installmentValue,
    });
    submitted.value = true;
  } catch (error) {
    toast.error(error?.response?.data?.error ?? 'Erro ao enviar. Tente novamente.');
  } finally {
    submitting.value = false;
  }
}
</script>

<template>
  <div
    class="fixed inset-0 z-50 flex items-end justify-center bg-black/60 p-0 sm:items-center sm:p-4"
    @click.self="$emit('close')"
  >
    <div class="max-h-[95vh] w-full max-w-2xl overflow-y-auto rounded-t-2xl bg-white shadow-2xl sm:rounded-2xl">
      <div class="relative aspect-video overflow-hidden rounded-t-2xl bg-slate-100">
        <img
          v-if="currentPhoto"
          :src="currentPhoto"
          class="h-full w-full object-cover"
          alt="Foto do lote"
        >
        <div
          v-else
          class="flex h-full items-center justify-center bg-slate-200 text-slate-400"
        >
          <svg class="h-16 w-16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
          </svg>
        </div>

        <button
          type="button"
          class="absolute right-3 top-3 rounded-full bg-black/50 p-1.5 text-white hover:bg-black/70"
          aria-label="Fechar"
          @click="$emit('close')"
        >
          <XMarkIcon class="h-5 w-5" />
        </button>

        <div
          v-if="lot.photos?.length > 1"
          class="absolute bottom-2 left-2 flex gap-1"
        >
          <button
            v-for="(photo, index) in lot.photos"
            :key="photo.id ?? index"
            type="button"
            class="h-8 w-8 overflow-hidden rounded border-2 transition-all"
            :class="index === photoIndex ? 'border-white' : 'border-transparent opacity-70'"
            @click="photoIndex = index"
          >
            <img
              :src="photo.url"
              class="h-full w-full object-cover"
              alt=""
            >
          </button>
        </div>
      </div>

      <div class="space-y-5 p-5">
        <div>
          <h2 class="text-xl font-bold text-slate-800">
            Lote {{ lot.number }}
          </h2>
          <p class="text-sm text-slate-500">
            {{ lot.full_address }}
          </p>
          <p class="text-sm text-slate-500">
            {{ lot.development?.name }}
          </p>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
          <div class="rounded-lg bg-slate-50 p-3 text-center">
            <p class="text-xs text-slate-400">
              Área
            </p>
            <p class="font-semibold text-slate-700">
              {{ lot.area ? `${lot.area}m²` : '–' }}
            </p>
          </div>
          <div class="rounded-lg bg-slate-50 p-3 text-center">
            <p class="text-xs text-slate-400">
              Valor
            </p>
            <p class="font-semibold text-emerald-700">
              {{ formatCurrency(lot.total_value) }}
            </p>
          </div>
          <div class="rounded-lg bg-slate-50 p-3 text-center">
            <p class="text-xs text-slate-400">
              Status
            </p>
            <p class="font-semibold text-emerald-600">
              Disponível
            </p>
          </div>
        </div>

        <div class="space-y-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
          <p class="text-sm font-semibold text-emerald-800">
            Simulador de parcelas
          </p>

          <div class="grid grid-cols-2 gap-3">
            <SelectInput
              v-model="sim.downPercent"
              label="Entrada"
              :options="downPaymentOptions"
              :searchable="false"
              :can-clear="false"
              @update:model-value="calcSim"
            />
            <SelectInput
              v-model="sim.installments"
              label="Prazo"
              :options="installmentOptions"
              :searchable="false"
              :can-clear="false"
              @update:model-value="calcSim"
            />
          </div>

          <div class="grid grid-cols-3 gap-2 text-center">
            <div class="rounded-lg border border-emerald-100 bg-white p-2">
              <p class="text-xs text-slate-400">
                Entrada
              </p>
              <p class="text-sm font-bold text-slate-700">
                {{ formatCurrency(sim.downValue) }}
              </p>
            </div>
            <div class="rounded-lg border border-emerald-100 bg-white p-2">
              <p class="text-xs text-slate-400">
                Saldo
              </p>
              <p class="text-sm font-bold text-slate-700">
                {{ formatCurrency(sim.balance) }}
              </p>
            </div>
            <div class="rounded-lg bg-emerald-600 p-2 text-white">
              <p class="text-xs opacity-80">
                {{ sim.installments }}x de
              </p>
              <p class="text-sm font-bold">
                {{ formatCurrency(sim.installmentValue) }}
              </p>
            </div>
          </div>
        </div>

        <div v-if="!submitted" class="space-y-3">
          <p class="text-sm font-semibold text-slate-700">
            Tenho interesse neste lote
          </p>

          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <label class="mb-1 block text-xs text-slate-500">Nome completo *</label>
              <input
                v-model="form.name"
                type="text"
                required
                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                placeholder="Seu nome"
              >
            </div>
            <div>
              <label class="mb-1 block text-xs text-slate-500">WhatsApp *</label>
              <input
                v-model="form.phone"
                type="tel"
                required
                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                placeholder="(74) 9 0000-0000"
              >
            </div>
            <div>
              <label class="mb-1 block text-xs text-slate-500">CPF</label>
              <input
                v-model="form.cpf"
                type="text"
                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                placeholder="000.000.000-00"
              >
            </div>
            <div>
              <label class="mb-1 block text-xs text-slate-500">E-mail</label>
              <input
                v-model="form.email"
                type="email"
                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                placeholder="seu@email.com"
              >
            </div>
          </div>

          <div>
            <label class="mb-1 block text-xs text-slate-500">Mensagem (opcional)</label>
            <textarea
              v-model="form.message"
              rows="2"
              class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
              placeholder="Alguma dúvida ou informação adicional?"
            />
          </div>

          <button
            type="button"
            :disabled="submitting || !form.name || !form.phone"
            class="w-full rounded-xl bg-emerald-600 py-3 text-sm font-semibold text-white transition-colors hover:bg-emerald-700 disabled:opacity-50"
            @click="submitLead"
          >
            {{ submitting ? 'Enviando...' : 'Solicitar contato sobre este lote' }}
          </button>
        </div>

        <div
          v-else
          class="space-y-2 rounded-xl border border-emerald-200 bg-emerald-50 p-5 text-center"
        >
          <p class="font-semibold text-emerald-800">
            Solicitação enviada!
          </p>
          <p class="text-sm text-emerald-700">
            Nossa equipe entrará em contato em breve pelo seu WhatsApp.
          </p>
        </div>
      </div>
    </div>
  </div>
</template>
