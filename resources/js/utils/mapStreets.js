export const STREET_MIN_POINTS = 4;

export const DEFAULT_STREET_COLOR = '#64748B';

/**
 * @param {number | undefined | null} pointCount
 */
export function hasValidStreetPolygon(pointCount) {
  return (pointCount ?? 0) >= STREET_MIN_POINTS;
}

/**
 * @param {{ color?: string } | null | undefined} street
 */
export function getStreetColor(street) {
  return street?.color || DEFAULT_STREET_COLOR;
}

/**
 * @param {Array<{ coordinates?: Array<[number, number]>, id?: number | string }>} streets
 */
export function getMappedStreets(streets) {
  if (!Array.isArray(streets)) {
    return [];
  }

  return streets.filter((street) =>
    hasValidStreetPolygon(street.coordinates?.length ?? 0),
  );
}
