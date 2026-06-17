import * as turf from '@turf/turf';

import { distanceBetweenPointsMeters } from '@/utils/mapGeometry';

export const MAP_VERTEX_SNAP_TOLERANCE_METERS = 12;
export const MAP_SNAP_PIXEL_RADIUS = 20;
export const MAP_SEGMENT_SNAP_PIXEL_RADIUS = 32;
export const MAP_SNAP_MIN_METERS = 5;
export const MAP_SNAP_MAX_METERS = 28;

export function metersPerPixelAtLatLng(lat, zoom) {
  const clampedLat = Math.max(Math.min(Number(lat), 85), -85);
  const clampedZoom = Number(zoom);

  return (156543.03392 * Math.cos((clampedLat * Math.PI) / 180)) / (2 ** clampedZoom);
}

export function resolveSnapToleranceMeters(map, lat, lng, {
  pixelRadius = MAP_SNAP_PIXEL_RADIUS,
  minMeters = MAP_SNAP_MIN_METERS,
  maxMeters = MAP_SNAP_MAX_METERS,
  fallbackMeters = MAP_VERTEX_SNAP_TOLERANCE_METERS,
} = {}) {
  const zoom = typeof map?.getZoom === 'function' ? map.getZoom() : null;

  if (!Number.isFinite(zoom) || !Number.isFinite(Number(lat)) || !Number.isFinite(Number(lng))) {
    return fallbackMeters;
  }

  const meters = metersPerPixelAtLatLng(lat, zoom) * pixelRadius;

  return Math.min(maxMeters, Math.max(minMeters, meters));
}

function normalizeCoord(point) {
  if (!point) {
    return null;
  }

  if (Array.isArray(point) && point.length >= 2) {
    const lat = Number(point[0]);
    const lng = Number(point[1]);

    if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
      return null;
    }

    return [lat, lng];
  }

  if (typeof point.lat === 'number' && typeof point.lng === 'number') {
    return [point.lat, point.lng];
  }

  return null;
}

function coordKey(coord) {
  return `${coord[0].toFixed(6)}:${coord[1].toFixed(6)}`;
}

/**
 * @param {object} options
 * @param {Array<[number, number]>} [options.perimeterCoordinates]
 * @param {Array<object>} [options.zones]
 * @param {Array<object>} [options.streets]
 * @param {Array<object>} [options.lots]
 * @param {Array<[number, number]>} [options.currentDrawingPoints]
 * @param {number|string|null} [options.excludeZoneId]
 * @param {number|string|null} [options.excludeStreetId]
 * @param {number|string|null} [options.excludeLotId]
 * @param {boolean} [options.includeDrawingPoints]
 * @param {number|null} [options.excludeDrawingVertexIndex]
 */
export function collectMapSnapTargets({
  perimeterCoordinates = [],
  zones = [],
  streets = [],
  lots = [],
  currentDrawingPoints = [],
  excludeZoneId = null,
  excludeStreetId = null,
  excludeLotId = null,
  includeDrawingPoints = true,
  excludeDrawingVertexIndex = null,
} = {}) {
  const targets = [];
  const seen = new Set();

  const addTarget = (coord, source) => {
    const normalized = normalizeCoord(coord);

    if (!normalized) {
      return;
    }

    const key = coordKey(normalized);

    if (seen.has(key)) {
      return;
    }

    seen.add(key);
    targets.push({ coord: normalized, source });
  };

  perimeterCoordinates.forEach((coord) => addTarget(coord, 'perimeter'));

  zones.forEach((zone) => {
    if (excludeZoneId != null && String(zone.id) === String(excludeZoneId)) {
      return;
    }

    zone.coordinates?.forEach((coord) => addTarget(coord, 'zone'));
  });

  streets.forEach((street) => {
    if (excludeStreetId != null && String(street.id) === String(excludeStreetId)) {
      return;
    }

    const centerline = street.centerline ?? street.center_line;

    if (Array.isArray(centerline)) {
      centerline.forEach((coord) => addTarget(coord, 'street'));
    }

    street.coordinates?.forEach((coord) => addTarget(coord, 'street-polygon'));
  });

  lots.forEach((lot) => {
    if (excludeLotId != null && String(lot.id) === String(excludeLotId)) {
      return;
    }

    lot.coordinates?.forEach((coord) => addTarget(coord, 'lot'));
  });

  if (includeDrawingPoints) {
    currentDrawingPoints.forEach((coord, index) => {
      if (
        excludeDrawingVertexIndex != null
        && Number(index) === Number(excludeDrawingVertexIndex)
      ) {
        return;
      }

      addTarget(coord, 'drawing');
    });
  }

  return targets;
}

