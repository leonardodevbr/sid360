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
        v-for="(items, group) in sortedGroupedSettings"
        :key="group"
        class="border border-slate-200 rounded-lg p-6"
      >
        <h2
          class="text-base font-semibold text-slate-800"
          :class="group === 'whatsapp_integration' ? 'mb-1' : 'mb-4'"
        >
          {{ groupLabel(group) }}
        </h2>
        <p v-if="group === 'whatsapp_integration'" class="text-xs text-slate-500 mb-4">
          Credenciais do WPPConnect. Campos vazios usam o fallback do arquivo <code class="bg-slate-100 px-1 rounded">.env</code>.
        </p>
        <div class="space-y-4">
          <div
            v-for="item in items"
            :key="item.key"
            class="flex flex-col sm:flex-row sm:items-start gap-2"
          >
            <label class="text-sm font-medium text-slate-700 sm:w-48 shrink-0 sm:pt-2">
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
                  class="rounded border-slate-300 text-sid-accent focus:ring-sid-accent"
                >
                <span class="text-sm text-slate-700">{{ opt.label }}</span>
              </label>
              <p class="w-full text-xs text-slate-500 mt-1">
                Marque os métodos que os usuários podem usar para entrar no sistema. Pelo menos um deve estar ativo.
              </p>
            </div>
            <div
              v-else-if="item.key.endsWith('_message')"
              class="flex-1 max-w-2xl"
            >
              <textarea
                v-model="form[item.key]"
                rows="6"
                class="input-base w-full font-mono text-xs"
                placeholder="Use {nome}, {contrato}, {lote}, {valor}, {vencimento}, {dias}..."
              />
              <p class="text-xs text-slate-400 mt-1">
                Variáveis disponíveis:
                <template v-if="item.key === 'whatsapp_overdue_message'">
                  <code class="bg-slate-100 px-1 rounded">{nome}</code>
                  <code class="bg-slate-100 px-1 rounded">{contrato}</code>
                  <code class="bg-slate-100 px-1 rounded">{lote}</code>
                  <code class="bg-slate-100 px-1 rounded">{qtd_atrasadas}</code>
                  <code class="bg-slate-100 px-1 rounded">{parcelas_atrasadas}</code>
                  <code class="bg-slate-100 px-1 rounded">{dias_atraso}</code>
                  <code class="bg-slate-100 px-1 rounded">{valor_total_atraso}</code>
                  <code class="bg-slate-100 px-1 rounded">{valor_total_corrigido}</code>
                  <code class="bg-slate-100 px-1 rounded">{valor_corrigido}</code>
                  <code class="bg-slate-100 px-1 rounded">{data_pagamento_prevista}</code>
                  <span class="block mt-2 text-slate-500">
                    Inclua as opções *1*, *2* e *3* no final para o cliente responder via WhatsApp.
                  </span>
                </template>
                <template v-else-if="item.key === 'whatsapp_manual_overdue_message'">
                  <code class="bg-slate-100 px-1 rounded">{nome}</code>
                  <code class="bg-slate-100 px-1 rounded">{contrato}</code>
                  <code class="bg-slate-100 px-1 rounded">{parcela}</code>
                  <code class="bg-slate-100 px-1 rounded">{vencimento}</code>
                  <code class="bg-slate-100 px-1 rounded">{valor}</code>
                </template>
                <template v-else-if="item.key === 'whatsapp_bot_menu_message'">
                  <code class="bg-slate-100 px-1 rounded">{nome}</code>
                  <code class="bg-slate-100 px-1 rounded">{portal_url}</code>
                  <span class="block mt-2 text-slate-500">
                    Comandos que o cliente pode digitar: 2ª via, saldo, extrato, contrato, atendimento.
                  </span>
                </template>
                <template v-else>
                  <code class="bg-slate-100 px-1 rounded">{nome}</code>
                  <code class="bg-slate-100 px-1 rounded">{contrato}</code>
                  <code class="bg-slate-100 px-1 rounded">{lote}</code>
                  <code class="bg-slate-100 px-1 rounded">{valor}</code>
                  <code class="bg-slate-100 px-1 rounded">{vencimento}</code>
                  <code class="bg-slate-100 px-1 rounded">{dias}</code>
                  <code class="bg-slate-100 px-1 rounded">{empreendimento}</code>
                  <code class="bg-slate-100 px-1 rounded">{valor_total}</code>
                  <code class="bg-slate-100 px-1 rounded">{primeira_parcela}</code>
                </template>
              </p>
            </div>
            <input
              v-else-if="item.type === 'integer'"
              v-model.number="form[item.key]"
              type="number"
              min="1"
              max="600"
              class="input-base flex-1 max-w-md"
            />
            <div v-else-if="item.masked" class="flex-1 max-w-md space-y-1">
              <input
                v-model="form[item.key]"
                type="password"
                autocomplete="new-password"
                class="input-base w-full"
                :placeholder="maskedPlaceholder(item.key)"
              />
              <p class="text-xs text-slate-500">
                {{ maskedHint(item.key) }}
              </p>
            </div>
            <div v-else-if="item.type !== 'boolean'" class="flex-1 max-w-md space-y-1">
              <input
                v-model="form[item.key]"
                type="text"
                class="input-base w-full"
              />
              <p v-if="envFallbackLabel(item.key)" class="text-xs text-amber-700">
                {{ envFallbackLabel(item.key) }}
              </p>
            </div>
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
          class="px-4 py-2 bg-sid-accent text-white rounded-lg hover:bg-sid-accent-light disabled:bg-slate-400 disabled:cursor-not-allowed transition-colors"
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
  whatsapp_notifications_enabled: 'Notificações WhatsApp ativas',
  whatsapp_welcome_enabled: 'Enviar boas-vindas ao cadastrar venda',
  whatsapp_welcome_message: 'Mensagem de boas-vindas',
  whatsapp_reminder_enabled: 'Enviar lembrete de vencimento',
  whatsapp_reminder_days_before: 'Dias de antecedência do lembrete',
  whatsapp_reminder_message: 'Mensagem de lembrete',
  whatsapp_overdue_enabled: 'Enviar aviso de atraso',
  whatsapp_overdue_message: 'Mensagem de aviso de atraso',
  whatsapp_manual_overdue_message: 'Mensagem — contato manual (atraso)',
  whatsapp_reply_window_hours: 'Janela de resposta (horas)',
  whatsapp_sid_phone: 'WhatsApp do corretor (notificações)',
  whatsapp_bot_enabled: 'Bot WhatsApp ativo',
  whatsapp_bot_menu_message: 'Mensagem do menu do bot',
  wppconnect_base_url: 'URL do servidor WPPConnect',
  wppconnect_session: 'Nome da sessão WPPConnect',
  wppconnect_token: 'Token de acesso WPPConnect',
  whatsapp_webhook_key: 'Chave do webhook WhatsApp',
  wppconnect_timeout: 'Timeout padrão (segundos)',
  wppconnect_media_timeout: 'Timeout de mídia (segundos)',
  email_notifications_enabled: 'Notificações por e-mail ativas',
  email_welcome_enabled: 'E-mail de boas-vindas',
  email_reminder_enabled: 'E-mail de lembrete de vencimento',
  email_overdue_enabled: 'E-mail de inadimplência',
};

