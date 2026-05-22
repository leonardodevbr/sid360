import { ref, onMounted, onUnmounted } from 'vue';

export function useMapFullscreen(sectionRef, onResize) {
  const isFullscreen = ref(false);

  function refreshMapSize() {
    window.setTimeout(() => onResize?.(), 100);
  }

  function syncFullscreenState() {
    isFullscreen.value = document.fullscreenElement === sectionRef.value;
    refreshMapSize();
  }

  async function toggleFullscreen() {
    if (!sectionRef.value) return;

    try {
      if (document.fullscreenElement) {
        await document.exitFullscreen();
      } else {
        await sectionRef.value.requestFullscreen();
      }
    } catch {
      // Browser blocked or unsupported — ignore silently
    }
  }

  onMounted(() => {
    document.addEventListener('fullscreenchange', syncFullscreenState);
  });

  onUnmounted(() => {
    document.removeEventListener('fullscreenchange', syncFullscreenState);
    if (sectionRef.value && document.fullscreenElement === sectionRef.value) {
      document.exitFullscreen();
    }
  });

  return {
    isFullscreen,
    toggleFullscreen,
  };
}
