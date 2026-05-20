<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import Input from '@/components/Common/Input.vue'
import Button from '@/components/Common/Button.vue'
import { useToast } from 'vue-toastification'
import api from '@/services/api'

const router = useRouter()
const route = useRoute()
const toast = useToast()

const token = ref('')
const email = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const loading = ref(false)
const formErrors = ref({})
const success = ref(false)

onMounted(() => {
  token.value = (route.query.token || '').trim()
  email.value = (route.query.email || '').trim()
  if (!token.value || !email.value) {
    toast.error('Link inválido ou expirado. Solicite uma nova redefinição de senha.')
  }
})

async function handleSubmit() {
  formErrors.value = {}
  if (!token.value || !email.value) {
    toast.error('Link inválido. Use o link que enviamos por e-mail.')
    return
  }
  if (!password.value) {
    formErrors.value.password = 'Informe a nova senha.'
    return
  }
  if (password.value.length < 8) {
    formErrors.value.password = 'A senha deve ter no mínimo 8 caracteres.'
    return
  }
  if (password.value !== passwordConfirmation.value) {
    formErrors.value.password_confirmation = 'As senhas não coincidem.'
    return
  }

  loading.value = true
  try {
    await api.post('/reset-password', {
      token: token.value,
      email: email.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })
    success.value = true
    toast.success('Senha redefinida com sucesso!')
    setTimeout(() => {
      router.push({ name: 'login' })
    }, 2000)
  } catch (error) {
    const msg = error.response?.data?.message || 'Não foi possível redefinir a senha. Link pode estar expirado.'
    toast.error(msg)
    formErrors.value.password = msg
  } finally {
    loading.value = false
  }
}

function goToLogin() {
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="space-y-5">
    <div class="space-y-1">
      <h2 class="text-lg font-semibold text-slate-800">Redefinir senha</h2>
      <p class="text-xs text-slate-500">
        Defina uma nova senha para acessar o sistema.
      </p>
    </div>

    <form v-if="!success && token && email" class="space-y-5" @submit.prevent="handleSubmit">
      <Input
        id="email"
        :model-value="email"
        label="E-mail"
        type="email"
        disabled
      />
      <Input
        id="password"
        v-model="password"
        label="Nova senha"
        type="password"
        autocomplete="new-password"
        :error="formErrors.password"
      />
      <Input
        id="password_confirmation"
        v-model="passwordConfirmation"
        label="Confirmar nova senha"
        type="password"
        autocomplete="new-password"
        :error="formErrors.password_confirmation"
      />
      <div class="flex gap-2 pt-2">
        <Button type="button" variant="outline" class="flex-1" @click="goToLogin">
          Cancelar
        </Button>
        <Button type="submit" :loading="loading" class="flex-1">
          Redefinir senha
        </Button>
      </div>
    </form>

    <div v-else-if="success" class="rounded-lg bg-green-50 p-4 text-sm text-green-800">
      <p class="font-medium">Senha redefinida!</p>
      <p class="mt-1 text-green-700">Redirecionando para o login...</p>
    </div>

    <div v-else class="rounded-lg bg-amber-50 p-4 text-sm text-amber-800">
      <p class="font-medium">Link inválido ou expirado</p>
      <p class="mt-1 text-amber-700">Solicite uma nova redefinição de senha na tela de login.</p>
      <Button type="button" variant="outline" class="mt-4" @click="goToLogin">
        Ir para o login
      </Button>
    </div>
  </div>
</template>
