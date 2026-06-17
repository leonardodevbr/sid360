import * as turf from '@turf/turf';

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

/**
 * Gera o polígono de uma rua a partir do eixo (centerline) e largura.
 *
 * @param {Array<[number,number]>} centerlineLatLng - eixo da rua em [lat,lng], mínimo 2 pontos
 * @param {number} widthMeters - largura total da rua em metros
 * @returns {Array<[number,number]> | null} polígono [lat,lng] ou null se inválido
 */
export function buildStreetPolygon(centerlineLatLng, widthMeters) {
  if (!Array.isArray(centerlineLatLng) || centerlineLatLng.length < 2) {
    return null;
  }
  if (!(widthMeters > 0)) {
    return null;
  }

  const line = turf.lineString(toGeoJsonLine(centerlineLatLng));
  const buffered = turf.buffer(line, widthMeters / 2, { units: 'meters', steps: 8 });

  if (!buffered || !buffered.geometry) {
    return null;
  }

  const ring = buffered.geometry.type === 'MultiPolygon'
    ? buffered.geometry.coordinates[0][0]
    : buffered.geometry.coordinates[0];

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
