export function slugify(value) {
  return String(value ?? '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '');
}

export function developmentSlug(development) {
  return `${development.id}-${slugify(development.name)}`;
}

export function parseDevelopmentIdFromSlug(slug) {
  const match = String(slug ?? '').match(/^(\d+)/);
  return match ? Number(match[1]) : null;
}
