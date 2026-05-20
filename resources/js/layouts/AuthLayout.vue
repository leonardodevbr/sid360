<script setup>
import AppLogo from '@/components/Common/AppLogo.vue';
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-slate-50 px-4">
    <div class="w-full max-w-md">
      <div class="mb-6 flex flex-col items-center">
        <AppLogo icon-class="h-16 w-16" text-class="text-2xl" />
        <p class="mt-2 text-sm text-slate-500">Sistema de Concessão de Diárias</p>
      </div>
      <div class="card p-6">
        <router-view />
      </div>
    </div>
  </div>
</template>

