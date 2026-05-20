<script setup>
import { computed, ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useAppStore } from '@/stores/app';
import { useSettingsStore } from '@/stores/settings';
import { usePushNotifications } from '@/composables/usePushNotifications';
import Sidebar from '@/components/Layout/Sidebar.vue';
import Header from '@/components/Layout/Header.vue';

const auth = useAuthStore();
const router = useRouter();
const appStore = useAppStore();
const settingsStore = useSettingsStore();
const { register: registerSw, requestPermission, subscribeUser } = usePushNotifications();

const isSidebarOpen = ref(false);

const currentUserName = computed(() => auth.user?.name ?? 'Usuário');

async function handleLogout() {
  await auth.logout();
  router.push({ name: 'login' });
}

async function setupPushNotifications() {
  if (!('serviceWorker' in navigator)) return;

  try {
    const reg = await registerSw();
    if (reg && settingsStore.vapidPublicKey) {
      const permission = await requestPermission();
      if (permission === 'granted') {
        await subscribeUser(settingsStore.vapidPublicKey);
      }
    }
  } catch (e) {
    console.warn('Erro ao configurar notificações push:', e);
  }
}

onMounted(async () => {
  if (auth.token) {
    await auth.fetchMe();
  }
  if (auth.user) {
    try {
      await settingsStore.fetchPublicConfig();
      appStore.appName = settingsStore.publicConfig?.app_name || '';
      if (!appStore.appName) {
        const meta = document.querySelector('meta[name="apple-mobile-web-app-title"]');
        appStore.appName = meta?.getAttribute('content') || '';
      }
      
      // Configura push após carregar vapidPublicKey do settingsStore
      setupPushNotifications();
    } catch (error) {
      console.error('Erro ao carregar configurações públicas:', error);
    }
  }
});
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <Sidebar
      :is-open="isSidebarOpen"
      @close="isSidebarOpen = false"
    />

    <div class="flex flex-col flex-1 transition-all duration-300 md:ml-64">
      <Header
        :user-name="currentUserName"
        @toggleSidebar="isSidebarOpen = !isSidebarOpen"
        @logout="handleLogout"
      />

      <main class="flex-1 overflow-hidden bg-gray-50">
        <div class="h-full p-4 md:p-6">
          <div class="card h-full p-4 md:p-6">
            <router-view />
          </div>
        </div>
      </main>
    </div>
  </div>
</template>

