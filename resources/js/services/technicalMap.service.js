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
