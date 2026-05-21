import axios from 'axios';

const PORTAL_TOKEN_KEY = 'portal_token';
const PORTAL_CLIENT_KEY = 'portal_client';

const portalApi = axios.create({
  baseURL: '/api',
});

portalApi.interceptors.request.use((config) => {
  const token = window.localStorage.getItem(PORTAL_TOKEN_KEY);
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

portalApi.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      window.localStorage.removeItem(PORTAL_TOKEN_KEY);
      window.localStorage.removeItem(PORTAL_CLIENT_KEY);
      if (!window.location.pathname.startsWith('/pagamentos')) {
        window.location.href = '/pagamentos';
      }
    }
    return Promise.reject(error);
  },
);

export function getStoredPortalClient() {
  const raw = window.localStorage.getItem(PORTAL_CLIENT_KEY);
  if (!raw) {
    return null;
  }
  try {
    return JSON.parse(raw);
  } catch {
    return null;
  }
}

export function storePortalSession(portalToken, client) {
  window.localStorage.setItem(PORTAL_TOKEN_KEY, portalToken);
  window.localStorage.setItem(PORTAL_CLIENT_KEY, JSON.stringify(client));
}

export function clearPortalSession() {
  window.localStorage.removeItem(PORTAL_TOKEN_KEY);
  window.localStorage.removeItem(PORTAL_CLIENT_KEY);
}

export async function portalAccess(cpf, phone) {
  const { data } = await portalApi.post('/portal/access', { cpf, phone });
  return data;
}

export async function portalDashboard() {
  const { data } = await portalApi.get('/portal/dashboard');
  return data;
}

export async function portalLogout() {
  try {
    await portalApi.post('/portal/logout');
  } finally {
    clearPortalSession();
  }
}

export function buildWhatsAppUrl(number, message) {
  const digits = String(number ?? '').replace(/\D/g, '');
  return `https://wa.me/${digits}?text=${encodeURIComponent(message)}`;
}

export default portalApi;
