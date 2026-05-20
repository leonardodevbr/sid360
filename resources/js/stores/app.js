import { defineStore } from 'pinia';
import api from '@/services/api';

export const useAppStore = defineStore('app', {
  state: () => ({
    appName: '',
    currentDepartment: JSON.parse(window.localStorage.getItem('currentDepartment') || 'null'),
    departments: [],
    loadingDepartments: false,
  }),
  actions: {
    async fetchConfig() {
      try {
        const response = await api.get('/config');
        this.appName = response.data?.app_name || '';
        return response.data;
      } catch (e) {
        return {};
      }
    },
    async fetchDepartments() {
      if (this.loadingDepartments) return;
      this.loadingDepartments = true;
      try {
        const response = await api.get('/departments?all=1');
        let items = [];
        if (response.data) {
          if (Array.isArray(response.data)) {
            items = response.data;
          } else if (response.data.data && Array.isArray(response.data.data)) {
            items = response.data.data;
          }
        }
        this.departments = items;
        if (!this.currentDepartment && items.length > 0) {
          this.setDepartment(items[0]);
        }
      } catch (e) {
        console.error('Erro ao carregar secretarias', e);
      } finally {
        this.loadingDepartments = false;
      }
    },
    setDepartment(department) {
      this.currentDepartment = department ? { id: department.id, name: department.name } : null;

      if (this.currentDepartment) {
        window.localStorage.setItem('currentDepartment', JSON.stringify(this.currentDepartment));
        window.localStorage.setItem('selected_department_id', String(this.currentDepartment.id));
      } else {
        window.localStorage.removeItem('currentDepartment');
        window.localStorage.removeItem('selected_department_id');
      }

      window.location.reload();
    },
  },
});
