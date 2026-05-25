/**
 * Abre uma aba vazia de forma síncrona (chamar no clique, antes de await).
 * Depois use open() com a URL retornada pela API.
 */
export function prepareNewTab() {
  const tab = window.open('about:blank', '_blank');

  return {
    open(url) {
      if (!url) {
        this.close();
        return false;
      }

      if (tab && !tab.closed) {
        tab.opener = null;
        tab.location.href = url;
        tab.focus?.();
        return true;
      }

      const fallback = window.open(url, '_blank', 'noopener,noreferrer');
      return fallback !== null;
    },
    close() {
      if (tab && !tab.closed) {
        tab.close();
      }
    },
  };
}
