export const ZONE_LOT_GENERATION_TYPES = ['quadra', 'conjunto', 'setor'];

export const LOT_SELECTABLE_ZONE_TYPES = ['quadra'];

export const ZONE_TYPE_OPTIONS = [
  { value: 'setor', label: 'Setor' },
  { value: 'conjunto', label: 'Conjunto' },
  { value: 'quadra', label: 'Quadra' },
  { value: 'rua', label: 'Rua' },
  { value: 'outro', label: 'Outro' },
];

/** Ordem topológica: setor engloba conjunto, que engloba quadra. */
export const ZONE_TYPE_RANK = {
  setor: 1,
  conjunto: 2,
  quadra: 3,
  rua: 4,
  outro: 5,
};

export function getZoneTypeRank(type) {
  return ZONE_TYPE_RANK[type] ?? 99;
}

export function compareZonesByHierarchy(a, b) {
  const rankDiff = getZoneTypeRank(a?.type) - getZoneTypeRank(b?.type);

  if (rankDiff !== 0) {
    return rankDiff;
  }

  const orderA = Number(a?.order);
  const orderB = Number(b?.order);

  if (Number.isFinite(orderA) && Number.isFinite(orderB) && orderA !== orderB) {
    return orderA - orderB;
  }

  return String(a?.name ?? '').localeCompare(String(b?.name ?? ''), 'pt-BR', {
    sensitivity: 'base',
    numeric: true,
  });
}

export function canZoneTypeBeChildOfParent(childType, parentType) {
  if (!childType || !parentType) {
    return false;
  }

  return getZoneTypeRank(parentType) < getZoneTypeRank(childType);
}

export function getZoneDescendantIds(zones, zoneId) {
  const descendants = new Set();

  const walk = (parentId) => {
    zones
      .filter((zone) => String(zone.parent_zone_id) === String(parentId))
      .forEach((zone) => {
        descendants.add(zone.id);
        walk(zone.id);
      });
  };

  if (zoneId != null) {
    walk(zoneId);
  }

  return descendants;
}

export function getValidParentZones(zones, childType, editingZoneId = null) {
  const blockedIds = new Set();

  if (editingZoneId != null) {
    blockedIds.add(editingZoneId);
    getZoneDescendantIds(zones, editingZoneId).forEach((id) => blockedIds.add(id));
  }

  return zones.filter((zone) => {
    if (blockedIds.has(zone.id)) {
      return false;
    }

    return canZoneTypeBeChildOfParent(childType, zone.type);
  });
}

/**
 * Monta lista achatada em profundidade: setor → conjunto → quadra.
 * @returns {Array<{ zone: object, depth: number }>}
 */
export function buildZoneHierarchyList(zones) {
  if (!Array.isArray(zones) || !zones.length) {
    return [];
  }

  const zoneIds = new Set(zones.map((zone) => zone.id));
  const childrenByParent = new Map();

  zones.forEach((zone) => {
    const parentId = zone.parent_zone_id && zoneIds.has(zone.parent_zone_id)
      ? zone.parent_zone_id
      : null;

    if (!childrenByParent.has(parentId)) {
      childrenByParent.set(parentId, []);
    }

    childrenByParent.get(parentId).push(zone);
  });

  childrenByParent.forEach((children) => {
    children.sort(compareZonesByHierarchy);
  });

  const result = [];

  const walk = (parentId, depth) => {
    const children = childrenByParent.get(parentId) ?? [];

    children.forEach((zone) => {
      result.push({ zone, depth });
      walk(zone.id, depth + 1);
    });
  };

  walk(null, 0);

  return result;
}

export function getZoneParentName(zones, zone) {
  if (!zone?.parent_zone_id) {
    return null;
  }

  const parent = zones.find((item) => item.id === zone.parent_zone_id);
  return parent?.name ?? null;
}

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
