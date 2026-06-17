import { eventToMapLatLng } from '@/utils/mapLayers';
import { getLiveSegmentEdge } from '@/utils/mapGeometry';

function normalizeLatLng(point) {
  if (!point) {
    return null;
  }

  if (Array.isArray(point) && point.length >= 2) {
    const lat = Number(point[0]);
    const lng = Number(point[1]);

    if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
      return null;
    }

    return { lat, lng };
  }

  if (typeof point.lat === 'number' && typeof point.lng === 'number') {
    return { lat: point.lat, lng: point.lng };
  }

  return null;
}

function buildPreviewLabelHtml(edge, invalid = false) {
  return `<span class="map-edge-label map-edge-label--preview${edge.isShortEdge ? ' map-edge-label--short' : ''}${invalid ? ' map-edge-label--invalid' : ''}">${edge.lengthLabel}</span>`;
}

export function createCursorPreviewController() {
  let map = null;
  let L = null;
  let lineLayer = null;
  let polygonLayer = null;
  let labelMarker = null;
  let snapMarker = null;
  let onMove = null;
  let onLeave = null;
  let config = {};

  function clearLineAndLabel() {
    if (map && lineLayer) {
      map.removeLayer(lineLayer);
      lineLayer = null;
    }

    if (map && labelMarker) {
      map.removeLayer(labelMarker);
      labelMarker = null;
    }
  }

  function clearPolygonLayer() {
    if (map && polygonLayer) {
      map.removeLayer(polygonLayer);
      polygonLayer = null;
    }
  }

  function clearSnapMarker() {
    if (map && snapMarker) {
      map.removeLayer(snapMarker);
      snapMarker = null;
    }
  }

  function clear() {
    clearLineAndLabel();
    clearPolygonLayer();
    clearSnapMarker();
  }

  function updateSnapMarker(to, snapped) {
    if (!map || !L || !to || !snapped) {
      clearSnapMarker();
      return;
    }

    const icon = L.divIcon({
      className: 'map-snap-target-icon',
      html: '<span class="map-snap-target-indicator"></span>',
      iconSize: [14, 14],
      iconAnchor: [7, 7],
    });

    if (snapMarker) {
      snapMarker.setLatLng([to.lat, to.lng]);
      snapMarker.setIcon(icon);
    } else {
      snapMarker = L.marker([to.lat, to.lng], {
        interactive: false,
        keyboard: false,
        zIndexOffset: 1400,
        icon,
      }).addTo(map);
    }

    snapMarker.bringToFront?.();
  }

  function updatePolygonPreview(points, color, invalid) {
    clearLineAndLabel();

    if (!map || !L || !Array.isArray(points) || points.length < 3) {
      clearPolygonLayer();
      return;
    }

    const path = points.map((point) => {
      const normalized = normalizeLatLng(point);
      return normalized ? [normalized.lat, normalized.lng] : null;
    }).filter(Boolean);

    if (path.length < 3) {
      clearPolygonLayer();
      return;
    }

    if (polygonLayer) {
      polygonLayer.setLatLngs(path);
      polygonLayer.setStyle({
        color,
        fillColor: color,
      });
    } else {
      polygonLayer = L.polygon(path, {
        color,
        weight: 2,
        dashArray: '6 6',
        fillColor: color,
        fillOpacity: invalid ? 0.08 : 0.12,
        interactive: false,
        className: 'map-cursor-preview-polygon',
      }).addTo(map);
    }

    polygonLayer.bringToFront?.();
  }

  function update(cursorLatLng) {
    if (!map || !L || !config.isActive?.()) {
      clear();
      return;
    }

    const resolved = config.resolveCursorLatLng?.(cursorLatLng) ?? cursorLatLng;
    const to = normalizeLatLng(
      typeof resolved.lat === 'number' && typeof resolved.lng === 'number'
        ? resolved
        : cursorLatLng,
    );

    if (!to) {
      clear();
      return;
    }

    updateSnapMarker(to, Boolean(resolved.snapped));

    const strokeColor = config.getStrokeColor?.() ?? '#1E5F8E';
    const cursorInvalid = config.isCursorInvalid?.(to) ?? false;
    const invalid = cursorInvalid || (config.getInvalid?.() ?? false);
    const color = invalid ? '#DC2626' : strokeColor;
    const previewPolygon = config.getPreviewPolygon?.(to);

    if (Array.isArray(previewPolygon) && previewPolygon.length >= 3) {
      updatePolygonPreview(previewPolygon, color, invalid);
      return;
    }

    clearPolygonLayer();

    const lastPoint = config.getLastPoint?.();
    const from = normalizeLatLng(lastPoint);

    if (!from) {
      clearLineAndLabel();
      return;
    }

    const path = [[from.lat, from.lng], [to.lat, to.lng]];
    const edge = getLiveSegmentEdge([from.lat, from.lng], [to.lat, to.lng]);

    if (lineLayer) {
      lineLayer.setLatLngs(path);
      lineLayer.setStyle({ color });
    } else {
      lineLayer = L.polyline(path, {
        color,
        weight: 2,
        dashArray: '6 6',
        interactive: false,
        className: 'map-cursor-preview-line',
      }).addTo(map);
    }

    const labelHtml = buildPreviewLabelHtml(edge, invalid);
    const icon = L.divIcon({
      className: 'map-edge-label-icon map-edge-label-icon--preview',
      html: labelHtml,
      iconSize: [0, 0],
    });

    if (labelMarker) {
      labelMarker.setLatLng(edge.midpoint);
      labelMarker.setIcon(icon);
    } else {
      labelMarker = L.marker(edge.midpoint, {
        interactive: false,
        keyboard: false,
        zIndexOffset: 1300,
        icon,
      }).addTo(map);
    }

    labelMarker.bringToFront?.();
    lineLayer.bringToFront?.();
  }

  function attachMoveListeners() {
    if (!map || onMove) {
      return;
    }

    onMove = (event) => {
      const latLng = eventToMapLatLng(map, event) ?? event.latlng;
      if (!latLng) {
        return;
      }

      update(latLng);
    };

    onLeave = () => {
      clear();
    };

    map.on('mousemove', onMove);
    map.on('touchmove', onMove);
    map.on('mouseleave', onLeave);
  }

  function configure(nextConfig = {}) {
    config = nextConfig;
  }

  function bind(nextMap, nextL, nextConfig = {}) {
    if (map === nextMap && L === nextL && onMove) {
      configure(nextConfig);
      return;
    }

    unbind();

    map = nextMap;
    L = nextL;
    config = nextConfig;

    if (!map || !L) {
      return;
    }

    attachMoveListeners();
  }

  function unbind() {
    if (map && onMove) {
      map.off('mousemove', onMove);
      map.off('touchmove', onMove);
    }

    if (map && onLeave) {
      map.off('mouseleave', onLeave);
    }

    onMove = null;
    onLeave = null;
    config = {};
    clear();
    map = null;
    L = null;
  }

  function showSnapIndicator(latLng, snapped = true) {
    const to = normalizeLatLng(latLng);
    updateSnapMarker(to, snapped);
  }

  function clearSnapIndicator() {
    clearSnapMarker();
  }

  return {
    bind,
    configure,
    unbind,
    clear,
    clearSnapIndicator,
    showSnapIndicator,
    update,
  };
}
