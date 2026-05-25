/** @typedef {{ lat: number, lng: number, accuracy: number, timestamp?: number }} GeoPoint */

export const HIGH_ACCURACY_GEO_OPTIONS = {
  enableHighAccuracy: true,
  timeout: 20000,
  maximumAge: 0,
};

export const MAX_ACCEPTABLE_ACCURACY_M = 50;
export const TARGET_ACCURACY_M = 10;
export const STATIONARY_RADIUS_M = 3;
export const CAPTURE_TIMEOUT_MS = 20000;
export const SAMPLE_WINDOW = 5;

function haversineMeters(lat1, lng1, lat2, lng2) {
  const toRad = (value) => (value * Math.PI) / 180;
  const earthRadius = 6371000;
  const dLat = toRad(lat2 - lat1);
  const dLng = toRad(lng2 - lng1);
  const a = Math.sin(dLat / 2) ** 2
    + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng / 2) ** 2;

  return 2 * earthRadius * Math.asin(Math.sqrt(a));
}

/**
 * @param {GeolocationPosition} position
 * @returns {GeoPoint}
 */
export function positionToGeoPoint(position) {
  return {
    lat: position.coords.latitude,
    lng: position.coords.longitude,
    accuracy: position.coords.accuracy,
    timestamp: position.timestamp,
  };
}

/**
 * @param {GeoPoint[]} samples
 * @returns {GeoPoint}
 */
export function averageGeoPoints(samples) {
  if (!samples.length) {
    return { lat: 0, lng: 0, accuracy: Infinity };
  }

  const totals = samples.reduce(
    (acc, sample) => ({
      lat: acc.lat + sample.lat,
      lng: acc.lng + sample.lng,
      accuracy: Math.min(acc.accuracy, sample.accuracy),
    }),
    { lat: 0, lng: 0, accuracy: Infinity },
  );

  return {
    lat: totals.lat / samples.length,
    lng: totals.lng / samples.length,
    accuracy: totals.accuracy,
  };
}

/**
 * @param {GeoPoint[]} samples
 * @param {number} [radiusM]
 */
export function isStationaryGeoPoints(samples, radiusM = STATIONARY_RADIUS_M) {
  if (samples.length < 3) {
    return false;
  }

  const recent = samples.slice(-3);
  const anchor = recent[0];

  return recent.every(
    (sample) => haversineMeters(anchor.lat, anchor.lng, sample.lat, sample.lng) <= radiusM,
  );
}

export function isGeolocationAvailable() {
  return typeof navigator !== 'undefined' && Boolean(navigator.geolocation);
}

export function formatAccuracyHint(accuracyM) {
  if (accuracyM <= TARGET_ACCURACY_M) {
    return 'Excelente';
  }

  if (accuracyM <= 30) {
    return 'Boa';
  }

  if (accuracyM <= MAX_ACCEPTABLE_ACCURACY_M) {
    return 'Aceitável';
  }

  return 'Aguardando sinal melhor';
}

/**
 * Refina a posição com watchPosition, filtra leituras imprecisas e estabiliza parado.
 *
 * @param {{
 *   onProgress?: (accuracy: number) => void,
 *   timeoutMs?: number,
 *   maxAcceptableAccuracy?: number,
 * }} [options]
 * @returns {Promise<GeoPoint & { averaged: boolean }>}
 */