function pushPolylineSegment(segments, coords, source, meta = {}) {
  const normalized = (coords ?? [])
    .map((coord) => normalizeCoord(coord))
    .filter(Boolean);

  if (normalized.length < 2) {
    return;
  }

  segments.push({
    coords: normalized,
    source,
    ...meta,
  });
}

function pushPolygonRingSegment(segments, coords, source, meta = {}) {
  const normalized = (coords ?? [])
    .map((coord) => normalizeCoord(coord))
    .filter(Boolean);

  if (normalized.length < 2) {
    return;
  }

  const ring = [...normalized];

  if (ring.length >= 3) {
    const first = ring[0];
    const last = ring[ring.length - 1];

    if (first[0] !== last[0] || first[1] !== last[1]) {
      ring.push([...first]);
    }
  }

  pushPolylineSegment(segments, ring, source, meta);
}

/**
 * @param {object} options
 * @param {Array<[number, number]>} [options.perimeterCoordinates]
 * @param {Array<object>} [options.zones]
 * @param {Array<object>} [options.streets]
 * @param {Array<object>} [options.lots]
 * @param {Array<[number, number]>} [options.currentDrawingPoints]
 * @param {number|string|null} [options.excludeZoneId]
 * @param {number|string|null} [options.excludeStreetId]
 * @param {number|string|null} [options.excludeLotId]
 * @param {boolean} [options.includeDrawingSegments]
 */
export function collectMapSnapSegmentTargets({
  perimeterCoordinates = [],
  zones = [],
  streets = [],
  lots = [],
  currentDrawingPoints = [],
  excludeZoneId = null,
  excludeStreetId = null,
  excludeLotId = null,
  includeDrawingSegments = true,
} = {}) {
  const segments = [];

  if (perimeterCoordinates.length >= 2) {
    pushPolygonRingSegment(segments, perimeterCoordinates, 'perimeter-segment');
  }

  zones.forEach((zone) => {
    if (excludeZoneId != null && String(zone.id) === String(excludeZoneId)) {
      return;
    }

    if (zone.coordinates?.length >= 2) {
      pushPolygonRingSegment(segments, zone.coordinates, 'zone-segment', { zoneId: zone.id });
    }
  });

  streets.forEach((street) => {
    if (excludeStreetId != null && String(street.id) === String(excludeStreetId)) {
      return;
    }

    const centerline = street.centerline ?? street.center_line;

    if (Array.isArray(centerline) && centerline.length >= 2) {
      pushPolylineSegment(segments, centerline, 'street-centerline-segment', { streetId: street.id });
    }

    if (Array.isArray(street.coordinates) && street.coordinates.length >= 2) {
      pushPolygonRingSegment(segments, street.coordinates, 'street-polygon-segment', { streetId: street.id });
    }
  });

  lots.forEach((lot) => {
    if (excludeLotId != null && String(lot.id) === String(excludeLotId)) {
      return;
    }

    if (lot.coordinates?.length >= 2) {
      pushPolygonRingSegment(segments, lot.coordinates, 'lot-segment', { lotId: lot.id });
    }
  });

  if (includeDrawingSegments && currentDrawingPoints.length >= 2) {
    pushPolylineSegment(segments, currentDrawingPoints, 'drawing-segment');
  }

  return segments;
}

export function findNearestVertexSnap(lat, lng, targets, toleranceMeters = MAP_VERTEX_SNAP_TOLERANCE_METERS) {
  let best = null;

  targets.forEach((target) => {
    const distanceMeters = distanceBetweenPointsMeters([lat, lng], target.coord);

    if (distanceMeters > toleranceMeters) {
      return;
    }

    if (!best || distanceMeters < best.distanceMeters) {
      best = {
        ...target,
        lat: target.coord[0],
        lng: target.coord[1],
        distanceMeters,
      };
    }
  });

  return best;
}

