import { HIGH_ACCURACY_GEO_OPTIONS, MAX_ACCEPTABLE_ACCURACY_M } from '@/utils/geolocation';

export function isCoarsePointerDevice() {
  if (typeof window === 'undefined') {
    return false;
  }

  return window.matchMedia('(pointer: coarse)').matches;
}

export function createGpsPreviewController() {
  let watchId = null;

  function stop() {
    if (watchId === null || typeof navigator === 'undefined' || !navigator.geolocation) {
      watchId = null;
      return;
    }

    navigator.geolocation.clearWatch(watchId);
    watchId = null;
  }

  function sync({ active = false, onPosition, onError } = {}) {
    stop();

    if (
      !active
      || typeof navigator === 'undefined'
      || !navigator.geolocation
      || typeof onPosition !== 'function'
    ) {
      return;
    }

    watchId = navigator.geolocation.watchPosition(
      onPosition,
      onError ?? (() => {}),
      HIGH_ACCURACY_GEO_OPTIONS,
    );
  }

  return {
    sync,
    stop,
    maxAcceptableAccuracy: MAX_ACCEPTABLE_ACCURACY_M,
  };
}
