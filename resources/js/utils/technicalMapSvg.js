import { getZoneTypeMapStyle } from '@/utils/mapFeatureColors';
import { STREET_MAP_STYLE } from '@/utils/mapStreets';
import { buildZoneTitleLabel } from '@/utils/zone';
import { formatLotDimensionsDisplay } from '@/utils/mapLots';
import { resolveStreetLabelPathLatLng } from '@/utils/streetGeometry';
import {
  computeLocalBounds,
  computeOriginLatLng,
  latLngToLocalMeters,
  normalizeLatLngRing,
  projectLatLngRing,
  ringCentroidLocal,
  rotateLocalMeters,
} from '@/utils/technicalMapProjection';

export const TECHNICAL_MAP_PAPER_SIZES = {
  A4: { width: 842, height: 595 },
  A3: { width: 1191, height: 842 },
  A2: { width: 1684, height: 1191 },
  A1: { width: 2384, height: 1684 },
};

export const DEFAULT_TECHNICAL_MAP_OPTIONS = {
  showPerimeter: true,
  showZones: true,
  showZoneNames: true,
  showStreets: true,
  showStreetNames: true,
  showLots: true,
  showLotNumbers: true,
  showLotDimensions: true,
  showLotEdgeDimensions: true,
  showScaleBar: true,
  showNorthArrow: true,
  showLegend: true,
  paperSize: 'A3',
  orientation: 'landscape',
  mapBearing: 0,
};

const PERIMETER_STYLE = { color: '#1E5F8E', fill: '#93C5FD' };
const LOT_STYLE = { color: '#2d6a45', fill: '#3d8a5a' };
const TITLE_BLOCK_HEIGHT = 92;
const MAP_PADDING = 56;
const DIM_OFFSET_METERS = 2.4;
const DIM_EXTENSION_METERS = 0.7;
const STREET_NAME_SPACING_METERS = 42;

function escapeXml(value) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function mergeOptions(options = {}) {
  return { ...DEFAULT_TECHNICAL_MAP_OPTIONS, ...options };
}

function ringToPointsAttr(ring, project) {
  return ring.map((point) => project(point).join(',')).join(' ');
}

function polygonSvg(ring, project, { stroke, fill, fillOpacity = 0.22, strokeWidth = 1.4, dashArray = null }) {
  if (!ring || ring.length < 3) {
    return '';
  }

  const points = ringToPointsAttr(ring, project);
  const dash = dashArray ? ` stroke-dasharray="${dashArray}"` : '';

  return `<polygon points="${points}" fill="${fill}" fill-opacity="${fillOpacity}" stroke="${stroke}" stroke-width="${strokeWidth}"${dash} />`;
}

function formatDimensionLabel(lengthMeters) {
  if (!Number.isFinite(lengthMeters)) {
    return '—';
  }

  if (lengthMeters >= 1000) {
    return `${(lengthMeters / 1000).toLocaleString('pt-BR', { maximumFractionDigits: 2 })} km`;
  }

  return `${lengthMeters.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} m`;
}

function renderDimensionLine(a, b, centroid, lengthMeters, project) {
  const dx = b[0] - a[0];
  const dy = b[1] - a[1];
  const length = Math.hypot(dx, dy);

  if (length < 0.8) {
    return '';
  }

  const ux = dx / length;
  const uy = dy / length;
  let px = -uy;
  let py = ux;
  const mx = (a[0] + b[0]) / 2;
  const my = (a[1] + b[1]) / 2;

  if (centroid) {
    const toCentroidX = centroid[0] - mx;
    const toCentroidY = centroid[1] - my;

    if (px * toCentroidX + py * toCentroidY > 0) {
      px = -px;
      py = -py;
    }
  }

  const extA = [a[0] + px * DIM_EXTENSION_METERS, a[1] + py * DIM_EXTENSION_METERS];
  const extB = [b[0] + px * DIM_EXTENSION_METERS, b[1] + py * DIM_EXTENSION_METERS];
  const dimA = [a[0] + px * DIM_OFFSET_METERS, a[1] + py * DIM_OFFSET_METERS];
  const dimB = [b[0] + px * DIM_OFFSET_METERS, b[1] + py * DIM_OFFSET_METERS];
  const label = formatDimensionLabel(lengthMeters);
  const [sxA, syA] = project(extA);
  const [sxB, syB] = project(extB);
  const [dxA, dyA] = project(dimA);
  const [dxB, dyB] = project(dimB);
  const [tx, ty] = project([(dimA[0] + dimB[0]) / 2, (dimA[1] + dimB[1]) / 2]);

  let angle = (Math.atan2(dy, dx) * 180) / Math.PI;

  if (angle > 90 || angle < -90) {
    angle += 180;
  }

  return `
    <line x1="${sxA}" y1="${syA}" x2="${dxA}" y2="${dyA}" class="technical-map-dim-extension" />
    <line x1="${sxB}" y1="${syB}" x2="${dxB}" y2="${dyB}" class="technical-map-dim-extension" />
    <line x1="${dxA}" y1="${dyA}" x2="${dxB}" y2="${dyB}" class="technical-map-dim-line" marker-start="url(#technical-map-dim-arrow)" marker-end="url(#technical-map-dim-arrow)" />
    <text x="${tx}" y="${ty}" transform="rotate(${angle.toFixed(2)} ${tx} ${ty})" class="technical-map-dim-text">${escapeXml(label)}</text>
  `;
}

