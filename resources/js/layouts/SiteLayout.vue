<script setup>
import { ref } from 'vue';
import { RouterLink } from 'vue-router';
import AppLogo from '@/components/Common/AppLogo.vue';
import { Bars3Icon, XMarkIcon } from '@heroicons/vue/24/outline';

const mobileOpen = ref(false);

function closeMobile() {
  mobileOpen.value = false;
}
</script>

<template>
  <div class="site-public flex min-h-screen flex-col">
    <header style="background:var(--bg-dark);border-bottom:1px solid rgba(201,168,76,0.12);">
      <div style="max-width:1200px;margin:0 auto;padding:16px 5%;display:flex;align-items:center;justify-content:space-between;gap:16px;">
        <a href="/" class="shrink-0">
          <AppLogo height-class="h-10 sm:h-12" />
        </a>

        <nav class="hidden items-center gap-6 text-sm md:flex">
          <RouterLink
            :to="{ name: 'site.loteamentos' }"
            style="font-weight:600;color:rgba(250,245,238,0.75);text-decoration:none;"
            active-class="!text-[var(--accent-dark)]"
          >
            Loteamentos
          </RouterLink>
          <a
            href="/pagamentos"
            style="font-weight:600;color:rgba(250,245,238,0.75);text-decoration:none;"
          >
            Meus pagamentos
          </a>
          <a
            href="/"
            style="font-weight:600;color:rgba(250,245,238,0.75);text-decoration:none;"
          >
            Início
          </a>
        </nav>

        <button
          type="button"
          class="rounded-lg p-2 text-[#FAF5EE] md:hidden"
          aria-label="Abrir menu"
          @click="mobileOpen = !mobileOpen"
        >
          <Bars3Icon v-if="!mobileOpen" class="h-6 w-6" />
          <XMarkIcon v-else class="h-6 w-6" />
        </button>
      </div>

      <div
        v-if="mobileOpen"
        class="border-t border-[rgba(201,168,76,0.12)] px-[5%] py-4 md:hidden"
        style="background:var(--bg-darker);"
      >
        <nav class="flex flex-col gap-3 text-sm">
          <RouterLink
            :to="{ name: 'site.loteamentos' }"
            style="color:#FAF5EE;text-decoration:none;font-weight:600;"
            @click="closeMobile"
          >
            Loteamentos
          </RouterLink>
          <a
            href="/pagamentos"
            style="color:#FAF5EE;text-decoration:none;font-weight:600;"
            @click="closeMobile"
          >
            Meus pagamentos
          </a>
          <a
            href="/"
            style="color:#FAF5EE;text-decoration:none;font-weight:600;"
            @click="closeMobile"
          >
            Início
          </a>
        </nav>
      </div>
    </header>

    <main class="flex-1">
      <router-view />
    </main>

    <footer style="background:var(--bg-darker);border-top:1px solid rgba(201,168,76,0.12);padding:20px 5%;text-align:center;font-size:0.75rem;color:rgba(250,245,238,0.45);">
      Sid360 Imóveis · Cafarnaum-BA · sid360.com.br
    </footer>
  </div>
</template>
