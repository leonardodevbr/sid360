<template>
  <Multiselect
    :model-value="modelValue"
    :options="options"
    :searchable="searchable"
    :placeholder="placeholder"
    :can-clear="canClear"
    :can-deselect="canDeselect"
    value-prop="value"
    label="label"
    class="site-multiselect"
    @update:model-value="$emit('update:modelValue', $event)"
  />
</template>

<script setup>
import Multiselect from '@vueform/multiselect';

defineProps({
  modelValue: {
    type: [String, Number, Object],
    default: null,
  },
  options: {
    type: Array,
    required: true,
  },
  searchable: {
    type: Boolean,
    default: false,
  },
  placeholder: {
    type: String,
    default: 'Selecione',
  },
  canClear: {
    type: Boolean,
    default: false,
  },
  canDeselect: {
    type: Boolean,
    default: false,
  },
});

defineEmits(['update:modelValue']);
</script>

<style>
/* Global scope: override vueform/multiselect for the .site-multiselect variant */
.site-multiselect {
  --ms-border-color: rgba(201, 168, 76, 0.35);
  --ms-border-color-active: #c9a84c;
  --ms-ring-color: rgba(201, 168, 76, 0.2);
  --ms-ring-width: 3px;
  --ms-bg: #faf5ee;
  --ms-bg-disabled: #e8e4d8;
  --ms-font-size: 0.88rem;
  --ms-line-height: 1.4;
  --ms-py: 0.55rem;
  --ms-px: 0.85rem;
  --ms-radius: 10px;
  --ms-border-width: 1px;
  --ms-dropdown-bg: #faf5ee;
  --ms-dropdown-border-color: rgba(201, 168, 76, 0.35);
  --ms-dropdown-radius: 10px;
  --ms-option-font-size: 0.88rem;
  --ms-option-py: 0.5rem;
  --ms-option-px: 0.85rem;
  --ms-option-bg-pointed: rgba(201, 168, 76, 0.12);
  --ms-option-color-pointed: #1c0a06;
  --ms-option-bg-selected: rgba(201, 168, 76, 0.22);
  --ms-option-color-selected: #1c0a06;
  --ms-option-bg-selected-pointed: rgba(201, 168, 76, 0.3);
  --ms-option-color-selected-pointed: #1c0a06;
  --ms-caret-color: #a07a28;
  --ms-clear-color: #a07a28;
  --ms-spinner-color: #c9a84c;
  font-family: inherit;
  color: #1c0a06;
}

.site-multiselect .multiselect-single-label {
  color: #1c0a06;
  font-weight: 500;
}

.site-multiselect .multiselect-placeholder {
  color: rgba(28, 10, 6, 0.45);
}

.site-multiselect .multiselect-dropdown {
  /* Must be above modals (1200+) when SiteSelect is used inside a modal */
  z-index: 1400;
}
</style>
