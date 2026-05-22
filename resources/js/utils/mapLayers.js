const GOOGLE_MUTANT_SCRIPT_URL =
  'https://cdn.jsdelivr.net/npm/leaflet.gridlayer.googlemutant@0.16.0/dist/Leaflet.GoogleMutant.js';

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
  const maxZoom = options.maxZoom ?? 22;

  const streetLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap',
    maxZoom,
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

export function hasGoogleMapsApiKey() {
  return Boolean(getGoogleMapsApiKey());
}

function getScrollZoomHintLabel() {
  const isMac =
    typeof navigator !== 'undefined' && /Mac|iPhone|iPad|iPod/i.test(navigator.userAgent);

  return isMac
    ? 'Pressione ⌘ + scroll para alterar o zoom'
    : 'Pressione Ctrl + scroll para alterar o zoom';
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

  return () => {
    container.removeEventListener('wheel', onWheel, { capture: true });
    container.removeEventListener('mouseleave', onMouseLeave);
    hideHint();
    overlay.remove();
    map.scrollWheelZoom.disable();
  };
}