export function captureHighAccuracyPosition(options = {}) {
  const {
    onProgress,
    timeoutMs = CAPTURE_TIMEOUT_MS,
    maxAcceptableAccuracy = MAX_ACCEPTABLE_ACCURACY_M,
  } = options;

  if (!isGeolocationAvailable()) {
    return Promise.reject(new Error('GPS não disponível neste dispositivo.'));
  }

  return new Promise((resolve, reject) => {
    /** @type {GeoPoint[]} */
    const samples = [];
    let watchId = null;
    let settled = false;
    const startedAt = Date.now();

    function cleanup() {
      if (watchId !== null) {
        navigator.geolocation.clearWatch(watchId);
        watchId = null;
      }

      clearTimeout(timeoutTimer);
    }

    function finish(result, error) {
      if (settled) {
        return;
      }

      settled = true;
      cleanup();

      if (error) {
        reject(error);
        return;
      }

      resolve(result);
    }

    function pickBestSample(pool) {
      return pool.reduce((best, current) => (
        current.accuracy < best.accuracy ? current : best
      ));
    }

    function finalizeFromSamples() {
      const acceptable = samples.filter((sample) => sample.accuracy <= maxAcceptableAccuracy);
      const pool = acceptable.length ? acceptable : samples;

      if (!pool.length) {
        finish(null, new Error(
          'Não foi possível obter GPS com precisão suficiente. Ative a localização de alta precisão no celular e tente em área aberta.',
        ));
        return;
      }

      const recent = pool.slice(-SAMPLE_WINDOW);

      if (isStationaryGeoPoints(recent)) {
        const averaged = averageGeoPoints(recent);
        finish({
          ...averaged,
          accuracy: pickBestSample(recent).accuracy,
          averaged: true,
        });
        return;
      }

      const best = pickBestSample(pool);
      finish({ ...best, averaged: false });
    }

    function handlePosition(position) {
      const point = positionToGeoPoint(position);
      onProgress?.(point.accuracy);

      const elapsed = Date.now() - startedAt;
      const nearTimeout = elapsed >= timeoutMs * 0.75;

      if (point.accuracy > maxAcceptableAccuracy && !nearTimeout) {
        return;
      }

      samples.push(point);

      if (samples.length > SAMPLE_WINDOW) {
        samples.shift();
      }

      if (point.accuracy <= TARGET_ACCURACY_M) {
        if (isStationaryGeoPoints(samples)) {
          const averaged = averageGeoPoints(samples);
          finish({
            ...averaged,
            accuracy: point.accuracy,
            averaged: true,
          });
          return;
        }

        finish({ ...point, averaged: false });
        return;
      }

      if (
        samples.length >= 3
        && isStationaryGeoPoints(samples)
        && point.accuracy <= maxAcceptableAccuracy
      ) {
        const averaged = averageGeoPoints(samples);
        finish({
          ...averaged,
          accuracy: pickBestSample(samples).accuracy,
          averaged: true,
        });
      }
    }

    const timeoutTimer = setTimeout(finalizeFromSamples, timeoutMs);

    watchId = navigator.geolocation.watchPosition(
      handlePosition,
      (error) => finish(null, error),
      HIGH_ACCURACY_GEO_OPTIONS,
    );
  });
}

/**
 * @param {{
 *   onUpdate: (point: GeoPoint) => void,
 *   onError?: (error: GeolocationPositionError) => void,
 *   maxAcceptableAccuracy?: number,
 *   sampleWindow?: number,
 * }} options
 */
export function createHighAccuracyWatch(options) {
  const {
    onUpdate,
    onError,
    maxAcceptableAccuracy = MAX_ACCEPTABLE_ACCURACY_M,
    sampleWindow = SAMPLE_WINDOW,
  } = options;

  /** @type {GeoPoint[]} */
  let recentSamples = [];
  let watchId = null;

  function stop() {
    if (watchId !== null && isGeolocationAvailable()) {
      navigator.geolocation.clearWatch(watchId);
    }

    watchId = null;
    recentSamples = [];
  }

  function start() {
    stop();

    if (!isGeolocationAvailable()) {
      return;
    }

    watchId = navigator.geolocation.watchPosition(
      (position) => {
        const point = positionToGeoPoint(position);

        if (point.accuracy > maxAcceptableAccuracy) {
          onUpdate(point);
          return;
        }

        recentSamples.push(point);

        if (recentSamples.length > sampleWindow) {
          recentSamples.shift();
        }

        if (isStationaryGeoPoints(recentSamples)) {
          onUpdate({
            ...averageGeoPoints(recentSamples),
            accuracy: Math.min(...recentSamples.map((sample) => sample.accuracy)),
          });
          return;
        }

        onUpdate(point);
      },
      (error) => onError?.(error),
      HIGH_ACCURACY_GEO_OPTIONS,
    );
  }

  return { start, stop };
}
