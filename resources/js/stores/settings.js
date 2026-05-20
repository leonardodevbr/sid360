import { defineStore } from 'pinia';
import api from '@/services/api';

export const useSettingsStore = defineStore('settings', {
  state: () => ({
    settings: {},
    settingsMeta: {},
    /** Dados agrupados retornados pela API: { group: [ { key, value, type, masked } ] } */
    settingsGrouped: {},
    publicConfig: {
      app_name: '',
      vapid_public_key: null,
      municipality: {},
    },
    loading: false,
  }),

  getters: {
    appName: (state) => state.publicConfig.app_name || '',
    vapidPublicKey: (state) => state.publicConfig.vapid_public_key,
    municipality: (state) => state.publicConfig.municipality || {},
    getSetting: (state) => (key) => state.settings[key],
    getSettingMeta: (state) => (key) => state.settingsMeta[key] || null,
  },

  actions: {
    async fetchPublicConfig() {
      try {
        const response = await api.get('/config');
        const data = response.data || {};
        this.publicConfig = {
          app_name: data.app_name || '',
          vapid_public_key: data.vapid_public_key || null,
          municipality: data.municipality || {},
        };
        return this.publicConfig;
      } catch (error) {
        console.error('Erro ao carregar configurações públicas:', error);
        this.publicConfig = { app_name: '', municipality: {} };
        return this.publicConfig;
      }
    },

    async fetchSettings() {
      this.loading = true;
      try {
        const response = await api.get('/settings');
        const grouped = response.data || {};
        this.settingsGrouped = grouped;
        const flatSettings = {};
        const meta = {};
        Object.keys(grouped).forEach((group) => {
          (grouped[group] || []).forEach((setting) => {
            flatSettings[setting.key] = setting.value;
            meta[setting.key] = { masked: !!setting.masked };
          });
        });
        this.settings = flatSettings;
        this.settingsMeta = meta;
        return grouped;
      } catch (error) {
        console.error('Erro ao carregar configurações:', error);
        return {};
      } finally {
        this.loading = false;
      }
    },

    async updateSettings(settingsArray) {
      this.loading = true;
      try {
        await api.post('/settings/update', { settings: settingsArray });
        await this.fetchSettings();
      } catch (error) {
        console.error('Erro ao atualizar configurações:', error);
        throw error;
      } finally {
        this.loading = false;
      }
    },
  },
});
