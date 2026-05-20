<script setup>
import { ref, onMounted, onBeforeUnmount, watch, computed } from 'vue';
import flatpickr from 'flatpickr';
import { Portuguese } from 'flatpickr/dist/l10n/pt.js';
import { CalendarDaysIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
  id: {
    type: String,
    default: '',
  },
  label: {
    type: String,
    default: '',
  },
  modelValue: {
    type: String,
    default: '',
  },
  error: {
    type: String,
    default: '',
  },
  placeholder: {
    type: String,
    default: 'DD/MM/AAAA',
  },
  minDate: {
    type: String,
    default: '',
  },
  maxDate: {
    type: String,
    default: '',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['update:modelValue']);

const inputRef = ref(null);
let fpInstance = null;

const inputId = computed(() => props.id || `flatpickr-${Math.random().toString(36).slice(2, 9)}`);

function parseApiDate(value) {
  if (!value || !/^\d{4}-\d{2}-\d{2}$/.test(value)) {
    return null;
  }
  const [year, month, day] = value.split('-').map(Number);
  return new Date(year, month - 1, day);
}

function formatApiDate(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function buildConfig() {
  const config = {
    locale: Portuguese,
    dateFormat: 'd/m/Y',
    allowInput: true,
    disableMobile: true,
    defaultDate: parseApiDate(props.modelValue) ?? undefined,
    onChange(selectedDates) {
      if (selectedDates[0]) {
        emit('update:modelValue', formatApiDate(selectedDates[0]));
      } else {
        emit('update:modelValue', '');
      }
    },
    onClose(selectedDates) {
      if (selectedDates[0]) {
        emit('update:modelValue', formatApiDate(selectedDates[0]));
      } else {
        emit('update:modelValue', '');
      }
    },
  };

  const min = parseApiDate(props.minDate);
  const max = parseApiDate(props.maxDate);
  if (min) {
    config.minDate = min;
  }
  if (max) {
    config.maxDate = max;
  }

  return config;
}

onMounted(() => {
  if (!inputRef.value) {
    return;
  }

  fpInstance = flatpickr(inputRef.value, buildConfig());
  fpInstance.set('clickOpens', !props.disabled);
});

watch(
  () => props.modelValue,
  (value) => {
    if (!fpInstance) {
      return;
    }
    const selected = fpInstance.selectedDates[0];
    const currentApi = selected ? formatApiDate(selected) : '';
    if (value === currentApi) {
      return;
    }
    const parsed = parseApiDate(value);
    if (parsed) {
      fpInstance.setDate(parsed, false);
    } else {
      fpInstance.clear();
    }
  },
);

watch(
  () => props.disabled,
  (disabled) => {
    if (!fpInstance) {
      return;
    }
    fpInstance.set('clickOpens', !disabled);
    if (inputRef.value) {
      inputRef.value.disabled = disabled;
    }
  },
);

onBeforeUnmount(() => {
  fpInstance?.destroy();
  fpInstance = null;
});
</script>

<template>
  <div class="space-y-1.5">
    <label
      v-if="label"
      :for="inputId"
      class="block text-sm font-medium text-sid-dark"
    >
      {{ label }}
    </label>
    <div class="relative">
      <input
        ref="inputRef"
        :id="inputId"
        type="text"
        class="input-base w-full pr-10"
        :class="error ? 'border-red-500 focus:border-red-500 focus:ring-red-100' : ''"
        :placeholder="placeholder"
        :disabled="disabled"
        autocomplete="off"
      />
      <CalendarDaysIcon
        class="pointer-events-none absolute right-3 top-2.5 z-10 h-5 w-5 text-slate-400"
      />
    </div>
    <p
      v-if="error"
      class="text-xs text-red-600"
    >
      {{ error }}
    </p>
  </div>
</template>
