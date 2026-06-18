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
 * Enriquece arestas com a rua cadastrada mais próxima (se houver).
 *
 * @param {Array<[number,number]>} blockLatLng
 * @param {Array<{ name: string, coordinates?: Array<[number,number]> }>} streets
 * @param {number} [maxDistanceM]
 */
export function enrichBlockEdgesWithStreets(blockLatLng, streets, maxDistanceM = 30) {
  const edges = getBlockEdges(blockLatLng);
  const mappedStreets = (streets ?? []).filter(
    (street) => Array.isArray(street.coordinates) && street.coordinates.length >= 3,
  );

  return edges.map((edge) => {
    const midpoint = turf.point([edge.midpoint[1], edge.midpoint[0]]);
    let nearestStreet = null;
    let minDistance = Infinity;

    mappedStreets.forEach((street) => {
      const ring = toGeoJsonRing(street.coordinates);
      const polygon = turf.polygon([ring]);
      const distance = turf.pointToPolygonDistance(midpoint, polygon, { units: 'meters' });
      if (distance < minDistance) {
        minDistance = distance;
        nearestStreet = street;
      }
    });

    const withinRange = minDistance <= maxDistanceM;

    return {
      ...edge,
      nearestStreet: withinRange ? nearestStreet?.name ?? null : null,
      nearestStreetDistance: withinRange ? Math.round(minDistance) : null,
    };
  });
}

/**
 * Comprimento em metros da aresta de frente selecionada.
 */
export function getFrontEdgeLengthMeters(blockLatLng, frontEdgeIndex) {
  const edges = getBlockEdges(blockLatLng);
  const edge = edges.find((item) => item.index === frontEdgeIndex);
  return edge?.lengthMeters ?? 0;
}

/**
 * Divide o comprimento da frente em partes iguais entre N lotes.
 * O arredondamento residual fica no último lote.
 */
export function divideFrontLengthEqually(frontLengthM, lotCount) {
  const total = Number(frontLengthM);
  const count = Math.max(1, Math.floor(Number(lotCount) || 1));

  if (!Number.isFinite(total) || total <= 0) {
    return [];
  }

  if (count === 1) {
    return [Math.round(total * 100) / 100];
  }

  const baseWidth = Math.floor((total / count) * 100) / 100;
  const widths = Array.from({ length: count - 1 }, () => baseWidth);
  const assigned = baseWidth * (count - 1);
  const lastWidth = Math.round((total - assigned) * 100) / 100;

  widths.push(lastWidth);

  return widths;
}

/**
 * Gera larguras sugeridas (iguais) para preencher a frente.
 */
export function suggestEqualSliceWidths(frontLengthM, lotWidth, maxLots = 200) {
  return resolveSliceWidths(frontLengthM, {
    widthMode: 'equal',
    lotWidth,
    maxLots,
    remainderSide: 'end',
  }).widths;
}

/**
 * Resolve as larguras de cada fatia ao longo da frente.
 *
 * @returns {{ widths: number[], definedTotal: number, remainder: number, trimmed: boolean }}
 */
export function resolveSliceWidths(frontLengthM, {
  widthMode = 'equal',
  lotWidth = 20,
  customWidths = [],
  remainderSide = 'end',
  maxLots = 200,
} = {}) {
  if (!(frontLengthM > 0)) {
    return { widths: [], definedTotal: 0, remainder: 0, trimmed: false };
  }

  if (widthMode === 'custom') {
    return resolveCustomSliceWidths(frontLengthM, customWidths, remainderSide);
  }

  return resolveEqualSliceWidths(frontLengthM, lotWidth, maxLots, remainderSide);
}

