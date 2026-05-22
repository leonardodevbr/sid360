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
      {
        enableHighAccuracy: true,
        maximumAge: 1000,
        timeout: 20000,
      },
    );
  }

  return {
    sync,
    stop,
  };
}
