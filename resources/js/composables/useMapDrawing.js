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
} from '@/utils/mapGeometry';
import { buildZoneTitleLabel } from '@/utils/zone';
import { getStreetColor, hasValidStreetPolygon } from '@/utils/mapStreets';

const LOT_DRAWING_COLOR = '#1E5F8E';

export function useMapDrawing(options) {
  const toast = useToast();

  const {
    mode,
    coordinates,
    contextPerimeter,
    contextStreets,
    contextZones,
    boundaryPolygon,
    mapCenter,
    mapZoom,
    persistMapView = false,
    fitContextOnLoad = true,
    onMapViewChange,
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
  let firstVertexCloseTimer = null;

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

  function refreshMapLayout() {
    syncMapContainerHeight();
    refreshMapDisplay(map, mapLayersSetup ?? {});
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

  function tryClosePolygonOnFirstVertexTap(marker) {
    if (marker._vertexIndex !== 0 || drawingPoints.value.length < 3) {
      return false;
    }

    clearFirstVertexCloseTimer();
    finishDrawing({ closedExplicitly: true });
    return true;
  }

  function bindVertexMarkerDrag(marker) {
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
      if (!drawingMode.value) return;

      L.DomEvent.stopPropagation(startEvent);
      L.DomEvent.preventDefault(startEvent);

      if (!canDragVertexMarkers()) {
        return;
      }

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
  }

  function refreshVertexMarkerStyles() {
    if (!L) return;

    const baseColor = getDrawingBaseColor();
    tempMarkers.forEach((marker, index) => {
      const coord = drawingPoints.value[index];
      if (!coord) return;

      const invalid = isVertexInvalid(coord);
      const color = invalid ? '#DC2626' : baseColor;
      marker.setIcon(buildVertexIcon(color, invalid, getVertexIconOptions(marker)));
    });
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
    bindVertexMarkerDrag(marker);

    marker.on('click', (event) => {
      L.DomEvent.stopPropagation(event);
      tryClosePolygonOnFirstVertexTap(marker);
    });

    marker.on('touchend', (event) => {
      if (canDragVertexMarkers()) return;

      L.DomEvent.stopPropagation(event);
      tryClosePolygonOnFirstVertexTap(marker);
    });

    marker.on('dblclick', (event) => {
      L.DomEvent.stopPropagation(event);
      L.DomEvent.preventDefault(event);
      clearFirstVertexCloseTimer();
      removeVertexAtIndex(marker._vertexIndex);
    });

    marker.on('mousedown', (event) => {
      L.DomEvent.stopPropagation(event);
    });

    tempMarkers.push(marker);
  }

  function clearEdgeLabelMarkers() {
    edgeLabelMarkers.forEach((marker) => map?.removeLayer(marker));
    edgeLabelMarkers = [];
  }

  function refreshEdgeLabelsForCoords(coords, { invalid = false, onlyWhileDrawing = false } = {}) {
    if (onlyWhileDrawing && !drawingMode.value) return;
    if (!L || !map || !Array.isArray(coords) || coords.length < 2) return;

    const isPolygonDrawing = coords.length >= 3;
    const edges = getPolygonEdgesMeters(coords, {
      closed: isPolygonDrawing,
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

    const coords = coordinates?.value;
    if (!Array.isArray(coords) || coords.length < 2) {
      refreshSavedEdgeLabels();
      return;
    }

    savedFeatureLayer = (coords.length >= 3 ? L.polygon : L.polyline)(coords, {
      color: LOT_DRAWING_COLOR,
      weight: 2,
      fillColor: LOT_DRAWING_COLOR,
      fillOpacity: 0.15,
      interactive: false,
      className: 'map-lot-path',
    }).addTo(map);

    configureMapPathLayer(savedFeatureLayer);
    refreshSavedEdgeLabels();
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

  function hasSavedFeatureCoordinates() {
    const coords = coordinates?.value;
    return Array.isArray(coords) && coords.length >= 3;
  }

  function fitMapToPolygonCoords(coords, padding = [30, 30]) {
    if (!map || !L || !Array.isArray(coords) || coords.length < 3) {
      return false;
    }

    map.fitBounds(L.polygon(coords).getBounds(), { padding });
    return true;
  }

  function applyInitialMapView() {
    if (!map || !L || didInitialFit || drawingMode.value || !fitContextOnLoad) {
      return;
    }

    if (hasSavedFeatureCoordinates()) {
      if (fitMapToPolygonCoords(coordinates.value)) {
        didInitialFit = true;
      }
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

    if (drawingPoints.value.length > 2 && isNearFirst(event.latlng)) {
      finishDrawing({ closedExplicitly: true });
      return;
    }

    refreshTempPolyline(false);
    refreshVertexMarkerStyles();

    const boundary = getBoundary();
    if (boundary && !isPointInsideOrOnPolygon([lat, lng], boundary)) {
      toast.warning('Vértice fora da área permitida.');
    }
  }

  function startDrawLot() {
    if (drawingMode.value === 'lot') {
      cancelDrawing();
      return;
    }

    clearTempLayers();
    prepareMapForVertexEditing();
    setMapOverlaysPointerEvents(false);
    drawingMode.value = 'lot';

    if (savedFeatureLayer) {
      map?.removeLayer(savedFeatureLayer);
      savedFeatureLayer = null;
    }

    if (coordinates?.value?.length >= 3) {
      preloadDrawingPoints(coordinates.value);
      toast.info('Área do lote carregada. Arraste os vértices ou adicione novos pontos.');
    } else {
      drawingPoints.value = [];
      startedFromExistingPolygon.value = false;
    }

    map?.getContainer()?.style.setProperty('cursor', 'crosshair');
  }

  function cancelDrawing() {
    clearFirstVertexCloseTimer();
    clearTempLayers();
    resetMapCursor();
    drawingPoints.value = [];
    startedFromExistingPolygon.value = false;
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
    drawingMode.value = null;
    setMapOverlaysPointerEvents(true);
    restoreMapInteractionAfterDrawing();

    if (coordinates) {
      coordinates.value = savedCoords;
    }

    drawSavedFeatureLayer();
    toast.success('Demarcação do lote salva.');
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
  }

  function clearSavedFeature() {
    if (drawingMode.value) {
      cancelDrawing();
    }

    if (coordinates) {
      coordinates.value = null;
    }

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

        if (drawingPoints.value.length && isNearFirst(L.latLng(coords[0], coords[1]))) {
          finishDrawing({ closedExplicitly: true });
        } else {
          drawingPoints.value.push(coords);
          addDrawingMarker(coords, getDrawingBaseColor(), drawingPoints.value.length - 1);
          refreshTempPolyline(false);
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
    map.invalidateSize();
    mapReady.value = true;
  }

  function destroyMap() {
    if (fullscreenResizeHandler) {
      window.removeEventListener('resize', fullscreenResizeHandler);
      fullscreenResizeHandler = null;
    }

    map?.remove();
    map = null;
    L = null;
    didInitialFit = false;
    mapReady.value = false;
  }

  watch(
    () => [
      contextPerimeter?.value,
      contextStreets?.value,
      contextZones?.value,
      boundaryPolygon?.value,
      mapCenter?.value,
      mapZoom?.value,
      coordinates?.value,
    ],
    () => {
      if (!map || !L) return;
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
    () => {
      if (!map || !L || drawingMode.value) return;
      drawSavedFeatureLayer();
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
      const coords = coordinates?.value;
      if (!Array.isArray(coords) || coords.length < 3) return null;
      return computeGeodesicArea(coords);
    }),
  };
}
