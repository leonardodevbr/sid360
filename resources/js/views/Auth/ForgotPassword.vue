<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import Input from '@/components/Common/Input.vue'
import Button from '@/components/Common/Button.vue'
import { useToast } from 'vue-toastification'
import api from '@/services/api'

const router = useRouter()
const toast = useToast()

const email = ref('')
const loading = ref(false)
const formErrors = ref({})
const sent = ref(false)

async function handleSubmit() {
  formErrors.value = {}
  if (!email.value) {
    formErrors.value.email = 'Informe o e-mail.'
    return
  }

  loading.value = true
  try {
    await api.post('/forgot-password', { email: email.value })
    sent.value = true
    toast.success('Enviamos um link para seu e-mail. Verifique sua caixa de entrada.')
  } catch (error) {
    const msg = error.response?.data?.message || error.response?.data?.errors?.email?.[0] || 'Não foi possível enviar o e-mail.'
    toast.error(msg)
    formErrors.value.email = msg
  } finally {
    loading.value = false
  }
}

function backToLogin() {
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="space-y-5">
    <div class="space-y-1">
      <h2 class="text-lg font-semibold text-slate-800">Esqueci minha senha</h2>
      <p class="text-xs text-slate-500">
        Informe o e-mail da sua conta. Enviaremos um link para você redefinir a senha.
      </p>
    </div>

    <form v-if="!sent" class="space-y-5" @submit.prevent="handleSubmit">
      <Input
        id="email"
        v-model="email"
        label="E-mail"
        type="email"
        autocomplete="email"
        :error="formErrors.email"
      />
      <div class="flex gap-2 pt-2">
        <Button type="button" variant="outline" class="flex-1" @click="backToLogin">
          Voltar
        </Button>
        <Button type="submit" :loading="loading" class="flex-1">
          Enviar link
        </Button>
      </div>
    </form>

    <div v-else class="rounded-lg bg-green-50 p-4 text-sm text-green-800">
      <p class="font-medium">E-mail enviado!</p>
      <p class="mt-1 text-green-700">
        Verifique sua caixa de entrada e o spam. O link expira em 60 minutos.
      </p>
      <Button type="button" variant="outline" class="mt-4" @click="backToLogin">
        Voltar ao login
      </Button>
    </div>
  </div>
</template>
