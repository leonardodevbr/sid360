import { ref, computed, watch, onUnmounted } from 'vue';
import { useToast } from 'vue-toastification';
import { useMapFullscreen } from '@/composables/useMapFullscreen';
import {
  setupMapBaseLayers,
  ensureMapRotation,
  configureMapRotation,
  refreshMapDisplay,
  hideMapScrollZoomHint,
  showMapScrollZoomHint,
  eventToMapLatLng,
} from '@/utils/mapLayers';
import {
  arePointsInsideOrOnPolygon,
  computeGeodesicArea,
  getInvalidPointsInsidePolygon,
  getPolygonEdgesMeters,
  isPointInsideOrOnPolygon,
  normalizePolygonCoordinates,
} from '@/utils/mapGeometry';
import { buildZoneTitleLabel } from '@/utils/zone';
import { getLotMapStyle, buildLotMapLabel } from '@/utils/mapLots';
import { getStreetColor, hasValidStreetPolygon } from '@/utils/mapStreets';
import { createCursorPreviewController } from '@/utils/mapDrawingPreview';
import { createGpsPreviewController, isCoarsePointerDevice } from '@/utils/mapGpsPreview';

const LOT_DRAWING_COLOR = '#1E5F8E';
const LOT_SAVED_FEATURE_COLOR = '#c9a84c';

