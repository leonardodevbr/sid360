<script setup>
/**
 * Miniatura em SVG do formato real do lote (a partir do polígono salvo em
 * `coordinates`), com as duas maiores cotas (frente/lateral) opcionalmente
 * rotuladas. Quando o lote não tem polígono desenhado, cai para um
 * retângulo proporcional derivado de `size_label` (ex.: "19x23") ou da
 * área — sempre mostra "algum" formato em vez do ícone de casa genérico.
 *
 * Reaproveita a mesma matemática de projeção/medição usada no mapa
 * administrativo e na Planta Técnica (PDF), evitando duplicar lógica de
 * lat/lng <-> metros.
 */
import { computed } from 'vue';
import { computeOriginLatLng, projectLatLngRing } from '@/utils/technicalMapProjection';
import { normalizePolygonCoordinates, getPolygonEdgesMeters } from '@/utils/mapGeometry';

const props = defineProps({
  coordinates: { type: [Array, String], default: null },
  sizeLabel: { type: String, default: null },
  area: { type: [Number, String], default: null },
  showDimensions: { type: Boolean, default: false },
  size: { type: Number, default: 120 },
});

const PADDING_RATIO = 0.14;

function parseSizeLabelMeters(label) {
  if (!label) {
    return null;
  }
  const match = String(label).match(/(\d+(?:[.,]\d+)?)\s*[x×X]\s*(\d+(?:[.,]\d+)?)/);
  if (!match) {
    return null;
  }
  const a = Number(match[1].replace(',', '.'));
  const b = Number(match[2].replace(',', '.'));
  return Number.isFinite(a) && Number.isFinite(b) && a > 0 && b > 0 ? [a, b] : null;
}

function formatDimMeters(value) {
  return `${value.toLocaleString('pt-BR', { maximumFractionDigits: 1 })} m`;
}

function lerp(from, to, t) {
  return [from[0] + (to[0] - from[0]) * t, from[1] + (to[1] - from[1]) * t];
}

const ring = computed(() => normalizePolygonCoordinates(props.coordinates));

// Polígono real do lote, projetado para metros locais e depois normalizado
// para o viewBox do SVG (eixo Y invertido: latitude cresce p/ norte, SVG
// cresce p/ baixo).
const realShape = computed(() => {
  const points = ring.value;
  if (!points || points.length < 3) {
    return null;
  }

  const origin = computeOriginLatLng([points]);
  const local = projectLatLngRing(points, origin);
  if (local.length < 3) {
    return null;
  }

  const xs = local.map((p) => p[0]);
  const ys = local.map((p) => p[1]);
  const minX = Math.min(...xs);
  const maxX = Math.max(...xs);
  const minY = Math.min(...ys);
  const maxY = Math.max(...ys);
  const width = Math.max(maxX - minX, 0.5);
  const height = Math.max(maxY - minY, 0.5);

  const pad = props.size * PADDING_RATIO;
  const inner = props.size - pad * 2;
  const scale = Math.min(inner / width, inner / height);
  const drawW = width * scale;
  const drawH = height * scale;
  const offsetX = pad + (inner - drawW) / 2;
  const offsetY = pad + (inner - drawH) / 2;

  const pts = local.map(([x, y]) => [
    offsetX + (x - minX) * scale,
    offsetY + (drawH - (y - minY) * scale),
  ]);

  let labels = [];
  if (props.showDimensions) {
    const centroid = pts.reduce((acc, p) => [acc[0] + p[0] / pts.length, acc[1] + p[1] / pts.length], [0, 0]);
    const edges = getPolygonEdgesMeters(points, { closed: true });
    const byLength = [...edges].sort((a, b) => b.lengthMeters - a.lengthMeters);
    const seen = new Set();

    for (const edge of byLength) {
      const key = Math.round(edge.lengthMeters);
      if (seen.has(key)) {
        continue;
      }
      seen.add(key);

      const fromIdx = (edge.from - 1 + pts.length) % pts.length;
      const toIdx = (edge.to - 1 + pts.length) % pts.length;
      const a = pts[fromIdx];
      const b = pts[toIdx];
      if (!a || !b) {
        continue;
      }

      const midpoint = [(a[0] + b[0]) / 2, (a[1] + b[1]) / 2];
      labels.push({ point: lerp(midpoint, centroid, 0.2), text: edge.lengthLabel });
      if (labels.length >= 2) {
        break;
      }
    }
  }

  return { points: pts, labels };
});

// Sem polígono salvo: desenha um retângulo proporcional a partir do
// size_label (ex.: "19x23") ou, na falta dele, da área — só pra dar uma
// noção visual do formato, nunca fica sem nada além do ícone genérico.
const fallbackShape = computed(() => {
  if (realShape.value) {
    return null;
  }

  const dims = parseSizeLabelMeters(props.sizeLabel);
  let width;
  let depth;
  if (dims) {
    [width, depth] = dims;
  } else if (props.area && Number(props.area) > 0) {
    const side = Math.sqrt(Number(props.area));
    width = side * 0.82;
    depth = side * 1.22;
  } else {
    width = 10;
    depth = 12;
  }

  const pad = props.size * PADDING_RATIO;
  const inner = props.size - pad * 2;
  const scale = Math.min(inner / width, inner / depth);
  const drawW = width * scale;
  const drawH = depth * scale;
  const x0 = pad + (inner - drawW) / 2;
  const y0 = pad + (inner - drawH) / 2;

  const pts = [
    [x0, y0],
    [x0 + drawW, y0],
    [x0 + drawW, y0 + drawH],
    [x0, y0 + drawH],
  ];

  const labels = props.showDimensions && dims
    ? [
      { point: [x0 + drawW / 2, y0 + drawH * 0.22], text: formatDimMeters(width) },
      { point: [x0 + drawW / 2, y0 + drawH * 0.62], text: formatDimMeters(depth) },
    ]
    : [];

  return { points: pts, labels };
});

const shape = computed(() => realShape.value ?? fallbackShape.value);

const polygonPoints = computed(() => (shape.value ? shape.value.points.map((p) => p.join(',')).join(' ') : ''));

const strokeWidth = computed(() => Math.max(1.4, props.size / 64));
const fontSize = computed(() => Math.max(8, props.size / 13));
</script>

<template>
  <svg
    v-if="shape"
    :viewBox="`0 0 ${size} ${size}`"
    class="lot-shape-thumb"
    preserveAspectRatio="xMidYMid meet"
  >
    <polygon
      :points="polygonPoints"
      class="lot-shape-thumb__polygon"
      :style="{ strokeWidth: `${strokeWidth}px` }"
    />
    <text
      v-for="(label, index) in shape.labels"
      :key="index"
      :x="label.point[0]"
      :y="label.point[1]"
      class="lot-shape-thumb__label"
      :style="{ fontSize: `${fontSize}px` }"
      text-anchor="middle"
      dominant-baseline="middle"
    >{{ label.text }}</text>
  </svg>
</template>

<style scoped>
.lot-shape-thumb {
  width: 100%;
  height: 100%;
}

.lot-shape-thumb__polygon {
  fill: var(--lot-shape-fill, rgba(201, 168, 76, 0.12));
  stroke: var(--lot-shape-stroke, rgba(201, 168, 76, 0.85));
  stroke-linejoin: round;
}

.lot-shape-thumb__label {
  fill: var(--lot-shape-text, #8a6d2f);
  font-weight: 600;
  paint-order: stroke fill;
  stroke: var(--lot-shape-halo, #fff);
  stroke-width: 3px;
  stroke-linejoin: round;
}
</style>
