<script setup>
import UserMenu from '@/components/Common/UserMenu.vue';
import NotificationsPanel from '@/components/Layout/NotificationsPanel.vue';

const props = defineProps({
  userName: {
    type: String,
    default: 'Usuário',
  },
});

const emit = defineEmits(['toggleSidebar', 'logout']);
</script>

<template>
  <header class="sticky top-0 z-30 flex h-16 items-center justify-between bg-white px-6 shadow-sm border-b border-gray-200">
    <div class="flex items-center gap-3">
      <!-- Mobile menu button -->
      <button
        type="button"
        class="inline-flex items-center justify-center rounded-md p-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 md:hidden"
        @click="emit('toggleSidebar')"
      >
        <span class="sr-only">Abrir menu</span>
        <svg
          class="h-5 w-5"
          xmlns="http://www.w3.org/2000/svg"
          fill="none"
          viewBox="0 0 24 24"
          stroke-width="1.5"
          stroke="currentColor"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"
          />
        </svg>
      </button>
    </div>

    <div class="flex items-center gap-2">
      <NotificationsPanel />
      <UserMenu :user-name="props.userName" @logout="emit('logout')" />
    </div>
  </header>
</template>

