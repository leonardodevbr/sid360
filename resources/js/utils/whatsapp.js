export function formatBrazilWhatsappNumber(phone) {
  const digits = String(phone ?? '').replace(/\D/g, '');
  if (digits.length < 10) {
    return '';
  }
  return digits.length >= 11 ? `55${digits}` : `559${digits}`;
}

export function buildWhatsAppUrl(phone, message) {
  const numero = formatBrazilWhatsappNumber(phone);
  if (!numero) {
    return '';
  }
  return `https://wa.me/${numero}?text=${encodeURIComponent(message)}`;
}

export function installmentDisplayStatus(installment) {
  if (installment.status === 'paid') return 'paid';
  if (installment.status === 'overdue') return 'overdue';
  if (installment.status === 'pending' && installment.due_date) {
    const due = new Date(`${installment.due_date}T00:00:00`);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    if (due < today) return 'overdue';
  }
  return installment.status ?? 'pending';
}

export function buildManualOverdueMessage({ clientName, contractNo, installment, formatDate, formatCurrency }) {
  const label = installment.type === 'down_payment'
    ? 'Entrada'
    : `Parcela ${installment.number}`;

  return [
    `Olá, *${clientName}*! Tudo bem?`,
    '',
    'Aqui é da *Sid360 Imóveis*. Estou entrando em contato pessoalmente sobre o pagamento abaixo:',
    '',
    `📋 Contrato: *${contractNo}*`,
    `📄 ${label}`,
    `📅 Vencimento: *${formatDate(installment.due_date)}*`,
    `💰 Valor: *${formatCurrency(installment.value)}*`,
    '',
    'Gostaria de entender se houve alguma dificuldade e como podemos ajudar na regularização.',
    '',
    'Aguardo seu retorno.',
    '_Sid360 Imóveis_',
  ].join('\n');
}
