import { defineStore } from 'pinia';
import api from '@/services/api';

export const useUserStore = defineStore('user', {
  state: () => ({
    users: [],
    loading: false,
    error: null,
    pagination: null,
  }),
  actions: {
    async fetchUsers(params = {}) {
      this.loading = true;
      this.error = null;

      try {
        const response = await api.get('/users', { params });
        this.users = response.data.data ?? response.data ?? [];
        this.pagination = response.data.meta ?? null;
        return this.users;
      } catch (err) {
        this.error = err.response?.data?.message ?? 'Erro ao carregar usuários';
        throw err;
      } finally {
        this.loading = false;
      }
    },
    async fetchUser(id) {
      this.loading = true;
      this.error = null;

      try {
        const response = await api.get(`/users/${id}`);
        return response.data.data ?? response.data;
      } catch (err) {
        this.error = err.response?.data?.message ?? 'Erro ao carregar usuário';
        throw err;
      } finally {
        this.loading = false;
      }
    },
    async createUser(payload) {
      this.loading = true;
      this.error = null;

      try {
        const response = await api.post('/users', payload);
        const user = response.data.data ?? response.data;
        this.users = [user, ...this.users];
        return user;
      } catch (err) {
        this.error = err.response?.data?.message ?? 'Erro ao criar usuário';
        throw err;
      } finally {
        this.loading = false;
      }
    },
    async updateUser(id, payload) {
      this.loading = true;
      this.error = null;

      try {
        const response = await api.post(`/users/${id}/update`, payload);
        const user = response.data.data ?? response.data;
        const idx = this.users.findIndex((u) => u.id === Number(id));
        if (idx !== -1) this.users[idx] = user;
        return user;
      } catch (err) {
        this.error = err.response?.data?.message ?? 'Erro ao atualizar usuário';
        throw err;
      } finally {
        this.loading = false;
      }
    },
    async deleteUser(id) {
      this.loading = true;
      this.error = null;

      try {
        await api.delete(`/users/${id}`);
        this.users = this.users.filter((u) => u.id !== Number(id));
      } catch (err) {
        this.error = err.response?.data?.message ?? 'Erro ao excluir usuário';
        throw err;
      } finally {
        this.loading = false;
      }
    },
  },
});
