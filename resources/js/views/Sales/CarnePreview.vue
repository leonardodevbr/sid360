<script setup>
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import { fetchCarnePreviewHtml } from '@/services/sale.service';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const failed = ref(false);

onMounted(async () => {
  try {
    const html = await fetchCarnePreviewHtml(route.params.id);
    document.open();
    document.write(html);
    document.close();
  } catch {
    failed.value = true;
    toast.error('Erro ao carregar preview do carnê.');
  }
});

function goBack() {
  router.push({ name: 'sales.show', params: { id: route.params.id } });
}
</script>

<template>
  <div v-if="failed" class="flex min-h-screen flex-col items-center justify-center gap-4 bg-[#f5f0e8] p-6 text-center">
    <p class="text-sm text-slate-600">Não foi possível carregar o preview do carnê.</p>
    <button
      type="button"
      class="rounded-lg bg-[#c9a84c] px-4 py-2 text-sm font-semibold text-[#1a3a28]"
      @click="goBack"
    >
      Voltar para a venda
    </button>
  </div>
</template>
