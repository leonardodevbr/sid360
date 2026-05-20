<template>
    <div class="space-y-2">
      <label v-if="label" class="block text-sm font-medium text-slate-700">{{ label }}</label>
  
      <!-- Toolbar de controle -->
      <div class="flex items-center gap-2 flex-wrap">
        <button
          v-if="!readonly && !drawing"
          type="button"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 transition-colors"
          @click="startDrawing"
        >
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <polygon points="3 11 22 2 13 21 11 13 3 11"/>
          </svg>
          {{ hasPolygon ? 'Redesenhar lote' : 'Desenhar lote no mapa' }}
        </button>
  
        <button
          v-if="!readonly && drawing"
          type="button"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border border-amber-300 bg-amber-50 text-amber-700 hover:bg-amber-100 transition-colors"
          @click="cancelDrawing"
        >
          Cancelar desenho
        </button>
  
        <button
          v-if="!readonly && hasPolygon && !drawing"
          type="button"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border border-red-200 bg-white text-red-600 hover:bg-red-50 transition-colors"
          @click="clearPolygon"
        >
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
          </svg>
          Limpar
        </button>
  
        <span v-if="drawing" class="text-xs text-amber-600 font-medium">
          Clique no mapa para definir os pontos do lote. Clique no primeiro ponto para fechar.
        </span>
  
        <span v-if="computedArea && hasPolygon" class="ml-auto text-xs text-slate-500">
          Área calculada: <strong class="text-slate-800">{{ computedArea }} m²</strong>
        </span>
      </div>
  
      <!-- Mapa -->
      <div
        ref="mapContainer"
        class="w-full rounded-lg border border-slate-300 overflow-hidden"
        :style="{ height: height }"
      />
  
      <!-- Coordenadas (debug opcional) -->
      <p v-if="showCoords && modelValue?.length" class="text-xs text-slate-400 font-mono truncate">
        {{ modelValue.length }} pontos · {{ modelValue[0] }}...
      </p>
    </div>
  </template>
  
  <script setup>
  import { ref, onMounted, onUnmounted, watch, computed, nextTick } from 'vue'
  
  // NOTE: instalar via npm install leaflet leaflet-draw
  // e importar CSS no app.js:
  //   import 'leaflet/dist/leaflet.css'
  //   import 'leaflet-draw/dist/leaflet.draw.css'
  
  const props = defineProps({
    /** Array de [lat, lng] representando os vértices do polígono do lote */
    modelValue: {
      type: Array,
      default: () => [],
    },
    /** Outros lotes do mesmo empreendimento para exibir no mapa (readonly) */
    otherLots: {
      type: Array,
      default: () => [],
      // esperado: [{ id, number, block, status, coordinates: [[lat,lng],...] }]
    },
    /** Modo somente leitura (não permite desenhar) */
    readonly: {
      type: Boolean,
      default: false,
    },
    label: {
      type: String,
      default: 'Localização no mapa',
    },
    height: {
      type: String,
      default: '400px',
    },
    showCoords: {
      type: Boolean,
      default: false,
    },
  })
  
  const emit = defineEmits(['update:modelValue', 'area-calculated'])
  
  // Cafarnaum-BA: -11.4667, -39.9833
  const DEFAULT_CENTER = [-11.4667, -39.9833]
  const DEFAULT_ZOOM = 15
  
  const mapContainer = ref(null)
  let map = null
  let drawnLayer = null       // camada do polígono em edição
  let drawControl = null      // toolbar de desenho do Leaflet.draw
  let otherLotsLayer = null   // camada dos outros lotes
  
  const drawing = ref(false)
  
  const hasPolygon = computed(() => Array.isArray(props.modelValue) && props.modelValue.length >= 3)
  
  /** Calcula área em m² a partir de array de [lat, lng] usando fórmula de Shoelace geodésica */
  const computedArea = computed(() => {
    const coords = props.modelValue
    if (!coords || coords.length < 3) return null
    return Math.round(geodesicArea(coords))
  })
  
  // ─── Lifecycle ────────────────────────────────────────────────────────────────
  
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
  
  // Atualiza mapa quando o valor muda externamente
  watch(() => props.modelValue, (val) => {
    if (!map) return
    renderCurrentPolygon(val)
  }, { deep: true })
  
  // Atualiza outros lotes quando mudam
  watch(() => props.otherLots, () => {
    if (!map) return
    renderOtherLots()
  }, { deep: true })
  
  // ─── Inicialização ────────────────────────────────────────────────────────────
  
  async function initMap() {
    const L = await import('leaflet')
    await import('leaflet-draw')
  
    // Fix ícone padrão Leaflet (problema comum com bundlers)
    delete L.Icon.Default.prototype._getIconUrl
    L.Icon.Default.mergeOptions({
      iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
      iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
      shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
    })
  
    map = L.map(mapContainer.value, {
      center: DEFAULT_CENTER,
      zoom: DEFAULT_ZOOM,
      zoomControl: true,
    })
  
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap contributors',
      maxZoom: 20,
    }).addTo(map)
  
    // Camada editável
    drawnLayer = new L.FeatureGroup().addTo(map)
  
    // Camada dos outros lotes
    otherLotsLayer = new L.FeatureGroup().addTo(map)
  
    if (!props.readonly) {
      setupDrawControl(L)
    }
  
    // Renderiza polígono atual se existir
    if (hasPolygon.value) {
      renderCurrentPolygon(props.modelValue)
    }
  
    // Renderiza outros lotes
    renderOtherLots()
  
    // Ajusta viewport para conter tudo
    fitBounds()
  }
  
  // ─── Controle de Desenho ─────────────────────────────────────────────────────
  
  function setupDrawControl(L) {
    drawControl = new L.Control.Draw({
      draw: {
        polygon: {
          allowIntersection: false,
          showArea: true,
          shapeOptions: {
            color: '#2d6a45',
            fillColor: '#3d8a5a',
            fillOpacity: 0.3,
            weight: 2,
          },
        },
        polyline: false,
        rectangle: false,
        circle: false,
        circlemarker: false,
        marker: false,
      },
      edit: {
        featureGroup: drawnLayer,
        remove: false,
      },
    })
  
    // Polígono criado
    map.on(L.Draw.Event.CREATED, (e) => {
      drawnLayer.clearLayers()
      drawnLayer.addLayer(e.layer)
      drawing.value = false
  
      const coords = e.layer.getLatLngs()[0].map(ll => [ll.lat, ll.lng])
      emit('update:modelValue', coords)
      emit('area-calculated', geodesicArea(coords))
  
      // Remove toolbar após desenhar
      if (drawControl) {
        map.removeControl(drawControl)
        drawControl = null
      }
    })
  
    // Polígono editado
    map.on(L.Draw.Event.EDITED, (e) => {
      e.layers.eachLayer((layer) => {
        const coords = layer.getLatLngs()[0].map(ll => [ll.lat, ll.lng])
        emit('update:modelValue', coords)
        emit('area-calculated', geodesicArea(coords))
      })
    })
  
    map.on(L.Draw.Event.DRAWSTART, () => {
      drawing.value = true
      drawnLayer.clearLayers()
    })
  
    map.on(L.Draw.Event.DRAWSTOP, () => {
      drawing.value = false
    })
  }
  
  function startDrawing() {
    if (!map) return
    import('leaflet').then((L) => {
      import('leaflet-draw').then(() => {
        if (!drawControl) {
          setupDrawControl(L)
        }
        map.addControl(drawControl)
        new L.Draw.Polygon(map, drawControl.options.draw.polygon).enable()
        drawing.value = true
      })
    })
  }
  
  function cancelDrawing() {
    if (map) {
      map.fire('draw:drawstop')
    }
    drawing.value = false
  }
  
  function clearPolygon() {
    if (drawnLayer) drawnLayer.clearLayers()
    emit('update:modelValue', [])
    emit('area-calculated', null)
  }
  
  // ─── Renderização ─────────────────────────────────────────────────────────────
  
  async function renderCurrentPolygon(coords) {
    if (!drawnLayer) return
    drawnLayer.clearLayers()
    if (!coords || coords.length < 3) return
  
    const L = await import('leaflet')
    const polygon = L.polygon(coords, {
      color: '#2d6a45',
      fillColor: '#3d8a5a',
      fillOpacity: 0.35,
      weight: 2,
    })
    drawnLayer.addLayer(polygon)
    fitBounds()
  }
  
  async function renderOtherLots() {
    if (!otherLotsLayer) return
    otherLotsLayer.clearLayers()
  
    const lots = props.otherLots
    if (!lots?.length) return
  
    const L = await import('leaflet')
  
    const colorByStatus = {
      available: { color: '#2d6a45', fill: '#3d8a5a' },
      reserved:  { color: '#92400e', fill: '#f59e0b' },
      sold:      { color: '#475569', fill: '#94a3b8' },
    }
  
    lots.forEach((lot) => {
      if (!lot.coordinates?.length) return
      const style = colorByStatus[lot.status] ?? colorByStatus.available
  
      const polygon = L.polygon(lot.coordinates, {
        color: style.color,
        fillColor: style.fill,
        fillOpacity: 0.25,
        weight: 1.5,
        dashArray: '4 4',
      })
  
      polygon.bindPopup(`
        <div style="font-family:system-ui;font-size:13px;min-width:120px">
          <strong>Lote ${lot.number}${lot.block ? ' · Qd. ' + lot.block : ''}</strong><br>
          <span style="color:#64748b">${statusLabel(lot.status)}</span>
        </div>
      `)
  
      otherLotsLayer.addLayer(polygon)
    })
  }
  
  function fitBounds() {
    if (!map) return
    const allLayers = []
    if (drawnLayer) allLayers.push(drawnLayer)
    if (otherLotsLayer) allLayers.push(otherLotsLayer)
  
    // Verifica se há alguma camada com conteúdo
    const hasBounds = allLayers.some(l => {
      try { return l.getBounds().isValid() } catch { return false }
    })
  
    if (!hasBounds) {
      map.setView(DEFAULT_CENTER, DEFAULT_ZOOM)
      return
    }
  
    import('leaflet').then((L) => {
      const group = L.featureGroup(allLayers)
      try {
        map.fitBounds(group.getBounds(), { padding: [32, 32], maxZoom: 19 })
      } catch { /* bounds inválido */ }
    })
  }
  
  // ─── Helpers ──────────────────────────────────────────────────────────────────
  
  function statusLabel(status) {
    const map = { available: 'Disponível', reserved: 'Reservado', sold: 'Vendido' }
    return map[status] ?? status
  }
  
  /**
   * Calcula área geodésica em m² a partir de array de [lat, lng]
   * Fórmula de Shoelace adaptada para coordenadas geográficas
   */
  function geodesicArea(coords) {
    if (!coords || coords.length < 3) return 0
    const R = 6378137 // raio da Terra em metros
    let area = 0
    const n = coords.length
  
    for (let i = 0; i < n; i++) {
      const [lat1, lng1] = coords[i]
      const [lat2, lng2] = coords[(i + 1) % n]
      const rad1 = (lat1 * Math.PI) / 180
      const rad2 = (lat2 * Math.PI) / 180
      const dLng = ((lng2 - lng1) * Math.PI) / 180
      area += dLng * (2 + Math.sin(rad1) + Math.sin(rad2))
    }
  
    return Math.abs((area * R * R) / 2)
  }
  </script>