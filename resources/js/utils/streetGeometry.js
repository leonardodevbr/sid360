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

function isLatLngRing(value) {
  return Array.isArray(value)
    && value.length >= 3
    && Array.isArray(value[0])
    && value[0].length >= 2
    && typeof value[0][0] === 'number'
    && typeof value[0][1] === 'number';
}

function isLatLngPolygonWithHoles(value) {
  return Array.isArray(value)
    && value.length >= 2
    && isLatLngRing(value[0])
    && isLatLngRing(value[1]);
}

/**
 * Converte anéis GeoJSON [lng,lat] em formato Leaflet (com furos quando existirem).
 * @param {Array<Array<[number, number]>>} lngLatRings
 * @returns {Array<[number, number]> | Array<Array<[number, number]>> | null}
 */
function geoJsonPolygonToLeafletLatLngs(lngLatRings) {
  if (!Array.isArray(lngLatRings) || !lngLatRings.length) {
    return null;
  }

  const rings = lngLatRings
    .map((ring) => fromGeoJsonRing(ring))
    .filter((ring) => ring.length >= 3);

  if (!rings.length) {
    return null;
  }

  if (rings.length === 1) {
    return rings[0];
  }

  return rings;
}

function bufferedGeometryToLatLngRings(buffered) {
  if (!buffered) {
    return [];
  }

  if (buffered.type === 'Polygon') {
    const latLngs = geoJsonPolygonToLeafletLatLngs(buffered.coordinates);
    return latLngs ? [latLngs] : [];
  }

  if (buffered.type === 'MultiPolygon') {
    return buffered.coordinates
      .map((polygonRings) => geoJsonPolygonToLeafletLatLngs(polygonRings))
      .filter(Boolean);
  }

  return [];
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
  return bufferGeoJsonLines([line.geometry.coordinates], radiusMeters, steps, endCapStyle);
}