function renderLotEdgeDimensions(ring, project) {
  if (!ring || ring.length < 3) {
    return '';
  }

  const centroid = ringCentroidLocal(ring);
  let html = '';

  for (let index = 0; index < ring.length; index += 1) {
    const a = ring[index];
    const b = ring[(index + 1) % ring.length];
    const lengthMeters = Math.hypot(b[0] - a[0], b[1] - a[1]);
    html += renderDimensionLine(a, b, centroid, lengthMeters, project);
  }

  return html;
}

function buildStreetRepeatedName(name, pathLengthMeters) {
  const count = Math.max(1, Math.ceil(pathLengthMeters / STREET_NAME_SPACING_METERS));

  return Array.from({ length: count }, () => name).join('   ·   ');
}

function pathLengthMetersLocal(ring) {
  let total = 0;

  for (let index = 1; index < ring.length; index += 1) {
    const dx = ring[index][0] - ring[index - 1][0];
    const dy = ring[index][1] - ring[index - 1][1];
    total += Math.hypot(dx, dy);
  }

  return total;
}

function renderStreetNames(streets, origin, bearingDeg, project, options) {
  if (!options.showStreetNames) {
    return { defs: '', labels: '' };
  }

  const defs = [];
  const labels = [];

  streets.forEach((street, index) => {
    const name = String(street?.name ?? '').trim();
    const pathLatLng = resolveStreetLabelPathLatLng(street);

    if (!name || !pathLatLng || pathLatLng.length < 2) {
      return;
    }

    const pathLocal = pathLatLng
      .map((point) => latLngToLocalMeters(point, origin))
      .map((point) => (bearingDeg ? rotateLocalMeters(point, bearingDeg) : point))
      .filter(Boolean);

    if (pathLocal.length < 2) {
      return;
    }

    const pathId = `technical-street-path-${index}`;
    const d = pathLocal.map((point, pointIndex) => {
      const [x, y] = project(point);

      return `${pointIndex === 0 ? 'M' : 'L'}${x.toFixed(2)} ${y.toFixed(2)}`;
    }).join(' ');

    defs.push(`<path id="${pathId}" d="${d}" fill="none" stroke="none" />`);

    const repeated = buildStreetRepeatedName(name, pathLengthMetersLocal(pathLocal));
    labels.push(`
      <text class="technical-map-street-name">
        <textPath href="#${pathId}" startOffset="3%">${escapeXml(repeated)}</textPath>
      </text>
    `);
  });

  return {
    defs: defs.join(''),
    labels: labels.join(''),
  };
}

function renderScaleBar(bounds, project, mapAreaHeight) {
  const candidates = [10, 20, 50, 100, 200, 500];
  const targetMeters = bounds.width / 6;
  const barMeters = candidates.find((value) => value >= targetMeters) ?? candidates[candidates.length - 1];
  const start = [bounds.minX, bounds.minY - 8];
  const end = [bounds.minX + barMeters, bounds.minY - 8];
  const [x1, y1] = project(start);
  const [x2, y2] = project(end);
  const labelY = Math.min(mapAreaHeight - 12, y1 + 18);

  return `
    <g class="technical-map-scale-bar">
      <line x1="${x1}" y1="${y1}" x2="${x2}" y2="${y2}" />
      <line x1="${x1}" y1="${y1 - 4}" x2="${x1}" y2="${y1 + 4}" />
      <line x1="${x2}" y1="${y2 - 4}" x2="${x2}" y2="${y2 + 4}" />
      <text x="${(x1 + x2) / 2}" y="${labelY}" text-anchor="middle">${escapeXml(formatDimensionLabel(barMeters))}</text>
    </g>
  `;
}

