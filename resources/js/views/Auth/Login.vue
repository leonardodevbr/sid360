<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import Input from '@/components/Common/Input.vue';
import Button from '@/components/Common/Button.vue';
import { useToast } from 'vue-toastification';

const router = useRouter();
const auth = useAuthStore();
const toast = useToast();

const login = ref('');
const password = ref('');
const formErrors = ref({});

async function handleSubmit() {
  formErrors.value = {};

  if (!login.value) {
    formErrors.value.login = 'Informe seu e-mail ou usuário.';
  }
  if (!password.value) {
    formErrors.value.password = 'Informe a senha.';
  }

  if (Object.keys(formErrors.value).length > 0) {
    return;
  }

  try {
    await auth.login(login.value, password.value);
    toast.success('Login realizado com sucesso.');
    router.push({ name: 'dashboard' });
  } catch (error) {
    const errors = error.response?.data?.errors;
    const message = errors?.login?.[0] || errors?.email?.[0] || error.response?.data?.message || 'Falha ao autenticar.';
    toast.error(message);
  }
}
</script>

<template>
  <form
    class="space-y-5"
    @submit.prevent="handleSubmit"
  >
    <div class="space-y-1">
      <h2 class="text-lg font-semibold text-sid-dark">Entrar</h2>
      <p class="text-xs text-slate-500">
        Use seu e-mail ou usuário e senha cadastrados para acessar o sistema.
      </p>
    </div>

    <Input
      id="login"
      v-model="login"
      label="E-mail ou Usuário"
      type="text"
      autocomplete="username"
      placeholder="exemplo@email.com ou usuario"
      :error="formErrors.login"
    />

    <Input
      id="password"
      v-model="password"
      label="Senha"
      type="password"
      autocomplete="current-password"
      :error="formErrors.password"
    />

    <div class="pt-2">
      <Button
        type="submit"
        :loading="auth.loading"
        class="w-full justify-center"
      >
        Entrar
      </Button>
    </div>

    <p class="text-center text-sm">
      <router-link
        :to="{ name: 'forgot-password' }"
        class="font-medium text-action hover:text-action-hover hover:underline"
      >
        Esqueci minha senha / Primeiro acesso
      </router-link>
    </p>
  </form>
</template>

