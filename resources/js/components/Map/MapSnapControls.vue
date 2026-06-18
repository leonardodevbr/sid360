<template>
  <div
    class="map-snap-controls flex flex-wrap items-center gap-1 rounded-lg border border-slate-300 bg-white p-0.5"
    role="group"
    aria-label="Controles de snap magnético"
  >
    <button
      type="button"
      class="map-toolbar-action-btn rounded-md px-2 py-1.5 text-[11px] font-semibold transition-colors sm:px-2.5 sm:text-xs"
      :class="mapSnapEnabled
        ? 'bg-amber-50 text-amber-800 ring-1 ring-amber-200'
        : 'text-slate-500 hover:bg-slate-50'"
      :aria-pressed="mapSnapEnabled"
      @click="toggleMapSnapEnabled"
    >
      Magnético
    </button>
    <button
      type="button"
      class="map-toolbar-action-btn rounded-md px-2 py-1.5 text-[11px] font-semibold transition-colors sm:px-2.5 sm:text-xs"
      :class="mapSnapEnabled && mapSnapPoints
        ? 'bg-slate-100 text-slate-800'
        : 'text-slate-400'"
      :disabled="!mapSnapEnabled"
      :aria-pressed="mapSnapEnabled && mapSnapPoints"
      @click="toggleMapSnapPoints"
    >
      Pontos
    </button>
    <button
      type="button"
      class="map-toolbar-action-btn rounded-md px-2 py-1.5 text-[11px] font-semibold transition-colors sm:px-2.5 sm:text-xs"
      :class="mapSnapEnabled && mapSnapLines
        ? 'bg-slate-100 text-slate-800'
        : 'text-slate-400'"
      :disabled="!mapSnapEnabled"
      :aria-pressed="mapSnapEnabled && mapSnapLines"
      @click="toggleMapSnapLines"
    >
      Linhas
    </button>
  </div>
</template>

<script setup>
import {
  mapSnapEnabled,
  mapSnapLines,
  mapSnapPoints,
  toggleMapSnapEnabled,
  toggleMapSnapLines,
  toggleMapSnapPoints,
} from '@/utils/mapSnapSettings';
</script>
