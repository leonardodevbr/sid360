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

export const DEFAULT_MANUAL_OVERDUE_MESSAGE = [
  'Olá, *{nome}*! Tudo bem?',
  '',
  'Aqui é da *Sid360 Imóveis*. Estou entrando em contato pessoalmente sobre o pagamento abaixo:',
  '',
  'Contrato: *{contrato}*',
  '{parcela}',
  'Vencimento: *{vencimento}*',
  'Valor: *{valor}*',
  '',
  'Gostaria de entender se houve alguma dificuldade e como podemos ajudar na regularização.',
  '',
  'Aguardo seu retorno.',
  '_Sid360 Imóveis_',
].join('\n');

export function interpolateTemplate(template, vars) {
  let result = template;
  Object.entries(vars).forEach(([key, value]) => {
    result = result.split(`{${key}}`).join(String(value ?? ''));
  });

  return result;
}

export function buildManualOverdueMessage({
  clientName,
  contractNo,
  installment,
  formatDate,
  formatCurrency,
  template,
}) {
  const parcela = installment.type === 'down_payment'
    ? 'Entrada'
    : `Parcela ${installment.number}`;

  return interpolateTemplate(template || DEFAULT_MANUAL_OVERDUE_MESSAGE, {
    nome: clientName,
    contrato: contractNo,
    parcela,
    vencimento: formatDate(installment.due_date),
    valor: formatCurrency(installment.value),
  });
}

export function buildPixPaymentMessage({
  clientName,
  contractNo,
  installment,
  pixCopyPaste,
  formatDate,
  formatCurrency,
}) {
  const parcela = installment.type === 'down_payment'
    ? 'Entrada'
    : `Parcela ${installment.number}`;

  return [
    `Olá, *${clientName}*!`,
    '',
    `Segue o PIX da *${parcela}* do contrato *${contractNo}*:`,
    '',
    `Vencimento: *${formatDate(installment.due_date)}*`,
    `Valor: *${formatCurrency(installment.value)}*`,
    '',
    '*Código PIX (Copia e Cola):*',
    pixCopyPaste,
    '',
    'Qualquer dúvida, estou à disposição.',
    '_Sid360 Imóveis_',
  ].join('\n');
}

export function buildBoletoPaymentMessage({
  clientName,
  contractNo,
  installment,
  formatDate,
  formatCurrency,
  barcode,
  pdfUrl,
}) {
  const parcela = installment.type === 'down_payment'
    ? 'Entrada'
    : `Parcela ${installment.number}`;

  const lines = [
    `Olá, *${clientName}*!`,
    '',
    `Segue o boleto da *${parcela}* do contrato *${contractNo}*:`,
    '',
    `Vencimento: *${formatDate(installment.due_date)}*`,
    `Valor: *${formatCurrency(installment.value)}*`,
  ];

  if (barcode) {
    lines.push('', '*Linha digitável:*', barcode);
  }

  if (pdfUrl) {
    lines.push('', '*Link do boleto:*', pdfUrl);
  }

  lines.push('', 'Qualquer dúvida, estou à disposição.', '_Sid360 Imóveis_');

  return lines.join('\n');
}
