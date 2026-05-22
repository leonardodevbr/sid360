export const ZONE_LOT_GENERATION_TYPES = ['quadra', 'conjunto', 'setor'];

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
