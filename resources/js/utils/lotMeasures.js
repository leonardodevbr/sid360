/**
 * Helpers espelhados de App\Support\LotMeasures (área, faces, texto contratual).
 */

export const DEFAULT_LOT_FACES = [
  { name: 'Frente', meters: '' },
  { name: 'Lado esquerdo', meters: '' },
  { name: 'Lado direito', meters: '' },
  { name: 'Fundo', meters: '' },
];

export function resolveLotArea(lot) {
  if (lot?.area != null && lot.area !== '') {
    return Number(lot.area);
  }

  if (lot?.area_computed != null && lot.area_computed !== '') {
    return Number(lot.area_computed);
  }

  return null;
}

export function normalizeLotFaces(faces) {
  if (!Array.isArray(faces)) {
    return [];
  }

  const normalized = [];

  for (const face of faces) {
    if (!face || typeof face !== 'object') {
      continue;
    }

    const name = String(face.name ?? '').trim();
    const metersRaw = face.meters;
    const meters = metersRaw === '' || metersRaw == null ? null : Number(metersRaw);

    if (!name || meters == null || !Number.isFinite(meters) || meters <= 0) {
      continue;
    }

    normalized.push({ name, meters });
  }

  return normalized;
}

export function formatMetersLabel(meters) {
  const numeric = Number(meters);

  if (!Number.isFinite(numeric)) {
    return '';
  }

  if (Math.abs(numeric - Math.round(numeric)) < 0.001) {
    return String(Math.round(numeric));
  }

  return numeric.toLocaleString('pt-BR', { maximumFractionDigits: 2 });
}

export function formatFacesAsDimensions(faces, { useTimes = true } = {}) {
  const normalized = normalizeLotFaces(faces);

  if (normalized.length === 0) {
    return null;
  }

  if (normalized.length === 2) {
    const sep = useTimes ? '×' : 'x';
    return `${formatMetersLabel(normalized[0].meters)}${sep}${formatMetersLabel(normalized[1].meters)}`;
  }

  return normalized
    .map((face) => `${face.name} ${formatMetersLabel(face.meters)}`)
    .join(' · ');
}

export function resolveLotDimensionsLabel(lot, { useTimes = true } = {}) {
  const sizeLabel = String(lot?.size_label ?? '').trim();

  if (sizeLabel) {
    return sizeLabel
      .replace(/m$/i, '')
      .replace(/[×xX]/g, useTimes ? '×' : 'x')
      .trim() || null;
  }

  return formatFacesAsDimensions(lot?.faces, { useTimes });
}

function joinPortugueseList(items) {
  if (items.length === 0) {
    return '';
  }

  if (items.length === 1) {
    return items[0];
  }

  if (items.length === 2) {
    return `${items[0]} e ${items[1]}`;
  }

  const last = items[items.length - 1];
  return `${items.slice(0, -1).join(', ')} e ${last}`;
}

export function buildAutoContractMeasuresText(lot) {
  const parts = [];
  const area = resolveLotArea(lot);

  if (area != null) {
    const formatted = Math.round(area).toLocaleString('pt-BR');
    parts.push(`com área total de ${formatted}m² (${formatted} metros quadrados)`);
  }

  const faces = normalizeLotFaces(lot?.faces);

  if (faces.length > 0) {
    const faceParts = faces.map(
      (face) => `${face.name} de ${formatMetersLabel(face.meters)}m`,
    );
    parts.push(`medindo ${joinPortugueseList(faceParts)}`);
  } else {
    const dimensions = resolveLotDimensionsLabel(lot, { useTimes: true });
    if (dimensions) {
      parts.push(`medindo ${dimensions}`);
    }
  }

  return parts.length ? parts.join(', ') : null;
}

export function resolveLotContractMeasuresText(lot, saleOverride = null) {
  const override = String(saleOverride ?? '').trim();
  if (override) {
    return override;
  }

  const lotText = String(lot?.contract_measures_text ?? '').trim();
  if (lotText) {
    return lotText;
  }

  return buildAutoContractMeasuresText(lot);
}

export function facesForForm(faces) {
  const normalized = normalizeLotFaces(faces);

  if (normalized.length > 0) {
    return normalized.map((face) => ({
      name: face.name,
      meters: String(face.meters),
    }));
  }

  return DEFAULT_LOT_FACES.map((face) => ({ ...face }));
}
