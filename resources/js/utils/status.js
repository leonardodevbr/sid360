/** Semantic badge color tokens — use across all status badges. */
export const badgeColors = {
  success: 'bg-emerald-100 text-emerald-700',
  danger: 'bg-red-100 text-red-700',
  warning: 'bg-amber-100 text-amber-700',
  info: 'bg-blue-100 text-blue-700',
  neutral: 'bg-slate-100 text-slate-500',
};

export function developmentStatusClass(status) {
  return {
    active: badgeColors.success,
    inactive: badgeColors.danger,
    under_construction: badgeColors.warning,
  }[status] ?? badgeColors.neutral;
}

export function saleStatusClass(status) {
  return {
    active: badgeColors.success,
    completed: badgeColors.info,
    cancelled: badgeColors.danger,
  }[status] ?? badgeColors.neutral;
}

export function saleStatusLabel(status) {
  return { active: 'Ativo', completed: 'Concluído', cancelled: 'Cancelado' }[status] ?? status;
}

export function installmentStatusClass(status) {
  return {
    paid: badgeColors.success,
    pending: badgeColors.warning,
    overdue: badgeColors.danger,
  }[status] ?? badgeColors.neutral;
}

export function installmentStatusLabel(status) {
  return { paid: 'Pago', pending: 'Pendente', overdue: 'Atrasado' }[status] ?? status;
}

export function installmentTypeLabel(type) {
  return { down_payment: 'Entrada', financing: 'Parcela' }[type] ?? type;
}

export function lotStatusClass(status) {
  return {
    available: badgeColors.success,
    reserved: badgeColors.warning,
    sold: badgeColors.danger,
  }[status] ?? badgeColors.neutral;
}

export function lotStatusLabel(status) {
  return { available: 'Disponível', reserved: 'Reservado', sold: 'Vendido' }[status] ?? status;
}

/** Positive confirmation badges (WhatsApp, verificado, etc.). */
export const confirmationBadgeClass = badgeColors.success;
