<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { UserCircleIcon, ChevronDownIcon, ArrowRightOnRectangleIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
  userName: {
    type: String,
    required: true,
  },
});

const emit = defineEmits(['logout']);

const router = useRouter();
const isOpen = ref(false);
const dropdownRef = ref(null);

function toggleDropdown() {
  isOpen.value = !isOpen.value;
}

function goToProfile() {
  router.push({ name: 'profile' });
  isOpen.value = false;
}

function handleLogout() {
  emit('logout');
  isOpen.value = false;
}

function handleClickOutside(event) {
  if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
    isOpen.value = false;
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
  <div ref="dropdownRef" class="relative">
    <button
      type="button"
      class="inline-flex cursor-pointer items-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 transition-colors shadow-sm"
      @click.stop="toggleDropdown"
    >
      <UserCircleIcon class="mr-2 h-4 w-4 text-slate-500" aria-hidden="true" />
      <span class="hidden sm:inline">{{ userName }}</span>
      <ChevronDownIcon
        class="ml-2 h-4 w-4 text-slate-500 transition-transform"
        :class="{ 'rotate-180': isOpen }"
        aria-hidden="true"
      />
    </button>

    <Transition
      enter-active-class="transition ease-out duration-100"
      enter-from-class="transform opacity-0 scale-95"
      enter-to-class="transform opacity-100 scale-100"
      leave-active-class="transition ease-in duration-75"
      leave-from-class="transform opacity-100 scale-100"
      leave-to-class="transform opacity-0 scale-95"
    >
      <div
        v-show="isOpen"
        class="absolute right-0 z-50 mt-2 w-48 origin-top-right rounded-lg border border-slate-200 bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none"
      >
        <div class="py-1">
          <button
            type="button"
            class="flex w-full items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 transition-colors"
            @click="goToProfile"
          >
            <UserCircleIcon class="h-4 w-4 text-slate-500" />
            Meu Perfil
          </button>
          <div class="border-t border-slate-100"></div>
          <button
            type="button"
            class="flex w-full items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors"
            @click="handleLogout"
          >
            <ArrowRightOnRectangleIcon class="h-4 w-4" />
            Sair
          </button>
        </div>
      </div>
    </Transition>
  </div>
</template>
