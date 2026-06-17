function toRadians(degrees) {
  return (degrees * Math.PI) / 180;
}

function haversineMeters(lat1, lng1, lat2, lng2) {
  const earthRadius = 6371000;
  const dLat = toRadians(lat2 - lat1);
  const dLng = toRadians(lng2 - lng1);
  const startLat = toRadians(lat1);
  const endLat = toRadians(lat2);
  const a =
    Math.sin(dLat / 2) ** 2
    + Math.cos(startLat) * Math.cos(endLat) * Math.sin(dLng / 2) ** 2;

  return 2 * earthRadius * Math.asin(Math.sqrt(a));
}

function normalizePoint(point) {
  if (Array.isArray(point)) {
    return [Number(point[0]), Number(point[1])];
  }

  return [Number(point.lat), Number(point.lng)];
}

/**
 * Ray casting — suitable for small local polygons.
 * @param {[number, number] | { lat: number, lng: number }} point
 * @param {Array<[number, number]>} polygon
 */
export function isPointInsidePolygon(point, polygon) {
  if (!Array.isArray(polygon) || polygon.length < 3) {
    return true;
  }

  const [lat, lng] = normalizePoint(point);
  let inside = false;

  for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i, i += 1) {
    const [yi, xi] = [Number(polygon[i][0]), Number(polygon[i][1])];
    const [yj, xj] = [Number(polygon[j][0]), Number(polygon[j][1])];
    const intersects =
      yi > lat !== yj > lat
      && lng < ((xj - xi) * (lat - yi)) / (yj - yi) + xi;

    if (intersects) {
      inside = !inside;
    }
  }

  return inside;
}

function distancePointToSegmentMeters(point, segmentStart, segmentEnd) {
  const [pLat, pLng] = normalizePoint(point);
  const [aLat, aLng] = normalizePoint(segmentStart);
  const [bLat, bLng] = normalizePoint(segmentEnd);

  const closest = closestPointOnSegment(pLat, pLng, aLat, aLng, bLat, bLng);

  return haversineMeters(pLat, pLng, closest[0], closest[1]);
}

function closestPointOnSegment(pLat, pLng, aLat, aLng, bLat, bLng) {
  const dx = bLng - aLng;
  const dy = bLat - aLat;

  if (dx === 0 && dy === 0) {
    return [aLat, aLng];
  }

  const t = Math.max(
    0,
    Math.min(1, ((pLng - aLng) * dx + (pLat - aLat) * dy) / (dx * dx + dy * dy)),
  );

  return [aLat + t * dy, aLng + t * dx];
}

/**
 * Accepts points inside the polygon or near its border (for vertices snapped to the edge).
 * @param {[number, number] | { lat: number, lng: number }} point
 * @param {Array<[number, number]>} polygon
 * @param {number} [boundaryToleranceMeters=12]
 */
export function isPointInsideOrOnPolygon(point, polygon, boundaryToleranceMeters = 12) {
  if (!Array.isArray(polygon) || polygon.length < 3) {
    return true;
  }

  if (isPointInsidePolygon(point, polygon)) {
    return true;
  }

  for (let i = 0; i < polygon.length; i += 1) {
    const start = polygon[i];
    const end = polygon[(i + 1) % polygon.length];

    if (distancePointToSegmentMeters(point, start, end) <= boundaryToleranceMeters) {
      return true;
    }
  }

  return false;
}

export function arePointsInsideOrOnPolygon(points, polygon, boundaryToleranceMeters = 12) {
  if (!Array.isArray(points) || !points.length) {
    return true;
  }

  return points.every((point) =>
    isPointInsideOrOnPolygon(point, polygon, boundaryToleranceMeters),
  );
}

export function distanceBetweenPointsMeters(pointA, pointB) {
  const [lat1, lng1] = normalizePoint(pointA);
  const [lat2, lng2] = normalizePoint(pointB);

  return haversineMeters(lat1, lng1, lat2, lng2);
}

/**
 * @param {Array<[number, number]>} polygon
 * @returns {[number, number] | null}
 */
export function getPolygonCentroid(polygon) {
  if (!Array.isArray(polygon) || polygon.length < 1) {
    return null;
  }

  const lat = polygon.reduce((sum, point) => sum + Number(point[0]), 0) / polygon.length;
  const lng = polygon.reduce((sum, point) => sum + Number(point[1]), 0) / polygon.length;

  if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
    return null;
  }

  return [lat, lng];
}

export function formatMeters(lengthMeters) {
  if (!Number.isFinite(lengthMeters)) {
    return '—';
  }

  if (lengthMeters >= 1000) {
    return `${(lengthMeters / 1000).toLocaleString('pt-BR', { maximumFractionDigits: 2 })} km`;
  }

  return `${Math.round(lengthMeters).toLocaleString('pt-BR')} m`;
}

const SHORT_EDGE_LABEL_OFFSET_THRESHOLD_METERS = 15;

