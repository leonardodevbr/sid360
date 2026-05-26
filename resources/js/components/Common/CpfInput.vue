<script setup>
import { computed, ref } from 'vue';
import { formatCpf } from '@/utils/format';
import { getCpfValidationMessage } from '@/utils/validation';
import { enableInputOnMousedown, noAutofillInputAttrs } from '@/utils/noAutofill';

const model = defineModel({ type: String, default: '' });

const props = defineProps({
  id: { type: String, default: '' },
  label: { type: String, default: 'CPF' },
  required: { type: Boolean, default: false },
  error: { type: String, default: '' },
  placeholder: { type: String, default: '000.000.000-00' },
  inputClass: { type: String, default: 'input-base' },
  labelClass: { type: String, default: 'block text-sm font-medium text-sid-dark' },
  disableAutofill: { type: Boolean, default: false },
  inputName: { type: String, default: '' },
});

const touched = ref(false);

const validationMessage = computed(() =>
  getCpfValidationMessage(model.value, { required: props.required }),
);

const displayError = computed(() => props.error || (touched.value ? validationMessage.value : ''));

const hasError = computed(() => Boolean(displayError.value));

function onInput(event) {
  touched.value = true;
  model.value = formatCpf(event.target.value);
  event.target.value = model.value;
}

function onBlur() {
  touched.value = true;
}
</script>

<template>
  <div class="space-y-1.5">
    <label v-if="label" :for="id" :class="labelClass">
      {{ label }}<span v-if="required" class="text-red-500"> *</span>
    </label>
    <input
      :id="id"
      :name="inputName || undefined"
      type="text"
      inputmode="numeric"
      pattern="[0-9]*"
      autocomplete="off"
      :value="model"
      :placeholder="placeholder"
      maxlength="14"
      :class="[inputClass, hasError ? 'border-red-500 focus:border-red-500 focus:ring-red-100' : '']"
      :required="required"
      :readonly="disableAutofill"
      v-bind="disableAutofill ? noAutofillInputAttrs : {}"
      @input="onInput"
      @blur="onBlur"
      @mousedown="disableAutofill ? enableInputOnMousedown($event) : undefined"
      @focus="disableAutofill ? enableInputOnMousedown($event) : undefined"
    />
    <p v-if="displayError" class="text-xs text-red-600">
      {{ displayError }}
    </p>
  </div>
</template>
