import { createRouter, createWebHistory } from 'vue-router';
import { useToast } from 'vue-toastification';
import { useAuthStore } from '@/stores/auth';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';

import HomeView from '@/views/Dashboard/Home.vue';
import LoginView from '@/views/Auth/Login.vue';
import ForgotPasswordView from '@/views/Auth/ForgotPassword.vue';
import ResetPasswordView from '@/views/Auth/ResetPassword.vue';
import DevelopmentsIndex from '@/views/Developments/Index.vue';
import DevelopmentForm from '@/views/Developments/Form.vue';
import LotsIndex from '@/views/Lots/Index.vue';
import LotForm from '@/views/Lots/Form.vue';
import UsersIndex from '@/views/Users/Index.vue';
import UserForm from '@/views/Users/UserForm.vue';
import SettingsIndex from '@/views/Settings/Index.vue';
import ProfileIndex from '@/views/Profile/Index.vue';
import PortalLayout from '@/layouts/PortalLayout.vue';
import PortalPayments from '@/views/Portal/Payments.vue';

const portalRoutes = [
  {
    path: '/pagamentos',
    component: PortalLayout,
    children: [
      {
        path: '',
        name: 'portal.payments',
        component: PortalPayments,
        meta: { title: 'Meus pagamentos' },
      },
    ],
  },
];

const devRoutes = import.meta.env.DEV
  ? [
      {
        path: '/app/sales/:id/carne/preview',
        name: 'sales.carne.preview',
        component: () => import('@/views/Sales/CarnePreview.vue'),
        meta: {
          requiresAuth: true,
          permission: 'sales.view',
          title: 'Preview Promissória',
        },
      },
    ]
  : [];

const routes = [
  {
    path: '/login',
    component: AuthLayout,
    children: [
      {
        path: '',
        name: 'login',
        component: LoginView,
        meta: { title: 'Entrar', guestOnly: true },
      },
    ],
    meta: { guestOnly: true },
  },
  {
    path: '/forgot-password',
    component: AuthLayout,
    children: [
      {
        path: '',
        name: 'forgot-password',
        component: ForgotPasswordView,
        meta: { title: 'Esqueci minha senha', guestOnly: true },
      },
    ],
    meta: { guestOnly: true },
  },
  {
    path: '/reset-password',
    component: AuthLayout,
    children: [
      {
        path: '',
        name: 'reset-password',
        component: ResetPasswordView,
        meta: { title: 'Redefinir senha', guestOnly: true },
      },
    ],
    meta: { guestOnly: true },
  },
  ...portalRoutes,
  ...devRoutes,
  {
    path: '/app',
    component: DefaultLayout,
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        name: 'dashboard',
        component: HomeView,
        meta: { title: 'Dashboard' },
      },
      {
        path: 'developments',
        name: 'developments.index',
        component: DevelopmentsIndex,
        meta: { title: 'Empreendimentos', permission: 'developments.view' },
      },
      {
        path: 'developments/create',
        name: 'developments.create',
        component: DevelopmentForm,
        meta: { title: 'Novo empreendimento', permission: 'developments.create' },
      },
      {
        path: 'developments/:id/edit',
        name: 'developments.edit',
        component: DevelopmentForm,
        meta: { title: 'Editar empreendimento', permission: 'developments.edit' },
      },
      {
        path: 'lots',
        name: 'lots.index',
        component: LotsIndex,
        meta: { title: 'Lotes', permission: 'lots.view' },
      },
      {
        path: 'lots/create',
        name: 'lots.create',
        component: LotForm,
        meta: { title: 'Novo lote', permission: 'lots.create' },
      },
      {
        path: 'lots/:id/edit',
        name: 'lots.edit',
        component: LotForm,
        meta: { title: 'Editar lote', permission: 'lots.edit' },
      },
      {
        path: 'clients',
        name: 'clients.index',
        component: () => import('@/views/Clients/Index.vue'),
        meta: { title: 'Clientes', permission: 'clients.view' },
      },
      {
        path: 'clients/new',
        name: 'clients.create',
        component: () => import('@/views/Clients/Form.vue'),
        meta: { title: 'Novo cliente', permission: 'clients.create' },
      },
      {
        path: 'clients/:id/edit',
        name: 'clients.edit',
        component: () => import('@/views/Clients/Form.vue'),
        meta: { title: 'Editar cliente', permission: 'clients.edit' },
      },
      {
        path: 'sales',
        name: 'sales.index',
        component: () => import('@/views/Sales/Index.vue'),
        meta: { title: 'Vendas', permission: 'sales.view' },
      },
      {
        path: 'sales/new',
        name: 'sales.create',
        component: () => import('@/views/Sales/Form.vue'),
        meta: { title: 'Nova venda', permission: 'sales.create' },
      },
      {
        path: 'sales/:id',
        name: 'sales.show',
        component: () => import('@/views/Sales/Show.vue'),
        meta: { title: 'Detalhes da venda', permission: 'sales.view' },
      },
      {
        path: 'users',
        name: 'users.index',
        component: UsersIndex,
        meta: { title: 'Usuários', permission: 'users.view' },
      },
      {
        path: 'users/new',
        name: 'users.create',
        component: UserForm,
        meta: { title: 'Novo usuário', permission: 'users.create' },
      },
      {
        path: 'users/:id/edit',
        name: 'users.edit',
        component: UserForm,
        meta: { title: 'Editar usuário', permission: 'users.edit' },
      },
      {
        path: 'profile',
        name: 'profile',
        component: ProfileIndex,
        meta: { title: 'Perfil' },
      },
      {
        path: 'settings',
        name: 'settings',
        component: SettingsIndex,
        meta: { title: 'Configurações', roles: ['super-admin'] },
      },
    ],
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: '/app',
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

router.beforeEach((to, from, next) => {
  const token = window.localStorage.getItem('token');
  const toast = useToast();

  if (to.meta.requiresAuth && !token) {
    next({ name: 'login' });
    return;
  }

  if (to.meta.guestOnly && token) {
    next({ name: 'dashboard' });
    return;
  }

  if (to.path.startsWith('/pagamentos')) {
    next();
    return;
  }

  if (to.meta.permission && token) {
    const authStore = useAuthStore();
    if (!authStore.user) {
      next({ name: 'login' });
      return;
    }
    if (authStore.isSuperAdmin || authStore.can(to.meta.permission)) {
      next();
      return;
    }
    toast.error('Você não tem permissão para acessar esta página.');
    next({ name: 'dashboard' });
    return;
  }

  if (to.meta.roles && token) {
    const authStore = useAuthStore();
    if (!authStore.user) {
      next({ name: 'login' });
      return;
    }
    if (authStore.isSuperAdmin) {
      next();
      return;
    }
    const requiredRoles = Array.isArray(to.meta.roles) ? to.meta.roles : [to.meta.roles];
    if (!authStore.hasRole(requiredRoles)) {
      toast.error('Você não tem permissão para acessar esta página.');
      next({ name: 'dashboard' });
      return;
    }
  }

  next();
});

router.afterEach((to) => {
  const title = to.meta.title;
  const appName = document.querySelector('meta[name="apple-mobile-web-app-title"]')?.getAttribute('content') || 'Sid360';
  document.title = title ? `${title} | ${appName}` : appName;
});

export default router;
