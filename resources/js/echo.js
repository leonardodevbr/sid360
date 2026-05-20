import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

let echoInstance = null;

/**
 * Retorna a instância do Echo (Pusher) para canais privados.
 * Só cria/conecta se BROADCAST estiver configurado (PUSHER_APP_KEY) e usuário autenticado.
 */
export function getEcho() {
  const key = import.meta.env.VITE_PUSHER_APP_KEY;
  const cluster = import.meta.env.VITE_PUSHER_APP_CLUSTER || 'sa1';
  const token = typeof window !== 'undefined' ? window.localStorage.getItem('token') : null;

  if (!key || !token) {
    return null;
  }

  if (!echoInstance) {
    const baseUrl = import.meta.env.VITE_APP_URL || (typeof window !== 'undefined' ? window.location.origin : '');
    const authEndpoint = baseUrl.replace(/\/$/, '') + '/api/broadcasting/auth';
    echoInstance = new Echo({
      broadcaster: 'pusher',
      key,
      cluster,
      forceTLS: true,
      authEndpoint,
      auth: {
        headers: {
          Authorization: `Bearer ${token}`,
          Accept: 'application/json',
        },
      },
    });
  }

  return echoInstance;
}

/**
 * Desconecta e limpa a instância do Echo (chamar no logout).
 */
export function disconnectEcho() {
  if (echoInstance) {
    try {
      echoInstance.disconnect();
    } catch (_) {
      // ignore
    }
    echoInstance = null;
  }
}
