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

    <form v-if="!loading" class="card space-y-4 p-4 sm:p-6" novalidate @submit.prevent="submit">
      <ClientFormFields
        :form="form"
        :errors="errors"
        :buscando-cep="buscandoCep"
        :erro-cep="erroCep"
        :whatsapp-status="whatsappStatus"
        v-model:whatsapp-manual="whatsappManual"
        :otp-sent="otpSent"
        :otp-code="otpCode"
        :otp-verified="otpVerified"
        :otp-error="otpError"
        :otp-sending="otpSending"
        :otp-verifying="otpVerifying"
        :otp-countdown="otpCountdown"
        :check-whatsapp="checkWhatsapp"
        :send-otp="sendOtp"
        :verify-otp="verifyOtp"
        :reset-otp="resetOtp"
        :on-otp-input="onOtpInput"
        :on-state-input="onStateInput"
        :buscar-cep="buscarCep"
      />

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
import Button from '@/components/Common/Button.vue';
import ClientFormFields from '@/components/Clients/ClientFormFields.vue';
import { useClientForm } from '@/composables/useClientForm';
import { getApiErrorMessage } from '@/utils/apiError';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const loading = ref(false);
const saving = ref(false);
const isEdit = computed(() => Boolean(route.params.id));

const {
  form,
  errors,
  buscandoCep,
  erroCep,
  whatsappStatus,
  whatsappManual,
  otpSent,
  otpCode,
  otpVerified,
  otpError,
  otpSending,
  otpVerifying,
  otpCountdown,
  applyClientData,
  onStateInput,
  onOtpInput,
  checkWhatsapp,
  sendOtp,
  verifyOtp,
  resetOtp,
  validate,
  getPayload,
  resolveWhatsappStatus,
  buscarCep,
} = useClientForm();

async function loadItem() {
  if (!isEdit.value) return;
  loading.value = true;
  try {
    const { data } = await api.get(`/clients/${route.params.id}`);
    const item = data.data ?? data;
    applyClientData(item);
  } catch {
    toast.error('Erro ao carregar cliente');
    router.push({ name: 'clients.index' });
  } finally {
    loading.value = false;
  }
}

async function submit() {
  if (!validate()) {
    toast.error('Corrija os campos destacados antes de salvar.');
    return;
  }

  saving.value = true;
  try {
    const payload = getPayload();

    if (isEdit.value) {
      const { data } = await api.post(`/clients/${route.params.id}/update`, payload);
      const client = data.data ?? data;
      await resolveWhatsappStatus(client.id);
      toast.success('Cliente atualizado.');
    } else {
      const { data } = await api.post('/clients', payload);
      const client = data.data ?? data;
      await resolveWhatsappStatus(client.id);
      toast.success('Cliente cadastrado.');
    }
    router.push({ name: 'clients.index' });
  } catch (err) {
    const apiErrors = err?.response?.data?.errors;
    if (apiErrors && typeof apiErrors === 'object') {
      Object.entries(apiErrors).forEach(([field, messages]) => {
        const msg = Array.isArray(messages) ? messages[0] : messages;
        if (msg && Object.prototype.hasOwnProperty.call(form.value, field)) {
          errors.value[field] = msg;
        }
      });
    }
    toast.error(getApiErrorMessage(err, 'Erro ao salvar cliente.'));
  } finally {
    saving.value = false;
  }
}

onMounted(() => loadItem());
</script>
