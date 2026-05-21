<script setup>
import { ref, computed } from 'vue';
import { EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
  id: {
    type: String,
    default: '',
  },
  label: {
    type: String,
    default: '',
  },
  type: {
    type: String,
    default: 'text',
  },
  modelValue: {
    type: [String, Number],
    default: '',
  },
  error: {
    type: String,
    default: '',
  },
  autocomplete: {
    type: String,
    default: '',
  },
  placeholder: {
    type: String,
    default: '',
  },
});

const emit = defineEmits(['update:modelValue']);

const showPassword = ref(false);

const isPasswordField = computed(() => props.type === 'password');

const inputType = computed(() => {
  if (!isPasswordField.value) {
    return props.type;
  }
  return showPassword.value ? 'text' : 'password';
});

function togglePasswordVisibility() {
  showPassword.value = !showPassword.value;
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
    <div class="relative">
      <input
        :id="id"
        :type="inputType"
        class="input-base"
        :class="[
          error ? 'border-red-500 focus:border-red-500 focus:ring-red-100' : '',
          isPasswordField ? 'pr-10' : '',
        ]"
        :autocomplete="autocomplete"
        :placeholder="placeholder"
        :value="modelValue"
        @input="emit('update:modelValue', $event.target.value)"
      />
      <button
        v-if="isPasswordField"
        type="button"
        tabindex="-1"
        class="absolute right-2 top-1/2 -translate-y-1/2 border-0 bg-transparent p-1 text-slate-400 shadow-none outline-none hover:text-slate-600 focus:border-0 focus:outline-none focus:ring-0 active:outline-none"
        :aria-label="showPassword ? 'Ocultar senha' : 'Mostrar senha'"
        @click="togglePasswordVisibility"
      >
        <EyeSlashIcon v-if="showPassword" class="h-5 w-5" />
        <EyeIcon v-else class="h-5 w-5" />
      </button>
    </div>
    <p
      v-if="error"
      class="text-xs text-red-600"
    >
      {{ error }}
    </p>
  </div>
</template>
