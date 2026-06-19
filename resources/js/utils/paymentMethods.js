/**
 * Meios de pagamento aceitos para entrada/parcelas.
 * Espelha as constantes PAYMENT_METHOD_* de App\Models\Installment (fonte única de verdade no backend).
 */
export const PAYMENT_METHODS = [
  { value: 'dinheiro', label: 'Dinheiro' },
  { value: 'pix', label: 'PIX' },
  { value: 'cartao', label: 'Cartão' },
  { value: 'transferencia', label: 'Transferência' },
  { value: 'permuta', label: 'Permuta/Bem' },
  { value: 'outro', label: 'Outro' },
];

/**
 * Meios que exigem descrição livre (ex.: "Veículo Fiat Uno 2018, placa ABC1234").
 * Espelha Installment::PAYMENT_METHODS_REQUIRING_DESCRIPTION.
 */
export const PAYMENT_METHODS_REQUIRING_DESCRIPTION = ['cartao', 'transferencia', 'permuta', 'outro'];

export function paymentMethodLabel(method) {
  return PAYMENT_METHODS.find((option) => option.value === method)?.label ?? method ?? '';
}

export function paymentMethodRequiresDescription(method) {
  return PAYMENT_METHODS_REQUIRING_DESCRIPTION.includes(method);
}
