import * as turf from '@turf/turf';

/**
 * Converte [lat, lng] (padrão do projeto) → [lng, lat] (GeoJSON/Turf)
 */
function toGeoJsonRing(latLngRing) {
  const ring = latLngRing.map(([lat, lng]) => [Number(lng), Number(lat)]);
  const first = ring[0];
  const last = ring[ring.length - 1];
  if (first[0] !== last[0] || first[1] !== last[1]) {
    ring.push([...first]);
  }
  return ring;
}

/**
 * Converte coordenadas GeoJSON [lng, lat] → [lat, lng]
 */
function fromGeoJsonRing(geoJsonRing) {
  return geoJsonRing.map(([lng, lat]) => [Number(lat), Number(lng)]);
}

/**
 * Calcula o comprimento (em metros) de cada aresta do polígono da quadra.
 * Retorna lista de { index, lengthMeters, fromCoord, toCoord, midpoint }
 * para o usuário escolher qual é a frente.
 */
export function getBlockEdges(blockLatLng) {
  const ring = toGeoJsonRing(blockLatLng);
  const edges = [];
  for (let i = 0; i < ring.length - 1; i += 1) {
    const a = ring[i];
    const b = ring[i + 1];
    const lengthMeters = turf.distance(turf.point(a), turf.point(b), { units: 'meters' });
    edges.push({
      index: i,
      fromCoord: [a[1], a[0]],
      toCoord: [b[1], b[0]],
      lengthMeters: Math.round(lengthMeters * 100) / 100,
      midpoint: [(a[1] + b[1]) / 2, (a[0] + b[0]) / 2],
    });
  }
  return edges;
}

/**
 * Subdivide a quadra em lotes retangulares ao longo da aresta de frente.
 *
 * @param {Object} params
 * @param {Array<[number,number]>} params.blockLatLng - polígono da quadra [lat,lng]
 * @param {number} params.frontEdgeIndex - índice da aresta de frente (getBlockEdges)
 * @param {number} params.lotWidth - largura do lote em metros (ao longo da frente)
 * @param {number} params.lotDepth - profundidade do lote em metros (perpendicular)
 * @param {number} [params.maxLots] - limite de lotes (segurança)
 * @returns {Array<{ index, coordinates, area, widthMeters, depthMeters, clipped }>}
 */
export function subdivideBlockIntoLots({
  blockLatLng,
  frontEdgeIndex,
  lotWidth,
  lotDepth,
  maxLots = 200,
}) {
  if (!Array.isArray(blockLatLng) || blockLatLng.length < 3) {
    return [];
  }
  if (!(lotWidth > 0) || !(lotDepth > 0)) {
    return [];
  }

  const ring = toGeoJsonRing(blockLatLng);
  const blockPolygon = turf.polygon([ring]);

  const aIdx = frontEdgeIndex;
  const bIdx = frontEdgeIndex + 1;
  const frontStart = ring[aIdx];
  const frontEnd = ring[bIdx];
  const frontLine = turf.lineString([frontStart, frontEnd]);
  const frontLengthM = turf.length(frontLine, { units: 'meters' });

  if (frontLengthM < lotWidth) {
    return buildLotsFromSlices({
      blockPolygon,
      frontStart,
      frontEnd,
      frontLengthM,
      sliceWidth: frontLengthM,
      lotDepth,
      maxLots: 1,
    });
  }

  return buildLotsFromSlices({
    blockPolygon,
    frontStart,
    frontEnd,
    frontLengthM,
    sliceWidth: lotWidth,
    lotDepth,
    maxLots,
  });
}

function buildLotsFromSlices({
  blockPolygon,
  frontStart,
  frontEnd,
  frontLengthM,
  sliceWidth,
  lotDepth,
  maxLots,
}) {
  const lots = [];

  const startPt = turf.point(frontStart);
  const endPt = turf.point(frontEnd);
  const frontBearing = turf.bearing(startPt, endPt);

  const testNormal = frontBearing + 90;
  const frontMid = turf.midpoint(startPt, endPt);
  const probe = turf.destination(frontMid, lotDepth / 2, testNormal, { units: 'meters' });
  const insideBearing = turf.booleanPointInPolygon(probe, blockPolygon)
    ? testNormal
    : frontBearing - 90;

  const sliceCount = Math.min(maxLots, Math.ceil(frontLengthM / sliceWidth));

  for (let i = 0; i < sliceCount; i += 1) {
    const distStart = i * sliceWidth;
    const distEnd = Math.min((i + 1) * sliceWidth, frontLengthM);
    if (distEnd - distStart < 0.5) {
      break;
    }

    const p1 = turf.destination(startPt, distStart, frontBearing, { units: 'meters' });
    const p2 = turf.destination(startPt, distEnd, frontBearing, { units: 'meters' });
    const p3 = turf.destination(p2, lotDepth, insideBearing, { units: 'meters' });
    const p4 = turf.destination(p1, lotDepth, insideBearing, { units: 'meters' });

    const rawLot = turf.polygon([[
      p1.geometry.coordinates,
      p2.geometry.coordinates,
      p3.geometry.coordinates,
      p4.geometry.coordinates,
      p1.geometry.coordinates,
    ]]);

    let finalLot = rawLot;
    let clipped = false;
    try {
      const intersection = turf.intersect(
        turf.featureCollection([rawLot, blockPolygon]),
      );
      if (intersection && intersection.geometry) {
        if (intersection.geometry.type === 'MultiPolygon') {
          const polys = intersection.geometry.coordinates.map((c) => turf.polygon(c));
          finalLot = polys.reduce((a, b) => (turf.area(a) >= turf.area(b) ? a : b));
        } else {
          finalLot = intersection;
        }
        clipped = turf.area(finalLot) < turf.area(rawLot) - 1;
      } else {
        continue;
      }
    } catch {
      finalLot = rawLot;
    }

    const areaM2 = Math.round(turf.area(finalLot));
    if (areaM2 < 1) {
      continue;
    }

    const outerRing = finalLot.geometry.type === 'Polygon'
      ? finalLot.geometry.coordinates[0]
      : finalLot.geometry.coordinates[0][0];

    lots.push({
      index: i,
      coordinates: fromGeoJsonRing(outerRing),
      area: areaM2,
      widthMeters: Math.round((distEnd - distStart) * 100) / 100,
      depthMeters: lotDepth,
      clipped,
    });
  }

  return lots;
}
