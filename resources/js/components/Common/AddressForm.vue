<template>
  <div class="border border-slate-200 rounded-lg">
    <button
      type="button"
      @click="isOpen = !isOpen"
      class="w-full flex items-center justify-between px-4 py-3 text-left hover:bg-slate-50 transition-colors"
    >
      <span class="text-sm font-medium text-slate-700">
        Endereço {{ isOpen ? '' : '(Opcional)' }}
      </span>
      <ChevronDownIcon
        :class="['h-5 w-5 text-slate-400 transition-transform duration-200', isOpen ? 'rotate-180' : '']"
      />
    </button>

    <div v-show="isOpen" class="px-4 pb-4 space-y-4 border-t border-slate-200 pt-4">
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="sm:col-span-1">
          <label class="block text-sm font-medium text-slate-700 mb-1">
            CEP
          </label>
          <input
            :value="addressData.zip_code"
            type="text"
            @blur="fetchAddress"
            @input="(e) => formatCep(e)"
            class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="00000-000"
            maxlength="9"
          />
        </div>
        <div class="sm:col-span-2">
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Logradouro
          </label>
          <input
            :value="addressData.street"
            @input="updateField('street', $event.target.value)"
            type="text"
            class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="Rua, Avenida, etc."
          />
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="sm:col-span-1">
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Número
          </label>
          <input
            ref="numberInput"
            :value="addressData.number"
            @input="updateField('number', $event.target.value)"
            type="text"
            class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="123"
          />
        </div>
        <div class="sm:col-span-2">
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Complemento
          </label>
          <input
            :value="addressData.complement"
            @input="updateField('complement', $event.target.value)"
            type="text"
            class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="Apto, Bloco, etc."
          />
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Bairro
          </label>
          <input
            :value="addressData.neighborhood"
            @input="updateField('neighborhood', $event.target.value)"
            type="text"
            class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="Bairro"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Cidade
          </label>
          <input
            :value="addressData.city"
            @input="updateField('city', $event.target.value)"
            type="text"
            class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="Cidade"
          />
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">
          Estado (UF)
        </label>
        <input
          :value="addressData.state"
          @input="updateField('state', $event.target.value.toUpperCase())"
          type="text"
          maxlength="2"
          class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 uppercase"
          placeholder="SP"
        />
      </div>
    </div>
  </div>
</template>

<script>
import { ref, watch, nextTick, computed } from 'vue';
import { ChevronDownIcon } from '@heroicons/vue/24/outline';

export default {
  name: 'AddressForm',
  components: {
    ChevronDownIcon,
  },
  props: {
    modelValue: {
      type: Object,
      default: () => ({
        zip_code: '',
        street: '',
        number: '',
        complement: '',
        neighborhood: '',
        city: '',
        state: '',
      }),
    },
  },
  emits: ['update:modelValue'],
  setup(props, { emit }) {
    const isOpen = ref(false);
    const numberInput = ref(null);
    const loading = ref(false);

    const addressData = computed({
      get: () => props.modelValue || {
        zip_code: '',
        street: '',
        number: '',
        complement: '',
        neighborhood: '',
        city: '',
        state: '',
      },
      set: (value) => {
        emit('update:modelValue', value);
      },
    });

    const updateField = (field, value) => {
      const updated = { ...addressData.value, [field]: value };
      emit('update:modelValue', updated);
    };

    const formatCep = (event) => {
      let value = event.target.value.replace(/\D/g, '');
      value = value.substring(0, 8);
      value = value.replace(/(\d{5})(\d{3})/, '$1-$2');
      updateField('zip_code', value);
    };

    const fetchAddress = async () => {
      const cep = addressData.value.zip_code.replace(/\D/g, '');
      
      if (cep.length !== 8) {
        return;
      }

      loading.value = true;

      try {
        const response = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
        const data = await response.json();

        if (data.erro) {
          return;
        }

        const updated = {
          ...addressData.value,
          street: data.logradouro || '',
          neighborhood: data.bairro || '',
          city: data.localidade || '',
          state: data.uf || '',
        };

        emit('update:modelValue', updated);

        await nextTick();
        if (numberInput.value) {
          numberInput.value.focus();
        }
      } catch (error) {
        console.error('Erro ao buscar CEP:', error);
      } finally {
        loading.value = false;
      }
    };

    watch(
      () => props.modelValue,
      (newValue) => {
        if (newValue && (newValue.street || newValue.city || newValue.zip_code)) {
          isOpen.value = true;
        }
      },
      { immediate: true, deep: true }
    );

    return {
      isOpen,
      addressData,
      numberInput,
      loading,
      formatCep,
      fetchAddress,
      updateField,
    };
  },
};
</script>
