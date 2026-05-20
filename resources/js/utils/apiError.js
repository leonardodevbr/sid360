export function getApiErrorMessage(error, fallback = 'Ocorreu um erro.') {
  const data = error?.response?.data;
  if (!data) return fallback;

  const errors = data.errors;
  if (errors && typeof errors === 'object') {
    const first = Object.values(errors).flat().find(Boolean);
    if (first) return first;
  }

  if (typeof data.message === 'string' && data.message) {
    return data.message;
  }

  return fallback;
}
