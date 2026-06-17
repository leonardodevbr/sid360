import * as turf from '@turf/turf';
import center from '@turf/center';
import { geoAzimuthalEquidistant } from 'd3-geo';
import { earthRadius, lengthToRadians, radiansToLength } from '@turf/helpers';
import jsts from '@turf/jsts';

const { BufferOp, GeoJSONReader, GeoJSONWriter } = jsts;

const END_CAP_ROUND = 1;
const END_CAP_FLAT = 2;

/**
 * Converte [lat,lng] → [lng,lat] (GeoJSON)
 */
function toGeoJsonLine(latLngLine) {
  return latLngLine.map(([lat, lng]) => [Number(lng), Number(lat)]);
}

/**
 * Converte anel GeoJSON [lng,lat] → [lat,lng]
 */
function fromGeoJsonRing(ring) {
  return ring.map(([lng, lat]) => [Number(lat), Number(lng)]);
}

function projectCoords(coords, projection) {
  if (typeof coords[0] !== 'object') {
    return projection(coords);
  }

  return coords.map((coord) => projectCoords(coord, projection));
}

function unprojectCoords(coords, projection) {
  if (typeof coords[0] !== 'object') {
    return projection.invert(coords);
  }

  return coords.map((coord) => unprojectCoords(coord, projection));
}

function coordsIsNaN(coords) {
  if (Array.isArray(coords[0])) {
    return coordsIsNaN(coords[0]);
  }

  return Number.isNaN(coords[0]);
}

export function normalizeStreetEndCap(value) {
  return value === 'square' ? 'square' : 'round';
}

function bufferLineWithEndCap(centerlineLatLng, radiusMeters, steps, endCapStyle) {
  const line = turf.lineString(toGeoJsonLine(centerlineLatLng));
  const anchor = center(line).geometry.coordinates;
  const projection = geoAzimuthalEquidistant()
    .rotate([-anchor[0], -anchor[1]])
    .scale(earthRadius);

  const projected = {
    type: line.geometry.type,
    coordinates: projectCoords(line.geometry.coordinates, projection),
  };

  const geometry = new GeoJSONReader().read(projected);
  const distance = radiansToLength(lengthToRadians(radiusMeters, 'meters'), 'meters');
  const buffered = BufferOp.bufferOp(geometry, distance, steps, endCapStyle);
  const result = new GeoJSONWriter().write(buffered);

  if (coordsIsNaN(result.coordinates)) {
    return null;
  }

  return {
    type: result.type,
    coordinates: unprojectCoords(result.coordinates, projection),
  };
}

/**
 * Gera o polígono de uma rua a partir do eixo (centerline) e largura.
 *
 * @param {Array<[number,number]>} centerlineLatLng - eixo da rua em [lat,lng], mínimo 2 pontos
 * @param {number} widthMeters - largura total da rua em metros
 * @param {'round'|'square'} endCap - formato das extremidades do eixo
 * @returns {Array<[number,number]> | null} polígono [lat,lng] ou null se inválido
 */
export function buildStreetPolygon(centerlineLatLng, widthMeters, endCap = 'round') {
  if (!Array.isArray(centerlineLatLng) || centerlineLatLng.length < 2) {
    return null;
  }
  if (!(widthMeters > 0)) {
    return null;
  }

  const cap = normalizeStreetEndCap(endCap);
  const radius = widthMeters / 2;
  const steps = cap === 'square' ? 2 : 8;
  const endCapStyle = cap === 'square' ? END_CAP_FLAT : END_CAP_ROUND;
  const buffered = bufferLineWithEndCap(centerlineLatLng, radius, steps, endCapStyle);

  if (!buffered) {
    return null;
  }

  const ring = buffered.type === 'MultiPolygon'
    ? buffered.coordinates[0][0]
    : buffered.coordinates[0];

  return fromGeoJsonRing(ring);
}

/**
 * Comprimento total do eixo em metros (para exibir ao usuário)
 */
export function centerlineLengthMeters(centerlineLatLng) {
  if (!Array.isArray(centerlineLatLng) || centerlineLatLng.length < 2) {
    return 0;
  }
  const line = turf.lineString(toGeoJsonLine(centerlineLatLng));
  return Math.round(turf.length(line, { units: 'meters' }));
}

function latLngRingToTurfPolygon(ringLatLng) {
  if (!Array.isArray(ringLatLng) || ringLatLng.length < 3) {
    return null;
  }

  const coords = ringLatLng.map(([lat, lng]) => [Number(lng), Number(lat)]);
  const first = coords[0];
  const last = coords[coords.length - 1];

  if (first[0] !== last[0] || first[1] !== last[1]) {
    coords.push(first);
  }

  if (coords.length < 4) {
    return null;
  }

  try {
    return turf.polygon([coords]);
  } catch {
    return null;
  }
}

/**
 * @param {import('@turf/helpers').Feature<import('@turf/helpers').Polygon | import('@turf/helpers').MultiPolygon>} feature
 * @returns {Array<Array<[number, number]>>}
 */
function turfPolygonFeatureToLatLngRings(feature) {
  const geometry = feature?.geometry;
  if (!geometry) {
    return [];
  }

  if (geometry.type === 'Polygon') {
    const ring = geometry.coordinates[0]?.map(([lng, lat]) => [Number(lat), Number(lng)]) ?? [];
    return ring.length >= 3 ? [ring] : [];
  }

  if (geometry.type === 'MultiPolygon') {
    return geometry.coordinates
      .map((polygon) => polygon[0]?.map(([lng, lat]) => [Number(lat), Number(lng)]) ?? [])
      .filter((ring) => ring.length >= 3);
  }

  return [];
}

/**
 * Une polígonos de ruas para exibição fluida em cruzamentos (somente visual).
 *
 * @param {Array<Array<[number, number]>>} rings - polígonos das ruas em [lat,lng]
 * @returns {Array<Array<[number, number]>>} anel(is) resultante(s) em [lat,lng]
 */
export function mergeStreetPolygons(rings) {
  const features = (rings ?? [])
    .map((ring) => latLngRingToTurfPolygon(ring))
    .filter(Boolean);

  if (!features.length) {
    return [];
  }

  if (features.length === 1) {
    return turfPolygonFeatureToLatLngRings(features[0]);
  }

  let merged = features[0];

  for (let index = 1; index < features.length; index += 1) {
    try {
      const next = turf.union(turf.featureCollection([merged, features[index]]));
      if (next) {
        merged = next;
      }
    } catch {
      /* mantém merged anterior se o par falhar */
    }
  }

  return turfPolygonFeatureToLatLngRings(merged);
}
