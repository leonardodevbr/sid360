import api from '@/services/api';
import { buildTechnicalMapSvg } from '@/utils/technicalMapSvg';
import {
  downloadTechnicalMapPdf,
  downloadTechnicalMapSvg,
} from '@/utils/technicalMapPdf';

export async function exportTechnicalMapClientPdf(data, options = {}) {
  return downloadTechnicalMapPdf(data, options);
}

export async function exportTechnicalMapClientSvg(data, options = {}) {
  return downloadTechnicalMapSvg(data, options);
}

export async function exportTechnicalMapServerPdf(developmentId, data, options = {}) {
  const result = buildTechnicalMapSvg(data, options);

  const response = await api.post(
    `/developments/${developmentId}/technical-map/pdf`,
    {
      svg: result.svg,
      paper_size: result.paperSize,
      orientation: result.orientation,
    },
    { responseType: 'blob' },
  );

  const blob = new Blob([response.data], { type: 'application/pdf' });
  const url = window.URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = `planta-tecnica-${developmentId}.pdf`;
  link.click();
  window.URL.revokeObjectURL(url);

  return result;
}

export function buildTechnicalMapPayload({
  development,
  zones = [],
  streets = [],
  lots = [],
}) {
  return {
    development: {
      name: development?.name ?? '',
      map_bearing: development?.map_bearing ?? 0,
      map_color: development?.map_color ?? null,
      coordinates: development?.coordinates ?? null,
    },
    perimeter: development?.coordinates ?? null,
    zones,
    streets,
    lots,
  };
}