function renderNorthArrow(width, height, bearingDeg) {
  const x = width - 72;
  const y = 72;
  const rotation = -bearingDeg;

  return `
    <g class="technical-map-north-arrow" transform="translate(${x} ${y}) rotate(${rotation})">
      <line x1="0" y1="18" x2="0" y2="-20" />
      <polygon points="0,-24 -7,-8 7,-8" />
      <text x="0" y="34" text-anchor="middle">N</text>
    </g>
  `;
}

function renderLegend(x, y) {
  const items = [
    { label: 'Perímetro', color: PERIMETER_STYLE.color, fill: PERIMETER_STYLE.fill },
    { label: 'Quadra', color: '#0E7490', fill: '#22D3EE' },
    { label: 'Rua', color: STREET_MAP_STYLE.color, fill: STREET_MAP_STYLE.fill },
    { label: 'Lote', color: LOT_STYLE.color, fill: LOT_STYLE.fill },
  ];

  return `
    <g class="technical-map-legend" transform="translate(${x} ${y})">
      ${items.map((item, index) => `
        <rect x="0" y="${index * 18}" width="14" height="10" fill="${item.fill}" stroke="${item.color}" stroke-width="1" />
        <text x="20" y="${index * 18 + 9}">${escapeXml(item.label)}</text>
      `).join('')}
    </g>
  `;
}

function collectGeometryPoints({ perimeter, zones, streets, lots }, origin, bearingDeg) {
  const groups = [];

  if (perimeter?.length >= 3) {
    groups.push(projectLatLngRing(perimeter, origin, bearingDeg));
  }

  zones.forEach((zone) => {
    if (zone?.coordinates?.length >= 3) {
      groups.push(projectLatLngRing(zone.coordinates, origin, bearingDeg));
    }
  });

  streets.forEach((street) => {
    if (street?.coordinates?.length >= 3) {
      groups.push(projectLatLngRing(street.coordinates, origin, bearingDeg));
    }
  });

  lots.forEach((lot) => {
    if (lot?.coordinates?.length >= 3) {
      groups.push(projectLatLngRing(lot.coordinates, origin, bearingDeg));
    }
  });

  return groups.filter((group) => group.length >= 3);
}

/**
 * @param {{
 *   development: { name?: string, map_bearing?: number, map_color?: string },
 *   perimeter?: Array<[number, number]> | null,
 *   zones?: Array<object>,
 *   streets?: Array<object>,
 *   lots?: Array<object>,
 * }} data
 * @param {Partial<typeof DEFAULT_TECHNICAL_MAP_OPTIONS>} [options]
 */
