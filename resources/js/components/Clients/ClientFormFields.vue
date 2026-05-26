<template>
  <div class="space-y-4">
    <Input
      v-model="form.name"
      label="Nome completo *"
      placeholder="Nome do cliente"
      :error="errors.name"
    />

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <CpfInput v-model="form.cpf" label="CPF" required :error="errors.cpf" />
      <Input v-model="form.rg" label="RG" placeholder="Número do RG" />
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <Input v-model="form.rg_issuer" label="Órgão emissor" placeholder="SSP/BA" />
      <Input v-model="form.profession" label="Profissão" placeholder="Ex.: Comerciante" />
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <SelectInput
        v-model="form.marital_status"
        label="Estado civil"
        :options="maritalStatusOptions"
        placeholder="Selecione…"
      />

      <PhoneInput
        v-model="form.phone"
        :error="errors.phone"
        trailing-class="pr-28"
        @blur="checkWhatsapp"
      >
        <template #trailing>
          <span
            v-if="otpVerified"
            :class="confirmationBadgeClass"
            class="absolute right-2 top-2 flex items-center rounded-full px-2 py-0.5 text-xs font-semibold"
          >
            Verificado
          </span>
          <span
            v-else-if="whatsappStatus === 'has'"
            :class="confirmationBadgeClass"
            class="absolute right-2 top-2 flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
          >
            WhatsApp
          </span>
          <span
            v-else-if="whatsappStatus === 'no'"
            :class="badgeColors.warning"
            class="absolute right-2 top-2 flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
          >
            Sem WhatsApp
          </span>
          <span
            v-else-if="whatsappStatus === 'checking'"
            :class="badgeColors.neutral"
            class="absolute right-2 top-2 flex items-center rounded-full px-2 py-0.5 text-xs"
          >
            Verificando…
          </span>
          <button
            v-else-if="whatsappStatus === 'error'"
            type="button"
            :class="badgeColors.danger"
            class="absolute right-2 top-2 flex items-center rounded-full px-2 py-0.5 text-xs"
            title="Clique para tentar novamente"
            @click="checkWhatsapp"
          >
            Tentar novamente
          </button>
        </template>
      </PhoneInput>
    </div>

    <div v-if="whatsappStatus === 'has' && !otpVerified" class="-mt-2 space-y-2 sm:col-span-2">
      <div v-if="!otpSent" class="flex flex-wrap items-center gap-2">
        <button
          type="button"
          :disabled="otpSending"
          class="rounded-lg bg-sid-accent px-3 py-1.5 text-xs font-medium text-white hover:bg-sid-accent/90 disabled:opacity-50"
          @click="sendOtp"
        >
          {{ otpSending ? 'Enviando…' : 'Confirmar via WhatsApp' }}
        </button>
        <span class="text-xs text-slate-400">Enviaremos um código de verificação</span>
      </div>
      <div v-else class="space-y-2">
        <div class="flex flex-wrap items-center gap-2">
          <input
            :value="otpCode"
            type="text"
            inputmode="numeric"
            maxlength="4"
            placeholder="0000"
            class="w-20 rounded-lg border border-slate-200 px-3 py-1.5 text-center text-base font-bold tracking-widest focus:border-sid-accent focus:outline-none focus:ring-2 focus:ring-sid-accent/30"
            @input="onOtpInput($event.target.value)"
            @keyup.enter="verifyOtp"
          />
          <button
            type="button"
            :disabled="otpCode.length !== 4 || otpVerifying"
            class="rounded-lg bg-sid-accent px-3 py-1.5 text-xs font-medium text-white disabled:opacity-40"
            @click="verifyOtp"
          >
            {{ otpVerifying ? 'Verificando…' : 'Confirmar' }}
          </button>
          <button type="button" class="text-xs text-slate-400 hover:text-slate-600" @click="resetOtp">
            Cancelar
          </button>
        </div>
        <p v-if="otpError" class="text-xs text-red-500">{{ otpError }}</p>
        <p class="text-xs text-slate-400">
          Código enviado para {{ form.phone }}.
          <button
            v-if="otpCountdown <= 0"
            type="button"
            class="text-sid-accent hover:underline"
            @click="sendOtp"
          >
            Reenviar código
          </button>
          <span v-else>Reenviar em {{ otpCountdown }}s</span>
        </p>
      </div>
    </div>

    <div
      v-if="(whatsappStatus === null || whatsappStatus === 'error' || whatsappStatus === 'no') && !otpVerified"
    >
      <label class="flex cursor-pointer select-none items-center gap-1.5">
        <input
          v-model="whatsappManual"
          type="checkbox"
          class="h-3.5 w-3.5 rounded border-slate-300 text-sid-accent focus:ring-sid-accent/30"
        />
        <span class="text-xs text-slate-500">Confirmar WhatsApp manualmente</span>
      </label>
    </div>

    <Input
      v-model="form.email"
      label="E-mail"
      type="email"
      placeholder="email@exemplo.com"
      :error="errors.email"
    />

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <CepInput
        v-model="form.zip_code"
        :loading="buscandoCep"
        :hint="erroCep"
        @complete="buscarCep"
      />
    </div>

    <Input v-model="form.address" label="Logradouro" placeholder="Rua, avenida…" />

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <Input
        v-model="form.address_number"
        label="Número"
        placeholder="123"
        inputmode="numeric"
      />
      <Input v-model="form.neighborhood" label="Bairro" placeholder="Centro" />
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <Input v-model="form.city" label="Cidade" placeholder="Cafarnaum" />
      <Input
        v-model="form.state"
        label="Estado"
        placeholder="BA"
        maxlength="2"
        @update:model-value="onStateInput"
      />
    </div>

    <div>
      <label class="mb-1 block text-sm font-medium text-sid-dark">Observações</label>
      <textarea
        v-model="form.notes"
        rows="3"
        class="input-base"
        placeholder="Anotações internas..."
      />
    </div>
  </div>
</template>

<script setup>
import Input from '@/components/Common/Input.vue';
import CpfInput from '@/components/Common/CpfInput.vue';
import PhoneInput from '@/components/Common/PhoneInput.vue';
import CepInput from '@/components/Common/CepInput.vue';
import SelectInput from '@/components/Common/SelectInput.vue';
import { maritalStatusOptions } from '@/constants/maritalStatus';
import { confirmationBadgeClass, badgeColors } from '@/utils/status';

const whatsappManual = defineModel('whatsappManual', { type: Boolean, default: false });

defineProps({
  form: { type: Object, required: true },
  errors: { type: Object, required: true },
  buscandoCep: { type: Boolean, default: false },
  erroCep: { type: String, default: '' },
  whatsappStatus: { type: [String, null], default: null },
  otpSent: { type: Boolean, default: false },
  otpCode: { type: String, default: '' },
  otpVerified: { type: Boolean, default: false },
  otpError: { type: String, default: '' },
  otpSending: { type: Boolean, default: false },
  otpVerifying: { type: Boolean, default: false },
  otpCountdown: { type: Number, default: 0 },
  checkWhatsapp: { type: Function, required: true },
  sendOtp: { type: Function, required: true },
  verifyOtp: { type: Function, required: true },
  resetOtp: { type: Function, required: true },
  onOtpInput: { type: Function, required: true },
  onStateInput: { type: Function, required: true },
  buscarCep: { type: Function, required: true },
});
</script>
