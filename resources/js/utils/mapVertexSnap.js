import * as turf from '@turf/turf';

import { distanceBetweenPointsMeters } from '@/utils/mapGeometry';

export const MAP_VERTEX_SNAP_TOLERANCE_METERS = 12;

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
  });

  lots.forEach((lot) => {
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

/**
 * @param {object} options
 * @param {Array<object>} [options.streets]
 * @param {number|string|null} [options.excludeStreetId]
 */
export function collectMapSnapSegmentTargets({
  streets = [],
  excludeStreetId = null,
} = {}) {
  const segments = [];

  streets.forEach((street) => {
    if (excludeStreetId != null && String(street.id) === String(excludeStreetId)) {
      return;
    }

    const centerline = street.centerline ?? street.center_line;

    if (!Array.isArray(centerline) || centerline.length < 2) {
      return;
    }

    segments.push({
      coords: centerline,
      source: 'street-segment',
      streetId: street.id,
    });
  });

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

export function resolveSnappedCoordinate(
  lat,
  lng,
  {
    targets = [],
    segmentTargets = [],
    toleranceMeters = MAP_VERTEX_SNAP_TOLERANCE_METERS,
  } = {},
) {
  const vertexSnap = findNearestVertexSnap(lat, lng, targets, toleranceMeters);
  const segmentSnap = segmentTargets.length
    ? findNearestSegmentSnap(lat, lng, segmentTargets, toleranceMeters)
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
    };
  }

  return {
    lat: segmentSnap.lat,
    lng: segmentSnap.lng,
    snapped: true,
    source: segmentSnap.source,
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
