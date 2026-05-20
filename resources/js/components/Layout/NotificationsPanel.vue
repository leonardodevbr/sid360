<template>
  <div v-if="canShow" class="relative">
    <button
      type="button"
      class="relative inline-flex p-2 text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-lg transition-colors"
      aria-label="Solicitações pendentes"
      @click="open = !open"
    >
      <BellIcon class="h-5 w-5" />
      <span
        v-if="pendingCount > 0"
        class="absolute -top-0.5 -right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white"
      >
        {{ pendingCount > 9 ? '9+' : pendingCount }}
      </span>
    </button>

    <div
      v-if="open"
      class="absolute right-0 mt-2 w-80 rounded-xl border border-slate-200 bg-white shadow-lg z-50"
    >
      <div class="p-3 border-b border-slate-100">
        <h3 class="text-sm font-semibold text-slate-800">Solicitações pendentes</h3>
        <p class="text-xs text-slate-500">Para deferir ou assinar</p>
      </div>
      <div class="max-h-72 overflow-y-auto">
        <template v-if="pending.length === 0 && !loading">
          <p class="p-4 text-sm text-slate-500 text-center">Nenhuma solicitação pendente.</p>
        </template>
        <template v-else-if="loading">
          <p class="p-4 text-sm text-slate-500 text-center">Carregando...</p>
        </template>
        <template v-else>
          <router-link
            v-for="item in pending"
            :key="item.id"
            :to="{ name: 'daily-requests.show', params: { id: item.id } }"
            class="block px-4 py-3 border-b border-slate-50 hover:bg-slate-50 transition-colors"
            @click="open = false"
          >
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-slate-900 truncate">#{{ item.id }} – {{ item.servant?.name ?? 'Servidor' }}</p>
                <p class="text-xs text-slate-500">{{ item.status_label }} · {{ formatCurrency(item.total_value) }}</p>
              </div>
              <span :class="actionBadgeClass(item.status)" class="shrink-0 px-2 py-0.5 text-xs rounded">
                {{ actionLabel(item.status) }}
              </span>
            </div>
          </router-link>
        </template>
      </div>
      <div class="p-2 border-t border-slate-100">
        <router-link
          :to="{ name: 'daily-requests.index' }"
          class="block text-center text-sm font-medium text-blue-600 hover:text-blue-800 py-2"
          @click="open = false"
        >
          Ver todas as solicitações
        </router-link>
      </div>
    </div>

    <div
      v-if="open"
      class="fixed inset-0 z-40"
      aria-hidden="true"
      @click="open = false"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'
import { formatCurrency } from '@/utils/format'
import { BellIcon } from '@heroicons/vue/24/outline'
import { getEcho } from '@/echo'

const authStore = useAuthStore()
const open = ref(false)
const loading = ref(false)
const pending = ref([])
const CHANNEL_NAME = 'private-daily-requests-pending'

const canShow = computed(() => {
  if (!authStore.isAuthenticated) return false
  return (
    authStore.can('daily-requests.view') &&
    (authStore.can('daily-requests.validate') ||
      authStore.can('daily-requests.authorize') ||
      authStore.can('daily-requests.pay'))
  )
})

const pendingCount = computed(() => pending.value.length)

function actionLabel(status) {
  const map = { requested: 'Validar', validated: 'Conceder', authorized: 'Pagar' }
  return map[status] ?? 'Ação'
}

function actionBadgeClass(status) {
  const map = {
    requested: 'bg-amber-100 text-amber-800',
    validated: 'bg-green-100 text-green-800',
    authorized: 'bg-indigo-100 text-indigo-800',
  }
  return map[status] ?? 'bg-slate-100 text-slate-800'
}

async function fetchPending() {
  if (!canShow.value) return
  loading.value = true
  try {
    const { data } = await api.get('/daily-requests/pending-signatures')
    pending.value = data?.data ?? data ?? []
  } catch {
    pending.value = []
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchPending()

  if (canShow.value) {
    const echo = getEcho()
    if (echo) {
      echo.private('daily-requests-pending').listen('.pending-signatures.updated', () => {
        fetchPending()
      })
    }
  }
})

onUnmounted(() => {
  const echo = getEcho()
  if (echo) {
    echo.leave(CHANNEL_NAME)
  }
})

watch(open, (isOpen) => {
  if (isOpen) fetchPending()
})
</script>
