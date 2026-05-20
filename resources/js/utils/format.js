import { format as formatDateFns, parseISO } from 'date-fns';
import { ptBR } from 'date-fns/locale';

/**
 * Formata um valor em centavos como moeda brasileira (R$).
 * O banco armazena em centavos; apenas na exibição convertemos para reais.
 * @param {number|string} cents - Valor em centavos (ex: 20000 = R$ 200,00)
 * @returns {string} Valor formatado (ex: "R$ 1.234,56")
 */
export function formatCurrency(cents) {
  const numCents = typeof cents === 'string' ? parseInt(cents, 10) : cents;

  if (isNaN(numCents) || numCents == null) {
    return 'R$ 0,00';
  }

  const reais = Number(numCents) / 100;

  return new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
  }).format(reais);
}

/**
 * Formata uma data usando date-fns com locale pt-BR
 * @param {Date|string} date - Data a ser formatada
 * @param {string} formatStr - Formato desejado (ex: 'dd/MM/yyyy', 'dd/MM/yyyy HH:mm')
 * @returns {string} Data formatada
 */
export function formatDate(date, formatStr = 'dd/MM/yyyy') {
  if (!date) {
    return '';
  }

  try {
    const dateObj = typeof date === 'string' ? parseISO(date) : date;
    return formatDateFns(dateObj, formatStr, { locale: ptBR });
  } catch (error) {
    console.error('Erro ao formatar data:', error);
    return '';
  }
}

/**
 * Formata uma data com hora (formato padrão brasileiro)
 * @param {Date|string} date - Data a ser formatada
 * @returns {string} Data formatada (ex: "25/12/2024 14:30")
 */
export function formatDateTime(date) {
  return formatDate(date, 'dd/MM/yyyy HH:mm');
}

/**
 * Formata apenas a hora (HH:mm)
 * @param {Date|string} date - Data a ser formatada
 * @returns {string} Hora formatada (ex: "14:30")
 */
export function formatTime(date) {
  return formatDate(date, 'HH:mm');
}

/**
 * Aplica máscara de CNPJ (00.000.000/0001-00). Aceita entrada parcial.
 * @param {string} value - Valor digitado (só números ou já formatado)
 * @returns {string} Valor formatado
 */
export function formatCnpj(value) {
  if (value == null || value === '') return '';
  const digits = String(value).replace(/\D/g, '').slice(0, 14);
  if (digits.length <= 2) return digits;
  if (digits.length <= 5) return `${digits.slice(0, 2)}.${digits.slice(2)}`;
  if (digits.length <= 8) return `${digits.slice(0, 2)}.${digits.slice(2, 5)}.${digits.slice(5)}`;
  if (digits.length <= 12) return `${digits.slice(0, 2)}.${digits.slice(2, 5)}.${digits.slice(5, 8)}/${digits.slice(8)}`;
  return `${digits.slice(0, 2)}.${digits.slice(2, 5)}.${digits.slice(5, 8)}/${digits.slice(8, 12)}-${digits.slice(12)}`;
}

/**
 * Aplica máscara de CPF (000.000.000-00). Aceita entrada parcial.
 * @param {string} value - Valor digitado (só números ou já formatado)
 * @returns {string} Valor formatado
 */
export function formatCpf(value) {
  if (value == null || value === '') return '';
  const digits = String(value).replace(/\D/g, '').slice(0, 11);
  if (digits.length <= 3) return digits;
  if (digits.length <= 6) return `${digits.slice(0, 3)}.${digits.slice(3)}`;
  if (digits.length <= 9) return `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6)}`;
  return `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6, 9)}-${digits.slice(9)}`;
}

/**
 * Formata quantidade de diárias: inteiro sem decimal quando for valor inteiro (ex: 3), caso contrário com uma casa decimal (ex: 2,5).
 * @param {number|string} value - Quantidade de diárias (ex: 3, 3.0, 2.5)
 * @returns {string} Valor formatado (ex: "3", "2,5")
 */
export function formatQuantityDays(value) {
  if (value == null || value === '') return '—';
  const num = typeof value === 'string' ? parseFloat(value) : Number(value);
  if (Number.isNaN(num)) return '—';
  if (num === Math.floor(num)) return String(Math.round(num));
  return num.toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
}
