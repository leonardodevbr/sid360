export const LOT_NUMBER_PARITY_OPTIONS = [
  { value: 'all', label: 'Todos' },
  { value: 'even', label: 'Apenas pares' },
  { value: 'odd', label: 'Apenas ímpares' },
];

export function normalizeLotNumberParity(parity) {
  if (parity === 'even' || parity === 'odd') {
    return parity;
  }

  return 'all';
}

export function matchesLotNumberParity(num, parity) {
  const value = Math.floor(Number(num) || 0);
  const normalizedParity = normalizeLotNumberParity(parity);

  if (normalizedParity === 'even') {
    return value % 2 === 0;
  }

  if (normalizedParity === 'odd') {
    return value % 2 !== 0;
  }

  return true;
}

export function alignStartToParity(startFrom, parity) {
  let current = Math.max(1, Math.floor(Number(startFrom) || 1));
  const normalizedParity = normalizeLotNumberParity(parity);

  if (normalizedParity === 'all') {
    return current;
  }

  while (!matchesLotNumberParity(current, normalizedParity)) {
    current += 1;
  }

  return current;
}

export function buildLotNumberSequence(startFrom, quantity, parity = 'all') {
  const count = Math.max(0, Math.floor(Number(quantity) || 0));

  if (count === 0) {
    return [];
  }

  const normalizedParity = normalizeLotNumberParity(parity);
  const step = normalizedParity === 'all' ? 1 : 2;
  let current = normalizedParity === 'all'
    ? Math.max(1, Math.floor(Number(startFrom) || 1))
    : alignStartToParity(startFrom, normalizedParity);

  const numbers = [];

  for (let i = 0; i < count; i += 1) {
    numbers.push(current);
    current += step;
  }

  return numbers;
}

export function resolveLotNumberForIndex(startFrom, index, parity = 'all') {
  const numbers = buildLotNumberSequence(startFrom, index + 1, parity);

  return numbers[index] ?? Math.max(1, Math.floor(Number(startFrom) || 1) + index);
}

export function formatLotNumberFromPattern(pattern, zoneName, num) {
  const value = Math.floor(Number(num) || 0);

  return pattern
    .replace('{zona}', zoneName)
    .replace('{numero}', String(value))
    .replace('{numero2}', String(value).padStart(2, '0'))
    .replace('{numero3}', String(value).padStart(3, '0'));
}
