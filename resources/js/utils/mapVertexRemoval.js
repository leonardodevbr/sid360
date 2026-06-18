/**
 * Remoção de vértice com Alt+clique (Option no Mac).
 * Shift fica reservado ao zoom por retângulo do Leaflet.
 */
export function isVertexRemoveModifierActive(originalEvent) {
  return Boolean(originalEvent?.altKey);
}

export function bindAltClickVertexRemoval(marker, {
  onRemove,
  onBeforeRemove = null,
  domEvent = null,
} = {}) {
  marker.on('mousedown', (event) => {
    if (!isVertexRemoveModifierActive(event.originalEvent)) {
      return;
    }

    if (domEvent?.stopPropagation) {
      domEvent.stopPropagation(event);
    } else {
      event.originalEvent.stopPropagation();
    }

    if (domEvent?.preventDefault) {
      domEvent.preventDefault(event);
    } else {
      event.originalEvent.preventDefault();
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
  });
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