function resolveEqualSliceWidths(frontLengthM, lotWidth, maxLots, remainderSide) {
  if (!(lotWidth > 0)) {
    return { widths: [], definedTotal: 0, remainder: frontLengthM, trimmed: false };
  }

  const sliceCount = Math.min(maxLots, Math.ceil(frontLengthM / lotWidth));
  if (sliceCount <= 0) {
    return { widths: [], definedTotal: 0, remainder: frontLengthM, trimmed: false };
  }

  const fullCount = Math.max(0, sliceCount - 1);
  const remainder = Math.round((frontLengthM - fullCount * lotWidth) * 100) / 100;
  const fullWidths = Array.from({ length: fullCount }, () => lotWidth);

  let widths = [];

  if (remainderSide === 'start') {
    widths = remainder >= 0.5
      ? [remainder, ...fullWidths]
      : [...fullWidths];
  } else if (remainder >= 0.5) {
    widths = [...fullWidths, remainder];
  } else {
    widths = [...fullWidths];
  }

  if (!widths.length) {
    widths = [frontLengthM];
  }

  const definedTotal = widths.reduce((sum, width) => sum + width, 0);

  return {
    widths,
    definedTotal: Math.round(definedTotal * 100) / 100,
    remainder: Math.round(Math.max(0, frontLengthM - definedTotal) * 100) / 100,
    trimmed: false,
  };
}

function resolveCustomSliceWidths(frontLengthM, customWidths, remainderSide) {
  const parsed = (customWidths ?? [])
    .map((value) => Number(value))
    .filter((value) => Number.isFinite(value) && value > 0);

  if (!parsed.length) {
    return { widths: [], definedTotal: 0, remainder: frontLengthM, trimmed: false };
  }

  let trimmed = false;
  const widths = [];
  let used = 0;

  parsed.forEach((width) => {
    if (used >= frontLengthM - 0.5) {
      trimmed = true;
      return;
    }

    const remaining = frontLengthM - used;
    const sliceWidth = width > remaining ? remaining : width;
    if (sliceWidth >= 0.5) {
      widths.push(Math.round(sliceWidth * 100) / 100);
      used += sliceWidth;
      if (width > remaining + 0.01) {
        trimmed = true;
      }
    }
  });

  if (!widths.length) {
    return { widths: [], definedTotal: 0, remainder: frontLengthM, trimmed };
  }

  const extra = Math.round((frontLengthM - used) * 100) / 100;
  if (extra >= 0.5) {
    if (remainderSide === 'start') {
      widths[0] = Math.round((widths[0] + extra) * 100) / 100;
    } else {
      widths[widths.length - 1] = Math.round((widths[widths.length - 1] + extra) * 100) / 100;
    }
    used = frontLengthM;
  }

  return {
    widths,
    definedTotal: Math.round(used * 100) / 100,
    remainder: Math.round(Math.max(0, frontLengthM - used) * 100) / 100,
    trimmed,
  };
}

/**
 * Subdivide a quadra em lotes retangulares ao longo da aresta de frente.
 *
 * @param {Object} params
 * @param {Array<[number,number]>} params.blockLatLng - polígono da quadra [lat,lng]
 * @param {number} params.frontEdgeIndex - índice da aresta de frente (getBlockEdges)
 * @param {'equal'|'custom'} [params.widthMode]
 * @param {number} params.lotWidth - largura do lote em metros (modo igual)
 * @param {number[]} [params.customWidths] - larguras individuais (modo personalizado)
 * @param {'start'|'end'} [params.remainderSide] - onde aplicar a sobra
 * @param {number} params.lotDepth - profundidade do lote em metros (paralela às laterais da quadra)
 * @param {number} [params.maxLots] - limite de lotes (segurança)
 * @returns {Array<{ index, coordinates, area, widthMeters, depthMeters, clipped }>}
 */
