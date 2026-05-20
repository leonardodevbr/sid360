import { defineStore } from 'pinia';
import api from '@/services/api';
import { disconnectEcho } from '@/echo';

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: JSON.parse(window.localStorage.getItem('user') || 'null'),
    token: window.localStorage.getItem('token') || null,
    loading: false,
    error: null,
  }),
  getters: {
    isAuthenticated: (state) => Boolean(state.token),
    can: (state) => (permissionName) => {
      if (!state.user) return false;
      const roles = state.user.roles || [];
      if (roles.some((r) => (typeof r === 'string' ? r : r?.name) === 'super-admin')) {
        return true;
      }
      const permissions = state.user.permissions || [];
      return permissions.some((p) => (typeof p === 'string' ? p : p?.name) === permissionName);
    },
    hasRole: (state) => (roleName) => {
      if (!state.user) return false;
      const roleNames = Array.isArray(roleName) ? roleName : [roleName];
      const roles = state.user.roles || [];
      return roles.some((r) => roleNames.includes(typeof r === 'string' ? r : r?.name));
    },
    isSuperAdmin: (state) => {
      if (!state.user) return false;
      return (state.user.roles || []).some((r) => (typeof r === 'string' ? r : r?.name) === 'super-admin');
    },
  },
  actions: {
    async login(login, password) {
      this.loading = true;
      this.error = null;
      try {
        const response = await api.post('/login', { login, password });
        const { token, user } = response.data;
        this.token = token;
        this.user = user;
        window.localStorage.setItem('token', token);
        window.localStorage.setItem('user', JSON.stringify(user));
        return { success: true };
      } catch (error) {
        const errors = error.response?.data?.errors;
        this.error = errors?.login?.[0] || error.response?.data?.message || 'Não foi possível fazer login.';
        throw error;
      } finally {
        this.loading = false;
      }
    },
    async fetchMe() {
      if (!this.token) return;
      try {
        const { data } = await api.get('/me');
        const user = data?.user ?? data;
        if (user) {
          this.user = user;
          window.localStorage.setItem('user', JSON.stringify(user));
        }
      } catch {
        // token inválido
      }
    },
    logout() {
      disconnectEcho();
      this.token = null;
      this.user = null;
      this.error = null;
      window.localStorage.removeItem('token');
      window.localStorage.removeItem('user');
    },
  },
});
