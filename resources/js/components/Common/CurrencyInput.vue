<script setup>
import { computed } from 'vue';
import { formatMoneyMaskFromCents, parseMoneyMaskToCents } from '@/utils/format';

const props = defineProps({
  id: { type: String, default: '' },
  label: { type: String, default: '' },
  modelValue: { type: [Number, String], default: 0 },
  placeholder: { type: String, default: 'R$ 0,00' },
  error: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const displayValue = computed(() => formatMoneyMaskFromCents(props.modelValue));

function onInput(e) {
  const cents = parseMoneyMaskToCents(e.target.value);
  e.target.value = formatMoneyMaskFromCents(cents);
  emit('update:modelValue', cents);
}

function onKeydown(e) {
  if (e.key !== 'Backspace') return;

  const digits = String(e.target.value).replace(/\D/g, '').slice(0, -1);
  const cents = digits ? parseInt(digits, 10) : 0;
  e.target.value = formatMoneyMaskFromCents(cents);
  emit('update:modelValue', cents);
  e.preventDefault();
}

function onMousedown(e) {
  if (e.button !== 0 || props.disabled) return;
  const el = e.target;
  if (document.activeElement !== el) {
    e.preventDefault();
    el.focus();
    requestAnimationFrame(() => el.setSelectionRange(0, el.value.length));
  }
}

function onFocus(e) {
  requestAnimationFrame(() => e.target.setSelectionRange(0, e.target.value.length));
}
</script>

<template>
  <div class="space-y-1.5">
    <label
      v-if="label"
      :for="id"
      class="block text-sm font-medium text-sid-dark"
    >
      {{ label }}
    </label>
    <input
      :id="id"
      type="text"
      inputmode="numeric"
      autocomplete="off"
      :value="displayValue"
      :placeholder="placeholder"
      :disabled="disabled"
      class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-[#c23028] focus:outline-none focus:ring-2 focus:ring-[#c23028]/20 disabled:bg-slate-50 disabled:text-slate-400"
      :class="error ? 'border-red-500 focus:border-red-500 focus:ring-red-500/20' : ''"
      @input="onInput"
      @keydown="onKeydown"
      @mousedown="onMousedown"
      @focus="onFocus"
    />
    <p v-if="error" class="text-xs text-red-500">{{ error }}</p>
  </div>
</template>
