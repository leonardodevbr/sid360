import { buildLotDimensionLabelMarkerHtml } from '@/utils/mapLots';
import { getPolygonCentroid, normalizePolygonCoordinates } from '@/utils/mapGeometry';

/**
 * Overlay com a label de número/metragem do lote, posicionada manualmente a cada
 * redraw — mesmo princípio do mapStreetNameOverlay.js: nada de L.marker/divIcon,
 * a posição é recalculada via map.latLngToLayerPoint() em todo move/zoom/viewreset.
 * O tamanho da label fica fixo em px de tela (definido só por CSS), independente
 * do zoom, em vez de "respirar" durante o gesto de zoom.
 * @param {import('leaflet').Map} map
 */
export function createMapLotDimensionLabelOverlay(map) {
  let root = null;
  let lots = [];
  let visible = false;
  const boundRedraw = () => redraw();

  function ensureRoot() {
    if (root) {
      return root;
    }

    const pane = map.getPanes().overlayPane;
    root = document.createElement('div');
    root.className = 'map-lot-dimension-label-overlay';
    root.style.position = 'absolute';
    root.style.top = '0';
    root.style.left = '0';
    root.style.pointerEvents = 'none';
    pane.appendChild(root);

    return root;
  }

  function redraw() {
    if (!map) {
      return;
    }

    if (!visible || !lots.length) {
      if (root) {
        root.innerHTML = '';
      }

      return;
    }

    ensureRoot();
    root.innerHTML = '';

    lots.forEach((lot) => {
      const html = buildLotDimensionLabelMarkerHtml(lot);

      if (!html) {
        return;
      }

      const coords = normalizePolygonCoordinates(lot.coordinates);
      const centroid = coords ? getPolygonCentroid(coords) : null;

      if (!centroid) {
        return;
      }

      const point = map.latLngToLayerPoint(centroid);
      const wrapper = document.createElement('div');
      wrapper.style.position = 'absolute';
      wrapper.style.left = `${point.x}px`;
      wrapper.style.top = `${point.y}px`;
      wrapper.style.zIndex = '500';
      wrapper.innerHTML = html;

      root.appendChild(wrapper);
    });
  }

  function setLots(nextLots) {
    lots = Array.isArray(nextLots) ? nextLots : [];
    redraw();
  }

  function setVisible(nextVisible) {
    visible = Boolean(nextVisible);
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
    lots = [];
  }

  return {
    setLots,
    setVisible,
    redraw,
    bind,
    destroy,
  };
}
