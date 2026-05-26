import SiteLayout from '@/layouts/SiteLayout.vue';
import Loteamentos from '@/site/pages/Loteamentos.vue';

export const siteRoutes = [
  {
    path: '/loteamentos',
    component: SiteLayout,
    children: [
      {
        path: '',
        name: 'site.loteamentos',
        component: Loteamentos,
        meta: { title: 'Loteamentos disponíveis', publicSite: true },
      },
    ],
  },
];
