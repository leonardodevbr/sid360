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