const GROUP_LABELS = {
  general: 'Geral',
  auth: 'Login / Autenticação',
  municipality: 'Município',
  whatsapp_integration: 'WhatsApp — Integração (WPPConnect)',
  whatsapp: 'WhatsApp — Mensagens e notificações',
  email: 'Notificações por E-mail',
};

const GROUP_ORDER = [
  'general',
  'auth',
  'municipality',
  'whatsapp_integration',
  'whatsapp',
  'email',
];

const LOGIN_METHOD_OPTIONS = [
  { value: 'email', label: 'E-mail' },
  { value: 'username', label: 'Usuário' },
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
          masked: !!s.masked,
          source: s.source || null,
          configured: !!s.configured,
        }));
        if (items.length) result[group] = items;
      }
      return result;
    });

    const sortedGroupedSettings = computed(() => {
      const raw = groupedSettings.value;
      const ordered = {};
      GROUP_ORDER.forEach((group) => {
        if (raw[group]) ordered[group] = raw[group];
      });
      Object.keys(raw).forEach((group) => {
        if (!ordered[group]) ordered[group] = raw[group];
      });
      return ordered;
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
          if (s.masked) {
            f[s.key] = '';
          } else if (s.key === 'allowed_login_methods' && s.type === 'json') {
            f[s.key] = Array.isArray(s.value)
              ? s.value.filter((m) => m === 'email' || m === 'username')
              : ['email', 'username'];
          } else {
            f[s.key] = s.type === 'boolean'
              ? !!s.value
              : s.type === 'integer'
                ? (s.value ?? '')
                : (s.value ?? '');
          }
        });
      });
      form.value = f;
    }

    function maskedPlaceholder(key) {
      const meta = settingsStore.getSettingMeta(key);
      if (meta?.configured && meta?.source === 'env') return 'Configurado via .env';
      if (meta?.configured && meta?.source === 'database') return 'Configurado — digite para alterar';
      return 'Informe o valor';
    }

    function maskedHint(key) {
      const meta = settingsStore.getSettingMeta(key);
      if (meta?.configured && meta?.source === 'env') {
        return 'Valor ativo vem do .env. Preencha apenas se quiser sobrescrever.';
      }
      if (meta?.configured && meta?.source === 'database') {
        return 'Deixe em branco para manter o valor atual.';
      }
      return 'Obrigatório se não estiver configurado no .env.';
    }

    function envFallbackLabel(key) {
      const meta = settingsStore.getSettingMeta(key);
      if (meta?.source === 'env' && meta?.configured) {
        return 'Usando valor do .env. Limpe o campo e salve para continuar com o fallback.';
      }
      return '';
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
        for (const [groupName, list] of Object.entries(sortedGroupedSettings.value)) {
          for (const item of list || []) {
            const key = item.key;
            const type = item.type || 'string';
            let value = form.value[key];
            if (item.masked && (value === null || value === undefined || value === '')) {
              continue;
            }
            if (key === 'allowed_login_methods' && type === 'json') {
              value = Array.isArray(value)
                ? value.filter((m) => m === 'email' || m === 'username')
                : ['email', 'username'];
            } else if (type === 'boolean') {
              value = !!value;
            } else if (type === 'integer') {
              if (value === '' || value === null || value === undefined) {
                value = '';
              } else {
                value = parseInt(value, 10) || 0;
              }
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
      sortedGroupedSettings,
      isEmpty,
      keyLabel,
      groupLabel,
      maskedPlaceholder,
      maskedHint,
      envFallbackLabel,
      loginMethodOptions: LOGIN_METHOD_OPTIONS,
      handleSave,
    };
  },
};
</script>
