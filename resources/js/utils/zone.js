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

export function computeGeodesicArea(coords) {
  if (!Array.isArray(coords) || coords.length < 3) return null;

  const earthRadius = 6371000;
  let area = 0;

  for (let i = 0; i < coords.length; i += 1) {
    const [lat1, lng1] = coords[i];
    const [lat2, lng2] = coords[(i + 1) % coords.length];
    area +=
      ((lng2 - lng1) * Math.PI) / 180
      * (2 + Math.sin((lat1 * Math.PI) / 180) + Math.sin((lat2 * Math.PI) / 180));
  }

  return Math.round(Math.abs((area * earthRadius * earthRadius) / 2));
}

export function zoneShowsLotsCount(type) {
  return ZONE_LOT_GENERATION_TYPES.includes(type);
}

export function buildZoneMetaLabel(zone, lotsCount = 0) {
  const parts = [zoneTypeLabel(zone?.type)];

  if (zoneShowsLotsCount(zone?.type)) {
    parts.push(`${lotsCount} lote(s)`);
    parts.push(zone?.coordinates?.length >= 3 ? 'área demarcada' : 'sem área');
  } else if (zone?.coordinates?.length >= 3) {
    const area = computeGeodesicArea(zone.coordinates);
    if (area != null) {
      parts.push(`~${area.toLocaleString('pt-BR')} m²`);
    }
  } else {
    parts.push('sem área');
  }

  return parts.join(' · ');
}
