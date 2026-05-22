const GOOGLE_MUTANT_SCRIPT_URL =
  'https://cdn.jsdelivr.net/npm/leaflet.gridlayer.googlemutant@0.16.0/dist/Leaflet.GoogleMutant.js';

const OPEN_STREET_MAP_MAX_ZOOM = 19;

/**
 * Converts a DOM or Leaflet mouse/touch event to map coordinates.
 * Returns null when conversion fails (e.g. during zoom animation).
 * @param {import('leaflet').Map | null | undefined} map
 * @param {Event | { latlng?: import('leaflet').LatLng, originalEvent?: Event }} event
 * @returns {import('leaflet').LatLng | null}
 */
export function eventToMapLatLng(map, event) {
  if (!map || !event) {
    return null;
  }

  if (event.latlng && Number.isFinite(event.latlng.lat) && Number.isFinite(event.latlng.lng)) {
    return event.latlng;
  }

  if (map._animatingZoom) {
    return null;
  }

  const domEvent = event.originalEvent ?? event;

  try {
    let latLng = null;

    if (typeof map.mouseEventToLatLng === 'function') {
      latLng = map.mouseEventToLatLng(domEvent);
    } else if (typeof map.mouseEventToContainerPoint === 'function') {
      latLng = map.containerPointToLatLng(map.mouseEventToContainerPoint(domEvent));
    }

    if (latLng && Number.isFinite(latLng.lat) && Number.isFinite(latLng.lng)) {
      return latLng;
    }
  } catch {
    return null;
  }

  return null;
}

let googleMutantPromise = null;

function getGoogleMapsApiKey() {
  return import.meta.env.VITE_GOOGLE_MAPS_API_KEY || '';
}

function loadScript(src) {
  return new Promise((resolve, reject) => {
    const existing = document.querySelector(`script[src="${src}"]`);
    if (existing) {
      if (existing.dataset.loaded === '1') {
        resolve();
        return;
      }
      existing.addEventListener('load', resolve, { once: true });
      existing.addEventListener('error', reject, { once: true });
      return;
    }

    const script = document.createElement('script');
    script.src = src;
    script.async = true;
    script.onload = () => {
      script.dataset.loaded = '1';
      resolve();
    };
    script.onerror = reject;
    document.head.appendChild(script);
  });
}

function loadGoogleMapsApi(apiKey) {
  return new Promise((resolve, reject) => {
    if (typeof google !== 'undefined' && google.maps) {
      resolve();
      return;
    }

    const callbackName = '__sid360GoogleMapsInit';
    window[callbackName] = () => {
      delete window[callbackName];
      resolve();
    };

    const script = document.createElement('script');
    script.src =
      `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(apiKey)}&callback=${callbackName}`;
    script.async = true;
    script.defer = true;
    script.onerror = () => {
      delete window[callbackName];
      reject(new Error('Google Maps API failed to load'));
    };
    document.head.appendChild(script);
  });
}

/**
 * @param {typeof import('leaflet')} leaflet
 */
async function ensureGoogleMutant(leaflet) {
  const L = leaflet.default ?? leaflet;
  const apiKey = getGoogleMapsApiKey();

  if (!apiKey) return false;
  if (L.gridLayer?.googleMutant) return true;

  if (!googleMutantPromise) {
    googleMutantPromise = loadGoogleMapsApi(apiKey)
      .then(() => loadScript(GOOGLE_MUTANT_SCRIPT_URL))
      .then(() => Boolean(L.gridLayer?.googleMutant))
      .catch(() => false);
  }

  return googleMutantPromise;
}

/**
 * @param {import('leaflet').Map} map
 * @param {typeof import('leaflet')} leaflet
 * @param {{ maxZoom?: number, position?: 'topleft' | 'topright' | 'bottomleft' | 'bottomright', collapsed?: boolean }} [options]
 */
export async function setupMapBaseLayers(map, leaflet, options = {}) {
  const L = leaflet.default ?? leaflet;
  const streetMaxZoom = options.streetMaxZoom ?? OPEN_STREET_MAP_MAX_ZOOM;
  const mapMaxZoom = options.maxZoom ?? 21;

  if (typeof map.setMaxZoom === 'function') {
    map.setMaxZoom(mapMaxZoom);
  }

  const streetLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap',
    maxZoom: streetMaxZoom,
    maxNativeZoom: streetMaxZoom,
  });

  const baseLayers = {
    Mapa: streetLayer,
  };

  streetLayer.addTo(map);

  const hasGoogleMutant = await ensureGoogleMutant(leaflet);

  if (hasGoogleMutant && L.gridLayer?.googleMutant) {
    baseLayers.Satélite = L.gridLayer.googleMutant({
      type: 'satellite',
      maxZoom: 21,
    });
  } else {
    baseLayers.Satélite = L.tileLayer(
      'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
      {
        attribution: 'Tiles © Esri',
        maxZoom: 19,
      },
    );
  }

  const layerControl = L.control.layers(baseLayers, null, {
    position: options.position ?? 'topright',
    collapsed: options.collapsed ?? true,
  });

  layerControl.addTo(map);

  configureModifierScrollZoom(map);

  return {
    streetLayer,
    baseLayers,
    layerControl,
    usesGoogleSatellite: hasGoogleMutant,
  };
}

