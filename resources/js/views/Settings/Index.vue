<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold text-slate-800">Configurações do sistema</h1>
    </div>

    <p class="text-sm text-slate-600">
      Acesso restrito ao Super Admin. Configurações globais do sistema (nome do aplicativo, dados do município) são definidas aqui.
    </p>

    <div v-if="loading" class="flex items-center justify-center py-12">
      <p class="text-slate-500">Carregando...</p>
    </div>

    <div v-else class="space-y-6">
      <div
        v-for="(items, group) in groupedSettings"
        :key="group"
        class="border border-slate-200 rounded-lg p-6"
      >
        <h2 class="text-base font-semibold text-slate-800 mb-4">
          {{ groupLabel(group) }}
        </h2>
        <div class="space-y-4">
          <div
            v-for="item in items"
            :key="item.key"
            class="flex flex-col sm:flex-row sm:items-center gap-2"
          >
            <label class="text-sm font-medium text-slate-700 sm:w-48 shrink-0">
              {{ keyLabel(item.key) }}
            </label>
            <div
              v-if="item.key === 'allowed_login_methods' && item.type === 'json'"
              class="flex flex-wrap gap-4"
            >
              <label
                v-for="opt in loginMethodOptions"
                :key="opt.value"
                class="flex items-center gap-2 cursor-pointer"
              >
                <input
                  v-model="form[item.key]"
                  type="checkbox"
                  :value="opt.value"
                  class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                >
                <span class="text-sm text-slate-700">{{ opt.label }}</span>
              </label>
              <p class="w-full text-xs text-slate-500 mt-1">
                Marque os métodos que os usuários podem usar para entrar no sistema. Pelo menos um deve estar ativo.
              </p>
            </div>
            <input
              v-else-if="item.type !== 'boolean'"
              v-model="form[item.key]"
              type="text"
              class="input-base flex-1 max-w-md"
            />
            <div v-else class="flex items-center gap-2">
              <input
                :id="'setting-' + item.key"
                v-model="form[item.key]"
                type="checkbox"
                class="rounded border-slate-300"
              />
              <label :for="'setting-' + item.key" class="text-sm text-slate-600">
                {{ form[item.key] ? 'Sim' : 'Não' }}
              </label>
            </div>
          </div>
        </div>
      </div>

      <div v-if="isEmpty" class="rounded-lg border border-slate-200 bg-slate-50 p-6 text-center text-slate-600">
        Nenhuma configuração cadastrada. Execute o seeder de settings para criar as chaves iniciais.
      </div>

      <div v-if="!isEmpty" class="flex justify-end">
        <button
          type="button"
          :disabled="saving"
          @click="handleSave"
          class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:bg-slate-400 disabled:cursor-not-allowed transition-colors"
        >
          <span v-if="saving">Salvando...</span>
          <span v-else>Salvar</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import { useToast } from 'vue-toastification';
import { useSettingsStore } from '@/stores/settings';

const KEY_LABELS = {
  app_name: 'Nome do aplicativo',
  allowed_login_methods: 'Métodos de login permitidos',
  municipality_name: 'Nome do município',
  municipality_state: 'Estado',
  municipality_address: 'Endereço',
  municipality_email: 'E-mail',
  municipality_cnpj: 'CNPJ',
};

const GROUP_LABELS = {
  general: 'Geral',
  auth: 'Login / Autenticação',
  municipality: 'Município',
};

const LOGIN_METHOD_OPTIONS = [
  { value: 'email', label: 'E-mail' },
  { value: 'username', label: 'Usuário' },
  { value: 'matricula', label: 'Matrícula' },
];

export default {
  name: 'SettingsIndex',
  setup() {
    const toast = useToast();
    const settingsStore = useSettingsStore();
    const loading = ref(false);
    const saving = ref(false);
    const form = ref({});

    const groupedSettings = computed(() => {
      const raw = settingsStore.settingsGrouped || {};
      const result = {};
      for (const [group, list] of Object.entries(raw)) {
        const items = (Array.isArray(list) ? list : []).map((s) => ({
          key: s.key,
          type: s.type || 'string',
        }));
        if (items.length) result[group] = items;
      }
      return result;
    });

    const isEmpty = computed(() => Object.keys(groupedSettings.value).length === 0);

    function keyLabel(key) {
      return KEY_LABELS[key] || key;
    }

    function groupLabel(group) {
      return GROUP_LABELS[group] || group;
    }

    function buildFormFromResponse(data) {
      const f = {};
      Object.keys(data || {}).forEach((group) => {
        (Array.isArray(data[group]) ? data[group] : []).forEach((s) => {
          if (s.key === 'allowed_login_methods' && s.type === 'json') {
            f[s.key] = Array.isArray(s.value) ? [...s.value] : ['email', 'username', 'matricula'];
          } else {
            f[s.key] = s.type === 'boolean' ? !!s.value : (s.value ?? '');
          }
        });
      });
      form.value = f;
    }

    const loadSettings = async () => {
      loading.value = true;
      try {
        const data = await settingsStore.fetchSettings();
        buildFormFromResponse(data);
      } catch (error) {
        toast.error('Erro ao carregar configurações.');
      } finally {
        loading.value = false;
      }
    };

    const handleSave = async () => {
      saving.value = true;
      try {
        const settingsArray = [];
        for (const [groupName, list] of Object.entries(groupedSettings.value)) {
          for (const item of list || []) {
            const key = item.key;
            const type = item.type || 'string';
            let value = form.value[key];
            if (key === 'allowed_login_methods' && type === 'json') {
              value = Array.isArray(value) ? value : ['email', 'username', 'matricula'];
            } else if (type === 'boolean') {
              value = !!value;
            }
            settingsArray.push({
              key,
              value,
              type,
              group: groupName,
            });
          }
        }
        await settingsStore.updateSettings(settingsArray);
        toast.success('Configurações salvas.');
      } catch (error) {
        const msg = error.response?.data?.message || 'Erro ao salvar.';
        toast.error(msg);
      } finally {
        saving.value = false;
      }
    };

    onMounted(() => loadSettings());

    return {
      loading,
      saving,
      form,
      groupedSettings,
      isEmpty,
      keyLabel,
      groupLabel,
      loginMethodOptions: LOGIN_METHOD_OPTIONS,
      handleSave,
    };
  },
};
</script>
