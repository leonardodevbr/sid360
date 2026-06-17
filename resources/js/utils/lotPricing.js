/**
 * Valor por m² efetivo: zona tem prioridade sobre o empreendimento.
 *
 * @param {{ price_per_m2?: number|null }} zone
 * @param {number|null|undefined} developmentBasePricePerM2
 * @returns {number} centavos por m²
 */
export function resolveEffectivePricePerM2(zone, developmentBasePricePerM2 = 0) {
  const zonePrice = Number(zone?.price_per_m2);
  if (Number.isFinite(zonePrice) && zonePrice > 0) {
    return zonePrice;
  }

  const basePrice = Number(developmentBasePricePerM2);
  if (Number.isFinite(basePrice) && basePrice > 0) {
    return basePrice;
  }

  return 0;
}

/**
 * @param {number|null|undefined} areaM2
 * @param {number} pricePerM2Cents centavos por m²
 * @returns {number} valor total em centavos
 */
export function computeLotTotalValueFromArea(areaM2, pricePerM2Cents) {
  const area = Number(areaM2);
  const pricePerM2 = Number(pricePerM2Cents);

  if (!Number.isFinite(area) || area <= 0 || !Number.isFinite(pricePerM2) || pricePerM2 <= 0) {
    return 0;
  }

  return Math.round(area * pricePerM2);
}
