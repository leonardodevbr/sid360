import api from '@/services/api';

/**
 * Registra o Service Worker para notificações push e opcionalmente solicita permissão.
 */
let swRegistration = null;

function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  const rawData = window.atob(base64);
  const outputArray = new Uint8Array(rawData.length);
  for (let i = 0; i < rawData.length; ++i) {
    outputArray[i] = rawData.charCodeAt(i);
  }
  return outputArray;
}

export function usePushNotifications() {
  async function register() {
    if (!('serviceWorker' in navigator)) return null;
    try {
      const reg = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
      swRegistration = reg;
      return reg;
    } catch (e) {
      console.warn('Service Worker registration failed:', e);
      return null;
    }
  }

  async function requestPermission() {
    if (!('Notification' in window)) return 'denied';
    if (Notification.permission === 'granted') return 'granted';
    if (Notification.permission === 'denied') return 'denied';
    const permission = await Notification.requestPermission();
    return permission;
  }

  async function subscribeUser(vapidPublicKey) {
    if (!swRegistration || !vapidPublicKey) return null;

    try {
      const subscription = await swRegistration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
      });

      // Envia para o backend
      await api.post('/push/subscribe', subscription.toJSON());
      return subscription;
    } catch (e) {
      console.error('Failed to subscribe the user:', e);
      return null;
    }
  }

  return { 
    register, 
    requestPermission, 
    subscribeUser,
    get registration() { return swRegistration; } 
  };
}
