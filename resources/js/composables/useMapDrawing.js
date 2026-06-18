import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useToast } from 'vue-toastification';
import { useMapFullscreen } from '@/composables/useMapFullscreen';
import {
  setupMapBaseLayers,
  applyMapEditingZoom,
  restoreMapDefaultZoom,
  MAP_EDITING_MAX_ZOOM,
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
  getPolygonCentroid,
  isPointInsideOrOnPolygon,
  normalizePolygonCoordinates,
} from '@/utils/mapGeometry';
import { buildZoneTitleLabel } from '@/utils/zone';
import { getLotMapStyle, buildMapFixedLabelIconHtml, formatLotDimensionsLabel } from '@/utils/mapLots';
import { getStreetColor, getMappedStreets, hasValidStreetPolygon, DEFAULT_STREET_COLOR } from '@/utils/mapStreets';
import { buildStreetNetworkVisualRings } from '@/utils/streetGeometry';
import { createCursorPreviewController } from '@/utils/mapDrawingPreview';
import { createGpsPreviewController, isCoarsePointerDevice } from '@/utils/mapGpsPreview';
import {
  applyMapDrawingSnap,
  MAP_SEGMENT_SNAP_PIXEL_RADIUS,
  resolveSnapToleranceMeters,
  findNearestPolygonEdgeInsert,
} from '@/utils/mapVertexSnap';
import { withMapSnapSettings } from '@/utils/mapSnapSettings';
import {
  bindVertexRemoveInteractions,
  tryVertexRemoveOnPointerDown,
  tryRemoveHoveredVertex,
  isVertexDeleteKey,
  VERTEX_HANDLE_HIT_SIZE,
  VERTEX_HANDLE_ICON_ANCHOR,
  setMapBoxZoomForDrawing,
  setMapDrawingCursor,
} from '@/utils/mapVertexRemoval';
import {
  captureHighAccuracyPosition,
  formatAccuracyHint,
  MAX_ACCEPTABLE_ACCURACY_M,
} from '@/utils/geolocation';

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
    featureLabel,
    editingLotId,
  } = options;

  const mapContainer = ref(null);
  const mapSectionRef = ref(null);
  const mapFooterRef = ref(null);

  let map = null;
  let L = null;
  let mapLayersSetup = null;
  let fullscreenResizeHandler = null;

  let contextPerimeterLayer = null;
  let contextStreetUnionLayer = null;
  const contextStreetLayerMap = {};
  const contextZoneLayerMap = {};
  const contextLotLayerMap = {};
  const contextLotLabelMarkers = [];
  let savedFeatureLayer = null;
  let tempMarkers = [];
  let edgeLabelMarkers = [];
  let locationMarker = null;
  let locationAccuracyCircle = null;
  let isFinishing = false;

  const drawingMode = ref(null);
  const drawingPoints = ref([]);
  const mapReady = ref(false);
  const locatingUser = ref(false);
  const capturingGps = ref(false);
  const gpsAccuracy = ref(null);
  const mapPanLocked = ref(false);
  const visibleZoneNameTypes = ref([]);
  const startedFromExistingPolygon = ref(false);
  const gpsWalkPreviewEnabled = ref(false);
  let firstVertexCloseTimer = null;
  let mapFooterResizeObserver = null;
  let mapLayoutRefreshTimer = null;
  let lastMapContainerSizeKey = '';
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
    isFinishing = true;

    if (map?._tempLine) {
      map.removeLayer(map._tempLine);
      delete map._tempLine;
    }

    cursorPreview.clear();

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

  const peekSavedCoordinates = computed(() => {
    if (drawingMode.value && drawingPoints.value.length >= 3) {
      return drawingPoints.value.map((point) => [Number(point[0]), Number(point[1])]);
    }

    return normalizePolygonCoordinates(coordinates?.value)
      ?? normalizePolygonCoordinates(savedCoordinates?.value);
  });

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

  function applyMapPanLockState() {
    if (!map) {
      return;
    }

    const container = map.getContainer();

    if ((map._vertexDragActiveCount ?? 0) > 0) {
      map.dragging.disable();
      return;
    }

    if (mapPanLocked.value) {
      map.dragging.disable();
      container?.classList.add('map-pan-locked');
    } else {
      map.dragging.enable();
      container?.classList.remove('map-pan-locked');
    }
  }

  function ensureMapDraggingEnabled() {
    applyMapPanLockState();
  }

  function toggleMapPanLock() {
    mapPanLocked.value = !mapPanLocked.value;
    applyMapPanLockState();
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
    return Boolean(drawingMode.value) && drawingPoints.value.length >= 1;
  }

  function isFirstVertexClosable(marker) {
    return !startedFromExistingPolygon.value
      && marker._vertexIndex === 0
      && drawingPoints.value.length >= 3;
  }

  function buildVertexIcon(color, invalid = false, options = {}) {
    const { closeTarget = false, drawOnly = false, interactive = false } = options;

    return L.divIcon({
      className: `map-vertex-handle-icon${interactive ? ' map-vertex-handle-icon--interactive' : ''}`,
      html: `<span class="map-vertex-handle-wrap"><span class="map-vertex-handle${invalid ? ' map-vertex-handle--invalid' : ''}${closeTarget ? ' map-vertex-handle--close-target' : ''}${drawOnly ? ' map-vertex-handle--draw-only' : ''}" style="--vertex-color:${color}"></span></span>`,
      iconSize: [VERTEX_HANDLE_HIT_SIZE, VERTEX_HANDLE_HIT_SIZE],
      iconAnchor: [VERTEX_HANDLE_ICON_ANCHOR, VERTEX_HANDLE_ICON_ANCHOR],
    });
  }

  function getVertexIconOptions(marker) {
    const interactive = canDragVertexMarkers() || isFirstVertexClosable(marker);

    return {
      closeTarget: isFirstVertexClosable(marker),
      drawOnly: !canDragVertexMarkers(),
      interactive,
    };
  }

  function enableMapDraggingAfterVertexDrag() {
    if (!map) return;

    map._vertexDragActiveCount = Math.max(0, (map._vertexDragActiveCount ?? 1) - 1);
    if (map._vertexDragActiveCount === 0) {
      applyMapPanLockState();
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

    clearFirstVertexCloseTimer();
    isFinishing = true;

    if (map?._tempLine) {
      map.removeLayer(map._tempLine);
      delete map._tempLine;
    }

    cursorPreview.clear();
    cursorPreview.unbind();

    finishDrawing({ closedExplicitly: true });
    return true;
  }

  function getDrawingSnapContext() {
    const lots = contextLots?.value ?? contextLots ?? [];
    const excludeLotId = editingLotId?.value ?? editingLotId ?? null;

    return {
      perimeterCoordinates: contextPerimeter?.value ?? contextPerimeter ?? [],
      zones: contextZones?.value ?? contextZones ?? [],
      streets: contextStreets?.value ?? contextStreets ?? [],
      lots,
      currentDrawingPoints: drawingPoints.value,
      excludeLotId,
    };
  }

  function applyDrawingSnap(lat, lng, overrides = {}) {
    return applyMapDrawingSnap(lat, lng, map, withMapSnapSettings({
      ...getDrawingSnapContext(),
      ...overrides,
    }));
  }

  function bindVertexMarkerDrag(marker) {
    const onMove = (moveEvent) => {
      L.DomEvent.preventDefault(moveEvent);
      marker._wasDragged = true;

      const latLng = eventToMapLatLng(map, moveEvent);
      if (!latLng) {
        return;
      }

      const snapped = applyDrawingSnap(latLng.lat, latLng.lng, {
        excludeDrawingVertexIndex: marker._vertexIndex,
        includeDrawingPoints: !startedFromExistingPolygon.value,
        includeDrawingSegments: !startedFromExistingPolygon.value,
        dragMode: true,
      });

      marker.setLatLng({ lat: snapped.lat, lng: snapped.lng });
      drawingPoints.value[marker._vertexIndex] = [snapped.lat, snapped.lng];
      cursorPreview.showSnapIndicator(
        { lat: snapped.lat, lng: snapped.lng },
        snapped.snapped,
      );
      refreshTempPolyline(
        startedFromExistingPolygon.value && drawingPoints.value.length >= 3,
        { livePreview: true },
      );
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

      cursorPreview.clearSnapIndicator();

      if (!marker._wasDragged && tryClosePolygonOnFirstVertexTap(marker)) {
        return;
      }

      refreshTempPolyline(
        startedFromExistingPolygon.value && drawingPoints.value.length >= 3,
      );
      refreshVertexMarkerStyles();
      bringVertexMarkersToFront();
      bringEdgeLabelMarkersToFront();
      syncDrawingCursorPreview();

      if (isLotMode.value && !isPointInsideOrOnPolygon(marker.getLatLng(), getBoundary())) {
        toast.warning('Vértice fora da área permitida.');
      }
    };

    const onStart = (startEvent) => {
      if (!drawingMode.value) {
        return;
      }

      if (tryVertexRemoveOnPointerDown(marker, startEvent, {
        onRemove: removeVertexAtIndex,
        onBeforeRemove: clearFirstVertexCloseTimer,
        domEvent: L,
      })) {
        return;
      }

      if (!canDragVertexMarkers()) {
        return;
      }

      L.DomEvent.stopPropagation(startEvent);
      L.DomEvent.preventDefault(startEvent);

      cursorPreview.clear();
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
      zIndexOffset: 2500,
      icon: buildVertexIcon(markerColor, invalid),
    }).addTo(map);

    marker._vertexIndex = index;
    marker.setIcon(buildVertexIcon(markerColor, invalid, getVertexIconOptions(marker)));
    updateVertexHandleStyle(marker);

    bindVertexRemoveInteractions(marker, {
      onRemove: removeVertexAtIndex,
      onBeforeRemove: clearFirstVertexCloseTimer,
      domEvent: L,
    });
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
      if (!isFirstVertexClosable(marker) || marker._wasDragged) {
        return;
      }

      L.DomEvent.stopPropagation(event);
      L.DomEvent.preventDefault(event);
      tryClosePolygonOnFirstVertexTap(marker);
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

  function updateLiveGpsMarker(latLng, accuracyM = null) {
    if (!map || !L || !latLng) {
      return;
    }

    const coords = [latLng.lat, latLng.lng];

    if (locationMarker) {
      locationMarker.setLatLng(coords);
    } else {
      locationMarker = L.circleMarker(coords, {
        radius: 8,
        color: '#2563EB',
        fillColor: '#3B82F6',
        fillOpacity: 0.85,
        weight: 2,
      }).addTo(map);
    }

    if (locationAccuracyCircle) {
      map.removeLayer(locationAccuracyCircle);
      locationAccuracyCircle = null;
    }

    if (typeof accuracyM === 'number' && accuracyM > 0) {
      const color = accuracyM <= 10
        ? '#059669'
        : accuracyM <= 30
          ? '#D97706'
          : '#DC2626';

      locationAccuracyCircle = L.circle(coords, {
        radius: accuracyM,
        color,
        fillColor: color,
        fillOpacity: 0.12,
        weight: 1,
      }).addTo(map);
    }
  }

  function clearLiveGpsMarker() {
    if (locationMarker && map) {
      map.removeLayer(locationMarker);
      locationMarker = null;
    }

    if (locationAccuracyCircle && map) {
      map.removeLayer(locationAccuracyCircle);
      locationAccuracyCircle = null;
    }
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
        const accuracy = position.coords.accuracy;
        gpsAccuracy.value = accuracy;

        const latLng = {
          lat: position.coords.latitude,
          lng: position.coords.longitude,
        };

        if (accuracy > MAX_ACCEPTABLE_ACCURACY_M) {
          return;
        }

        cursorPreview.update(latLng);
        updateLiveGpsMarker(latLng, accuracy);
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

    if (startedFromExistingPolygon.value) {
      cursorPreview.bind(map, L, {
        isActive: () => Boolean(drawingMode.value),
        getLastPoint: () => null,
        resolveCursorLatLng: (cursorLatLng) => {
          if (!cursorLatLng) {
            return cursorLatLng;
          }

          return applyDrawingSnap(cursorLatLng.lat, cursorLatLng.lng, {
            includeDrawingPoints: false,
            includeDrawingSegments: false,
          });
        },
        getStrokeColor: getDrawingStrokeColor,
        getInvalid: () => false,
        isCursorInvalid: () => false,
      });
      syncGpsDrawingPreview();
      return;
    }

    cursorPreview.bind(map, L, {
      isActive: () => Boolean(drawingMode.value) && drawingPoints.value.length >= 1,
      getLastPoint: () => {
        const points = drawingPoints.value;
        return points.length ? points[points.length - 1] : null;
      },
      resolveCursorLatLng: (cursorLatLng) => {
        if (!cursorLatLng) {
          return cursorLatLng;
        }

        return applyDrawingSnap(cursorLatLng.lat, cursorLatLng.lng);
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

    refreshEdgeLabelsForCoords(coords, { closed: coords.length >= 3 });
  }

  function removeTempShapeHitLayer() {
    if (map?._tempLineHit) {
      map.removeLayer(map._tempLineHit);
      delete map._tempLineHit;
    }
  }

  function addTempShapeHitLayer(coords, closed) {
    if (!map || !L || !coords?.length) {
      return;
    }

    removeTempShapeHitLayer();

    const hitOptions = {
      color: '#000000',
      weight: 16,
      opacity: 0,
      fillColor: '#000000',
      fillOpacity: 0.001,
      interactive: true,
      className: 'map-temp-shape-hit',
    };

    if (closed && coords.length >= 3) {
      map._tempLineHit = L.polygon(coords, hitOptions).addTo(map);
    } else {
      map._tempLineHit = L.polyline(coords, hitOptions).addTo(map);
    }

    bindTempShapeEdgeHandlers(map._tempLineHit);
    map._tempLineHit.bringToFront();
  }

  function refreshTempPolyline(closed = false, options = {}) {
    if (isFinishing) return;

    const { livePreview = false } = options;

    if (!L || drawingPoints.value.length < 2) return;
    if (map._tempLine) map.removeLayer(map._tempLine);
    removeTempShapeHitLayer();

    const boundary = getBoundary();
    const zoneInvalid = boundary
      && getInvalidPointsInsidePolygon(drawingPoints.value, boundary).length > 0;
    const strokeColor = zoneInvalid ? '#DC2626' : getDrawingBaseColor();
    const edgeInsertEnabled = canInsertVerticesOnEdge();
    const isClosedShape = (closed || startedFromExistingPolygon.value) && drawingPoints.value.length >= 3;

    const layerOptions = {
      color: strokeColor,
      weight: 2,
      dashArray: '4',
      interactive: false,
      className: 'map-lot-path',
    };

    if (isClosedShape) {
      map._tempLine = L.polygon(drawingPoints.value, {
        ...layerOptions,
        fillColor: strokeColor,
        fillOpacity: 0.12,
      }).addTo(map);
      bindFeatureLabel(map._tempLine);
    } else {
      map._tempLine = L.polyline(drawingPoints.value, layerOptions).addTo(map);
    }

    if (edgeInsertEnabled) {
      addTempShapeHitLayer(drawingPoints.value, isClosedShape);
    }

    refreshEdgeLabels();

    if (livePreview) return;

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

    removeTempShapeHitLayer();

    cursorPreview.clear();
  }

  function prepareMapForVertexEditing() {
    if (!map) return;

    setMapBoxZoomForDrawing(map, false);
    setMapDrawingCursor(map, true);
    applyMapEditingZoom(map, mapLayersSetup ?? {});
    map.touchRotate?.disable?.();

    const bearing = typeof map.getBearing === 'function' ? map.getBearing() : 0;
    if (bearing !== 0) {
      map.setBearing(0);
      refreshMapDisplay(map, mapLayersSetup ?? {});
    }
  }

  function restoreMapInteractionAfterDrawing() {
    if (!map) return;

    setMapBoxZoomForDrawing(map, true);
    setMapDrawingCursor(map, false);
    restoreMapDefaultZoom(map, mapLayersSetup ?? {});
    map._vertexDragActiveCount = 0;
    mapPanLocked.value = false;
    applyMapPanLockState();
    configureMapRotation(map);
  }

  function bindFeatureLabel(layer) {
    if (!layer) {
      return;
    }

    layer.unbindTooltip();

    const label = featureLabel?.value?.trim();
    if (!label) {
      return;
    }

    layer.bindTooltip(label, {
      permanent: true,
      direction: 'center',
      className: 'map-lot-feature-label map-lot-fixed-center-label',
      opacity: 1,
    });
    layer.openTooltip();
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
    bindFeatureLabel(savedFeatureLayer);
    savedFeatureLayer.bringToFront?.();
    refreshSavedEdgeLabels();
  }

  function clearContextLotLabelMarkers() {
    contextLotLabelMarkers.forEach((marker) => map?.removeLayer(marker));
    contextLotLabelMarkers.length = 0;
  }

  function drawContextLots() {
    if (!L || !map) return;

    Object.values(contextLotLayerMap).forEach((layer) => map.removeLayer(layer));
    Object.keys(contextLotLayerMap).forEach((key) => {
      delete contextLotLayerMap[key];
    });
    clearContextLotLabelMarkers();

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
      }).addTo(map);

      const dimensionsLabel = formatLotDimensionsLabel(lot);
      if (dimensionsLabel) {
        const centroid = getPolygonCentroid(coords);

        if (centroid) {
          const marker = L.marker(centroid, {
            interactive: false,
            keyboard: false,
            zIndexOffset: 500,
            icon: L.divIcon({
              className: 'map-fixed-label-icon',
              html: buildMapFixedLabelIconHtml(dimensionsLabel),
              iconSize: [0, 0],
            }),
          }).addTo(map);

          contextLotLabelMarkers.push(marker);
        }
      }

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

    if (contextStreetUnionLayer) {
      map.removeLayer(contextStreetUnionLayer);
      contextStreetUnionLayer = null;
    }

    Object.values(contextStreetLayerMap).forEach((layer) => map.removeLayer(layer));
    Object.keys(contextStreetLayerMap).forEach((key) => {
      delete contextStreetLayerMap[key];
    });

    const mappedStreets = getMappedStreets(contextStreets?.value ?? []);
    if (!mappedStreets.length) {
      return;
    }

    const useUnionVisual = mappedStreets.length > 1;
    const mergedRings = useUnionVisual
      ? buildStreetNetworkVisualRings(mappedStreets)
      : [];

    const renderUnionVisual = useUnionVisual && mergedRings.length > 0;

    if (renderUnionVisual) {
      contextStreetUnionLayer = L.layerGroup();

      mergedRings.forEach((ring) => {
        L.polygon(ring, {
          color: DEFAULT_STREET_COLOR,
          weight: 2,
          fillColor: DEFAULT_STREET_COLOR,
          fillOpacity: 0.15,
          opacity: 0.95,
          interactive: false,
          className: 'map-street-union-visual map-lot-path',
        }).addTo(contextStreetUnionLayer);
      });

      contextStreetUnionLayer.addTo(map);
      configureMapPathLayer(contextStreetUnionLayer);
    }

    mappedStreets.forEach((street) => {
      const color = getStreetColor(street);
      const layer = L.polygon(street.coordinates, renderUnionVisual
        ? {
          color,
          weight: 0,
          opacity: 0,
          fillColor: color,
          fillOpacity: 0,
          interactive: false,
          className: 'map-lot-path',
        }
        : {
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

    const current = normalizePolygonCoordinates(coordinates.value);
    if ((current?.length ?? 0) >= 3) {
      return current;
    }

    coordinates.value = saved;
    onCoordinatesChange?.(saved);

    return saved;
  }

  function hasSavedFeatureCoordinates() {
    const coords = getSavedFeatureCoordinates();
    return Array.isArray(coords) && coords.length >= 3;
  }

  function fitMapToPolygonCoords(coords, padding = [30, 30], { maxZoom = null } = {}) {
    const normalized = normalizePolygonCoordinates(coords);
    if (!map || !L || !normalized || normalized.length < 3) {
      return false;
    }

    const fitOptions = { padding };
    const resolvedMaxZoom = maxZoom ?? (drawingMode.value ? MAP_EDITING_MAX_ZOOM : null);

    if (resolvedMaxZoom != null) {
      fitOptions.maxZoom = resolvedMaxZoom;
    }

    map.fitBounds(L.polygon(normalized).getBounds(), fitOptions);
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

    const padding = mode === 'lot' ? [48, 48] : [30, 30];
    if (!fitMapToPolygonCoords(savedCoords, padding)) {
      return false;
    }

    didFitToSavedFeature = true;
    didInitialFit = true;
    return true;
  }

  function refitActiveLotView({ force = true } = {}) {
    if (!map || !L || drawingMode.value || mode !== 'lot' || !hasSavedFeatureCoordinates()) {
      return false;
    }

    map.invalidateSize({ animate: false });
    return fitMapToSavedFeature({ force });
  }

  function scheduleActiveLotViewRefit({ force = true } = {}) {
    window.requestAnimationFrame(() => {
      window.requestAnimationFrame(() => {
        refitActiveLotView({ force });
      });
    });
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

    const snapped = applyDrawingSnap(event.latlng.lat, event.latlng.lng);
    const lat = snapped.lat;
    const lng = snapped.lng;

    drawingPoints.value.push([lat, lng]);
    addDrawingMarker([lat, lng], getDrawingBaseColor(), drawingPoints.value.length - 1);

    refreshTempPolyline(startedFromExistingPolygon.value && drawingPoints.value.length >= 3);
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

    if (isCoarsePointerDevice()) {
      mapPanLocked.value = true;
    }

    applyMapPanLockState();
  }

  function cancelDrawing() {
    clearFirstVertexCloseTimer();
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

    if (mode === 'lot' && hasSavedFeatureCoordinates()) {
      scheduleActiveLotViewRefit({ force: true });
    }
  }

  function finishDrawing({ closedExplicitly = false } = {}) {
    isFinishing = true;

    if (drawingPoints.value.length < 3) {
      isFinishing = false;
      toast.warning('O lote precisa de pelo menos 3 pontos.');
      return;
    }

    const boundary = getBoundary();
    if (boundary && !arePointsInsideOrOnPolygon(drawingPoints.value, boundary)) {
      isFinishing = false;
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

    if (map?._tempLine) {
      map.removeLayer(map._tempLine);
      delete map._tempLine;
    }

    if (coordinates) {
      coordinates.value = savedCoords;
    }

    onCoordinatesChange?.(savedCoords);

    gpsPreview.stop();
    cursorPreview.unbind();
    drawSavedFeatureLayer();

    if (map?._tempLine && !drawingMode.value) {
      map.removeLayer(map._tempLine);
      delete map._tempLine;
    }

    isFinishing = false;
    scheduleMapLayoutRefresh();

    if (mode === 'lot' && hasSavedFeatureCoordinates()) {
      scheduleActiveLotViewRefit({ force: true });
    }

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

  function canInsertVerticesOnEdge() {
    if (!drawingMode.value || drawingPoints.value.length < 2) {
      return false;
    }

    return startedFromExistingPolygon.value || drawingPoints.value.length >= 3;
  }

  function bindTempShapeEdgeHandlers(layer) {
    if (!layer || !L) {
      return;
    }

    layer.off('dblclick', onTempShapeDblClickInsertVertex);
    layer.on('dblclick', onTempShapeDblClickInsertVertex);
  }

  function onTempShapeDblClickInsertVertex(event) {
    L.DomEvent.stopPropagation(event);
    L.DomEvent.preventDefault(event);
    clearFirstVertexCloseTimer();

    const latLng = event.latlng ?? eventToMapLatLng(map, event);
    if (!latLng) {
      return;
    }

    insertVertexOnNearestEdge(latLng.lat, latLng.lng);
  }

  function insertVertexOnNearestEdge(lat, lng) {
    if (!canInsertVerticesOnEdge()) {
      return;
    }

    const closed = startedFromExistingPolygon.value || drawingPoints.value.length >= 3;
    const snapped = applyDrawingSnap(lat, lng, {
      includeDrawingPoints: !startedFromExistingPolygon.value,
      includeDrawingSegments: !startedFromExistingPolygon.value,
    });
    const toleranceMeters = resolveSnapToleranceMeters(map, snapped.lat, snapped.lng, {
      pixelRadius: MAP_SEGMENT_SNAP_PIXEL_RADIUS,
    });
    const nearestEdge = findNearestPolygonEdgeInsert(
      snapped.lat,
      snapped.lng,
      drawingPoints.value,
      { closed, toleranceMeters },
    );

    if (!nearestEdge) {
      toast.info('Clique mais perto de uma aresta para adicionar um ponto.');
      return;
    }

    insertVertexAtIndex(nearestEdge.insertIndex, nearestEdge.lat, nearestEdge.lng);
  }

  function insertVertexAtIndex(insertIndex, lat, lng) {
    if (!drawingMode.value) {
      return;
    }

    drawingPoints.value.splice(insertIndex, 0, [lat, lng]);

    if (drawingPoints.value.length >= 3) {
      startedFromExistingPolygon.value = true;
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

    refreshTempPolyline(startedFromExistingPolygon.value && drawingPoints.value.length >= 3);
    refreshVertexMarkerStyles();
    bringVertexMarkersToFront();
    syncDrawingCursorPreview();
    ensureMapDraggingEnabled();

    const boundary = getBoundary();
    if (boundary && !isPointInsideOrOnPolygon([lat, lng], boundary)) {
      toast.warning('Vértice fora da área permitida.');
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
      refreshTempPolyline(startedFromExistingPolygon.value && drawingPoints.value.length >= 3);
    } else {
      clearEdgeLabelMarkers();
    }

    refreshVertexMarkerStyles();
    bringVertexMarkersToFront();
    syncDrawingCursorPreview();
    ensureMapDraggingEnabled();
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
      refreshTempPolyline(startedFromExistingPolygon.value && drawingPoints.value.length >= 3);
    } else {
      clearEdgeLabelMarkers();
    }

    syncDrawingCursorPreview();
    ensureMapDraggingEnabled();
  }

  function handleDrawingEscape(event) {
    if (!drawingMode.value) {
      return;
    }

    if (isVertexDeleteKey(event) && tryRemoveHoveredVertex()) {
      event.preventDefault();
      event.stopImmediatePropagation();
      return;
    }

    if (event.key !== 'Escape') {
      return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();

    if (drawingPoints.value.length > 0) {
      undoLastPoint();
    } else {
      cancelDrawing();
    }
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

  async function captureGpsPoint() {
    if (!navigator.geolocation) {
      toast.error('GPS não disponível neste dispositivo.');
      return;
    }

    if (!drawingMode.value) {
      startDrawLot();
    }

    capturingGps.value = true;
    gpsAccuracy.value = null;

    try {
      const result = await captureHighAccuracyPosition({
        onProgress: (accuracy) => {
          gpsAccuracy.value = accuracy;
        },
      });

      gpsAccuracy.value = result.accuracy;
      gpsWalkPreviewEnabled.value = true;
      const coords = [result.lat, result.lng];

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
      updateLiveGpsMarker({ lat: coords[0], lng: coords[1] }, result.accuracy);

      const precisionLabel = formatAccuracyHint(result.accuracy);
      const baseMessage = result.averaged
        ? `Ponto capturado (média estável)! Precisão: ±${Math.round(result.accuracy)}m — ${precisionLabel}`
        : `Ponto capturado! Precisão: ±${Math.round(result.accuracy)}m — ${precisionLabel}`;

      if (result.accuracy > 30) {
        toast.warning(`${baseMessage}. Arraste o ponto no mapa para ajustar.`);
      } else {
        toast.success(`${baseMessage} Arraste o ponto no mapa para ajustar, se necessário.`);
      }
    } catch (error) {
      toast.error(error?.message ?? 'Erro ao capturar GPS.');
    } finally {
      capturingGps.value = false;
    }
  }

  async function goToMyLocation() {
    if (!navigator.geolocation) {
      toast.error('GPS não disponível neste dispositivo.');
      return;
    }

    locatingUser.value = true;

    try {
      const result = await captureHighAccuracyPosition({
        timeoutMs: 12000,
        onProgress: (accuracy) => {
          gpsAccuracy.value = accuracy;
        },
      });

      const coords = [result.lat, result.lng];

      if (map && L) {
        map.setView(coords, Math.max(map.getZoom(), 17));
        clearLiveGpsMarker();
        updateLiveGpsMarker({ lat: coords[0], lng: coords[1] }, result.accuracy);
      }

      gpsAccuracy.value = result.accuracy;
    } catch (error) {
      toast.error(error?.message ?? 'Erro ao obter localização.');
    } finally {
      locatingUser.value = false;
    }
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

    refreshContextLayers({ fit: false });
    lastMapContainerSizeKey = '';
    refreshMapLayout({ forceFullRefresh: true });
    bindMapFooterResizeObserver();

    if (mode === 'lot' && hasSavedFeatureCoordinates()) {
      scheduleActiveLotViewRefit({ force: true });
    } else {
      applyInitialMapView();
    }

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
    () => featureLabel?.value,
    () => {
      if (!map || !L) {
        return;
      }

      if (drawingMode.value) {
        if (map._tempLine && startedFromExistingPolygon.value) {
          bindFeatureLabel(map._tempLine);
        }
        return;
      }

      if (savedFeatureLayer) {
        bindFeatureLabel(savedFeatureLayer);
      }
    },
  );

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
        if (mode === 'lot') {
          scheduleActiveLotViewRefit({ force: gainedSavedFeature || !didFitToSavedFeature });
        } else {
          fitMapToSavedFeature({ force: gainedSavedFeature });
        }
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
        if (mode === 'lot') {
          scheduleActiveLotViewRefit({ force: true });
        } else {
          fitMapToSavedFeature({ force: true });
        }
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

  watch(isMapFullscreen, () => {
    if (!isMapFullscreen.value && fullscreenResizeHandler) {
      window.removeEventListener('resize', fullscreenResizeHandler);
      fullscreenResizeHandler = null;
    } else if (isMapFullscreen.value) {
      fullscreenResizeHandler = () => refreshMapLayout();
      window.addEventListener('resize', fullscreenResizeHandler);
    }

    scheduleMapLayoutRefresh();

    if (mode === 'lot' && hasSavedFeatureCoordinates() && !drawingMode.value) {
      scheduleActiveLotViewRefit({ force: true });
    }
  });

  onMounted(() => {
    document.addEventListener('keydown', handleDrawingEscape, true);
  });

  onUnmounted(() => {
    document.removeEventListener('keydown', handleDrawingEscape, true);
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
    mapPanLocked,
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
    toggleMapPanLock,
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
