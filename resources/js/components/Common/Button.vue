<script setup>
const props = defineProps({
  variant: {
    type: String,
    default: 'primary', // primary, outline
  },
  loading: {
    type: Boolean,
    default: false,
  },
  type: {
    type: String,
    default: 'button',
  },
});

const baseClasses =
  'inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-offset-1 disabled:cursor-not-allowed disabled:opacity-60';

const variants = {
  primary: 'bg-action text-white hover:bg-action-hover focus:ring-action',
  outline: 'border border-[rgba(28,10,6,0.15)] bg-white text-sid-dark hover:bg-sid-cream-dark focus:ring-sid-gold',
  danger: 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500',
};
</script>

<template>
  <button
    :type="type"
    :disabled="loading"
    :class="[baseClasses, variants[variant] || variants.primary]"
  >
    <svg
      v-if="loading"
      class="-ml-0.5 mr-2 h-4 w-4 animate-spin text-white"
      xmlns="http://www.w3.org/2000/svg"
      fill="none"
      viewBox="0 0 24 24"
    >
      <circle
        class="opacity-25"
        cx="12"
        cy="12"
        r="10"
        stroke="currentColor"
        stroke-width="4"
      />
      <path
        class="opacity-75"
        fill="currentColor"
        d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
      />
    </svg>
    <slot />
  </button>
</template>

