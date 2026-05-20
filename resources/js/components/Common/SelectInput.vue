<template>
  <div class="select-input-wrap w-full min-w-0">
    <label v-if="label" class="block text-sm font-medium text-slate-700 mb-1">
      {{ label }}
    </label>
    <Multiselect
      :model-value="modelValue"
      :options="options"
      :mode="mode"
      :value-prop="valueProp"
      :label="labelProp"
      :searchable="searchable"
      :placeholder="placeholder"
      :disabled="disabled"
      :close-on-select="closeOnSelect"
      :can-clear="true"
      :multiple-label="multipleLabelFn"
      @update:model-value="handleUpdate"
    >
      <template #multiplelabel="{ values }">
        <span class="multiselect-tags-inline flex flex-wrap gap-1.5 items-center py-1 px-0.5">
          <span
            v-for="(val, i) in values"
            :key="typeof val === 'object' && val && (val.id ?? val.value) !== undefined ? (val.id ?? val.value) : i"
            class="multiselect-tag select-input-tag inline-flex items-center rounded-md bg-blue-100 text-blue-900 px-2.5 py-1 text-xs font-medium"
          >
            {{ getOptionLabel(val) }}
          </span>
        </span>
      </template>
    </Multiselect>
  </div>
</template>

<script>
import { defineComponent } from 'vue';
import Multiselect from '@vueform/multiselect';

export default defineComponent({
  name: 'SelectInput',
  components: {
    Multiselect,
  },
  props: {
    modelValue: {
      type: [String, Number, Array, Object],
      default: null,
    },
    options: {
      type: Array,
      required: true,
    },
    label: {
      type: String,
      default: '',
    },
    mode: {
      type: String,
      default: 'single',
    },
    searchable: {
      type: Boolean,
      default: true,
    },
    placeholder: {
      type: String,
      default: 'Selecione uma opção',
    },
    disabled: {
      type: Boolean,
      default: false,
    },
    closeOnSelect: {
      type: Boolean,
      default: true,
    },
    valueProp: {
      type: String,
      default: 'value',
    },
    labelProp: {
      type: String,
      default: 'label',
    },
  },
  emits: ['update:modelValue'],
  setup(props, { emit }) {
    const handleUpdate = (value) => {
      if (props.mode === 'multiple' && Array.isArray(value)) {
        value = value.map((v) => (v != null && typeof v === 'object' && props.valueProp in v ? v[props.valueProp] : v));
      }
      emit('update:modelValue', value);
    };

    const multipleLabelFn = (values) => {
      if (!values || !values.length) return '';
      const opts = props.options || [];
      const labels = values.map((v) => {
        if (v != null && typeof v === 'object') {
          return v[props.labelProp] ?? v.label ?? v.name ?? v.symbol ?? '';
        }
        const opt = opts.find((o) => o[props.valueProp] === v);
        return opt ? opt[props.labelProp] : String(v);
      });
      return labels.join(', ');
    };

    const getOptionLabel = (val) => {
      if (val != null && typeof val === 'object') {
        return val[props.labelProp] ?? val.label ?? val.name ?? val.symbol ?? String(val);
      }
      const opt = (props.options || []).find((o) => o[props.valueProp] === val);
      return opt ? opt[props.labelProp] : String(val);
    };

    return {
      handleUpdate,
      multipleLabelFn,
      getOptionLabel,
    };
  },
});
</script>

<style scoped>
.select-input-wrap :deep(.multiselect-container) {
  @apply relative min-w-[18rem] w-full;
}

:deep(.multiselect-single-label),
:deep(.multiselect-placeholder) {
  @apply text-sm text-slate-900;
}

:deep(.multiselect-container .multiselect-single-label),
:deep(.multiselect-container .multiselect-placeholder) {
  @apply px-3 py-2 border border-slate-300 rounded-lg text-sm;
  min-height: 2.5rem;
  display: flex;
  align-items: center;
}

:deep(.multiselect-container .multiselect-search) {
  @apply px-3 py-2 text-sm border-0 outline-none;
  width: 100%;
}

:deep(.multiselect-container-open .multiselect-search) {
  display: block !important;
  opacity: 1 !important;
}

:deep(.multiselect-container .multiselect-single-label .multiselect-search) {
  position: absolute;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
}

:deep(.multiselect-dropdown) {
  @apply border border-slate-300 rounded-lg bg-white mt-1 z-50;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
  min-width: 22rem;
  white-space: nowrap;
}

:deep(.multiselect-option) {
  @apply px-3 py-2 text-sm cursor-pointer text-slate-800 font-medium;
  white-space: nowrap;
}

:deep(.multiselect-option:hover) {
  @apply bg-slate-50;
}

:deep(.multiselect-option-pointed) {
  @apply bg-blue-50 text-slate-900;
}

:deep(.multiselect-option.is-pointed),
:deep(.multiselect-option-pointed) {
  background-color: rgb(239 246 255) !important;
  color: rgb(15 23 42) !important;
}

:deep(.multiselect-container-active .multiselect-single-label),
:deep(.multiselect-container-active .multiselect-placeholder) {
  @apply border-blue-500 ring-2 ring-blue-100;
}

:deep(.multiselect-caret) {
  @apply text-slate-400;
}

:deep(.multiselect-clear) {
  @apply text-slate-400 hover:text-slate-600;
}

:deep(.multiselect-tag) {
  @apply bg-blue-100 text-blue-900 rounded px-2 py-1 text-xs font-medium;
}

:deep(.multiselect-tag-remove) {
  @apply text-blue-700 hover:text-blue-900 hover:bg-blue-200 rounded;
}

:deep(.multiselect-multiple-label) {
  min-height: 2.5rem;
  padding: 0.5rem 0.75rem;
  @apply flex flex-wrap items-center gap-1.5;
}

:deep(.multiselect-tags-inline) {
  flex-wrap: wrap;
  padding: 0.125rem;
}

:deep(.multiselect-tag),
:deep(.select-input-tag) {
  background-color: rgb(219 234 254) !important;
  color: rgb(30 58 138) !important;
}

:deep(.multiselect-option.is-selected),
:deep(.multiselect-option-selected),
:deep(.multiselect-option-selected-pointed) {
  background-color: rgb(219 234 254) !important;
  color: rgb(30 58 138) !important;
}
</style>
