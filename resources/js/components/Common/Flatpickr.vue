<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue';
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

function applyAltInputClasses() {
  const alt = fpInstance?.altInput;
  if (!alt) {
    return;
  }
  alt.classList.add('input-base', 'pr-10');
  alt.placeholder = props.placeholder;
  alt.id = props.id || undefined;
  alt.disabled = props.disabled;
  if (props.error) {
    alt.classList.add('border-red-500', 'focus:border-red-500', 'focus:ring-red-100');
  } else {
    alt.classList.remove('border-red-500', 'focus:border-red-500', 'focus:ring-red-100');
  }
}

function buildConfig() {
  const config = {
    locale: Portuguese,
    dateFormat: 'Y-m-d',
    altInput: true,
    altFormat: 'd/m/Y',
    allowInput: true,
    disableMobile: true,
    defaultDate: props.modelValue || undefined,
    onReady() {
      applyAltInputClasses();
    },
    onChange(_selectedDates, _dateStr, instance) {
      emit('update:modelValue', instance.input.value);
    },
    onClose(_selectedDates, _dateStr, instance) {
      emit('update:modelValue', instance.input.value);
    },
  };

  if (props.minDate) {
    config.minDate = props.minDate;
  }
  if (props.maxDate) {
    config.maxDate = props.maxDate;
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
    if (value && value !== fpInstance.input.value) {
      fpInstance.setDate(value, false);
    } else if (!value) {
      fpInstance.clear();
    }
  },
);

watch(
  () => props.error,
  () => applyAltInputClasses(),
);

watch(
  () => props.disabled,
  (disabled) => {
    if (!fpInstance) {
      return;
    }
    fpInstance.set('clickOpens', !disabled);
    if (fpInstance.altInput) {
      fpInstance.altInput.disabled = disabled;
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
      :for="id"
      class="block text-sm font-medium text-sid-dark"
    >
      {{ label }}
    </label>
    <div class="relative">
      <input
        ref="inputRef"
        :id="id"
        type="text"
        class="sr-only"
        tabindex="-1"
        aria-hidden="true"
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
