<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { RouterLink } from 'vue-router';
import publicApi from '@/services/publicApi';

const mobileOpen = ref(false);
const scrolled = ref(false);
const whatsappPhone = ref('5574988230151');

const waUrl = computed(() => `https://wa.me/${whatsappPhone.value}`);

function onScroll() {
  scrolled.value = window.scrollY > 60;
}

async function loadConfig() {
  try {
    const { data } = await publicApi.get('/public/config');
    if (data?.whatsapp) {
      whatsappPhone.value = String(data.whatsapp).replace(/\D/g, '') || whatsappPhone.value;
    }
  } catch {
    // keep default
  }
}

onMounted(() => {
  window.addEventListener('scroll', onScroll, { passive: true });
  loadConfig();
});

onUnmounted(() => window.removeEventListener('scroll', onScroll));

function closeMobile() {
  mobileOpen.value = false;
  document.body.classList.remove('nav-menu-open');
}

function toggleMobile() {
  mobileOpen.value = !mobileOpen.value;
  document.body.classList.toggle('nav-menu-open', mobileOpen.value);
}
</script>

<template>
  <div class="site-public" style="min-height:100vh;display:flex;flex-direction:column;">
    <nav :class="{ scrolled }">
      <RouterLink :to="{ name: 'site.home' }" class="nav-logo">
        <img
          src="/img/logo-full-bg.png"
          alt="Sid360"
          class="nav-logo-img"
        >
      </RouterLink>

      <ul class="nav-links">
        <li>
          <RouterLink :to="{ name: 'site.loteamentos' }" class="nav-link">
            Loteamento
          </RouterLink>
        </li>
        <li>
          <a href="#localizacao" class="nav-link">Localização</a>
        </li>
        <li>
          <a href="#simulador" class="nav-link">Simular</a>
        </li>
        <li>
          <a href="/pagamentos" class="nav-link">Meus pagamentos</a>
        </li>
        <li>
          <a href="#contato" class="nav-cta">Falar com Corretor</a>
        </li>
      </ul>

      <div class="nav-mobile-actions">
        <a
          :href="waUrl"
          class="nav-wa-btn"
          target="_blank"
          rel="noopener noreferrer"
          aria-label="Chamar no WhatsApp"
        >
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
          </svg>
          <span>Chamar</span>
        </a>
        <button
          type="button"
          class="nav-toggle"
          :class="{ 'is-open': mobileOpen }"
          aria-label="Menu"
          :aria-expanded="mobileOpen"
          @click="toggleMobile"
        >
          <span /><span /><span />
        </button>
      </div>
    </nav>

    <div
      class="nav-backdrop"
      :class="{ 'is-visible': mobileOpen }"
      aria-hidden="true"
      @click="closeMobile"
    />

    <aside class="nav-drawer" :class="{ 'is-open': mobileOpen }" :aria-hidden="!mobileOpen">
      <ul class="nav-drawer-links">
        <li>
          <RouterLink :to="{ name: 'site.loteamentos' }" @click="closeMobile">
            Loteamento
          </RouterLink>
        </li>
        <li>
          <a href="#localizacao" @click="closeMobile">Localização</a>
        </li>
        <li>
          <a href="#simulador" @click="closeMobile">Simular</a>
        </li>
        <li>
          <a href="#imoveis" @click="closeMobile">Tipos de imóveis</a>
        </li>
        <li>
          <a href="/pagamentos" @click="closeMobile">Meus pagamentos</a>
        </li>
        <li>
          <a href="#contato" class="nav-drawer-cta" @click="closeMobile">Falar com Corretor</a>
        </li>
      </ul>
    </aside>

    <main style="flex:1;">
      <router-view />
    </main>

    <footer>
      <div class="footer-top">
        <div class="footer-logo">
          <img
            src="/img/logo-full-bg.png"
            alt="Sid360"
            class="footer-logo-img"
          >
        </div>
        <ul class="footer-links">
          <li>
            <RouterLink :to="{ name: 'site.loteamentos' }">
              Loteamento
            </RouterLink>
          </li>
          <li>
            <a href="/pagamentos">Meus pagamentos</a>
          </li>
          <li>
            <a href="#contato">Contato</a>
          </li>
        </ul>
      </div>
      <div class="footer-bottom">
        <div class="footer-copy">
          © {{ new Date().getFullYear() }} Sid360 Imóveis · Cafarnaum, Bahia
        </div>
        <span class="footer-poweredby" style="cursor:default;user-select:none;">Desenvolvido por Nunes, Leonardo</span>
      </div>
    </footer>
  </div>
</template>
