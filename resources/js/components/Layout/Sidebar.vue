<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import AppLogo from '@/components/Common/AppLogo.vue';
import {
  HomeIcon,
  BuildingOffice2Icon,
  Squares2X2Icon,
  UsersIcon,
  UserGroupIcon,
  CurrencyDollarIcon,
  Cog6ToothIcon,
} from '@heroicons/vue/24/outline';

defineProps({
  isOpen: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['close']);

const route = useRoute();
const authStore = useAuthStore();

function itemVisible(item) {
  if (authStore.isSuperAdmin) {
    return true;
  }
  if (item.role) {
    const roles = Array.isArray(item.role) ? item.role : [item.role];
    if (!authStore.hasRole(roles)) {
      return false;
    }
  }
  if (!item.permission) {
    return true;
  }
  return authStore.can(item.permission);
}

const menuGroups = computed(() => {
  const allGroups = [
    {
      title: 'Principal',
      items: [
        { name: 'Dashboard', to: { name: 'dashboard' }, icon: HomeIcon, permission: null },
      ],
    },
    {
      title: 'Empreendimentos',
      items: [
        { name: 'Empreendimentos', to: { name: 'developments.index' }, icon: BuildingOffice2Icon, permission: 'developments.view' },
        { name: 'Lotes', to: { name: 'lots.index' }, icon: Squares2X2Icon, permission: 'lots.view' },
      ],
    },
    {
      title: 'Comercial',
      items: [
        { name: 'Clientes', to: { name: 'clients.index' }, icon: UserGroupIcon, permission: 'clients.view' },
        { name: 'Vendas', to: { name: 'sales.index' }, icon: CurrencyDollarIcon, permission: 'sales.view' },
      ],
    },
    {
      title: 'Sistema',
      items: [
        { name: 'Usuários', to: { name: 'users.index' }, icon: UsersIcon, permission: 'users.view' },
        { name: 'Configurações', to: { name: 'settings' }, icon: Cog6ToothIcon, permission: null, role: 'super-admin' },
      ],
    },
  ];

  return allGroups
    .map((group) => ({
      ...group,
      items: group.items.filter((item) => itemVisible(item)),
    }))
    .filter((group) => group.items.length > 0);
});

function isActive(item) {
  if (route.name === item.to.name) {
    return true;
  }
  const menuPrefix = item.to.name?.split('.')[0];
  const currentPrefix = route.name?.split('.')[0];
  return menuPrefix && currentPrefix && menuPrefix === currentPrefix;
}

function handleClick() {
  emit('close');
}
</script>

<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-40 bg-black/50 md:hidden"
    @click="emit('close')"
  />

  <aside
    class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-slate-200 bg-white shadow-lg"
    :class="{
      '-translate-x-full md:translate-x-0': !isOpen,
      'translate-x-0': isOpen,
    }"
  >
    <div class="flex h-16 items-center border-b border-[rgba(28,10,6,0.08)] bg-white px-4">
      <AppLogo height-class="h-10" />
    </div>

    <nav class="flex-1 space-y-1 py-4 text-sm">
      <div
        v-for="group in menuGroups"
        :key="group.title"
        class="mt-4 first:mt-0"
      >
        <p class="mb-1 mt-2 px-4 text-xs font-semibold uppercase tracking-wide text-slate-500">
          {{ group.title }}
        </p>

        <div class="space-y-0.5 px-2">
          <router-link
            v-for="item in group.items"
            :key="item.name"
            :to="item.to"
            class="group flex items-center gap-3 rounded-md border-l-4 border-transparent px-3 py-2 text-sm text-slate-700 hover:bg-slate-100 hover:text-slate-900"
            :class="isActive(item) ? 'border-sid-accent bg-primary-50 text-sid-dark' : ''"
            @click="handleClick"
          >
            <component
              :is="item.icon"
              :class="['h-5 w-5 shrink-0', isActive(item) ? 'text-sid-accent' : 'text-slate-500 group-hover:text-sid-secondary']"
            />
            <span class="truncate">{{ item.name }}</span>
          </router-link>
        </div>
      </div>
    </nav>
  </aside>
</template>
