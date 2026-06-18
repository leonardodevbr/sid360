import { ZONE_TYPE_OPTIONS } from '@/utils/zone';

const ZONE_LAYER_LABELS = {
  quadra: 'Quadras',
  conjunto: 'Conjuntos',
  setor: 'Setores',
  rua: 'Zonas — rua',
  outro: 'Outras zonas',
};

export const MAP_LAYER_PERIMETER = 'perimeter';
export const MAP_LAYER_STREETS = 'streets';
export const MAP_LAYER_LOTS = 'lots';

export function getZoneMapLayerId(zoneType) {
  return `zone:${zoneType}`;
}

export const MAP_LAYER_OPTIONS = [
  { id: MAP_LAYER_PERIMETER, label: 'Perímetro' },
  ...ZONE_TYPE_OPTIONS.map((option) => ({
    id: getZoneMapLayerId(option.value),
    label: ZONE_LAYER_LABELS[option.value] ?? option.label,
  })),
  { id: MAP_LAYER_STREETS, label: 'Ruas' },
  { id: MAP_LAYER_LOTS, label: 'Lotes' },
];

export const ALL_MAP_LAYER_IDS = MAP_LAYER_OPTIONS.map((option) => option.id);

export const DEFAULT_VISIBLE_MAP_LAYER_IDS = ALL_MAP_LAYER_IDS.filter(
  (layerId) => layerId !== MAP_LAYER_LOTS,
);

export function isMapLayerVisible(visibleLayers, layerId) {
  return Array.isArray(visibleLayers) && visibleLayers.includes(layerId);
}

export function setLeafletLayerVisibility(map, layer, visible) {
  if (!map || !layer) {
    return;
  }

  if (visible) {
    if (!map.hasLayer(layer)) {
      layer.addTo(map);
    }
    return;
  }

  if (map.hasLayer(layer)) {
    map.removeLayer(layer);
  }
}
