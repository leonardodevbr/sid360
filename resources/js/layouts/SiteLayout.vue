<script setup>
import AppLogo from '@/components/Common/AppLogo.vue';
</script>

<template>
  <div class="flex min-h-screen flex-col bg-sid-cream">
    <header class="shrink-0 border-b border-[#e8e4d8] bg-white">
      <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
        <a href="/" class="shrink-0">
          <AppLogo height-class="h-10 sm:h-12" />
        </a>
        <nav class="flex items-center gap-4 text-sm">
          <router-link
            :to="{ name: 'site.loteamentos' }"
            class="font-medium text-sid-secondary hover:text-sid-accent"
            active-class="text-sid-accent"
          >
            Loteamentos
          </router-link>
          <a
            href="/pagamentos"
            class="font-medium text-sid-secondary hover:text-sid-accent"
          >
            Meus pagamentos
          </a>
        </nav>
      </div>
    </header>

    <main class="flex-1">
      <router-view />
    </main>

    <footer class="mt-auto shrink-0 border-t border-[#e8e4d8] bg-white py-4 text-center text-xs text-slate-500">
      Sid360 Imóveis · Cafarnaum-BA · sid360.com.br
    </footer>
  </div>
</template>
