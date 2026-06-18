<template>
  <div
    class="select-input-wrap w-full min-w-0"
    :class="{ 'select-input-wrap--compact': compact }"
  >
    <label v-if="label" class="mb-1 block text-sm font-medium text-slate-700">
      {{ label }}
    </label>
    <div class="select-input-field">
      <Multiselect
        :model-value="modelValue"
        :options="options"
        :mode="mode"
        :value-prop="valueProp"
        :label="labelProp"
        :searchable="searchable"
        :placeholder="placeholder"
        :disabled="disabled"
        :close-on-select="resolvedCloseOnSelect"
        :can-clear="canClear"
        :multiple-label="multipleLabelFn"
        no-options-text="Nenhuma opção"
        no-results-text="Nenhum resultado"
        @update:model-value="handleUpdate"
      >
        <template #multiplelabel="{ values }">
          <span
            v-if="!values || !values.length"
            class="multiselect-placeholder px-2.5 py-2 text-sm text-slate-400"
          >
            {{ placeholder }}
          </span>
          <span
            v-else
            class="multiselect-tags-inline flex flex-wrap items-center gap-1.5 px-0.5 py-1"
          >
            <span
              v-for="(val, i) in values"
              :key="typeof val === 'object' && val && (val.id ?? val.value) !== undefined ? (val.id ?? val.value) : i"
              class="multiselect-tag select-input-tag inline-flex items-center rounded-md px-2.5 py-1 text-xs font-medium"
            >
              {{ getOptionLabel(val) }}
            </span>
          </span>
        </template>
      </Multiselect>
    </div>
    <p v-if="error" class="mt-1 text-xs text-red-600">{{ error }}</p>
  </div>
</template>

<script>
import { computed, defineComponent } from 'vue';
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
      default: null,
    },
    canClear: {
      type: Boolean,
      default: true,
    },
    compact: {
      type: Boolean,
      default: false,
    },
    error: {
      type: String,
      default: '',
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
    const resolvedCloseOnSelect = computed(() => {
      if (props.closeOnSelect != null) {
        return props.closeOnSelect;
      }

      return props.mode !== 'multiple';
    });

    const handleUpdate = (value) => {
      if (props.mode === 'multiple' && Array.isArray(value)) {
        value = value.map((v) => (v != null && typeof v === 'object' && props.valueProp in v ? v[props.valueProp] : v));
      } else if (value != null && typeof value === 'object' && props.valueProp in value) {
        value = value[props.valueProp];
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
        const opt = opts.find((o) => String(o[props.valueProp]) === String(v));
        return opt ? opt[props.labelProp] : String(v);
      });
      return labels.join(', ');
    };

    const getOptionLabel = (val) => {
      if (val != null && typeof val === 'object') {
        return val[props.labelProp] ?? val.label ?? val.name ?? val.symbol ?? String(val);
      }
      const opt = (props.options || []).find((o) => String(o[props.valueProp]) === String(val));
      return opt ? opt[props.labelProp] : String(val);
    };

    return {
      resolvedCloseOnSelect,
      handleUpdate,
      multipleLabelFn,
      getOptionLabel,
    };
  },
});
</script>

<style scoped>
.select-input-field {
  position: relative;
  overflow: visible;
}

.select-input-wrap :deep(.multiselect) {
  min-height: 2.5rem;
  width: 100%;
  min-width: 0;
  font-size: 0.875rem;
  border-color: #cbd5e1;
  border-radius: 0.5rem;
}

.select-input-wrap--compact :deep(.multiselect) {
  min-height: 2.25rem;
  min-width: 4.5rem;
}

.select-input-wrap :deep(.multiselect-single-label),
.select-input-wrap :deep(.multiselect-placeholder),
.select-input-wrap :deep(.multiselect-multiple-label) {
  color: #0f172a;
  font-size: 0.875rem;
  display: flex;
  align-items: center;
  max-width: calc(100% - 2.25rem);
  padding-left: 0.625rem;
  padding-right: 2rem;
}

.select-input-wrap :deep(.multiselect-placeholder) {
  color: #94a3b8;
}

.select-input-wrap :deep(.multiselect-single-label-text) {
  color: #0f172a;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.select-input-wrap :deep(.multiselect:not(.is-active) .multiselect-search) {
  display: none;
}

.select-input-wrap :deep(.multiselect.is-active .multiselect-search) {
  display: block;
  width: 100%;
  padding-left: 0.625rem;
  padding-right: 2rem;
}

.select-input-wrap :deep(.multiselect-dropdown) {
  z-index: 200;
  max-height: 16rem;
  overflow-y: auto;
  overscroll-behavior: contain;
  min-width: 100%;
  border-color: #cbd5e1;
  border-radius: 0.5rem;
  box-shadow: 0 10px 15px -3px rgba(15, 23, 42, 0.1), 0 4px 6px -2px rgba(15, 23, 42, 0.05);
}

.select-input-wrap--compact :deep(.multiselect-dropdown) {
  min-width: 5rem;
}

.select-input-wrap :deep(.multiselect-option) {
  font-size: 0.875rem;
  color: #1e293b;
}

.select-input-wrap :deep(.multiselect-option.is-pointed) {
  background-color: #faf5ee;
  color: #1c0a06;
}

.select-input-wrap :deep(.multiselect-option.is-selected) {
  background-color: #fbe4e2;
  color: #1c0a06;
}

.select-input-wrap :deep(.multiselect-option.is-selected.is-pointed) {
  background-color: #f5d5d2;
  color: #1c0a06;
}

.select-input-wrap :deep(.multiselect-tag),
.select-input-wrap :deep(.select-input-tag) {
  background-color: #fbe4e2 !important;
  color: #1c0a06 !important;
}

.select-input-wrap :deep(.multiselect-tags) {
  padding-left: 0.375rem;
  padding-right: 0.375rem;
}
</style>
