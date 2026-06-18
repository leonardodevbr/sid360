/**
 * Interações de remoção de vértice no mapa.
 * Tamanho do alvo de clique — deve coincidir com iconSize/iconAnchor do divIcon.
 */
export const VERTEX_HANDLE_HIT_SIZE = 44;
export const VERTEX_HANDLE_ICON_ANCHOR = VERTEX_HANDLE_HIT_SIZE / 2;

let hoveredVertexIndex = null;
let hoveredVertexRemoveHandler = null;

export function isVertexRemoveModifierActive(originalEvent) {
  if (!originalEvent) {
    return false;
  }

  return Boolean(
    originalEvent.altKey
    || originalEvent.shiftKey
    || originalEvent.ctrlKey,
  );
}

export function registerVertexHoverTarget(marker, onRemove) {
  marker.on('mouseover', () => {
    hoveredVertexIndex = marker._vertexIndex;
    hoveredVertexRemoveHandler = onRemove;
  });

  marker.on('mouseout', () => {
    if (hoveredVertexIndex === marker._vertexIndex) {
      hoveredVertexIndex = null;
      hoveredVertexRemoveHandler = null;
    }
  });
}

export function tryRemoveHoveredVertex() {
  if (hoveredVertexIndex == null || typeof hoveredVertexRemoveHandler !== 'function') {
    return false;
  }

  hoveredVertexRemoveHandler(hoveredVertexIndex);
  hoveredVertexIndex = null;
  hoveredVertexRemoveHandler = null;

  return true;
}

export function isVertexDeleteKey(event) {
  return event?.key === 'Delete' || event?.key === 'Backspace';
}

/**
 * Shift/Alt/Ctrl + clique no vértice. Retorna true se o evento foi consumido.
 */
export function tryVertexRemoveOnPointerDown(marker, event, {
  onRemove,
  onBeforeRemove = null,
  domEvent = null,
} = {}) {
  if (!isVertexRemoveModifierActive(event?.originalEvent)) {
    return false;
  }

  if (domEvent?.stopPropagation) {
    domEvent.stopPropagation(event);
  } else {
    event.originalEvent?.stopPropagation?.();
  }

  if (domEvent?.preventDefault) {
    domEvent.preventDefault(event);
  } else {
    event.originalEvent?.preventDefault?.();
  }

  onBeforeRemove?.();

  const index = marker._vertexIndex;
  let moved = false;

  const onMove = () => {
    moved = true;
  };

  const onUp = (upEvent) => {
    document.removeEventListener('mousemove', onMove);
    document.removeEventListener('mouseup', onUp);

    if (!moved && upEvent.button === 0) {
      onRemove(index);
    }
  };

  document.addEventListener('mousemove', onMove);
  document.addEventListener('mouseup', onUp);

  return true;
}

export function bindVertexContextMenuRemoval(marker, {
  onRemove,
  onBeforeRemove = null,
  domEvent = null,
} = {}) {
  marker.on('contextmenu', (event) => {
    if (domEvent?.preventDefault) {
      domEvent.preventDefault(event);
    } else {
      event.originalEvent?.preventDefault?.();
    }

    if (domEvent?.stopPropagation) {
      domEvent.stopPropagation(event);
    } else {
      event.originalEvent?.stopPropagation?.();
    }

    onBeforeRemove?.();
    onRemove(marker._vertexIndex);
  });
}

export function bindVertexRemoveInteractions(marker, options) {
  registerVertexHoverTarget(marker, options.onRemove);
  bindVertexContextMenuRemoval(marker, options);
}

export function setMapBoxZoomForDrawing(map, enabled) {
  if (!map?.boxZoom) {
    return;
  }

  if (enabled) {
    if (map._boxZoomDisabledForDrawing) {
      map.boxZoom.enable();
      map._boxZoomDisabledForDrawing = false;
    }

    return;
  }

  if (!map._boxZoomDisabledForDrawing) {
    map.boxZoom.disable();
    map._boxZoomDisabledForDrawing = true;
  }
}

export function setMapDrawingCursor(map, active) {
  const container = map?.getContainer?.();

  if (!container) {
    return;
  }

  container.classList.toggle('map-drawing-active', Boolean(active));
}
