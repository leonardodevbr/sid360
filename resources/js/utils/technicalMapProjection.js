const METERS_PER_DEGREE_LAT = 111320;

function metersPerDegreeLng(lat) {
  return METERS_PER_DEGREE_LAT * Math.cos((lat * Math.PI) / 180);
}

export function normalizeLatLngPoint(point) {
  if (!point) {
    return null;
  }

  const lat = Number(Array.isArray(point) ? point[0] : point.lat);
  const lng = Number(Array.isArray(point) ? point[1] : point.lng);

  if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
    return null;
  }

  return [lat, lng];
}

export function normalizeLatLngRing(ring) {
  if (!Array.isArray(ring)) {
    return [];
  }

  return ring.map((point) => normalizeLatLngPoint(point)).filter(Boolean);
}

export function computeOriginLatLng(pointGroups) {
  const points = pointGroups.flat();

  if (!points.length) {
    return [-11.4667, -39.9833];
  }

  const lat = points.reduce((sum, point) => sum + point[0], 0) / points.length;
  const lng = points.reduce((sum, point) => sum + point[1], 0) / points.length;

  return [lat, lng];
}

export function latLngToLocalMeters(point, origin) {
  const normalized = normalizeLatLngPoint(point);
  const originPoint = normalizeLatLngPoint(origin);

  if (!normalized || !originPoint) {
    return null;
  }

  const [lat, lng] = normalized;
  const [originLat, originLng] = originPoint;

  return [
    (lng - originLng) * metersPerDegreeLng(originLat),
    (lat - originLat) * METERS_PER_DEGREE_LAT,
  ];
}

export function localMetersToLatLng(point, origin) {
  const originPoint = normalizeLatLngPoint(origin);

  if (!originPoint || !Array.isArray(point) || point.length < 2) {
    return null;
  }

  const [originLat, originLng] = originPoint;
  const [x, y] = point;

  return [
    originLat + y / METERS_PER_DEGREE_LAT,
    originLng + x / metersPerDegreeLng(originLat),
  ];
}

export function rotateLocalMeters(point, degrees) {
  if (!Array.isArray(point) || point.length < 2) {
    return null;
  }

  const radians = (degrees * Math.PI) / 180;
  const cos = Math.cos(radians);
  const sin = Math.sin(radians);
  const [x, y] = point;

  return [
    x * cos - y * sin,
    x * sin + y * cos,
  ];
}

export function projectLatLngRing(ring, origin, bearingDeg = 0) {
  return normalizeLatLngRing(ring)
    .map((point) => latLngToLocalMeters(point, origin))
    .map((point) => (bearingDeg ? rotateLocalMeters(point, bearingDeg) : point))
    .filter(Boolean);
}

export function computeLocalBounds(pointGroups) {
  const points = pointGroups.flat().filter(Boolean);

  if (!points.length) {
    return { minX: 0, maxX: 100, minY: 0, maxY: 100, width: 100, height: 100 };
  }

  const xs = points.map((point) => point[0]);
  const ys = points.map((point) => point[1]);

  const minX = Math.min(...xs);
  const maxX = Math.max(...xs);
  const minY = Math.min(...ys);
  const maxY = Math.max(...ys);

  return {
    minX,
    maxX,
    minY,
    maxY,
    width: Math.max(maxX - minX, 1),
    height: Math.max(maxY - minY, 1),
  };
}

export function ringCentroidLocal(ring) {
  if (!Array.isArray(ring) || !ring.length) {
    return null;
  }

  const x = ring.reduce((sum, point) => sum + point[0], 0) / ring.length;
  const y = ring.reduce((sum, point) => sum + point[1], 0) / ring.length;

  return [x, y];
}