function bufferGeoJsonLines(lineStringsLngLat, radiusMeters, steps, endCapStyle) {
  if (!lineStringsLngLat.length) {
    return null;
  }

  const geoJson = lineStringsLngLat.length === 1
    ? { type: 'LineString', coordinates: lineStringsLngLat[0] }
    : { type: 'MultiLineString', coordinates: lineStringsLngLat };

  const feature = geoJson.type === 'LineString'
    ? turf.lineString(geoJson.coordinates)
    : turf.multiLineString(geoJson.coordinates);
  const anchor = center(feature).geometry.coordinates;
  const projection = geoAzimuthalEquidistant()
    .rotate([-anchor[0], -anchor[1]])
    .scale(earthRadius);

  const projected = {
    type: geoJson.type,
    coordinates: projectCoords(geoJson.coordinates, projection),
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

function distanceMetersBetweenLatLng(a, b) {
  return turf.distance(turf.point([a[1], a[0]]), turf.point([b[1], b[0]]), { units: 'meters' });
}

function latLngPointsNear(a, b, toleranceMeters = 0.5) {
  return distanceMetersBetweenLatLng(a, b) <= toleranceMeters;
}

function nearestPointOnCenterlineLatLng(pointLatLng, lineLatLng) {
  const line = turf.lineString(toGeoJsonLine(lineLatLng));
  const point = turf.point([pointLatLng[1], pointLatLng[0]]);
  const snapped = turf.nearestPointOnLine(line, point, { units: 'meters' });

  return {
    coord: [snapped.geometry.coordinates[1], snapped.geometry.coordinates[0]],
    distanceMeters: Number(snapped.properties.dist ?? 0),
    segmentIndex: Number(snapped.properties.index ?? 0),
  };
}

function insertVertexOnCenterline(line, segmentIndex, coord) {
  const nextIndex = Math.min(segmentIndex + 1, line.length - 1);
  const reference = line[nextIndex];

  if (latLngPointsNear(reference, coord)) {
    return;
  }

  if (line.some((point) => latLngPointsNear(point, coord))) {
    return;
  }

  line.splice(nextIndex, 0, [...coord]);
}

/**
 * Alinha extremidades a outros eixos e insere vértices em cruzamentos em T.
 *
 * @param {Array<Array<[number, number]>>} centerlines
 * @param {number} [toleranceMeters=3]
 * @returns {Array<Array<[number, number]>>}
 */
export function snapStreetCenterlinesAtJunctions(centerlines, toleranceMeters = 3) {
  if (!Array.isArray(centerlines) || centerlines.length < 2) {
    return centerlines ?? [];
  }

  const lines = centerlines.map((line) =>
    line.map(([lat, lng]) => [Number(lat), Number(lng)]),
  );

  const endpointIndexes = [];
  lines.forEach((line, lineIndex) => {
    if (line.length < 2) {
      return;
    }

    endpointIndexes.push({ lineIndex, pointIndex: 0 });
    endpointIndexes.push({ lineIndex, pointIndex: line.length - 1 });
  });

  endpointIndexes.forEach(({ lineIndex, pointIndex }) => {
    const endpoint = lines[lineIndex][pointIndex];
    let bestEndpointSnap = null;

    endpointIndexes.forEach(({ lineIndex: otherLineIndex, pointIndex: otherPointIndex }) => {
      if (otherLineIndex === lineIndex) {
        return;
      }

      const otherPoint = lines[otherLineIndex][otherPointIndex];
      const distanceMeters = distanceMetersBetweenLatLng(endpoint, otherPoint);

      if (distanceMeters <= toleranceMeters && (!bestEndpointSnap || distanceMeters < bestEndpointSnap.distanceMeters)) {
        bestEndpointSnap = { coord: [...otherPoint], distanceMeters };
      }
    });

    if (bestEndpointSnap) {
      lines[lineIndex][pointIndex] = bestEndpointSnap.coord;
      return;
    }

    let bestSegmentSnap = null;

    lines.forEach((otherLine, otherLineIndex) => {
      if (otherLineIndex === lineIndex || otherLine.length < 2) {
        return;
      }

      const nearest = nearestPointOnCenterlineLatLng(endpoint, otherLine);

      if (nearest.distanceMeters <= toleranceMeters && (!bestSegmentSnap || nearest.distanceMeters < bestSegmentSnap.distanceMeters)) {
        bestSegmentSnap = {
          ...nearest,
          otherLineIndex,
        };
      }
    });

    if (bestSegmentSnap) {
      lines[lineIndex][pointIndex] = [...bestSegmentSnap.coord];
      insertVertexOnCenterline(
        lines[bestSegmentSnap.otherLineIndex],
        bestSegmentSnap.segmentIndex,
        bestSegmentSnap.coord,
      );
    }
  });

  return lines;
}

function collectStreetVisualRings(streets) {
  const rings = [];

  (streets ?? []).forEach((street) => {
    if (Array.isArray(street.coordinates) && street.coordinates.length >= 3) {
      rings.push(street.coordinates);
      return;
    }

    if (Array.isArray(street.centerline) && street.centerline.length >= 2) {
      const polygon = buildStreetPolygon(
        street.centerline,
        Number(street.width) > 0 ? Number(street.width) : 10,
        street.end_cap,
      );

      if (polygon) {
        rings.push(polygon);
      }
    }
  });

  return rings;
}

/**
 * @param {Array<{ centerline?: Array<[number, number]>, width?: number, end_cap?: string, coordinates?: Array<[number, number]> }>} streets
 * @returns {Array<Array<[number, number]>>}
 */
export function buildStreetNetworkVisualRings(streets) {
  const allStreets = (streets ?? []).filter(
    (street) =>
      (Array.isArray(street.centerline) && street.centerline.length >= 2)
      || (Array.isArray(street.coordinates) && street.coordinates.length >= 3),
  );

  if (!allStreets.length) {
    return [];
  }

  if (allStreets.length === 1) {
    return collectStreetVisualRings(allStreets);
  }

  const withCenterline = allStreets.filter(
    (street) => Array.isArray(street.centerline) && street.centerline.length >= 2,
  );
  const withoutCenterline = allStreets.filter(
    (street) => !Array.isArray(street.centerline) || street.centerline.length < 2,
  );

  let rings = [];

  if (withCenterline.length >= 2) {
    const widthGroups = new Map();

    withCenterline.forEach((street) => {
      const width = Number(street.width) > 0 ? Number(street.width) : 10;
      const cap = normalizeStreetEndCap(street.end_cap);
      const key = `${width}:${cap}`;

      if (!widthGroups.has(key)) {
        widthGroups.set(key, { width, cap, centerlines: [] });
      }

      widthGroups.get(key).centerlines.push(street.centerline);
    });

    widthGroups.forEach(({ width, cap, centerlines }) => {
      const snapped = snapStreetCenterlinesAtJunctions(centerlines);
      const steps = cap === 'square' ? 2 : 8;
      const endCapStyle = cap === 'square' ? END_CAP_FLAT : END_CAP_ROUND;

      snapped.forEach((line) => {
        const buffered = bufferGeoJsonLines([toGeoJsonLine(line)], width / 2, steps, endCapStyle);
        rings = rings.concat(bufferedGeometryToLatLngRings(buffered));
      });
    });
  } else if (withCenterline.length === 1) {
    rings = rings.concat(collectStreetVisualRings(withCenterline));
  }

  withoutCenterline.forEach((street) => {
    if (street.coordinates?.length >= 3) {
      rings.push(street.coordinates);
    }
  });

  if (!rings.length) {
    return collectStreetVisualRings(allStreets);
  }

  if (rings.length === 1) {
    return rings;
  }

  return mergeStreetPolygons(rings);
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

function latLngRingToGeoJsonCoords(ringLatLng) {
  const coords = ringLatLng.map(([lat, lng]) => [Number(lng), Number(lat)]);
  const first = coords[0];
  const last = coords[coords.length - 1];

  if (first[0] !== last[0] || first[1] !== last[1]) {
    coords.push(first);
  }

  return coords;
}

function latLngRingToTurfPolygon(ringLatLng) {
  if (!isLatLngRing(ringLatLng)) {
    return null;
  }

  const coords = latLngRingToGeoJsonCoords(ringLatLng);

  if (coords.length < 4) {
    return null;
  }

  try {
    return turf.polygon([coords]);
  } catch {
    return null;
  }
}

function leafletLatLngsToTurfFeature(latLngs) {
  if (isLatLngPolygonWithHoles(latLngs)) {
    try {
      const coords = latLngs.map((ring) => latLngRingToGeoJsonCoords(ring));
      return turf.polygon(coords);
    } catch {
      return null;
    }
  }

  return latLngRingToTurfPolygon(latLngs);
}

/**
 * @param {import('@turf/helpers').Feature<import('@turf/helpers').Polygon | import('@turf/helpers').MultiPolygon>} feature
 * @returns {Array<Array<[number, number]> | Array<Array<[number, number]>>>}
 */
function turfPolygonFeatureToLatLngRings(feature) {
  const geometry = feature?.geometry;
  if (!geometry) {
    return [];
  }

  if (geometry.type === 'Polygon') {
    const latLngs = geoJsonPolygonToLeafletLatLngs(geometry.coordinates);
    return latLngs ? [latLngs] : [];
  }

  if (geometry.type === 'MultiPolygon') {
    return geometry.coordinates
      .map((polygonRings) => geoJsonPolygonToLeafletLatLngs(polygonRings))
      .filter(Boolean);
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
    .map((ring) => leafletLatLngsToTurfFeature(ring))
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
