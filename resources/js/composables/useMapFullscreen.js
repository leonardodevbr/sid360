import { ref, onMounted, onUnmounted, watch, nextTick } from 'vue';

export function useMapFullscreen(_sectionRef, onResize) {
  const isFullscreen = ref(false);

  function refreshMapSize() {
    window.requestAnimationFrame(() => {
      onResize?.();
      window.setTimeout(() => onResize?.(), 150);
      window.setTimeout(() => onResize?.(), 400);
    });
  }

  function toggleFullscreen() {
    isFullscreen.value = !isFullscreen.value;
  }

  function onKeyDown(event) {
    if (event.key === 'Escape' && isFullscreen.value) {
      isFullscreen.value = false;
    }
  }

  watch(isFullscreen, async () => {
    document.body.style.overflow = isFullscreen.value ? 'hidden' : '';
    await nextTick();
    refreshMapSize();
  });

  onMounted(() => {
    document.addEventListener('keydown', onKeyDown);
  });

  onUnmounted(() => {
    document.removeEventListener('keydown', onKeyDown);
    document.body.style.overflow = '';
  });

  return {
    isFullscreen,
    toggleFullscreen,
  };
}
