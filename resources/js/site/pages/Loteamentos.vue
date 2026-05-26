<script setup>
import { ref, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import publicApi from '@/services/publicApi';
import { developmentSlug } from '@/site/utils/slug';

const developments = ref([]);
const loading = ref(true);

async function load() {
  try {
    const { data } = await publicApi.get('/public/developments');
    developments.value = data ?? [];
  } catch {
    developments.value = [];
  } finally {
    loading.value = false;
  }
}

onMounted(() => load());
</script>

<template>
  <div>
    <section style="background:var(--bg-dark);padding:80px 5% 60px;text-align:center;">
      <span style="display:inline-block;font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:2px;color:var(--accent-dark);margin-bottom:12px;">
        Cafarnaum · Bahia
      </span>
      <h1 style="font-size:clamp(2rem,4vw,3rem);font-weight:800;color:#FAF5EE;letter-spacing:-1px;line-height:1.1;margin-bottom:12px;">
        Loteamentos <span style="color:var(--accent)">disponíveis</span>
      </h1>
      <p style="color:rgba(250,245,238,0.65);font-size:0.95rem;max-width:440px;margin:0 auto;">
        Escolha o empreendimento ideal e veja os lotes disponíveis com simulador e formulário de interesse.
      </p>
    </section>

    <section style="background:var(--bg-page);padding:60px 5%;">
      <div
        v-if="loading"
        style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px;"
      >
        <div
          v-for="index in 3"
          :key="index"
          class="site-skeleton"
          style="height:320px;border-radius:16px;background:#e8e0d4;"
        />
      </div>

      <div
        v-else-if="developments.length"
        style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px;"
      >
        <RouterLink
          v-for="dev in developments"
          :key="dev.id"
          :to="{ name: 'site.loteamento', params: { slug: developmentSlug(dev) } }"
          class="dev-card"
        >
          <div style="height:200px;position:relative;overflow:hidden;background:#1C0A06;">
            <img
              v-if="dev.cover_photo"
              :src="dev.cover_photo"
              :alt="dev.name"
              style="width:100%;height:100%;object-fit:cover;transition:transform 0.5s;"
              loading="lazy"
            >
            <div
              v-else
              style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#1C0A06;"
            >
              <svg style="width:48px;height:48px;color:rgba(201,168,76,0.4);" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
              </svg>
            </div>
            <div style="position:absolute;inset:0;background:linear-gradient(to bottom,transparent 40%,rgba(28,10,6,0.7));" />
            <span
              style="position:absolute;bottom:12px;left:12px;background:var(--accent);color:#FAF5EE;font-size:0.65rem;font-weight:700;padding:4px 10px;border-radius:6px;text-transform:uppercase;"
            >
              {{ dev.lots_available_count }} lote{{ dev.lots_available_count !== 1 ? 's' : '' }} disponível{{ dev.lots_available_count !== 1 ? 'is' : '' }}
            </span>
          </div>

          <div style="padding:20px;flex:1;display:flex;flex-direction:column;gap:8px;">
            <span style="font-size:0.65rem;font-weight:600;text-transform:uppercase;letter-spacing:0.8px;color:var(--accent-dark);">
              Loteamento
            </span>
            <h2 style="font-size:1.1rem;font-weight:700;color:#FAF5EE;margin:0;">
              {{ dev.name }}
            </h2>
            <p v-if="dev.location" style="font-size:0.8rem;color:rgba(250,245,238,0.55);margin:0;">
              {{ dev.location }}
            </p>
            <p v-if="dev.description" style="font-size:0.82rem;color:rgba(250,245,238,0.65);line-height:1.6;margin:0;flex:1;">
              {{ dev.description?.slice(0, 100) }}{{ dev.description?.length > 100 ? '...' : '' }}
            </p>
            <div style="display:flex;align-items:center;justify-content:space-between;margin-top:8px;padding-top:12px;border-top:1px solid rgba(201,168,76,0.15);">
              <span style="font-size:0.78rem;color:rgba(250,245,238,0.45);">
                {{ dev.lots_count }} lotes no total
              </span>
              <span style="color:var(--accent-dark);font-size:0.82rem;font-weight:600;">
                Ver lotes →
              </span>
            </div>
          </div>
        </RouterLink>
      </div>

      <div v-else style="text-align:center;padding:60px 0;color:var(--text-secondary);">
        Nenhum loteamento disponível no momento.
      </div>
    </section>

    <section style="background:var(--bg-darker);padding:60px 5%;text-align:center;border-top:1px solid rgba(201,168,76,0.12);">
      <p style="font-size:1.1rem;font-weight:700;color:#FAF5EE;margin-bottom:16px;">
        Não encontrou o que procura?
      </p>
      <a
        href="https://wa.me/5574988230151"
        target="_blank"
        rel="noopener noreferrer"
        class="btn-whatsapp"
      >
        Falar com o Sid
      </a>
    </section>
  </div>
</template>
