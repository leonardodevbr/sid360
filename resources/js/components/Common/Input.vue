<script setup>
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
});

const emit = defineEmits(['update:modelValue']);
</script>

<template>
  <div class="space-y-1.5">
    <label
      v-if="label"
      :for="id"
      class="block text-sm font-medium text-slate-700"
    >
      {{ label }}
    </label>
    <input
      :id="id"
      :type="type"
      class="input-base"
      :class="error ? 'border-red-500 focus:border-red-500 focus:ring-red-100' : ''"
      :autocomplete="autocomplete"
      :value="modelValue"
      @input="emit('update:modelValue', $event.target.value)"
    />
    <p
      v-if="error"
      class="text-xs text-red-600"
    >
      {{ error }}
    </p>
  </div>
</template>

