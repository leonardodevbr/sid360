/**
 * Tipos de documento aceitos para clientes/vendas.
 * Espelha as constantes TYPE_* de App\Models\ClientDocument (fonte única de verdade no backend).
 */
export const DOCUMENT_TYPES = [
  { value: 'rg', label: 'RG' },
  { value: 'cpf', label: 'CPF' },
  { value: 'cnh', label: 'CNH' },
  { value: 'comprovante_residencia', label: 'Comprovante de residência' },
  { value: 'comprovante_renda', label: 'Comprovante de renda' },
  { value: 'outro', label: 'Outro' },
];

export function documentTypeLabel(type) {
  return DOCUMENT_TYPES.find((option) => option.value === type)?.label ?? type ?? '';
}

/**
 * Lados/páginas de um documento. Espelha as constantes SIDE_* de
 * App\Models\ClientDocument. Documentos de página única usam 'aberto'.
 */
export const DOCUMENT_SIDES = [
  { value: 'frente', label: 'Frente' },
  { value: 'verso', label: 'Verso' },
  { value: 'aberto', label: 'Aberto' },
];

export function documentSideLabel(side) {
  return DOCUMENT_SIDES.find((option) => option.value === side)?.label ?? side ?? '';
}
