import { RouterView } from 'vue-router';
import SiteLayout from '@/layouts/SiteLayout.vue';
import Home from '@/site/pages/Home.vue';
import Loteamentos from '@/site/pages/Loteamentos.vue';
import LoteamentoDetalhe from '@/site/pages/LoteamentoDetalhe.vue';

export const siteRoutes = [
  {
    path: '/',
    component: SiteLayout,
    children: [
      {
        path: '',
        name: 'site.home',
        component: Home,
        meta: {
          title: 'Sid360 — Lotes e Imóveis em Cafarnaum-BA',
          publicSite: true,
        },
      },
      {
        path: 'loteamentos',
        component: RouterView,
        children: [
          {
            path: '',
            name: 'site.loteamentos',
            component: Loteamentos,
            meta: {
              title: 'Loteamentos disponíveis',
              publicSite: true,
            },
          },
          {
            path: ':slug',
            name: 'site.loteamento',
            component: LoteamentoDetalhe,
            meta: { publicSite: true },
          },
        ],
      },
    ],
  },
];