export function hideMapScrollZoomHint(map) {
  const hint = map?.getContainer()?.querySelector('.map-scroll-zoom-hint');
  if (!hint) return;

  hint.classList.remove('is-visible');
  hint.style.display = 'none';
}

export function showMapScrollZoomHint(map) {
  const hint = map?.getContainer()?.querySelector('.map-scroll-zoom-hint');
  if (!hint) return;

  hint.style.display = '';
}

function refreshActiveBaseLayers(map, layers = {}) {
  if (!layers.baseLayers) return;

  Object.values(layers.baseLayers).forEach((baseLayer) => {
    if (!map.hasLayer(baseLayer)) return;

    map.removeLayer(baseLayer);
    baseLayer.addTo(map);
  });
}

/**
 * @param {import('leaflet').Map} map
 * @param {{ streetLayer?: import('leaflet').Layer, baseLayers?: Record<string, import('leaflet').Layer> }} [layers]
 */
export function refreshMapDisplay(map, layers = {}) {
  if (!map) return;

  hideMapScrollZoomHint(map);
  map.invalidateSize({ animate: false, pan: false, debounceMoveend: false });

  refreshActiveBaseLayers(map, layers);

  const center = map.getCenter();
  const zoom = map.getZoom();
  const bearing = typeof map.getBearing === 'function' ? map.getBearing() : 0;

  map.setView(center, zoom, { animate: false });

  if (typeof map.setBearing === 'function') {
    map.setBearing(bearing);
  }

  layers.streetLayer?.redraw?.();

  map.eachLayer((layer) => {
    if (typeof layer.redraw === 'function') {
      layer.redraw();
    }
  });
}

export function hasGoogleMapsApiKey() {
  return Boolean(getGoogleMapsApiKey());
}

let mapRotationPromise = null;

/**
 * Enables leaflet-rotate (patches global L).
 * @param {typeof import('leaflet')} leaflet
 */
export async function ensureMapRotation(leaflet) {
  const L = leaflet.default ?? leaflet;

  if (typeof window !== 'undefined') {
    window.L = L;
  }

  if (!mapRotationPromise) {
    mapRotationPromise = import('leaflet-rotate/dist/leaflet-rotate.js');
  }

  await mapRotationPromise;

  return L;
}

/**
 * @param {import('leaflet').Map} map
 * @param {{ touch?: boolean }} [options]
 */
export function configureMapRotation(map, options = {}) {
  if (!map?.setBearing) return;

  if (options.touch !== false && map.touchRotate?.enable) {
    map.touchRotate.enable();
  }
}

function getScrollZoomHintLabel() {
  const isMac =
    typeof navigator !== 'undefined' && /Mac|iPhone|iPad|iPod/i.test(navigator.userAgent);

  return isMac
    ? 'Pressione ⌘ para alterar o zoom'
    : 'Pressione Ctrl para alterar o zoom';
}

/**
 * Zoom with scroll wheel only when Ctrl (Windows/Linux) or Cmd (Mac) is held.
 * @param {import('leaflet').Map} map
 * @returns {() => void}
 */
export function configureModifierScrollZoom(map) {
  if (!map?.scrollWheelZoom) {
    return () => {};
  }

  map.scrollWheelZoom.disable();

  const container = map.getContainer();
  let hideHintTimer = null;

  const overlay = document.createElement('div');
  overlay.className = 'map-scroll-zoom-hint';
  overlay.innerHTML = `<span>${getScrollZoomHintLabel()}</span>`;
  container.appendChild(overlay);

  const hideHint = () => {
    if (hideHintTimer) {
      window.clearTimeout(hideHintTimer);
      hideHintTimer = null;
    }
    overlay.classList.remove('is-visible');
  };

  const showHint = () => {
    if (container.closest('.map-fullscreen-section--overlay')) return;

    overlay.classList.add('is-visible');
    if (hideHintTimer) {
      window.clearTimeout(hideHintTimer);
    }
    hideHintTimer = window.setTimeout(hideHint, 1800);
  };

  const onWheel = (event) => {
    if (event.ctrlKey || event.metaKey) {
      hideHint();
      if (!map.scrollWheelZoom.enabled()) {
        map.scrollWheelZoom.enable();
      }
      event.preventDefault();
    } else {
      if (map.scrollWheelZoom.enabled()) {
        map.scrollWheelZoom.disable();
      }
      showHint();
    }
  };

  const onMouseLeave = () => {
    hideHint();
    map.scrollWheelZoom.disable();
  };

  container.addEventListener('wheel', onWheel, { capture: true, passive: false });
  container.addEventListener('mouseleave', onMouseLeave);

  const resizeObserver = typeof ResizeObserver !== 'undefined'
    ? new ResizeObserver(() => hideHint())
    : null;

  resizeObserver?.observe(container);

  return () => {
    container.removeEventListener('wheel', onWheel, { capture: true });
    container.removeEventListener('mouseleave', onMouseLeave);
    resizeObserver?.disconnect();
    hideHint();
    overlay.remove();
    map.scrollWheelZoom.disable();
  };
}
