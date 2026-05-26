<script setup>
import { computed, ref } from 'vue';
import { formatCep } from '@/utils/format';
import { enableInputOnMousedown, noAutofillInputAttrs } from '@/utils/noAutofill';

const model = defineModel({ type: String, default: '' });

const props = defineProps({
  id: { type: String, default: '' },
  label: { type: String, default: 'CEP' },
  error: { type: String, default: '' },
  hint: { type: String, default: '' },
  placeholder: { type: String, default: '00000-000' },
  inputClass: { type: String, default: 'input-base' },
  labelClass: { type: String, default: 'block text-sm font-medium text-sid-dark' },
  loading: { type: Boolean, default: false },
  disableAutofill: { type: Boolean, default: false },
  inputName: { type: String, default: '' },
});

const emit = defineEmits(['complete']);

const touched = ref(false);

const cepDigits = computed(() => model.value.replace(/\D/g, ''));

const validationMessage = computed(() => {
  if (!cepDigits.value || cepDigits.value.length === 8) return '';
  return touched.value ? 'CEP incompleto.' : '';
});

const displayMessage = computed(() => props.error || props.hint || validationMessage.value);

const hasError = computed(() => Boolean(props.error || (validationMessage.value && !props.hint)));

function emitCompleteIfReady(value) {
  const digits = String(value ?? '').replace(/\D/g, '');
  if (digits.length === 8) {
    emit('complete', digits);
  }
}

function onInput(event) {
  touched.value = true;
  const formatted = formatCep(event.target.value);
  model.value = formatted;
  event.target.value = formatted;
  emitCompleteIfReady(formatted);
}

function onBlur() {
  touched.value = true;
  emitCompleteIfReady(model.value);
}
</script>

<template>
  <div class="space-y-1.5">
    <label v-if="label" :for="id" :class="labelClass">
      {{ label }}
    </label>
    <div class="relative">
      <input
        :id="id"
        :name="inputName || undefined"
        type="text"
        inputmode="numeric"
        autocomplete="postal-code"
        :value="model"
        :placeholder="placeholder"
        maxlength="9"
        :class="[inputClass, loading ? 'pr-8' : '', hasError ? 'border-red-500 focus:border-red-500 focus:ring-red-100' : '']"
        :readonly="disableAutofill"
        v-bind="disableAutofill ? noAutofillInputAttrs : {}"
        @input="onInput"
        @blur="onBlur"
        @mousedown="disableAutofill ? enableInputOnMousedown($event) : undefined"
        @focus="disableAutofill ? enableInputOnMousedown($event) : undefined"
      />
      <svg
        v-if="loading"
        class="absolute right-2 top-2.5 h-4 w-4 animate-spin text-sid-accent"
        fill="none"
        viewBox="0 0 24 24"
      >
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
      </svg>
    </div>
    <p v-if="displayMessage" class="text-xs text-red-600">
      {{ displayMessage }}
    </p>
  </div>
</template>
