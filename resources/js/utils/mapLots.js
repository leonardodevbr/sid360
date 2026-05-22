const LOT_STATUS_MAP_STYLES = {
  available: { color: '#2d6a45', fill: '#3d8a5a' },
  reserved: { color: '#92400e', fill: '#f59e0b' },
  sold: { color: '#475569', fill: '#94a3b8' },
};

export function getLotMapStyle(status) {
  return LOT_STATUS_MAP_STYLES[status] ?? LOT_STATUS_MAP_STYLES.available;
}

export function buildLotMapLabel(lot) {
  const blockLabel = lot.block ? `Quadra ${lot.block}` : lot.zone?.name;

  return blockLabel ? `${blockLabel} · Lote ${lot.number}` : `Lote ${lot.number}`;
}
