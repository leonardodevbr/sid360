export const ZONE_LOT_GENERATION_TYPES = ['quadra', 'conjunto', 'setor'];

export const LOT_SELECTABLE_ZONE_TYPES = ['quadra'];

export const ZONE_TYPE_OPTIONS = [
  { value: 'quadra', label: 'Quadra' },
  { value: 'conjunto', label: 'Conjunto' },
  { value: 'setor', label: 'Setor' },
  { value: 'rua', label: 'Rua' },
  { value: 'outro', label: 'Outro' },
];

export function isLotSelectableZone(zone) {
  return LOT_SELECTABLE_ZONE_TYPES.includes(zone?.type);
}

const ZONE_TYPE_LABELS = {
  quadra: 'Quadra',
  conjunto: 'Conjunto',
  setor: 'Setor',
  rua: 'Rua',
  outro: 'Outro',
};

export function zoneTypeLabel(type) {
  return ZONE_TYPE_LABELS[type] ?? type;
}

export function buildZoneMapLabel(zone) {
  const typeLabel = zoneTypeLabel(zone?.type);
  const name = String(zone?.name ?? '').trim();

  if (!name) return typeLabel;
  if (!typeLabel) return name;

  const normalizedName = name.toLocaleLowerCase('pt-BR');
  const normalizedType = typeLabel.toLocaleLowerCase('pt-BR');

  if (normalizedName.startsWith(normalizedType)) {
    return name;
  }

  return `${typeLabel} ${name}`;
}

export function buildZoneTitleLabel(zone) {
  return buildZoneMapLabel(zone).toLocaleUpperCase('pt-BR');
}

export function canGenerateLotsInZone(zone) {
  return (
    ZONE_LOT_GENERATION_TYPES.includes(zone?.type)
    && Array.isArray(zone?.coordinates)
    && zone.coordinates.length >= 3
  );
}

export function generateLotsBlockedReason(zone) {
  if (!zone) return 'Zona inválida.';

  if (!ZONE_LOT_GENERATION_TYPES.includes(zone.type)) {
    return `Zonas do tipo "${zoneTypeLabel(zone.type)}" não permitem geração automática de lotes.`;
  }

  if (!Array.isArray(zone.coordinates) || zone.coordinates.length < 3) {
    return 'Desenhe a área da zona no mapa antes de gerar lotes.';
  }

  return '';
}

import { formatPolygonAreaM2 } from '@/utils/mapGeometry';

export { computeGeodesicArea } from '@/utils/mapGeometry';

export function zoneShowsLotsCount(type) {
  return ZONE_LOT_GENERATION_TYPES.includes(type);
}

export function buildZoneMetaLabel(zone, lotsCount = 0) {
  const parts = [];
  const areaLabel = formatPolygonAreaM2(zone?.coordinates);

  if (zoneShowsLotsCount(zone?.type)) {
    parts.push(`${lotsCount} lote(s)`);
  }

  if (areaLabel) {
    parts.push(areaLabel);
  } else {
    parts.push('sem área');
  }

  return parts.join(' · ');
}