function offsetPointPerpendicular(start, end, offsetMeters, side = 1) {
  const [lat1, lng1] = normalizePoint(start);
  const [lat2, lng2] = normalizePoint(end);
  const midLat = (lat1 + lat2) / 2;
  const midLng = (lng1 + lng2) / 2;

  const deltaLat = lat2 - lat1;
  const deltaLng = lng2 - lng1;
  const length = Math.hypot(deltaLat, deltaLng) || 1;

  const perpLat = (-deltaLng / length) * side;
  const perpLng = (deltaLat / length) * side;

  const metersPerDegreeLat = 111320;
  const metersPerDegreeLng = 111320 * Math.cos(toRadians(midLat));

  return [
    midLat + (offsetMeters * perpLat) / metersPerDegreeLat,
    midLng + (offsetMeters * perpLng) / metersPerDegreeLng,
  ];
}

/**
 * @param {Array<[number, number]>} coords
 * @param {{ closed?: boolean, includeClosingPreview?: boolean }} [options]
 */
export function getPolygonEdgesMeters(coords, options = {}) {
  if (!Array.isArray(coords) || coords.length < 2) {
    return [];
  }

  const closed = options.closed ?? false;
  const includeClosingPreview = options.includeClosingPreview ?? false;
  const edges = [];
  const segmentCount = closed ? coords.length : coords.length - 1;

  for (let i = 0; i < segmentCount; i += 1) {
    const start = coords[i];
    const end = coords[(i + 1) % coords.length];
    edges.push(createEdge(start, end, i + 1, ((i + 1) % coords.length) + 1, false));
  }

  if (!closed && includeClosingPreview && coords.length >= 3) {
    const start = coords[coords.length - 1];
    const end = coords[0];
    edges.push(createEdge(start, end, coords.length, 1, true));
  }

  return edges;
}

export function getLiveSegmentEdge(start, end) {
  return createEdge(start, end, 0, 0, true);
}

function createEdge(start, end, from, to, isClosingPreview) {
  const lengthMeters = distanceBetweenPointsMeters(start, end);
  const midpoint = [
    (Number(start[0]) + Number(end[0])) / 2,
    (Number(start[1]) + Number(end[1])) / 2,
  ];

  let labelPosition = midpoint;
  if (lengthMeters < SHORT_EDGE_LABEL_OFFSET_THRESHOLD_METERS) {
    const offsetMeters = Math.max(3, Math.min(6, lengthMeters * 0.3));
    labelPosition = offsetPointPerpendicular(start, end, offsetMeters, from % 2 === 0 ? 1 : -1);
  }

  return {
    from,
    to,
    label: `${from}→${to}`,
    lengthMeters,
    lengthLabel: formatMeters(lengthMeters),
    midpoint: labelPosition,
    isClosingPreview,
    isShortEdge: lengthMeters < SHORT_EDGE_LABEL_OFFSET_THRESHOLD_METERS,
  };
}

export function computeGeodesicArea(coords) {
  if (!Array.isArray(coords) || coords.length < 3) {
    return null;
  }

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

/**
 * @param {number | string | null | undefined} area
 * @param {{ approximate?: boolean }} [options]
 * @returns {string | null}
 */
export function formatAreaM2(area, options = {}) {
  const value = Number(area);
  if (!Number.isFinite(value) || value <= 0) {
    return null;
  }

  const formatted = value.toLocaleString('pt-BR');
  return options.approximate ? `~${formatted} m²` : `${formatted} m²`;
}

/**
 * @param {unknown} coords
 * @param {{ approximate?: boolean }} [options]
 * @returns {string | null}
 */
export function formatPolygonAreaM2(coords, options = {}) {
  const normalized = normalizePolygonCoordinates(coords);
  if (!normalized || normalized.length < 3) {
    return null;
  }

  const area = computeGeodesicArea(normalized);
  return area != null ? formatAreaM2(area, { approximate: options.approximate ?? true }) : null;
}

export function normalizePolygonCoordinates(coords) {
  if (typeof coords === 'string') {
    try {
      const parsed = JSON.parse(coords.trim());
      if (typeof parsed === 'string') {
        return normalizePolygonCoordinates(parsed);
      }

      return normalizePolygonCoordinates(parsed);
    } catch {
      return null;
    }
  }

  if (!Array.isArray(coords)) {
    return null;
  }

  const normalized = coords
    .map((point) => {
      if (Array.isArray(point) && point.length >= 2) {
        const lat = Number(point[0]);
        const lng = Number(point[1]);

        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
          return null;
        }

        return [lat, lng];
      }

      if (point && typeof point === 'object') {
        const lat = Number(point.lat);
        const lng = Number(point.lng);

        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
          return null;
        }

        return [lat, lng];
      }

      return null;
    })
    .filter(Boolean);

  return normalized.length ? normalized : null;
}

export function getInvalidPointsInsidePolygon(points, polygon, boundaryToleranceMeters = 12) {
  if (!Array.isArray(points) || !Array.isArray(polygon) || polygon.length < 3) {
    return [];
  }

  return points
    .map((point, index) => (
      isPointInsideOrOnPolygon(point, polygon, boundaryToleranceMeters)
        ? null
        : index + 1
    ))
    .filter((value) => value != null);
}
