/** UI labels (Portuguese) for API enum values (English). */

export const developmentStatusLabels = {
  active: 'Ativo',
  inactive: 'Inativo',
  under_construction: 'Em obras',
};

export const lotStatusLabels = {
  available: 'Disponível',
  reserved: 'Reservado',
  sold: 'Vendido',
  inactive: 'Inativo',
};

export const developmentStatusOptions = [
  { value: '', label: 'Todos' },
  { value: 'active', label: 'Ativo' },
  { value: 'inactive', label: 'Inativo' },
  { value: 'under_construction', label: 'Em obras' },
];

export const lotStatusOptions = [
  { value: '', label: 'Todos' },
  { value: 'available', label: 'Disponível' },
  { value: 'reserved', label: 'Reservado' },
  { value: 'sold', label: 'Vendido' },
  { value: 'inactive', label: 'Inativo' },
];

export const developmentStatusFormOptions = developmentStatusOptions.filter((o) => o.value !== '');
export const lotStatusFormOptions = lotStatusOptions.filter((o) => o.value !== '');