export function useMapDrawing(options) {
  const toast = useToast();

  const {
    mode,
    coordinates,
    contextPerimeter,
    contextStreets,
    contextZones,
    contextLots,
    boundaryPolygon,
    mapCenter,
    mapZoom,
    persistMapView = false,
    fitContextOnLoad = true,
    onMapViewChange,
    onDemarcationSaved,
    savedCoordinates,
    onCoordinatesChange,
  } = options;

  const mapContainer = ref(null);
  const mapSectionRef = ref(null);
  const mapFooterRef = ref(null);

  let map = null;
  let L = null;
  let mapLayersSetup = null;
  let fullscreenResizeHandler = null;

  let contextPerimeterLayer = null;
  const contextStreetLayerMap = {};
  const contextZoneLayerMap = {};
  const contextLotLayerMap = {};
  let savedFeatureLayer = null;
  let tempMarkers = [];
  let edgeLabelMarkers = [];
  let locationMarker = null;

  const drawingMode = ref(null);
  const drawingPoints = ref([]);
  const mapReady = ref(false);
  const locatingUser = ref(false);
  const capturingGps = ref(false);
  const gpsAccuracy = ref(null);
  const visibleZoneNameTypes = ref([]);
  const startedFromExistingPolygon = ref(false);
  const gpsWalkPreviewEnabled = ref(false);
  let firstVertexCloseTimer = null;
  let mapFooterResizeObserver = null;
  let mapLayoutRefreshTimer = null;
  let lastMapContainerSizeKey = '';
  let closePolygonFromVertexPending = false;
  const cursorPreview = createCursorPreviewController();
  const gpsPreview = createGpsPreviewController();
  let gpsPreviewErrorNotified = false;

  function clearFirstVertexCloseTimer() {
    if (firstVertexCloseTimer) {
      clearTimeout(firstVertexCloseTimer);
      firstVertexCloseTimer = null;
    }
  }

  function scheduleCloseOnFirstVertex() {
    clearFirstVertexCloseTimer();
    firstVertexCloseTimer = setTimeout(() => {
      firstVertexCloseTimer = null;
      finishDrawing({ closedExplicitly: true });
    }, 250);
  }

  const isLotMode = computed(() => mode === 'lot');
  const hasMappedZones = computed(() =>
    (contextZones?.value ?? []).some(
      (zone) => Array.isArray(zone.coordinates) && zone.coordinates.length >= 3,
    ),
  );
  const isDrawing = computed(() => Boolean(drawingMode.value));

  const peekSavedCoordinates = computed(() =>
    normalizePolygonCoordinates(coordinates?.value)
    ?? normalizePolygonCoordinates(savedCoordinates?.value),
  );

  const hasSavedDemarcation = computed(
    () => (peekSavedCoordinates.value?.length ?? 0) >= 3,
  );

  const boundaryHint = computed(() => {
    if (!isDrawing.value || !isLotMode.value) {
      return '';
    }

    const boundary = boundaryPolygon?.value;
    if (!boundary?.length) {
      return 'Selecione uma quadra ou defina o perímetro do empreendimento';
    }

    const invalidPoints = getInvalidPointsInsidePolygon(drawingPoints.value, boundary);
    if (invalidPoints.length) {
      return 'Vértice fora da quadra — ajuste os pontos em vermelho';
    }

    if (drawingPoints.value.length > 0 && drawingPoints.value.length < 3) {
      return `Adicione mais ${3 - drawingPoints.value.length} ponto(s) para fechar o lote`;
    }

    if (drawingPoints.value.length >= 3 && startedFromExistingPolygon.value) {
      return 'Ajuste os vértices se necessário e clique em Salvar demarcação';
    }

    if (drawingPoints.value.length >= 3 && !startedFromExistingPolygon.value) {
      return 'Polígono pronto — clique em Salvar demarcação ou no primeiro vértice';
    }

    return '';
  });

  const canSaveDrawing = computed(() => {
    if (!isDrawing.value || drawingPoints.value.length < 3) {
      return false;
    }

    const boundary = boundaryPolygon?.value;
    if (boundary?.length && !arePointsInsideOrOnPolygon(drawingPoints.value, boundary)) {
      return false;
    }

    return true;
  });

  function syncMapContainerHeight() {
    if (!mapContainer.value || !mapSectionRef.value) return;

    if (isMapFullscreen.value) {
      const sectionStyle = window.getComputedStyle(mapSectionRef.value);
      const paddingTop = parseFloat(sectionStyle.paddingTop) || 0;
      const paddingBottom = parseFloat(sectionStyle.paddingBottom) || 0;
      const footerHeight = mapFooterRef.value?.offsetHeight ?? 0;
      const height = window.innerHeight - paddingTop - paddingBottom - 12 - footerHeight;

      mapContainer.value.style.height = `${Math.max(Math.floor(height), 240)}px`;
      hideMapScrollZoomHint(map);
      return;
    }

    mapContainer.value.style.height = '';
    showMapScrollZoomHint(map);
  }

  function ensureMapDraggingEnabled() {
    if (!map || map._vertexDragActiveCount > 0) {
      return;
    }

    map.dragging.enable();
  }

  function invalidateMapContainerSize() {
    if (!map || !mapContainer.value) {
      return false;
    }

    const { width, height } = mapContainer.value.getBoundingClientRect();
    const sizeKey = `${Math.round(width)}x${Math.round(height)}`;

    if (sizeKey === lastMapContainerSizeKey || width <= 0 || height <= 0) {
      return false;
    }

    lastMapContainerSizeKey = sizeKey;
    map.invalidateSize({ animate: false, pan: false, debounceMoveend: false });
    ensureMapDraggingEnabled();
    return true;
  }

  function refreshMapLayout({ forceFullRefresh = false } = {}) {
    syncMapContainerHeight();

    const sizeChanged = invalidateMapContainerSize();

    if (forceFullRefresh || (!drawingMode.value && sizeChanged)) {
      refreshMapDisplay(map, mapLayersSetup ?? {});
    }

    ensureMapDraggingEnabled();
  }

  function scheduleMapLayoutRefresh({ forceFullRefresh = false } = {}) {
    if (!map) {
      return;
    }

    if (mapLayoutRefreshTimer) {
      window.clearTimeout(mapLayoutRefreshTimer);
    }

    mapLayoutRefreshTimer = window.setTimeout(() => {
      mapLayoutRefreshTimer = null;
      refreshMapLayout({ forceFullRefresh });
    }, 120);
  }

  function bindMapFooterResizeObserver() {
    mapFooterResizeObserver?.disconnect();
    mapFooterResizeObserver = null;

    if (typeof ResizeObserver === 'undefined' || !mapFooterRef.value) {
      return;
    }

    mapFooterResizeObserver = new ResizeObserver(() => {
      scheduleMapLayoutRefresh();
    });
    mapFooterResizeObserver.observe(mapFooterRef.value);
  }

  const { isFullscreen: isMapFullscreen, toggleFullscreen: toggleMapFullscreen } = useMapFullscreen(
    mapSectionRef,
    refreshMapLayout,
  );

  function blurMapPath(layer) {
    const path = layer?._path;
    if (!path) return;

    path.style.outline = 'none';
    path.style.boxShadow = 'none';
    path.blur?.();
    path.closest?.('svg')?.blur?.();
  }

  function resetMapFeatureLayerInteraction(layer) {
    if (layer?._path) {
      layer._path.style.pointerEvents = '';
      layer._path.style.removeProperty('pointer-events');
    }
  }

  function configureMapPathLayer(layer, { interactive = false } = {}) {
    if (!layer) return;

    layer.on('add', () => {
      blurMapPath(layer);
      layer._path?.setAttribute?.('tabindex', '-1');
      if (layer._path && !interactive) {
        layer._path.style.pointerEvents = 'none';
      }
    });

    layer.on('mousedown click', (event) => {
      L?.DomEvent.stopPropagation(event);
      blurMapPath(layer);
    });
  }

  function setMapOverlaysPointerEvents(enabled) {
    map?.getContainer()?.classList.toggle('map-overlays-inactive', !enabled);
  }

  function resetMapCursor() {
    map?.getContainer()?.style.removeProperty('cursor');
  }

  function getDrawingBaseColor() {
    return LOT_DRAWING_COLOR;
  }

  function getBoundary() {
    const boundary = boundaryPolygon?.value;
    return Array.isArray(boundary) && boundary.length >= 3 ? boundary : null;
  }

  function isVertexInvalid(coord) {
    const boundary = getBoundary();
    if (!boundary) return false;

    return !isPointInsideOrOnPolygon(coord, boundary);
  }

  function canDragVertexMarkers() {
    return startedFromExistingPolygon.value;
  }

  function isFirstVertexClosable(marker) {
    return !startedFromExistingPolygon.value
      && marker._vertexIndex === 0
      && drawingPoints.value.length >= 3;
  }

  function buildVertexIcon(color, invalid = false, options = {}) {
    const { closeTarget = false, drawOnly = false } = options;

    return L.divIcon({
      className: 'map-vertex-handle-icon',
      html: `<span class="map-vertex-handle-wrap"><span class="map-vertex-handle${invalid ? ' map-vertex-handle--invalid' : ''}${closeTarget ? ' map-vertex-handle--close-target' : ''}${drawOnly ? ' map-vertex-handle--draw-only' : ''}" style="--vertex-color:${color}"></span></span>`,
      iconSize: [24, 24],
      iconAnchor: [12, 12],
    });
  }

  function getVertexIconOptions(marker) {
    return {
      closeTarget: isFirstVertexClosable(marker),
      drawOnly: !canDragVertexMarkers(),
    };
  }

  function enableMapDraggingAfterVertexDrag() {
    if (!map) return;

    map._vertexDragActiveCount = Math.max(0, (map._vertexDragActiveCount ?? 1) - 1);
    if (map._vertexDragActiveCount === 0) {
      map.dragging.enable();
      map.scrollWheelZoom?.disable?.();
    }
  }

  function closePolygonDrawing() {
    if (drawingPoints.value.length < 3) {
      return false;
    }

    const boundary = getBoundary();
    if (boundary && !arePointsInsideOrOnPolygon(drawingPoints.value, boundary)) {
      toast.error('Todos os pontos do lote devem ficar dentro da quadra selecionada.');
      return false;
    }

    startedFromExistingPolygon.value = true;
    refreshTempPolyline(true);
    refreshVertexMarkerStyles();
    syncDrawingCursorPreview();
    ensureMapDraggingEnabled();
    toast.info('Polígono fechado. Clique em Salvar demarcação para confirmar a área.');
    return true;
  }

  function tryClosePolygonOnFirstVertexTap(marker) {
    if (marker._vertexIndex !== 0 || drawingPoints.value.length < 3) {
      return false;
    }

    if (closePolygonFromVertexPending) {
      return true;
    }

    closePolygonFromVertexPending = true;
    clearFirstVertexCloseTimer();

    window.requestAnimationFrame(() => {
      closePolygonFromVertexPending = false;

      if (!drawingMode.value || drawingPoints.value.length < 3) {
        return;
      }

      closePolygonDrawing();
    });

    return true;
  }

  function bindVertexMarkerDrag(marker) {
    if (!canDragVertexMarkers()) {
      return;
    }

    const onMove = (moveEvent) => {
      L.DomEvent.preventDefault(moveEvent);
      marker._wasDragged = true;

      const latLng = eventToMapLatLng(map, moveEvent);
      if (!latLng) {
        return;
      }

      marker.setLatLng(latLng);
      drawingPoints.value[marker._vertexIndex] = [latLng.lat, latLng.lng];
      refreshTempPolyline(drawingPoints.value.length >= 3, { livePreview: true });
      updateVertexHandleStyle(marker);
    };

    const onEnd = (endEvent) => {
      L.DomEvent.preventDefault(endEvent);

      map.off('mousemove', onMove);
      map.off('touchmove', onMove);
      map.off('mouseup', onEnd);
      map.off('touchend', onEnd);
      map.off('mouseleave', onEnd);
      document.removeEventListener('mousemove', onMove);
      document.removeEventListener('mouseup', onEnd);
      document.removeEventListener('touchmove', onMove);
      document.removeEventListener('touchend', onEnd);

      enableMapDraggingAfterVertexDrag();

      if (!marker._wasDragged && tryClosePolygonOnFirstVertexTap(marker)) {
        return;
      }

      refreshTempPolyline(drawingPoints.value.length >= 3);
      refreshVertexMarkerStyles();
      bringVertexMarkersToFront();
      bringEdgeLabelMarkersToFront();

      if (isLotMode.value && !isPointInsideOrOnPolygon(marker.getLatLng(), getBoundary())) {
        toast.warning('Vértice fora da área permitida.');
      }
    };

    const onStart = (startEvent) => {
      if (!drawingMode.value || !canDragVertexMarkers()) {
        return;
      }

      L.DomEvent.stopPropagation(startEvent);
      L.DomEvent.preventDefault(startEvent);

      marker._wasDragged = false;

      if (!map._vertexDragActiveCount) {
        map._vertexDragActiveCount = 0;
        map.dragging.disable();
        map.scrollWheelZoom?.disable?.();
      }
      map._vertexDragActiveCount += 1;

      map.on('mousemove', onMove);
      map.on('touchmove', onMove);
      map.on('mouseup', onEnd);
      map.on('touchend', onEnd);
      map.on('mouseleave', onEnd);
      document.addEventListener('mousemove', onMove);
      document.addEventListener('mouseup', onEnd);
      document.addEventListener('touchmove', onMove, { passive: false });
      document.addEventListener('touchend', onEnd);
    };

    marker.on('mousedown', onStart);
    marker.on('touchstart', onStart);
  }

  function updateVertexHandleStyle(marker) {
    if (!marker?.getElement) return;

    const coord = drawingPoints.value[marker._vertexIndex];
    if (!coord) return;

    const invalid = isVertexInvalid(coord);
    const color = invalid ? '#DC2626' : getDrawingBaseColor();
    const handle = marker.getElement()?.querySelector('.map-vertex-handle');
    if (!handle) return;

    handle.classList.toggle('map-vertex-handle--invalid', invalid);
    handle.classList.toggle('map-vertex-handle--close-target', isFirstVertexClosable(marker));
    handle.classList.toggle('map-vertex-handle--draw-only', !canDragVertexMarkers());
    handle.style.setProperty('--vertex-color', color);

    const iconElement = marker.getElement?.();
    if (iconElement) {
      const interactive = canDragVertexMarkers() || isFirstVertexClosable(marker);
      iconElement.classList.toggle('map-vertex-handle-icon--interactive', interactive);
      iconElement.style.pointerEvents = interactive ? 'auto' : 'none';
    }
  }

  function refreshVertexMarkerStyles() {
    tempMarkers.forEach((marker) => updateVertexHandleStyle(marker));
  }

  function bringVertexMarkersToFront() {
    tempMarkers.forEach((marker) => marker.bringToFront?.());
  }

  function addDrawingMarker(coord, color, index) {
    const invalid = isVertexInvalid(coord);
    const markerColor = invalid ? '#DC2626' : color;

    const marker = L.marker(coord, {
      draggable: false,
      autoPan: false,
      zIndexOffset: 1000,
      icon: buildVertexIcon(markerColor, invalid),
    }).addTo(map);

    marker._vertexIndex = index;
    marker.setIcon(buildVertexIcon(markerColor, invalid, getVertexIconOptions(marker)));
    updateVertexHandleStyle(marker);
    bindVertexMarkerDrag(marker);

    marker.on('click', (event) => {
      if (!isFirstVertexClosable(marker)) {
        return;
      }

      L.DomEvent.stopPropagation(event);
      L.DomEvent.preventDefault(event);
      tryClosePolygonOnFirstVertexTap(marker);
    });

    marker.on('touchend', (event) => {
      if (canDragVertexMarkers() || !isFirstVertexClosable(marker)) {
        return;
      }

      L.DomEvent.stopPropagation(event);
      L.DomEvent.preventDefault(event);
      tryClosePolygonOnFirstVertexTap(marker);
    });

    marker.on('dblclick', (event) => {
      L.DomEvent.stopPropagation(event);
      L.DomEvent.preventDefault(event);
      clearFirstVertexCloseTimer();
      removeVertexAtIndex(marker._vertexIndex);
    });

    tempMarkers.push(marker);
  }

  function getDrawingStrokeColor() {
    const boundary = getBoundary();
    const zoneInvalid = boundary
      && getInvalidPointsInsidePolygon(drawingPoints.value, boundary).length > 0;

    return zoneInvalid ? '#DC2626' : getDrawingBaseColor();
  }

  function isDrawingStrokeInvalid() {
    const boundary = getBoundary();

    return Boolean(
      boundary
      && getInvalidPointsInsidePolygon(drawingPoints.value, boundary).length > 0,
    );
  }

  function updateLiveGpsMarker(latLng) {
    if (!map || !L || !latLng) {
      return;
    }

    const coords = [latLng.lat, latLng.lng];

    if (locationMarker) {
      locationMarker.setLatLng(coords);
      return;
    }

    locationMarker = L.circleMarker(coords, {
      radius: 8,
      color: '#2563EB',
      fillColor: '#3B82F6',
      fillOpacity: 0.85,
      weight: 2,
    }).addTo(map);
  }

  function shouldUseGpsLivePreview() {
    return Boolean(
      drawingMode.value
      && drawingPoints.value.length >= 1
      && gpsWalkPreviewEnabled.value
      && isCoarsePointerDevice()
      && typeof navigator !== 'undefined'
      && navigator.geolocation,
    );
  }

  function syncGpsDrawingPreview() {
    gpsPreview.sync({
      active: shouldUseGpsLivePreview(),
      onPosition: (position) => {
        gpsPreviewErrorNotified = false;
        gpsAccuracy.value = position.coords.accuracy;

        const latLng = {
          lat: position.coords.latitude,
          lng: position.coords.longitude,
        };

        cursorPreview.update(latLng);
        updateLiveGpsMarker(latLng);
      },
      onError: (error) => {
        if (gpsPreviewErrorNotified) {
          return;
        }

        gpsPreviewErrorNotified = true;
        toast.warning(`GPS em tempo real indisponível: ${error.message}`);
      },
    });
  }

  function syncDrawingCursorPreview() {
    if (!map || !L || !drawingMode.value) {
      gpsPreview.stop();
      cursorPreview.unbind();
      return;
    }

    cursorPreview.bind(map, L, {
      isActive: () => Boolean(drawingMode.value) && drawingPoints.value.length >= 1,
      getLastPoint: () => {
        const points = drawingPoints.value;
        return points.length ? points[points.length - 1] : null;
      },
      getStrokeColor: getDrawingStrokeColor,
      getInvalid: isDrawingStrokeInvalid,
      isCursorInvalid: (latLng) => {
        const boundary = getBoundary();
        if (!boundary || !latLng) {
          return false;
        }

        return !isPointInsideOrOnPolygon([latLng.lat, latLng.lng], boundary);
      },
    });

    syncGpsDrawingPreview();
  }

  function clearEdgeLabelMarkers() {
    edgeLabelMarkers.forEach((marker) => map?.removeLayer(marker));
    edgeLabelMarkers = [];
  }

  function refreshEdgeLabelsForCoords(
    coords,
    { invalid = false, onlyWhileDrawing = false, closed = null } = {},
  ) {
    if (onlyWhileDrawing && !drawingMode.value) return;
    if (!L || !map || !Array.isArray(coords) || coords.length < 2) return;

    const isClosedPolygon = closed ?? (startedFromExistingPolygon.value && coords.length >= 3);
    const edges = getPolygonEdgesMeters(coords, {
      closed: isClosedPolygon,
      includeClosingPreview: false,
    });

    edges.forEach((edge) => {
      const marker = L.marker(edge.midpoint, {
        interactive: false,
        keyboard: false,
        zIndexOffset: 1200,
        icon: L.divIcon({
          className: 'map-edge-label-icon',
          html: `<span class="map-edge-label${edge.isClosingPreview ? ' map-edge-label--closing' : ''}${edge.isShortEdge ? ' map-edge-label--short' : ''}${invalid ? ' map-edge-label--invalid' : ''}">${edge.lengthLabel}</span>`,
          iconSize: [0, 0],
        }),
      }).addTo(map);

      edgeLabelMarkers.push(marker);
    });
  }

  function bringEdgeLabelMarkersToFront() {
    edgeLabelMarkers.forEach((marker) => marker.bringToFront?.());
  }

  function refreshEdgeLabels() {
    clearEdgeLabelMarkers();

    if (!drawingMode.value || drawingPoints.value.length < 2) {
      return;
    }

    const boundary = getBoundary();
    const invalid = boundary
      && getInvalidPointsInsidePolygon(drawingPoints.value, boundary).length > 0;

    refreshEdgeLabelsForCoords(drawingPoints.value, { invalid });
  }

  function refreshSavedEdgeLabels() {
    clearEdgeLabelMarkers();

    const coords = coordinates?.value;
    if (!Array.isArray(coords) || coords.length < 2 || drawingMode.value) {
      return;
    }

    refreshEdgeLabelsForCoords(coords);
  }

  function refreshTempPolyline(closed = false, options = {}) {
    const { livePreview = false } = options;

    if (!L || drawingPoints.value.length < 2) return;
    if (map._tempLine) map.removeLayer(map._tempLine);

    const boundary = getBoundary();
    const zoneInvalid = boundary
      && getInvalidPointsInsidePolygon(drawingPoints.value, boundary).length > 0;
    const strokeColor = zoneInvalid ? '#DC2626' : getDrawingBaseColor();

    const layerOptions = {
      color: strokeColor,
      weight: 2,
      dashArray: '4',
      interactive: false,
      className: 'map-lot-path',
    };

    if (closed && drawingPoints.value.length >= 3) {
      map._tempLine = L.polygon(drawingPoints.value, {
        ...layerOptions,
        fillColor: strokeColor,
        fillOpacity: 0.12,
      }).addTo(map);
    } else {
      map._tempLine = L.polyline(drawingPoints.value, layerOptions).addTo(map);
    }

    if (livePreview) return;

    refreshEdgeLabels();
    refreshVertexMarkerStyles();
    bringVertexMarkersToFront();
    bringEdgeLabelMarkersToFront();
  }

  function clearTempLayers() {
    tempMarkers.forEach((marker) => map?.removeLayer(marker));
    tempMarkers = [];
    clearEdgeLabelMarkers();

    if (map?._tempLine) {
      map.removeLayer(map._tempLine);
      delete map._tempLine;
    }

    cursorPreview.clear();
  }

  function prepareMapForVertexEditing() {
    if (!map) return;

    map.touchRotate?.disable?.();

    const bearing = typeof map.getBearing === 'function' ? map.getBearing() : 0;
    if (bearing !== 0) {
      map.setBearing(0);
      refreshMapDisplay(map, mapLayersSetup ?? {});
    }
  }

  function restoreMapInteractionAfterDrawing() {
    if (!map) return;

    map._vertexDragActiveCount = 0;
    map.dragging.enable();
    configureMapRotation(map);
  }

  function drawSavedFeatureLayer() {
    if (!L || !map || drawingMode.value) return;

    if (savedFeatureLayer) {
      map.removeLayer(savedFeatureLayer);
      savedFeatureLayer = null;
    }

    const coords = getSavedFeatureCoordinates();
    if (!Array.isArray(coords) || coords.length < 2) {
      refreshSavedEdgeLabels();
      return;
    }

    savedFeatureLayer = (coords.length >= 3 ? L.polygon : L.polyline)(coords, {
      color: LOT_SAVED_FEATURE_COLOR,
      weight: 3,
      fillColor: LOT_SAVED_FEATURE_COLOR,
      fillOpacity: 0.28,
      interactive: false,
      className: 'map-lot-path map-lot-saved-feature',
    }).addTo(map);

    configureMapPathLayer(savedFeatureLayer);
    savedFeatureLayer.bringToFront?.();
    refreshSavedEdgeLabels();
  }

  function drawContextLots() {
    if (!L || !map) return;

    Object.values(contextLotLayerMap).forEach((layer) => map.removeLayer(layer));
    Object.keys(contextLotLayerMap).forEach((key) => {
      delete contextLotLayerMap[key];
    });

    const lots = contextLots?.value ?? [];
    lots.forEach((lot) => {
      const coords = normalizePolygonCoordinates(lot.coordinates);
      if (!coords || coords.length < 3) {
        return;
      }

      const style = getLotMapStyle(lot.status);
      const layer = L.polygon(coords, {
        color: style.color,
        weight: 2,
        fillColor: style.fill,
        fillOpacity: 0.35,
        interactive: false,
        className: 'map-lot-context-path',
      })
        .bindTooltip(buildLotMapLabel(lot), {
          sticky: true,
          direction: 'center',
          className: 'map-lot-context-label',
        })
        .addTo(map);

      configureMapPathLayer(layer);
      contextLotLayerMap[String(lot.id)] = layer;
    });
  }

  function getContextLotCoordinatePoints() {
    const lots = contextLots?.value ?? [];

    return lots.flatMap((lot) => normalizePolygonCoordinates(lot.coordinates) ?? []);
  }

  function fitMapToContextLots() {
    const points = getContextLotCoordinatePoints();
    if (points.length < 3) {
      return false;
    }

    return fitMapToPolygonCoords(points, [40, 40]);
  }

  function drawContextPerimeter() {
    if (!L || !map) return;

    const coords = contextPerimeter?.value;
    if (contextPerimeterLayer) {
      map.removeLayer(contextPerimeterLayer);
      contextPerimeterLayer = null;
    }

    if (!Array.isArray(coords) || coords.length < 3) return;

    contextPerimeterLayer = L.polygon(coords, {
      color: '#94A3B8',
      weight: 1.5,
      dashArray: '6',
      fillColor: '#94A3B8',
      fillOpacity: 0.05,
      interactive: false,
      className: 'map-lot-path',
    }).addTo(map);

    configureMapPathLayer(contextPerimeterLayer);
  }

  function drawContextStreets() {
    if (!L || !map) return;

    Object.values(contextStreetLayerMap).forEach((layer) => map.removeLayer(layer));
    Object.keys(contextStreetLayerMap).forEach((key) => {
      delete contextStreetLayerMap[key];
    });

    const streets = contextStreets?.value ?? [];
    streets.forEach((street) => {
      if (!hasValidStreetPolygon(street.coordinates?.length ?? 0)) {
        return;
      }

      const color = getStreetColor(street);
      const layer = L.polygon(street.coordinates, {
        color,
        weight: 2,
        fillColor: color,
        fillOpacity: 0.15,
        interactive: false,
        className: 'map-lot-path',
      })
        .bindTooltip(street.name, { sticky: true })
        .addTo(map);

      configureMapPathLayer(layer);
      contextStreetLayerMap[String(street.id)] = layer;
    });
  }

  function bindZoneLayerTooltip(layer, zone) {
    layer.unbindTooltip();

    if (!visibleZoneNameTypes.value.includes(zone.type)) {
      return;
    }

    layer.bindTooltip(buildZoneTitleLabel(zone), {
      permanent: true,
      direction: 'center',
      className: 'map-zone-name-label',
      opacity: 1,
    });
    layer.openTooltip();
  }

  function syncZoneNameLabels() {
    const zones = contextZones?.value ?? [];

    Object.entries(contextZoneLayerMap).forEach(([zoneId, layer]) => {
      const zone = zones.find((item) => String(item.id) === String(zoneId));
      if (!zone) return;

      bindZoneLayerTooltip(layer, zone);
    });
  }

  function mappedZonesCountByType(type) {
    return (contextZones?.value ?? []).filter(
      (zone) => zone.type === type && Array.isArray(zone.coordinates) && zone.coordinates.length >= 3,
    ).length;
  }

  function drawContextZones() {
    if (!L || !map) return;

    Object.values(contextZoneLayerMap).forEach((layer) => map.removeLayer(layer));
    Object.keys(contextZoneLayerMap).forEach((key) => {
      delete contextZoneLayerMap[key];
    });

    const zones = contextZones?.value ?? [];
    zones.forEach((zone) => {
      if (!zone.coordinates?.length) return;

      const layer = L.polygon(zone.coordinates, {
        color: zone.color,
        weight: 1.5,
        fillColor: zone.color,
        fillOpacity: 0.1,
        interactive: false,
        className: 'map-lot-path',
      }).addTo(map);

      configureMapPathLayer(layer);
      contextZoneLayerMap[String(zone.id)] = layer;
      bindZoneLayerTooltip(layer, zone);
    });
  }

  let didInitialFit = false;
  let didFitToSavedFeature = false;

  function getSavedFeatureCoordinates() {
    return peekSavedCoordinates.value;
  }

  function seedActiveCoordinatesFromSaved() {
    const saved = normalizePolygonCoordinates(savedCoordinates?.value);
    if (!saved?.length || !coordinates) {
      return saved;
    }

    if ((coordinates.value?.length ?? 0) < 3) {
      coordinates.value = saved;
    }

    onCoordinatesChange?.(normalizePolygonCoordinates(coordinates.value) ?? saved);

    return normalizePolygonCoordinates(coordinates.value) ?? saved;
  }

  function hasSavedFeatureCoordinates() {
    const coords = getSavedFeatureCoordinates();
    return Array.isArray(coords) && coords.length >= 3;
  }

  function fitMapToPolygonCoords(coords, padding = [30, 30]) {
    const normalized = normalizePolygonCoordinates(coords);
    if (!map || !L || !normalized || normalized.length < 3) {
      return false;
    }

    map.fitBounds(L.polygon(normalized).getBounds(), { padding });
    return true;
  }

  function fitMapToSavedFeature({ force = false } = {}) {
    if (!map || !L || drawingMode.value || (!force && didFitToSavedFeature)) {
      return false;
    }

    const savedCoords = getSavedFeatureCoordinates();
    if (!savedCoords || savedCoords.length < 3) {
      return false;
    }

    const padding = mode === 'lot' ? [24, 24] : [30, 30];
    if (!fitMapToPolygonCoords(savedCoords, padding)) {
      return false;
    }

    didFitToSavedFeature = true;
    didInitialFit = true;
    return true;
  }

  function applyInitialMapView() {
    if (!map || !L || didInitialFit || drawingMode.value || !fitContextOnLoad) {
      return;
    }

    if (hasSavedFeatureCoordinates()) {
      fitMapToSavedFeature();
      return;
    }

    if (fitMapToContextLots()) {
      didInitialFit = true;
      return;
    }

    const boundary = boundaryPolygon?.value;
    if (fitMapToPolygonCoords(boundary)) {
      didInitialFit = true;
      return;
    }

    if (contextPerimeterLayer) {
      map.fitBounds(contextPerimeterLayer.getBounds(), { padding: [30, 30] });
      didInitialFit = true;
      return;
    }

    const center = mapCenter?.value;
    if (
      Array.isArray(center)
      && center.length === 2
      && Number.isFinite(Number(center[0]))
      && Number.isFinite(Number(center[1]))
    ) {
      map.setView(center, mapZoom?.value ?? 17);
      didInitialFit = true;
    }
  }

  function refreshContextLayers({ fit = false } = {}) {
    drawContextPerimeter();
    drawContextStreets();
    drawContextZones();
    drawContextLots();
    drawSavedFeatureLayer();

    if (fit || !didInitialFit) {
      applyInitialMapView();
    }
  }

  function preloadDrawingPoints(coords) {
    clearTempLayers();
    drawingPoints.value = coords.map((point) => [Number(point[0]), Number(point[1])]);
    startedFromExistingPolygon.value = drawingPoints.value.length >= 3;
    drawingPoints.value.forEach((coord, index) => {
      addDrawingMarker(coord, getDrawingBaseColor(), index);
    });
    refreshTempPolyline(drawingPoints.value.length >= 3);
    syncDrawingCursorPreview();
  }

  function isNearFirst(latlng) {
    if (drawingPoints.value.length < 3 || !L) return false;
    const first = L.latLng(drawingPoints.value[0][0], drawingPoints.value[0][1]);
    return latlng.distanceTo(first) < 15;
  }

  function onMapClick(event) {
    if (!drawingMode.value || !L) return;

    const { lat, lng } = event.latlng;
    drawingPoints.value.push([lat, lng]);
    addDrawingMarker([lat, lng], getDrawingBaseColor(), drawingPoints.value.length - 1);

    refreshTempPolyline(false);
    syncDrawingCursorPreview();
    ensureMapDraggingEnabled();

    const boundary = getBoundary();
    if (boundary && !isPointInsideOrOnPolygon([lat, lng], boundary)) {
      toast.warning('Vértice fora da área permitida.');
    }
  }

  function startDrawLot() {
    if (drawingMode.value) {
      return;
    }

    const seedCoords = seedActiveCoordinatesFromSaved();

    clearTempLayers();
    prepareMapForVertexEditing();
    setMapOverlaysPointerEvents(false);
    drawingMode.value = 'lot';

    if (savedFeatureLayer) {
      map?.removeLayer(savedFeatureLayer);
      savedFeatureLayer = null;
    }

    if (seedCoords?.length >= 3) {
      preloadDrawingPoints(seedCoords);
    } else {
      drawingPoints.value = [];
      startedFromExistingPolygon.value = false;
      gpsWalkPreviewEnabled.value = false;
    }

    map?.getContainer()?.style.setProperty('cursor', 'crosshair');
    syncDrawingCursorPreview();
    ensureMapDraggingEnabled();
  }

  function cancelDrawing() {
    clearFirstVertexCloseTimer();
    closePolygonFromVertexPending = false;
    gpsPreview.stop();
    cursorPreview.unbind();
    clearTempLayers();
    resetMapCursor();
    drawingPoints.value = [];
    startedFromExistingPolygon.value = false;
    gpsWalkPreviewEnabled.value = false;
    drawingMode.value = null;
    setMapOverlaysPointerEvents(true);
    restoreMapInteractionAfterDrawing();
    refreshContextLayers();
  }

  function finishDrawing({ closedExplicitly = false } = {}) {
    if (drawingPoints.value.length < 3) {
      toast.warning('O lote precisa de pelo menos 3 pontos.');
      return;
    }

    const boundary = getBoundary();
    if (boundary && !arePointsInsideOrOnPolygon(drawingPoints.value, boundary)) {
      toast.error('Todos os pontos do lote devem ficar dentro da quadra selecionada.');
      return;
    }

    const savedCoords = drawingPoints.value.map((point) => [Number(point[0]), Number(point[1])]);

    clearTempLayers();
    resetMapCursor();
    drawingPoints.value = [];
    startedFromExistingPolygon.value = false;
    gpsWalkPreviewEnabled.value = false;
    drawingMode.value = null;
    setMapOverlaysPointerEvents(true);
    restoreMapInteractionAfterDrawing();

    if (coordinates) {
      coordinates.value = savedCoords;
    }

    onCoordinatesChange?.(savedCoords);

    gpsPreview.stop();
    cursorPreview.unbind();
    drawSavedFeatureLayer();
    scheduleMapLayoutRefresh();

    if (onDemarcationSaved) {
      onDemarcationSaved(savedCoords);
    } else {
      toast.success(
        mode === 'lot'
          ? 'Demarcação registrada no formulário. Clique em Salvar para persistir o lote.'
          : 'Demarcação salva.',
      );
    }
  }

  function removeVertexAtIndex(index) {
    if (!drawingMode.value || index < 0 || index >= drawingPoints.value.length) {
      return;
    }

    const minPoints = drawingMode.value === 'street' ? 2 : 1;
    if (drawingPoints.value.length <= minPoints) {
      toast.warning('Não é possível remover este ponto.');
      return;
    }

    drawingPoints.value.splice(index, 1);

    if (drawingPoints.value.length < 3) {
      startedFromExistingPolygon.value = false;
    }

    tempMarkers.forEach((marker) => map?.removeLayer(marker));
    tempMarkers = [];

    if (map?._tempLine) {
      map.removeLayer(map._tempLine);
      delete map._tempLine;
    }

    const baseColor = getDrawingBaseColor();
    drawingPoints.value.forEach((coord, pointIndex) => {
      addDrawingMarker(coord, baseColor, pointIndex);
    });

    if (drawingPoints.value.length >= 2) {
      refreshTempPolyline(drawingPoints.value.length >= 3);
    } else {
      clearEdgeLabelMarkers();
    }

    syncDrawingCursorPreview();
    ensureMapDraggingEnabled();
    toast.info('Ponto removido.');
  }

  function undoLastPoint() {
    if (!drawingPoints.value.length) return;

    drawingPoints.value.pop();
    if (drawingPoints.value.length < 3) {
      startedFromExistingPolygon.value = false;
    }

    const marker = tempMarkers.pop();
    if (marker) map?.removeLayer(marker);

    if (map?._tempLine) {
      map.removeLayer(map._tempLine);
      delete map._tempLine;
    }

    if (drawingPoints.value.length >= 2) {
      refreshTempPolyline(drawingPoints.value.length >= 3);
    } else {
      clearEdgeLabelMarkers();
    }

    syncDrawingCursorPreview();
    ensureMapDraggingEnabled();
  }

  function clearSavedFeature() {
    if (drawingMode.value) {
      cancelDrawing();
    }

    if (coordinates) {
      coordinates.value = null;
    }

    onCoordinatesChange?.(null);

    if (savedFeatureLayer) {
      map?.removeLayer(savedFeatureLayer);
      savedFeatureLayer = null;
    }

    clearEdgeLabelMarkers();
  }

  function captureGpsPoint() {
    if (!navigator.geolocation) {
      toast.error('GPS não disponível neste dispositivo.');
      return;
    }

    if (!drawingMode.value) {
      startDrawLot();
    }

    capturingGps.value = true;
    gpsAccuracy.value = null;

    navigator.geolocation.getCurrentPosition(
      (position) => {
        gpsAccuracy.value = position.coords.accuracy;
        const coords = [position.coords.latitude, position.coords.longitude];
        gpsWalkPreviewEnabled.value = true;

        if (drawingPoints.value.length && isNearFirst(L.latLng(coords[0], coords[1]))) {
          closePolygonDrawing();
        } else {
          drawingPoints.value.push(coords);
          addDrawingMarker(coords, getDrawingBaseColor(), drawingPoints.value.length - 1);
          refreshTempPolyline(false);
          syncDrawingCursorPreview();
          ensureMapDraggingEnabled();
        }

        map?.setView(coords, Math.max(map.getZoom(), 18));
        toast.success(`Ponto capturado! Precisão: ±${Math.round(position.coords.accuracy)}m`);
        capturingGps.value = false;
      },
      (error) => {
        toast.error(`Erro ao capturar GPS: ${error.message}`);
        capturingGps.value = false;
      },
      { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
    );
  }

  function goToMyLocation() {
    if (!navigator.geolocation) {
      toast.error('GPS não disponível neste dispositivo.');
      return;
    }

    locatingUser.value = true;

    navigator.geolocation.getCurrentPosition(
      (position) => {
        const coords = [position.coords.latitude, position.coords.longitude];

        if (map && L) {
          map.setView(coords, Math.max(map.getZoom(), 17));

          if (locationMarker) {
            map.removeLayer(locationMarker);
          }

          locationMarker = L.circleMarker(coords, {
            radius: 8,
            color: '#2563EB',
            fillColor: '#3B82F6',
            fillOpacity: 0.85,
            weight: 2,
          }).addTo(map);
        }

        locatingUser.value = false;
      },
      (error) => {
        toast.error(`Erro ao obter localização: ${error.message}`);
        locatingUser.value = false;
      },
      { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
    );
  }

  function rotateMapBy(degrees) {
    if (!map?.setBearing) return;
    map.setBearing(map.getBearing() + degrees);
  }

  function zoomMapIn() {
    map?.zoomIn();
  }

  function zoomMapOut() {
    map?.zoomOut();
  }

  async function initMap() {
    if (!mapContainer.value) return;

    L = (await import('leaflet')).default;
    await import('leaflet/dist/leaflet.css');
    await ensureMapRotation(L);

    const center = mapCenter?.value?.length === 2 ? mapCenter.value : [-11.4667, -39.9833];
    const zoom = mapZoom?.value ?? 17;

    map = L.map(mapContainer.value, {
      zoomControl: false,
      scrollWheelZoom: false,
      rotate: true,
      bearing: 0,
      rotateControl: false,
    }).setView(center, zoom);

    configureMapRotation(map);
    mapLayersSetup = await setupMapBaseLayers(map, L);

    map.on('click', onMapClick);

    seedActiveCoordinatesFromSaved();

    if (persistMapView) {
      map.on('moveend zoomend', () => {
        const centerPoint = map.getCenter();
        onMapViewChange?.({
          center: [centerPoint.lat, centerPoint.lng],
          zoom: map.getZoom(),
        });
      });
    }

    refreshContextLayers({ fit: true });
    lastMapContainerSizeKey = '';
    refreshMapLayout({ forceFullRefresh: true });
    bindMapFooterResizeObserver();
    mapReady.value = true;
  }

  function destroyMap() {
    if (fullscreenResizeHandler) {
      window.removeEventListener('resize', fullscreenResizeHandler);
      fullscreenResizeHandler = null;
    }

    mapFooterResizeObserver?.disconnect();
    mapFooterResizeObserver = null;

    if (mapLayoutRefreshTimer) {
      window.clearTimeout(mapLayoutRefreshTimer);
      mapLayoutRefreshTimer = null;
    }

    lastMapContainerSizeKey = '';
    map?.remove();
    map = null;
    L = null;
    gpsPreview.stop();
    cursorPreview.unbind();
    didInitialFit = false;
    didFitToSavedFeature = false;
    mapReady.value = false;
  }

  watch(
    () => [
      contextPerimeter?.value,
      contextStreets?.value,
      contextZones?.value,
      contextLots?.value,
      boundaryPolygon?.value,
      mapCenter?.value,
      mapZoom?.value,
      coordinates?.value,
      savedCoordinates?.value,
    ],
    () => {
      if (!map || !L || drawingMode.value) return;
      refreshContextLayers();
    },
    { deep: true },
  );

  watch(
    () => boundaryPolygon?.value,
    () => {
      if (!map || !L || !didInitialFit || drawingMode.value || hasSavedFeatureCoordinates()) {
        return;
      }

      fitMapToPolygonCoords(boundaryPolygon?.value);
    },
    { deep: true },
  );

  watch(
    () => coordinates?.value,
    (nextCoords, previousCoords) => {
      if (!map || !L || drawingMode.value) return;

      drawSavedFeatureLayer();

      const nextSaved = normalizePolygonCoordinates(nextCoords);
      const previousSaved = normalizePolygonCoordinates(previousCoords);
      const gainedSavedFeature =
        (nextSaved?.length ?? 0) >= 3
        && (previousSaved?.length ?? 0) < 3;

      if (gainedSavedFeature || (!didFitToSavedFeature && (nextSaved?.length ?? 0) >= 3)) {
        fitMapToSavedFeature({ force: gainedSavedFeature });
      }
    },
    { deep: true },
  );

  watch(
    () => savedCoordinates?.value,
    () => {
      if (!map || !L || drawingMode.value) return;

      seedActiveCoordinatesFromSaved();
      drawSavedFeatureLayer();

      if (!didFitToSavedFeature && hasSavedFeatureCoordinates()) {
        fitMapToSavedFeature({ force: true });
      }
    },
    { deep: true },
  );

  watch(
    () => boundaryPolygon?.value,
    () => {
      if (!map || !L || !drawingMode.value) return;
      refreshTempPolyline(drawingPoints.value.length >= 3);
    },
    { deep: true },
  );

  watch(isMapFullscreen, (active) => {
    if (!active && fullscreenResizeHandler) {
      window.removeEventListener('resize', fullscreenResizeHandler);
      fullscreenResizeHandler = null;
    } else if (active) {
      fullscreenResizeHandler = () => refreshMapLayout();
      window.addEventListener('resize', fullscreenResizeHandler);
    }
  });

  onUnmounted(() => {
    destroyMap();
  });

  return {
    mapContainer,
    mapSectionRef,
    mapFooterRef,
    mapReady,
    isMapFullscreen,
    toggleMapFullscreen,
    drawingMode,
    drawingPoints,
    isDrawing,
    boundaryHint,
    canSaveDrawing,
    hasSavedDemarcation,
    startedFromExistingPolygon,
    locatingUser,
    capturingGps,
    gpsAccuracy,
    initMap,
    destroyMap,
    refreshMapLayout,
    startDrawLot,
    cancelDrawing,
    finishDrawing,
    undoLastPoint,
    removeVertexAtIndex,
    clearSavedFeature,
    captureGpsPoint,
    goToMyLocation,
    rotateMapBy,
    zoomMapIn,
    zoomMapOut,
    visibleZoneNameTypes,
    hasMappedZones,
    mappedZonesCountByType,
    syncZoneNameLabels,
    computedArea: computed(() => {
      const coords = peekSavedCoordinates.value;
      if (!Array.isArray(coords) || coords.length < 3) return null;
      return computeGeodesicArea(coords);
    }),
  };
}
