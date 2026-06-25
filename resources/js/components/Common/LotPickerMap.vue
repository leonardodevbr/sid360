<template>
  <div class="space-y-2">
    <div class="flex items-center justify-between flex-wrap gap-2">
      <p class="text-xs text-slate-500">
        Clique em um lote <span class="text-[#2d6a45] font-medium">disponível</span> para selecioná-lo no mapa.
      </p>
      <div class="flex items-center gap-3 text-xs text-slate-500 flex-wrap">
        <span class="inline-flex items-center gap-1">
          <i class="inline-block w-2.5 h-2.5 rounded-full" style="background:#3d8a5a" /> Disponível
        </span>
        <span class="inline-flex items-center gap-1">
          <i class="inline-block w-2.5 h-2.5 rounded-full" style="background:#f59e0b" /> Reservado
        </span>
        <span class="inline-flex items-center gap-1">
          <i class="inline-block w-2.5 h-2.5 rounded-full" style="background:#94a3b8" /> Vendido
        </span>
        <span class="inline-flex items-center gap-1">
          <i class="inline-block w-2.5 h-2.5 rounded-full" style="background:#1d4ed8" /> Selecionado
        </span>
      </div>
    </div>

    <div
      ref="mapContainer"
      class="w-full rounded-lg border border-slate-300 overflow-hidden"
      :style="{ height }"
    />

    <p v-if="!loading && !lotsWithCoordinates.length" class="text-xs text-amber-600">
      Nenhum lote deste empreendimento tem desenho no mapa ainda. Use a lista abaixo para selecionar.
    </p>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { setupMapBaseLayers } from '@/utils/mapLayers'
import { getLotMapStyle, buildLotMapLabel, buildLotMapMetaText } from '@/utils/mapLots'
import { formatCurrency } from '@/utils/format'

const props = defineProps({
  /** Lotes do empreendimento (mesmo payload de /developments/{id}/lots) */
  lots: {
    type: Array,
    default: () => [],
  },
  /** IDs (string ou número) dos lotes selecionados */
  modelValue: {
    type: Array,
    default: () => [],
  },
  height: {
    type: String,
    default: '480px',
  },
})

const emit = defineEmits(['update:modelValue'])

// Cafarnaum-BA, mesmo fallback usado em LotMap.vue
const DEFAULT_CENTER = [-11.4667, -39.9833]
const DEFAULT_ZOOM = 16
const SELECTED_STYLE = { color: '#1d4ed8', fill: '#3b82f6' }

const mapContainer = ref(null)
const loading = ref(true)
let map = null
let lotsLayer = null
let polygonsByLotId = new Map()

const selectedIds = computed(() => new Set((props.modelValue || []).map((id) => String(id))))

const lotsWithCoordinates = computed(() =>
  (props.lots || []).filter((lot) => Array.isArray(lot.coordinates) && lot.coordinates.length >= 3),
)

function statusLabel(status) {
  return { available: 'Disponível', reserved: 'Reservado', sold: 'Vendido', inactive: 'Inativo' }[status] ?? status
}

/** Lote é clicável se estiver disponível, ou se já é o(s) lote(s) atual(is) desta venda (edição). */
function isSelectable(lot) {
  return lot.status === 'available' || selectedIds.value.has(String(lot.id))
}

function toggleLot(lot) {
  if (!isSelectable(lot)) return

  const id = String(lot.id)
  const current = (props.modelValue || []).map((v) => String(v))

  if (current.includes(id)) {
    emit('update:modelValue', current.filter((v) => v !== id))
    return
  }

  emit('update:modelValue', [...current, id])
}

onMounted(async () => {
  await nextTick()
  await initMap()
})

onUnmounted(() => {
  if (map) {
    map.remove()
    map = null
  }
})

watch(() => props.lots, () => renderLots(), { deep: true })
watch(() => props.modelValue, () => renderLots(), { deep: true })

async function initMap() {
  const L = await import('leaflet')
  await import('leaflet/dist/leaflet.css')

  map = L.map(mapContainer.value, {
    zoomControl: true,
    scrollWheelZoom: false,
  }).setView(DEFAULT_CENTER, DEFAULT_ZOOM)

  await setupMapBaseLayers(map, L)

  lotsLayer = L.featureGroup().addTo(map)

  await renderLots()
  fitBounds()
  loading.value = false
}

async function renderLots() {
  if (!lotsLayer) return

  lotsLayer.clearLayers()
  polygonsByLotId = new Map()

  const L = await import('leaflet')

  lotsWithCoordinates.value.forEach((lot) => {
    const selected = selectedIds.value.has(String(lot.id))
    const selectable = isSelectable(lot)
    const style = selected ? SELECTED_STYLE : getLotMapStyle(lot.status)

    const polygon = L.polygon(lot.coordinates, {
      color: style.color,
      fillColor: style.fill,
      fillOpacity: selected ? 0.55 : lot.status === 'available' ? 0.38 : 0.28,
      weight: selected ? 3 : 1.5,
      dashArray: selectable ? null : '4 4',
    })

    const meta = buildLotMapMetaText(lot, statusLabel(lot.status))
    const valueLabel = lot.total_value ? formatCurrency(lot.total_value) : ''

    polygon.bindTooltip(
      `<div style="font-family:system-ui;font-size:12.5px;min-width:120px">
        <strong>${buildLotMapLabel(lot)}</strong><br>
        <span style="color:#64748b">${meta}</span>
        ${valueLabel ? `<br><strong>${valueLabel}</strong>` : ''}
        ${!selectable ? '<br><span style="color:#b45309">Indisponível</span>' : ''}
      </div>`,
      { sticky: true },
    )

    if (selectable) {
      polygon.on('click', () => toggleLot(lot))
      polygon.on('mouseover', () => polygon.setStyle({ weight: 3 }))
      polygon.on('mouseout', () => polygon.setStyle({ weight: selected ? 3 : 1.5 }))
    }

    polygonsByLotId.set(String(lot.id), polygon)
    lotsLayer.addLayer(polygon)
  })
}

function fitBounds() {
  if (!map || !lotsLayer) return

  try {
    const bounds = lotsLayer.getBounds()
    if (bounds.isValid()) {
      map.fitBounds(bounds, { padding: [32, 32], maxZoom: 19 })
      return
    }
  } catch {
    /* bounds inválido */
  }

  map.setView(DEFAULT_CENTER, DEFAULT_ZOOM)
}
</script>
