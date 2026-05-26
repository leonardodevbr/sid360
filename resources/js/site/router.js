import SiteLayout from '@/layouts/SiteLayout.vue';
import Loteamentos from '@/site/pages/Loteamentos.vue';
import LoteamentoDetalhe from '@/site/pages/LoteamentoDetalhe.vue';

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
      {
        path: ':slug',
        name: 'site.loteamento',
        component: LoteamentoDetalhe,
        meta: { publicSite: true },
      },
    ],
  },
];
