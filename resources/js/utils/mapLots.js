import { formatAreaM2, formatPolygonAreaM2 } from '@/utils/mapGeometry';

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
  const raw = String(lot?.size_label ?? '').trim();

  if (!raw) {
    return null;
  }

  return raw.replace(/m$/i, '').replace(/x/gi, '×');
}

export function hasLotDimensionsLabel(lot) {
  return Boolean(formatLotDimensionsLabel(lot));
}

export function buildMapFixedLabelIconHtml(text, labelClass = 'map-lot-context-dimension-label') {
  const safe = String(text)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

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