export function subdivideBlockIntoLots({
  blockLatLng,
  frontEdgeIndex,
  lotWidth,
  lotDepth,
  widthMode = 'equal',
  customWidths = [],
  remainderSide = 'end',
  maxLots = 200,
  reverseFrontEdge = false,
}) {
  if (!Array.isArray(blockLatLng) || blockLatLng.length < 3) {
    return [];
  }
  if (!(lotDepth > 0)) {
    return [];
  }

  const ring = toGeoJsonRing(blockLatLng);
  const blockPolygon = turf.polygon([ring]);

  const aIdx = frontEdgeIndex;
  const bIdx = frontEdgeIndex + 1;
  let frontStart = ring[aIdx];
  let frontEnd = ring[bIdx];

  if (reverseFrontEdge) {
    frontStart = ring[bIdx];
    frontEnd = ring[aIdx];
  }

  const frontLine = turf.lineString([frontStart, frontEnd]);
  const frontLengthM = turf.length(frontLine, { units: 'meters' });

  const { widths: sliceWidths } = resolveSliceWidths(frontLengthM, {
    widthMode,
    lotWidth,
    customWidths,
    remainderSide,
    maxLots,
  });

  if (!sliceWidths.length) {
    return [];
  }

  return buildLotsFromSlices({
    blockPolygon,
    frontStart,
    frontEnd,
    frontLengthM,
    sliceWidths,
    lotDepth,
    frontEdgeIndex,
    ring,
  });
}

function getEdgeBearingFromRing(ring, edgeIndex) {
  const start = turf.point(ring[edgeIndex]);
  const end = turf.point(ring[edgeIndex + 1]);

  return turf.bearing(start, end);
}

/**
 * Direção de profundidade paralela às laterais da quadra (arestas adjacentes à frente).
 */
function pickParallelDepthBearing(ring, frontEdgeIndex, blockPolygon, frontStart, frontEnd, frontMid) {
  const vertexCount = ring.length - 1;
  const prevEdgeIndex = (frontEdgeIndex - 1 + vertexCount) % vertexCount;
  const nextEdgeIndex = (frontEdgeIndex + 1) % vertexCount;

  const prevBearing = getEdgeBearingFromRing(ring, prevEdgeIndex);
  const nextBearing = getEdgeBearingFromRing(ring, nextEdgeIndex);

  const prevInside = pickInsideBearing(
    prevBearing,
    prevBearing + 180,
    blockPolygon,
    frontStart,
    frontEnd,
    frontMid,
  );
  const nextInside = pickInsideBearing(
    nextBearing,
    nextBearing + 180,
    blockPolygon,
    frontStart,
    frontEnd,
    frontMid,
  );

  const prevDepth = measureTypicalInwardDepth(blockPolygon, frontStart, frontEnd, prevInside);
  const nextDepth = measureTypicalInwardDepth(blockPolygon, frontStart, frontEnd, nextInside);

  return prevDepth >= nextDepth ? prevInside : nextInside;
}

function resolveDepthBearing({
  blockPolygon,
  frontStart,
  frontEnd,
  frontMid,
  frontEdgeIndex,
  ring,
}) {
  return pickParallelDepthBearing(
    ring,
    frontEdgeIndex,
    blockPolygon,
    frontStart,
    frontEnd,
    frontMid,
  );
}

function normalizeBearingDifference(bearingA, bearingB) {
  let diff = (bearingB - bearingA) % 360;
  if (diff > 180) {
    diff -= 360;
  }
  if (diff < -180) {
    diff += 360;
  }

  return Math.abs(diff);
}

function measureRayDepthInsideBlock(origin, bearing, blockPolygon) {
  const originCoord = origin.geometry.coordinates;
  const farPoint = turf.destination(origin, 2000, bearing, { units: 'meters' });
  const ray = turf.lineString([originCoord, farPoint.geometry.coordinates]);

  let boundary;
  try {
    boundary = turf.polygonToLine(blockPolygon);
  } catch {
    return 0;
  }

  const intersections = turf.lineIntersect(ray, boundary);
  let maxDepth = 0;

  intersections.features.forEach((feature) => {
    const hit = turf.point(feature.geometry.coordinates);
    const distance = turf.distance(origin, hit, { units: 'meters' });

    if (distance <= 0.5) {
      return;
    }

    const hitBearing = turf.bearing(origin, hit);
    if (normalizeBearingDifference(bearing, hitBearing) > 2) {
      return;
    }

    if (distance > maxDepth) {
      maxDepth = distance;
    }
  });

  return maxDepth;
}

