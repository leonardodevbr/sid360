import { formatAreaM2, formatPolygonAreaM2 } from '@/utils/mapGeometry';
import { resolveLotArea, resolveLotDimensionsLabel } from '@/utils/lotMeasures';

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
  return resolveLotDimensionsLabel(lot, { useTimes: true });
}

export function formatLotDimensionsDisplay(lot) {
  return resolveLotDimensionsLabel(lot, { useTimes: false });
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

  return `<span class="map-lot-dimension-label map-lot-dimension-label--lot"><span class="map-lot-dimension-label-title">${escapeMapLabelHtml(title)}</span><span class="map-lot-dimension-label-size">${escapeMapLabelHtml(size)}</span></span>`;
}

export function buildMapFixedLabelIconHtml(text, labelClass = 'map-lot-context-dimension-label map-lot-context-dimension-label--lot') {
  const safe = escapeMapLabelHtml(text);

  return `<span class="${labelClass}">${safe}</span>`;
}

export function buildLotAreaLabel(lot) {
  const storedArea = resolveLotArea(lot);

  if (storedArea != null && !Number.isNaN(storedArea)) {
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
