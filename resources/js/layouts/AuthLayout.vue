<script setup>
import AppLogo from '@/components/Common/AppLogo.vue';
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-sid-cream px-4">
    <div class="w-full max-w-md">
      <div class="mb-8 flex flex-col items-center text-center">
        <AppLogo height-class="h-16 sm:h-20" />
        <p class="mt-4 text-sm font-medium text-slate-500">
          Lotes e Imóveis em Cafarnaum-BA
        </p>
      </div>
      <div class="card auth-card p-6 sm:p-8">
        <router-view />
      </div>
    </div>
  </div>
</template>