function measureMaxInwardDepth(blockPolygon, frontStart, frontEnd, insideBearing) {
  const frontLine = turf.lineString([frontStart, frontEnd]);
  const frontLengthM = turf.length(frontLine, { units: 'meters' });

  if (!(frontLengthM > 0)) {
    return 0;
  }

  const sampleCount = Math.max(3, Math.ceil(frontLengthM / 3));
  const endpointInset = Math.min(0.75, frontLengthM * 0.04);
  const depths = [];

  for (let i = 0; i <= sampleCount; i += 1) {
    let distanceAlong = (frontLengthM * i) / sampleCount;

    if (i === 0) {
      distanceAlong = endpointInset;
    } else if (i === sampleCount) {
      distanceAlong = Math.max(endpointInset, frontLengthM - endpointInset);
    }

    const point = turf.along(frontLine, distanceAlong, { units: 'meters' });
    const depth = measureRayDepthInsideBlock(point, insideBearing, blockPolygon);

    if (depth > 0.5) {
      depths.push(depth);
    }
  }

  if (!depths.length) {
    const midpoint = turf.along(frontLine, frontLengthM / 2, { units: 'meters' });
    const midpointDepth = measureRayDepthInsideBlock(midpoint, insideBearing, blockPolygon);

    return midpointDepth > 0.5 ? midpointDepth : 0;
  }

  return Math.min(...depths);
}

function measureTypicalInwardDepth(blockPolygon, frontStart, frontEnd, insideBearing) {
  const frontLine = turf.lineString([frontStart, frontEnd]);
  const frontLengthM = turf.length(frontLine, { units: 'meters' });

  if (!(frontLengthM > 0)) {
    return 0;
  }

  const midpoint = turf.along(frontLine, frontLengthM / 2, { units: 'meters' });
  return measureRayDepthInsideBlock(midpoint, insideBearing, blockPolygon);
}

function pickInsideBearing(normalA, normalB, blockPolygon, frontStart, frontEnd, frontMid) {
  const probeDistance = 1;
  const probeA = turf.destination(frontMid, probeDistance, normalA, { units: 'meters' });
  const probeB = turf.destination(frontMid, probeDistance, normalB, { units: 'meters' });
  const aInside = turf.booleanPointInPolygon(probeA, blockPolygon);
  const bInside = turf.booleanPointInPolygon(probeB, blockPolygon);

  if (aInside && !bInside) {
    return normalA;
  }

  if (bInside && !aInside) {
    return normalB;
  }

  const depthA = measureMaxInwardDepth(blockPolygon, frontStart, frontEnd, normalA);
  const depthB = measureMaxInwardDepth(blockPolygon, frontStart, frontEnd, normalB);

  return depthA >= depthB ? normalA : normalB;
}

function buildLotsFromSlices({
  blockPolygon,
  frontStart,
  frontEnd,
  frontLengthM,
  sliceWidths,
  lotDepth,
  frontEdgeIndex = 0,
  ring = [],
}) {
  const lots = [];

  const startPt = turf.point(frontStart);
  const endPt = turf.point(frontEnd);
  const frontBearing = turf.bearing(startPt, endPt);
  const frontMid = turf.midpoint(startPt, endPt);
  const depthBearing = resolveDepthBearing({
    blockPolygon,
    frontStart,
    frontEnd,
    frontMid,
    frontEdgeIndex,
    ring,
  });

  let distCursor = 0;

  for (let i = 0; i < sliceWidths.length; i += 1) {
    const sliceWidth = sliceWidths[i];
    const distStart = distCursor;
    const distEnd = Math.min(distStart + sliceWidth, frontLengthM);

    if (distEnd - distStart < 0.5) {
      break;
    }

    const p1 = turf.destination(startPt, distStart, frontBearing, { units: 'meters' });
    const p2 = turf.destination(startPt, distEnd, frontBearing, { units: 'meters' });
    const p3 = turf.destination(p2, lotDepth, depthBearing, { units: 'meters' });
    const p4 = turf.destination(p1, lotDepth, depthBearing, { units: 'meters' });

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
        distCursor = distEnd;
        continue;
      }
    } catch {
      finalLot = rawLot;
    }

    const areaM2 = Math.round(turf.area(finalLot));
    if (areaM2 < 1) {
      distCursor = distEnd;
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

    distCursor = distEnd;
  }

  return lots;
}
