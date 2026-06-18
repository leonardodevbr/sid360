/** Fixed map colors by feature type (independent of per-record color fields). */

export const ZONE_TYPE_MAP_STYLES = {
  setor: { color: '#6D28D9', fill: '#A78BFA' },
  conjunto: { color: '#1D4ED8', fill: '#60A5FA' },
  quadra: { color: '#0E7490', fill: '#22D3EE' },
  rua: { color: '#B45309', fill: '#FCD34D' },
  outro: { color: '#7C3AED', fill: '#C4B5FD' },
};

const DEFAULT_ZONE_MAP_STYLE = ZONE_TYPE_MAP_STYLES.outro;

export function getZoneTypeMapStyle(type) {
  return ZONE_TYPE_MAP_STYLES[type] ?? DEFAULT_ZONE_MAP_STYLE;
}

export function getZonePolygonMapOptions(type, { fillOpacity = 0.15, weight = 2 } = {}) {
  const style = getZoneTypeMapStyle(type);

  return {
    color: style.color,
    weight,
    fillColor: style.fill,
    fillOpacity,
    className: 'map-feature-polygon',
  };
}