export function buildTechnicalMapSvg(data, options = {}) {
  const config = mergeOptions({
    ...options,
    mapBearing: options.mapBearing ?? data.development?.map_bearing ?? 0,
  });

  const paper = TECHNICAL_MAP_PAPER_SIZES[config.paperSize] ?? TECHNICAL_MAP_PAPER_SIZES.A3;
  const width = config.orientation === 'portrait' ? paper.height : paper.width;
  const height = config.orientation === 'portrait' ? paper.width : paper.height;
  const mapAreaHeight = height - TITLE_BLOCK_HEIGHT;

  const perimeter = normalizeLatLngRing(data.perimeter);
  const zones = Array.isArray(data.zones) ? data.zones : [];
  const streets = Array.isArray(data.streets) ? data.streets : [];
  const lots = Array.isArray(data.lots) ? data.lots : [];

  const origin = computeOriginLatLng([
  ...(perimeter.length ? [perimeter] : []),
  ...zones.map((zone) => normalizeLatLngRing(zone.coordinates)),
  ...streets.map((street) => normalizeLatLngRing(street.coordinates)),
  ...lots.map((lot) => normalizeLatLngRing(lot.coordinates)),
  ]);

  const bearingDeg = -Number(config.mapBearing || 0);
  const geometryGroups = collectGeometryPoints({ perimeter, zones, streets, lots }, origin, bearingDeg);
  const bounds = computeLocalBounds(geometryGroups);
  const scale = Math.min(
    (width - MAP_PADDING * 2) / bounds.width,
    (mapAreaHeight - MAP_PADDING * 2) / bounds.height,
  );

  const project = (point) => {
    const [x, y] = point;

    return [
      MAP_PADDING + (x - bounds.minX) * scale,
      mapAreaHeight - MAP_PADDING - (y - bounds.minY) * scale,
    ];
  };

  const mapScaleRatio = Math.round(1000 / scale);
  const developmentName = String(data.development?.name ?? 'Empreendimento').trim();
  const generatedAt = new Date().toLocaleString('pt-BR');

  const layers = [];
  const dimensions = [];
  const labels = [];

  if (config.showPerimeter && perimeter.length >= 3) {
    const ring = projectLatLngRing(perimeter, origin, bearingDeg);
    layers.push(polygonSvg(ring, project, {
      stroke: data.development?.map_color || PERIMETER_STYLE.color,
      fill: PERIMETER_STYLE.fill,
      fillOpacity: 0.08,
      strokeWidth: 2,
    }));
  }

  if (config.showZones) {
    zones.forEach((zone) => {
      if (!zone?.coordinates || zone.coordinates.length < 3) {
        return;
      }

      const style = getZoneTypeMapStyle(zone.type);
      const ring = projectLatLngRing(zone.coordinates, origin, bearingDeg);
      layers.push(polygonSvg(ring, project, {
        stroke: style.color,
        fill: style.fill,
        fillOpacity: 0.18,
      }));

      if (config.showZoneNames) {
        const centroid = ringCentroidLocal(ring);

        if (centroid) {
          const [x, y] = project(centroid);
          labels.push(`<text x="${x}" y="${y}" class="technical-map-zone-label technical-map-zone-label--${escapeXml(zone.type || 'outro')}">${escapeXml(buildZoneTitleLabel(zone))}</text>`);
        }
      }
    });
  }

  if (config.showStreets) {
    streets.forEach((street) => {
      if (!street?.coordinates || street.coordinates.length < 3) {
        return;
      }

      const ring = projectLatLngRing(street.coordinates, origin, bearingDeg);
      layers.push(polygonSvg(ring, project, {
        stroke: STREET_MAP_STYLE.color,
        fill: STREET_MAP_STYLE.fill,
        fillOpacity: 0.35,
      }));
    });
  }

  if (config.showLots) {
    lots.forEach((lot) => {
      if (!lot?.coordinates || lot.coordinates.length < 3) {
        return;
      }

      const ring = projectLatLngRing(lot.coordinates, origin, bearingDeg);
      layers.push(polygonSvg(ring, project, {
        stroke: LOT_STYLE.color,
        fill: LOT_STYLE.fill,
        fillOpacity: 0.12,
      }));

      if (config.showLotEdgeDimensions) {
        dimensions.push(renderLotEdgeDimensions(ring, project));
      }

      const centroid = ringCentroidLocal(ring);

      if (!centroid) {
        return;
      }

      const [x, y] = project(centroid);
      const lotNumber = String(lot?.number ?? '').trim();
      const dimensionsLabel = formatLotDimensionsDisplay(lot);
      const labelLines = [];

      if (config.showLotNumbers && lotNumber) {
        labelLines.push(`Lote ${lotNumber}`);
      }

      if (config.showLotDimensions && dimensionsLabel) {
        labelLines.push(dimensionsLabel.replace(/x/gi, '×'));
      }

      if (labelLines.length) {
        const lineHeight = 12;
        const startY = y - ((labelLines.length - 1) * lineHeight) / 2;

        labelLines.forEach((line, index) => {
          labels.push(`<text x="${x}" y="${startY + index * lineHeight}" class="technical-map-lot-label${index > 0 ? ' technical-map-lot-label-sub' : ''}">${escapeXml(line)}</text>`);
        });
      }
    });
  }

  const streetNames = renderStreetNames(streets, origin, bearingDeg, project, config);
  const scaleBar = config.showScaleBar ? renderScaleBar(bounds, project, mapAreaHeight) : '';
  const northArrow = config.showNorthArrow ? renderNorthArrow(width, mapAreaHeight, config.mapBearing) : '';
  const legend = config.showLegend ? renderLegend(MAP_PADDING, mapAreaHeight - 88) : '';

  const svg = `<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}">
  <defs>
    <marker id="technical-map-dim-arrow" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="5" markerHeight="5" orient="auto-start-reverse">
      <path d="M 0 0 L 10 5 L 0 10 z" class="technical-map-dim-arrow-head" />
    </marker>
    ${streetNames.defs}
    <style>
      .technical-map-bg { fill: #ffffff; }
      .technical-map-dim-extension { stroke: #334155; stroke-width: 0.7; }
      .technical-map-dim-line { stroke: #334155; stroke-width: 0.8; fill: none; }
      .technical-map-dim-arrow-head { fill: #334155; }
      .technical-map-dim-text { font: 600 8px 'DM Sans', Arial, sans-serif; fill: #0f172a; text-anchor: middle; dominant-baseline: middle; }
      .technical-map-zone-label { font: 700 11px 'DM Sans', Arial, sans-serif; fill: #0f172a; text-anchor: middle; dominant-baseline: middle; }
      .technical-map-zone-label--quadra { fill: #0c4a6e; }
      .technical-map-zone-label--conjunto { fill: #1e3a8a; }
      .technical-map-zone-label--setor { fill: #4c1d95; }
      .technical-map-zone-label--rua { fill: #78350f; }
      .technical-map-lot-label { font: 700 9px 'DM Sans', Arial, sans-serif; fill: #1a3a28; text-anchor: middle; dominant-baseline: middle; }
      .technical-map-lot-label-sub { font-size: 8px; font-weight: 600; fill: #2d6a45; }
      .technical-map-street-name { font: 600 10px 'DM Sans', Arial, sans-serif; fill: #1e293b; stroke: #cbd5e1; stroke-width: 3px; paint-order: stroke fill; }
      .technical-map-scale-bar line { stroke: #0f172a; stroke-width: 1.2; }
      .technical-map-scale-bar text { font: 600 9px 'DM Sans', Arial, sans-serif; fill: #0f172a; text-anchor: middle; }
      .technical-map-north-arrow line { stroke: #0f172a; stroke-width: 1.4; }
      .technical-map-north-arrow polygon { fill: #0f172a; }
      .technical-map-north-arrow text { font: 700 10px 'DM Sans', Arial, sans-serif; fill: #0f172a; text-anchor: middle; }
      .technical-map-legend text { font: 600 9px 'DM Sans', Arial, sans-serif; fill: #334155; dominant-baseline: middle; }
      .technical-map-title { font: 800 18px 'Syne', 'DM Sans', Arial, sans-serif; fill: #1a3a28; }
      .technical-map-subtitle { font: 500 10px 'DM Sans', Arial, sans-serif; fill: #475569; }
      .technical-map-title-line { stroke: #cbd5e1; stroke-width: 1; }
    </style>
  </defs>
  <rect class="technical-map-bg" x="0" y="0" width="${width}" height="${height}" />
  <g id="technical-map-drawing">
    ${layers.join('')}
    ${dimensions.join('')}
    ${labels.join('')}
    ${streetNames.labels}
    ${scaleBar}
    ${northArrow}
    ${legend}
  </g>
  <line class="technical-map-title-line" x1="${MAP_PADDING}" y1="${mapAreaHeight + 12}" x2="${width - MAP_PADDING}" y2="${mapAreaHeight + 12}" />
  <text x="${MAP_PADDING}" y="${mapAreaHeight + 36}" class="technical-map-title">${escapeXml(developmentName)}</text>
  <text x="${MAP_PADDING}" y="${mapAreaHeight + 54}" class="technical-map-subtitle">Planta técnica · Escala 1:${mapScaleRatio} · Gerado em ${escapeXml(generatedAt)}</text>
  <text x="${width - MAP_PADDING}" y="${mapAreaHeight + 36}" class="technical-map-subtitle" text-anchor="end">SID360</text>
  <text x="${width - MAP_PADDING}" y="${mapAreaHeight + 54}" class="technical-map-subtitle" text-anchor="end">Projeção local métrica</text>
</svg>`;

  return {
    svg,
    width,
    height,
    mapScaleRatio,
    paperSize: config.paperSize,
    orientation: config.orientation,
  };
}
