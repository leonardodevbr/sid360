<script setup>
import { computed, ref } from 'vue';
import { formatPhone } from '@/utils/format';
import { enableInputOnMousedown, noAutofillInputAttrs } from '@/utils/noAutofill';

const model = defineModel({ type: String, default: '' });

const props = defineProps({
  id: { type: String, default: '' },
  label: { type: String, default: 'Telefone' },
  required: { type: Boolean, default: false },
  error: { type: String, default: '' },
  placeholder: { type: String, default: '(00) 00000-0000' },
  inputClass: { type: String, default: 'input-base' },
  labelClass: { type: String, default: 'block text-sm font-medium text-sid-dark' },
  disableAutofill: { type: Boolean, default: false },
  inputName: { type: String, default: '' },
  trailingClass: { type: String, default: '' },
});

const emit = defineEmits(['blur']);

const touched = ref(false);

const phoneDigits = computed(() => model.value.replace(/\D/g, ''));

const validationMessage = computed(() => {
  if (!phoneDigits.value) {
    return props.required ? 'Telefone é obrigatório.' : '';
  }
  if (phoneDigits.value.length < 10) {
    return touched.value ? 'Telefone incompleto.' : '';
  }
  return '';
});

const displayError = computed(() => props.error || validationMessage.value);

const hasError = computed(() => Boolean(displayError.value));

const inputPaddingClass = computed(() => (props.trailingClass ? props.trailingClass : ''));

function onInput(event) {
  touched.value = true;
  model.value = formatPhone(event.target.value);
  event.target.value = model.value;
}

function onBlur() {
  touched.value = true;
  emit('blur');
}
</script>

<template>
  <div class="space-y-1.5">
    <label v-if="label" :for="id" :class="labelClass">
      {{ label }}<span v-if="required" class="text-red-500"> *</span>
    </label>
    <div class="relative">
      <input
        :id="id"
        :name="inputName || undefined"
        type="tel"
        inputmode="tel"
        autocomplete="tel"
        :value="model"
        :placeholder="placeholder"
        maxlength="16"
        :class="[inputClass, inputPaddingClass, hasError ? 'border-red-500 focus:border-red-500 focus:ring-red-100' : '']"
        :required="required"
        :readonly="disableAutofill"
        v-bind="disableAutofill ? noAutofillInputAttrs : { autocomplete: 'tel' }"
        @input="onInput"
        @blur="onBlur"
        @mousedown="disableAutofill ? enableInputOnMousedown($event) : undefined"
        @focus="disableAutofill ? enableInputOnMousedown($event) : undefined"
      />
      <slot name="trailing" />
    </div>
    <p v-if="displayError" class="text-xs text-red-600">
      {{ displayError }}
    </p>
  </div>
</template>
