import * as turf from '@turf/turf';
import { STREET_MAP_LABEL_BADGE_CLASS } from '@/utils/mapFeatureColors';
import { resolveStreetLabelPathLatLng } from '@/utils/streetGeometry';

const STREET_NAME_REPEAT_SPACING_METERS = 42;

function normalizeStreetRing(ring) {
  if (!Array.isArray(ring) || ring.length < 3) {
    return null;
  }

  const points = ring
    .map((point) => {
      if (!Array.isArray(point) || point.length < 2) {
        return null;
      }

      const lat = Number(point[0]);
      const lng = Number(point[1]);

      if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
        return null;
      }

      return [lat, lng];
    })
    .filter(Boolean);

  if (points.length < 3) {
    return null;
  }

  const isClosed = Math.abs(points[0][0] - points[points.length - 1][0]) < 1e-9
    && Math.abs(points[0][1] - points[points.length - 1][1]) < 1e-9;

  return isClosed ? points : [...points, points[0]];
}

/**
 * Monta os segmentos de texto repetidos ao longo do eixo da rua: 1 nome visível,
 * seguido de um vão equivalente a 2x a largura do próprio nome antes de repetir.
 * O vão é o nome novamente, porém com fill/stroke transparentes — assim a largura
 * do espaçamento acompanha exatamente a largura renderizada do nome (sem precisar
 * estimar largura de caractere/fonte), e não depende de espaços em branco (que o
 * SVG colapsa por padrão).
 */
function buildRepeatedStreetNameSegments(name, pathLengthMeters) {
  const repeatCount = Math.max(1, Math.ceil(pathLengthMeters / STREET_NAME_REPEAT_SPACING_METERS));
  const segments = [];

  for (let i = 0; i < repeatCount; i += 1) {
    segments.push({ visible: true, text: name });

    if (i < repeatCount - 1) {
      segments.push({ visible: false, text: name });
      segments.push({ visible: false, text: name });
    }
  }

  return segments;
}

/**
 * Overlay SVG com nomes repetidos ao longo do eixo da rua, recortados no polígono.
 * @param {import('leaflet').Map} map
 */
export function createMapStreetNameOverlay(map) {
  let root = null;
  let defsEl = null;
  let labelsEl = null;
  let streets = [];
  let visible = false;
  const boundRedraw = () => redraw();

  function ensureSvg() {
    if (root) {
      return root;
    }

    const pane = map.getPanes().overlayPane;
    root = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    root.setAttribute('class', 'map-street-name-overlay map-street-name-overlay--hidden');
    root.style.position = 'absolute';
    root.style.top = '0';
    root.style.left = '0';
    root.style.pointerEvents = 'none';
    root.style.overflow = 'visible';

    defsEl = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
    labelsEl = document.createElementNS('http://www.w3.org/2000/svg', 'g');
    labelsEl.setAttribute('class', 'map-street-name-overlay-labels');

    root.appendChild(defsEl);
    root.appendChild(labelsEl);
    pane.appendChild(root);

    return root;
  }

  function latLngPathToD(pathLatLng) {
    return pathLatLng.map(([lat, lng], index) => {
      const point = map.latLngToLayerPoint([lat, lng]);

      return `${index === 0 ? 'M' : 'L'}${point.x.toFixed(2)},${point.y.toFixed(2)}`;
    }).join(' ');
  }

  function ringToPolygonPoints(ring) {
    return ring
      .map(([lat, lng]) => {
        const point = map.latLngToLayerPoint([lat, lng]);

        return `${point.x.toFixed(2)},${point.y.toFixed(2)}`;
      })
      .join(' ');
  }

  function redraw() {
    if (!map) {
      return;
    }

    if (!visible || !streets.length) {
      if (defsEl) {
        defsEl.innerHTML = '';
      }

      if (labelsEl) {
        labelsEl.innerHTML = '';
      }

      return;
    }

    ensureSvg();

    const size = map.getSize();
    root.setAttribute('width', String(size.x));
    root.setAttribute('height', String(size.y));
    root.style.width = `${size.x}px`;
    root.style.height = `${size.y}px`;

    defsEl.innerHTML = '';
    labelsEl.innerHTML = '';

    streets.forEach((street) => {
      const pathLatLng = resolveStreetLabelPathLatLng(street);
      const name = String(street?.name ?? '').trim();

      if (!pathLatLng || pathLatLng.length < 2 || !name) {
        return;
      }

      const streetKey = String(street.id ?? name).replace(/[^a-zA-Z0-9_-]/g, '_');
      const pathId = `map-street-label-path-${streetKey}`;
      const clipId = `map-street-label-clip-${streetKey}`;

      const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
      path.setAttribute('id', pathId);
      path.setAttribute('d', latLngPathToD(pathLatLng));
      defsEl.appendChild(path);

      const ring = normalizeStreetRing(street.coordinates);
      let clipRef = null;

      if (ring) {
        const clip = document.createElementNS('http://www.w3.org/2000/svg', 'clipPath');
        clip.setAttribute('id', clipId);
        clip.setAttribute('clipPathUnits', 'userSpaceOnUse');

        const polygon = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
        polygon.setAttribute('points', ringToPolygonPoints(ring));
        clip.appendChild(polygon);
        defsEl.appendChild(clip);
        clipRef = clipId;
      }

      const line = turf.lineString(pathLatLng.map(([lat, lng]) => [lng, lat]));
      const lengthMeters = turf.length(line, { units: 'meters' });
      const segments = buildRepeatedStreetNameSegments(name, lengthMeters);

      const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
      text.setAttribute('class', STREET_MAP_LABEL_BADGE_CLASS);

      if (clipRef) {
        text.setAttribute('clip-path', `url(#${clipRef})`);
      }

      const textPath = document.createElementNS('http://www.w3.org/2000/svg', 'textPath');
      textPath.setAttribute('href', `#${pathId}`);
      textPath.setAttribute('startOffset', '3%');

      segments.forEach((segment) => {
        const tspan = document.createElementNS('http://www.w3.org/2000/svg', 'tspan');
        tspan.textContent = segment.text;

        if (!segment.visible) {
          tspan.setAttribute('fill', 'transparent');
          tspan.setAttribute('stroke', 'none');
        }

        textPath.appendChild(tspan);
      });

      text.appendChild(textPath);
      labelsEl.appendChild(text);
    });
  }

  function setStreets(nextStreets) {
    streets = Array.isArray(nextStreets) ? nextStreets : [];
    redraw();
  }

  function setVisible(nextVisible) {
    visible = Boolean(nextVisible);

    if (root) {
      root.classList.toggle('map-street-name-overlay--hidden', !visible);
    }

    redraw();
  }

  function bind() {
    map.on('move', boundRedraw);
    map.on('zoom', boundRedraw);
    map.on('rotate', boundRedraw);
    map.on('resize', boundRedraw);
    map.on('viewreset', boundRedraw);
  }

  function destroy() {
    if (!map) {
      return;
    }

    map.off('move', boundRedraw);
    map.off('zoom', boundRedraw);
    map.off('rotate', boundRedraw);
    map.off('resize', boundRedraw);
    map.off('viewreset', boundRedraw);
    root?.remove();
    root = null;
    defsEl = null;
    labelsEl = null;
  }

  return {
    setStreets,
    setVisible,
    redraw,
    bind,
    destroy,
  };
}
