<script setup>
/**
 * Miniatura em SVG do formato real do lote (a partir do polígono salvo em
 * `coordinates`), com as duas maiores cotas (frente/lateral) opcionalmente
 * rotuladas. Quando o lote não tem polígono desenhado, cai para um
 * retângulo proporcional derivado de `size_label` (ex.: "19x23") ou da
 * área — sempre mostra "algum" formato em vez do ícone de casa genérico.
 *
 * O canvas (viewBox) acompanha a proporção real do lote em vez de forçar um
 * quadrado: um lote estreito (ex.: 40x13m) ocupa o espaço disponível de
 * verdade, em vez de "flutuar" pequeno dentro de uma moldura quadrada. O
 * aspecto é limitado por MAX_ASPECT pra não virar uma tira fina demais em
 * lotes muito alongados.
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

const PADDING_RATIO = 0.12;
const MAX_ASPECT = 2.4;

const uid = Math.random().toString(36).slice(2, 9);
const gridId = `lot-shape-grid-${uid}`;
const shadowId = `lot-shape-shadow-${uid}`;

function clamp(value, min, max) {
  return Math.min(max, Math.max(min, value));
}

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

// Polígono real projetado para metros locais (antes de qualquer escala pro SVG).
const projected = computed(() => {
  const points = ring.value;
  if (!points || points.length < 3) {
    return null;
  }
  const origin = computeOriginLatLng([points]);
  const local = projectLatLngRing(points, origin);
  return local.length >= 3 ? local : null;
});

const dims = computed(() => parseSizeLabelMeters(props.sizeLabel));

// Tamanho "natural" do lote — largura x altura da caixa que envolve o
// formato, em metros — do polígono real ou, na falta dele, do size_label/área.
const naturalSize = computed(() => {
  const local = projected.value;
  if (local) {
    const xs = local.map((p) => p[0]);
    const ys = local.map((p) => p[1]);
    return {
      width: Math.max(Math.max(...xs) - Math.min(...xs), 0.5),
      height: Math.max(Math.max(...ys) - Math.min(...ys), 0.5),
    };
  }

  if (dims.value) {
    return { width: dims.value[0], height: dims.value[1] };
  }
  if (props.area && Number(props.area) > 0) {
    const side = Math.sqrt(Number(props.area));
    return { width: side * 0.82, height: side * 1.22 };
  }
  return { width: 10, height: 12 };
});

// Canvas (viewBox) na proporção do lote, limitado a MAX_ASPECT, normalizado
// para que a maior dimensão seja `props.size`.
const canvas = computed(() => {
  const { width, height } = naturalSize.value;
  const ratio = clamp(width / height, 1 / MAX_ASPECT, MAX_ASPECT);
  return ratio >= 1
    ? { width: props.size, height: props.size / ratio }
    : { width: props.size * ratio, height: props.size };
});

function fitToCanvas(width, height) {
  const { width: canvasW, height: canvasH } = canvas.value;
  const pad = Math.min(canvasW, canvasH) * PADDING_RATIO;
  const innerW = canvasW - pad * 2;
  const innerH = canvasH - pad * 2;
  const scale = Math.min(innerW / width, innerH / height);
  const drawW = width * scale;
  const drawH = height * scale;
  return {
    scale,
    drawW,
    drawH,
    offsetX: pad + (innerW - drawW) / 2,
    offsetY: pad + (innerH - drawH) / 2,
  };
}

const realShape = computed(() => {
  const local = projected.value;
  if (!local) {
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

  const { scale, offsetX, offsetY, drawH } = fitToCanvas(width, height);

  const pts = local.map(([x, y]) => [
    offsetX + (x - minX) * scale,
    offsetY + (drawH - (y - minY) * scale),
  ]);

  let labels = [];
  if (props.showDimensions) {
    const centroid = pts.reduce((acc, p) => [acc[0] + p[0] / pts.length, acc[1] + p[1] / pts.length], [0, 0]);
    const edges = getPolygonEdgesMeters(ring.value, { closed: true });
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

  const { width, height } = naturalSize.value;
  const { offsetX, offsetY, drawW, drawH } = fitToCanvas(width, height);

  const x0 = offsetX;
  const y0 = offsetY;

  const pts = [
    [x0, y0],
    [x0 + drawW, y0],
    [x0 + drawW, y0 + drawH],
    [x0, y0 + drawH],
  ];

  const labels = props.showDimensions && dims.value
    ? [
      { point: [x0 + drawW / 2, y0 + drawH * 0.22], text: formatDimMeters(width) },
      { point: [x0 + drawW / 2, y0 + drawH * 0.62], text: formatDimMeters(height) },
    ]
    : [];

  return { points: pts, labels };
});

const shape = computed(() => realShape.value ?? fallbackShape.value);

const polygonPoints = computed(() => (shape.value ? shape.value.points.map((p) => p.join(',')).join(' ') : ''));

const strokeWidth = computed(() => Math.max(1.5, props.size / 56));
const vertexRadius = computed(() => Math.max(1.6, props.size / 60));
const fontSize = computed(() => Math.max(8, props.size / 13));
</script>

<template>
  <svg
    v-if="shape"
    :viewBox="`0 0 ${canvas.width} ${canvas.height}`"
    class="lot-shape-thumb"
    preserveAspectRatio="xMidYMid meet"
  >
    <defs>
      <pattern :id="gridId" width="9" height="9" patternUnits="userSpaceOnUse">
        <circle cx="1" cy="1" r="0.55" class="lot-shape-thumb__grid-dot" />
      </pattern>
      <filter :id="shadowId" x="-30%" y="-30%" width="160%" height="160%">
        <feDropShadow dx="0" dy="1.4" stdDeviation="1.6" flood-opacity="0.32" />
      </filter>
    </defs>

    <rect
      x="0"
      y="0"
      :width="canvas.width"
      :height="canvas.height"
      :fill="`url(#${gridId})`"
    />

    <polygon
      :points="polygonPoints"
      class="lot-shape-thumb__polygon"
      :style="{ strokeWidth: `${strokeWidth}px` }"
      :filter="`url(#${shadowId})`"
    />

    <circle
      v-for="(pt, index) in shape.points"
      :key="`v-${index}`"
      :cx="pt[0]"
      :cy="pt[1]"
      :r="vertexRadius"
      class="lot-shape-thumb__vertex"
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

.lot-shape-thumb__grid-dot {
  fill: var(--lot-shape-grid, rgba(150, 120, 60, 0.3));
}

.lot-shape-thumb__polygon {
  fill: var(--lot-shape-fill, rgba(201, 168, 76, 0.16));
  stroke: var(--lot-shape-stroke, rgba(201, 168, 76, 0.9));
  stroke-linejoin: round;
}

.lot-shape-thumb__vertex {
  fill: var(--lot-shape-stroke, rgba(201, 168, 76, 0.9));
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
