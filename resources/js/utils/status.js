export function saleStatusClass(status) {
  return {
    active: 'bg-emerald-100 text-emerald-700',
    completed: 'bg-blue-100 text-blue-700',
    cancelled: 'bg-red-100 text-red-700',
  }[status] ?? 'bg-slate-100 text-slate-500';
}

export function saleStatusLabel(status) {
  return { active: 'Ativo', completed: 'Concluído', cancelled: 'Cancelado' }[status] ?? status;
}

export function installmentStatusClass(status) {
  return {
    paid: 'bg-emerald-100 text-emerald-700',
    pending: 'bg-amber-100 text-amber-700',
    overdue: 'bg-red-100 text-red-700',
  }[status] ?? 'bg-slate-100 text-slate-500';
}

export function installmentStatusLabel(status) {
  return { paid: 'Pago', pending: 'Pendente', overdue: 'Atrasado' }[status] ?? status;
}

export function installmentTypeLabel(type) {
  return { down_payment: 'Entrada', financing: 'Parcela' }[type] ?? type;
}

export function lotStatusClass(status) {
  return {
    available: 'bg-emerald-100 text-emerald-700',
    reserved: 'bg-amber-100 text-amber-700',
    sold: 'bg-red-100 text-red-700',
  }[status] ?? 'bg-slate-100 text-slate-500';
}

export function lotStatusLabel(status) {
  return { available: 'Disponível', reserved: 'Reservado', sold: 'Vendido' }[status] ?? status;
}
