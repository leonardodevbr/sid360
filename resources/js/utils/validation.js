/**
 * @param {string} value
 * @returns {string} só dígitos do CPF
 */
export function cpfDigits(value) {
  return String(value ?? '').replace(/\D/g, '');
}

/**
 * Valida CPF brasileiro (11 dígitos, dígitos verificadores).
 * @param {string} value - CPF com ou sem máscara
 * @returns {boolean}
 */
export function isValidCpf(value) {
  const digits = cpfDigits(value);
  if (digits.length !== 11) return false;
  if (/^(\d)\1{10}$/.test(digits)) return false;

  let sum = 0;
  for (let i = 0; i < 9; i += 1) {
    sum += parseInt(digits.charAt(i), 10) * (10 - i);
  }
  let remainder = (sum * 10) % 11;
  if (remainder === 10) remainder = 0;
  if (remainder !== parseInt(digits.charAt(9), 10)) return false;

  sum = 0;
  for (let i = 0; i < 10; i += 1) {
    sum += parseInt(digits.charAt(i), 10) * (11 - i);
  }
  remainder = (sum * 10) % 11;
  if (remainder === 10) remainder = 0;
  return remainder === parseInt(digits.charAt(10), 10);
}

/**
 * Mensagem de validação de CPF em tempo real (dígito verificador).
 * @param {string} value
 * @param {{ required?: boolean }} [options]
 * @returns {string}
 */
export function getCpfValidationMessage(value, { required = false } = {}) {
  const digits = cpfDigits(value);
  if (!digits) {
    return required ? 'CPF é obrigatório.' : '';
  }
  if (digits.length < 11) {
    return '';
  }
  if (!isValidCpf(value)) {
    return 'CPF inválido.';
  }
  return '';
}

/**
 * @param {string} email
 * @returns {boolean}
 */
export function isValidEmail(email) {
  if (!email?.trim()) return true;
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim());
}
