import axios from 'axios';

const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || 'https://api-votacion-backend.azurewebsites.net/api',
  withCredentials: false,
  headers: { Accept: 'application/json' },
});

const DBG = false;

apiClient.interceptors.request.use((config) => {
  const token = localStorage.getItem('token');
  const tokenType = localStorage.getItem('token_type') || 'Bearer';
  if (token) config.headers.Authorization = `${tokenType} ${token}`;
  if (DBG) console.debug('[HTTP]', config.method?.toUpperCase(), config.url, 'Auth?', !!token);
  return config;
});

let isHandling401 = false;
const isAuthEndpoint = (url) =>
  typeof url === 'string' && (url.includes('/login') || url.includes('/logout'));

async function redirectToLoginSPA() {
  try {
    const { default: router } = await import('../router');
    await router.replace({ name: 'Login' });
  } catch {
    window.location.assign('/login');
  }
}

apiClient.interceptors.response.use(
  (res) => res,
  async (error) => {
    const status = error?.response?.status;
    const url = error?.config?.url || '';

    if (status === 401) {
      if (isAuthEndpoint(url)) {
        try {
          const { useAuthStore } = await import('../stores/authStore');
          await useAuthStore().logout({ server: false });
        } catch {}
        await redirectToLoginSPA();
        return Promise.reject(error);
      }

      if (isHandling401) return Promise.reject(error);
      isHandling401 = true;

      try {
        const { useAuthStore } = await import('../stores/authStore');
        await useAuthStore().logout({ server: false });
      } catch {} finally {
        isHandling401 = false;
      }

      await redirectToLoginSPA();
    }
    return Promise.reject(error);
  }
);

export default apiClient;
