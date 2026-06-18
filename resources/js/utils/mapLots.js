import { formatAreaM2, formatPolygonAreaM2 } from '@/utils/mapGeometry';

export const LOT_MAP_DIMENSION_LABEL_MIN_ZOOM = 17;

const LOT_STATUS_MAP_STYLES = {
  available: { color: '#2d6a45', fill: '#3d8a5a' },
  reserved: { color: '#92400e', fill: '#f59e0b' },
  sold: { color: '#475569', fill: '#94a3b8' },
  inactive: { color: '#64748b', fill: '#cbd5e1' },
};

export function getLotMapStyle(status) {
  return LOT_STATUS_MAP_STYLES[status] ?? LOT_STATUS_MAP_STYLES.available;
}

export function buildLotMapLabel(lot) {
  const blockLabel = lot.block || lot.zone?.name;

  return blockLabel ? `${blockLabel} · Lote ${lot.number}` : `Lote ${lot.number}`;
}

export function formatLotDimensionsLabel(lot) {
  return formatLotDimensionsDisplay(lot)?.replace(/x/g, '×') ?? null;
}

export function formatLotDimensionsDisplay(lot) {
  const raw = String(lot?.size_label ?? '').trim();

  if (!raw) {
    return null;
  }

  return raw.replace(/m$/i, '').replace(/×/gi, 'x').replace(/X/g, 'x');
}

export function hasLotDimensionsLabel(lot) {
  return Boolean(formatLotDimensionsDisplay(lot));
}

export function buildLotDimensionLabelTitle(lot) {
  const number = String(lot?.number ?? '').trim();

  return number ? `Lote ${number}` : 'Lote';
}

export function shouldShowLotDimensionLabelsAtZoom(zoom) {
  return Number(zoom) >= LOT_MAP_DIMENSION_LABEL_MIN_ZOOM;
}

function escapeMapLabelHtml(value) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

export function buildLotDimensionLabelMarkerHtml(lot) {
  const size = formatLotDimensionsDisplay(lot);

  if (!size) {
    return null;
  }

  const title = buildLotDimensionLabelTitle(lot);

  return `<span class="map-lot-dimension-label"><span class="map-lot-dimension-label-title">${escapeMapLabelHtml(title)}</span><span class="map-lot-dimension-label-size">${escapeMapLabelHtml(size)}</span></span>`;
}

export function buildMapFixedLabelIconHtml(text, labelClass = 'map-lot-context-dimension-label') {
  const safe = escapeMapLabelHtml(text);

  return `<span class="${labelClass}">${safe}</span>`;
}

export function buildLotAreaLabel(lot) {
  const storedArea = lot?.area_computed ?? lot?.area;

  if (storedArea != null && storedArea !== '') {
    return formatAreaM2(storedArea, { approximate: false });
  }

  if (lot?.coordinates?.length >= 3) {
    return formatPolygonAreaM2(lot.coordinates);
  }

  return null;
}

export function buildLotMapMetaParts(lot, statusLabel = null) {
  const parts = [];

  if (statusLabel) {
    parts.push(statusLabel);
  }

  const areaLabel = buildLotAreaLabel(lot);
  if (areaLabel) {
    parts.push(areaLabel);
  }

  const dimensionsLabel = formatLotDimensionsLabel(lot);
  if (dimensionsLabel) {
    parts.push(dimensionsLabel);
  }

  return parts;
}

export function buildLotMapMetaText(lot, statusLabel = null) {
  return buildLotMapMetaParts(lot, statusLabel).join(' · ');
}

export function buildLotDeleteConfirmMessage(lot) {
  const number = String(lot?.number ?? '—').trim();
  const location = lot?.zone?.name || lot?.block || null;

  if (location) {
    return `Excluir o lote ${number} alocado em ${location}?`;
  }

  return `Excluir o lote ${number}?`;
}
