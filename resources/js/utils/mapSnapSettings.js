import { ref } from 'vue';

export const mapSnapEnabled = ref(true);
export const mapSnapPoints = ref(true);
export const mapSnapLines = ref(true);

export function getMapSnapConfig() {
  return {
    snapEnabled: mapSnapEnabled.value,
    snapPoints: mapSnapPoints.value,
    snapLines: mapSnapLines.value,
  };
}

export function withMapSnapSettings(options = {}) {
  return {
    ...options,
    ...getMapSnapConfig(),
  };
}

export function toggleMapSnapEnabled() {
  mapSnapEnabled.value = !mapSnapEnabled.value;
}

export function toggleMapSnapPoints() {
  if (!mapSnapEnabled.value) {
    mapSnapEnabled.value = true;
    mapSnapPoints.value = true;
    return;
  }

  if (mapSnapPoints.value && !mapSnapLines.value) {
    return;
  }

  mapSnapPoints.value = !mapSnapPoints.value;
}

export function toggleMapSnapLines() {
  if (!mapSnapEnabled.value) {
    mapSnapEnabled.value = true;
    mapSnapLines.value = true;
    return;
  }

  if (mapSnapLines.value && !mapSnapPoints.value) {
    return;
  }

  mapSnapLines.value = !mapSnapLines.value;
}
