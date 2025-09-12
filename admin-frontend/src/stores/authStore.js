import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import apiClient from '../api/axios';

export const useAuthStore = defineStore('auth', () => {
  const user = ref(JSON.parse(localStorage.getItem('user') || 'null'));
  const token = ref(localStorage.getItem('token') || null);
  const tokenType = ref(localStorage.getItem('token_type') || 'Bearer');

  const isAuthenticated = computed(() => !!token.value);

  async function login(credentials) {
    try {
      // 👇 tu backend expone /api/admin/login (sin auth)
      const { data } = await apiClient.post('/admin/login', credentials);
      // Tymon/JWT suele devolver { access_token, token_type, user }
      token.value = data.access_token || data.token;
      tokenType.value = data.token_type || 'Bearer';
      user.value = data.user ?? null;

      localStorage.setItem('token', token.value);
      localStorage.setItem('token_type', tokenType.value);
      localStorage.setItem('user', JSON.stringify(user.value));

      // (Opcional) Valida el token llamando a /admin/me
      // await apiClient.get('/admin/me');

      return !!token.value;
    } catch (err) {
      console.error('Login error:', err);
      return false;
    }
  }

  /**
   * @param {{server?: boolean}} [opts] - si server=false, no llama /logout
   */
  async function logout(opts = {}) {
    const server = opts.server !== false; // por defecto true
    try {
      if (server && token.value) {
        // 👇 protegido por jwt.auth
        await apiClient.post('/admin/logout');
      }
    } catch (e) {
      console.warn('API logout falló, limpiando local igualmente.', e);
    } finally {
      token.value = null;
      tokenType.value = 'Bearer';
      user.value = null;
      localStorage.removeItem('token');
      localStorage.removeItem('token_type');
      localStorage.removeItem('user');
    }
  }

  return { user, token, tokenType, isAuthenticated, login, logout };
});
