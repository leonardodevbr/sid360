/**
 * Remoção de vértice com Shift+clique.
 * O arraste customizado usa preventDefault no mousedown e bloqueia click/dblclick do Leaflet.
 */
export function bindShiftClickVertexRemoval(marker, {
  onRemove,
  onBeforeRemove = null,
  domEvent = null,
} = {}) {
  marker.on('mousedown', (event) => {
    if (!event.originalEvent?.shiftKey) {
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
