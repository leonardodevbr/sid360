<template>
  <div class="flex items-center">
    <button
      type="button"
      :class="[
        'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2',
        modelValue ? 'bg-blue-600' : 'bg-slate-200',
      ]"
      :aria-pressed="modelValue"
      @click="toggle"
    >
      <span
        :class="[
          'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
          modelValue ? 'translate-x-5' : 'translate-x-0',
        ]"
      />
    </button>
    <label
      v-if="label"
      class="ml-3 text-sm font-medium text-slate-700 cursor-pointer"
      @click="toggle"
    >
      {{ label }}
    </label>
  </div>
</template>

<script>
import { defineComponent } from 'vue';

export default defineComponent({
  name: 'Toggle',
  props: {
    modelValue: {
      type: Boolean,
      default: false,
    },
    label: {
      type: String,
      default: '',
    },
  },
  emits: ['update:modelValue'],
  setup(props, { emit }) {
    const toggle = () => {
      emit('update:modelValue', !props.modelValue);
    };

    return {
      toggle,
    };
  },
});
</script>
