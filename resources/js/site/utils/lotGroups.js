function lotEffectiveArea(lot) {
  if (lot.area != null && lot.area !== '') {
    return Number(lot.area);
  }
  if (lot.area_computed != null && lot.area_computed !== '') {
    return Number(lot.area_computed);
  }
  return null;
}

function normalizeSizeLabel(label) {
  return String(label ?? '')
    .trim()
    .replace(/\s+/g, '')
    .toLowerCase();
}

export function lotGroupKey(lot) {
  const label = String(lot.size_label ?? '').trim();
  if (label) {
    return `label:${normalizeSizeLabel(label)}`;
  }

  const area = lotEffectiveArea(lot);
  if (area != null) {
    return `area:${area.toFixed(2)}`;
  }

  return `zone:${lot.zone?.id ?? 'none'}`;
}

export function lotGroupLabel(lot) {
  const label = String(lot.size_label ?? '').trim();
  if (label) {
    return label.replace(/x/gi, '×');
  }

  const area = lotEffectiveArea(lot);
  if (area != null) {
    return `${area.toLocaleString('pt-BR', { maximumFractionDigits: 0 })} m²`;
  }

  return lot.zone?.name ?? 'Outros';
}

/**
 * @param {Array<object>} lots
 * @returns {Array<object>}
 */
export function buildLotGroupsFromLots(lots) {
  const map = new Map();

  lots.forEach((lot) => {
    const key = lotGroupKey(lot);
    if (!map.has(key)) {
      map.set(key, {
        key,
        label: lotGroupLabel(lot),
        area: lotEffectiveArea(lot),
        available_count: 0,
        reserved_count: 0,
        sold_count: 0,
        total_count: 0,
        min_value: 0,
        max_value: 0,
        cover_photo: null,
        representative_lot_id: null,
        lot_ids: [],
        _values: [],
      });
    }

    const group = map.get(key);
    group.total_count += 1;
    group.lot_ids.push(lot.id);

    if (lot.status === 'available') {
      group.available_count += 1;
    } else if (lot.status === 'reserved') {
      group.reserved_count += 1;
    } else {
      group.sold_count += 1;
    }

    if (lot.total_value > 0) {
      group._values.push(lot.total_value);
    }

    if (!group.cover_photo && lot.cover_photo) {
      group.cover_photo = lot.cover_photo;
    }

    if (lot.status === 'available' && !group.representative_lot_id) {
      group.representative_lot_id = lot.id;
    }
  });

  return [...map.values()]
    .map((group) => {
      const values = group._values;
      delete group._values;

      return {
        ...group,
        min_value: values.length ? Math.min(...values) : 0,
        max_value: values.length ? Math.max(...values) : 0,
        representative_lot_id: group.representative_lot_id ?? group.lot_ids[0] ?? null,
      };
    })
    .sort((a, b) => a.label.localeCompare(b.label, 'pt-BR', { numeric: true }));
}

export function lotMapStatusColor(status) {
  if (status === 'available') {
    return '#25d366';
  }
  if (status === 'reserved') {
    return '#f59e0b';
  }
  return '#dc2626';
}