export function findNearestSegmentSnap(lat, lng, segments, toleranceMeters = MAP_VERTEX_SNAP_TOLERANCE_METERS) {
  let best = null;
  const point = turf.point([lng, lat]);

  segments.forEach((segment) => {
    const coordinates = segment.coords
      .map((coord) => normalizeCoord(coord))
      .filter(Boolean)
      .map(([segmentLat, segmentLng]) => [segmentLng, segmentLat]);

    if (coordinates.length < 2) {
      return;
    }

    const line = turf.lineString(coordinates);
    const nearest = turf.nearestPointOnLine(line, point);
    const distanceMeters = turf.distance(point, nearest, { units: 'meters' });

    if (distanceMeters > toleranceMeters) {
      return;
    }

    if (!best || distanceMeters < best.distanceMeters) {
      const [nearestLng, nearestLat] = nearest.geometry.coordinates;

      best = {
        lat: nearestLat,
        lng: nearestLng,
        distanceMeters,
        source: segment.source,
      };
    }
  });

  return best;
}

/**
 * Encontra a aresta mais próxima para inserir um novo vértice.
 * @returns {{ lat: number, lng: number, insertIndex: number, distanceMeters: number } | null}
 */
export function findNearestPolygonEdgeInsert(lat, lng, coords, {
  closed = true,
  toleranceMeters = MAP_VERTEX_SNAP_TOLERANCE_METERS,
} = {}) {
  if (!Array.isArray(coords) || coords.length < 2) {
    return null;
  }

  const segmentCount = closed && coords.length >= 3
    ? coords.length
    : coords.length - 1;

  if (segmentCount <= 0) {
    return null;
  }

  const segments = [];

  for (let edgeIndex = 0; edgeIndex < segmentCount; edgeIndex += 1) {
    segments.push({
      coords: [
        coords[edgeIndex],
        coords[(edgeIndex + 1) % coords.length],
      ],
      edgeIndex,
    });
  }

  let best = null;

  segments.forEach((segment) => {
    const snap = findNearestSegmentSnap(lat, lng, [segment], toleranceMeters);

    if (!snap || (best && snap.distanceMeters >= best.distanceMeters)) {
      return;
    }

    best = {
      lat: snap.lat,
      lng: snap.lng,
      insertIndex: segment.edgeIndex + 1,
      distanceMeters: snap.distanceMeters,
    };
  });

  return best;
}

export function resolveSnappedCoordinate(
  lat,
  lng,
  {
    targets = [],
    segmentTargets = [],
    vertexToleranceMeters = MAP_VERTEX_SNAP_TOLERANCE_METERS,
    segmentToleranceMeters = MAP_VERTEX_SNAP_TOLERANCE_METERS,
  } = {},
) {
  const vertexSnap = findNearestVertexSnap(lat, lng, targets, vertexToleranceMeters);
  const segmentSnap = segmentTargets.length
    ? findNearestSegmentSnap(lat, lng, segmentTargets, segmentToleranceMeters)
    : null;

  if (!vertexSnap && !segmentSnap) {
    return { lat, lng, snapped: false };
  }

  if (vertexSnap && (!segmentSnap || vertexSnap.distanceMeters <= segmentSnap.distanceMeters)) {
    return {
      lat: vertexSnap.lat,
      lng: vertexSnap.lng,
      snapped: true,
      source: vertexSnap.source,
      snapKind: 'vertex',
    };
  }

  return {
    lat: segmentSnap.lat,
    lng: segmentSnap.lng,
    snapped: true,
    source: segmentSnap.source,
    snapKind: 'segment',
  };
}

/**
 * Axis-aligned rectangle from two opposite corners.
 * @param {[number, number]} cornerA
 * @param {[number, number]} cornerB
 * @returns {Array<[number, number]>}
 */
export function rectangleFromOppositeCorners(cornerA, cornerB) {
  const latA = Number(cornerA[0]);
  const lngA = Number(cornerA[1]);
  const latB = Number(cornerB[0]);
  const lngB = Number(cornerB[1]);

  return [
    [latA, lngA],
    [latA, lngB],
    [latB, lngB],
    [latB, lngA],
  ];
}
