export const maritalStatusOptions = [
  { value: 'single', label: 'Solteiro(a)' },
  { value: 'married', label: 'Casado(a)' },
  { value: 'divorced', label: 'Divorciado(a)' },
  { value: 'widowed', label: 'Viúvo(a)' },
  { value: 'separated', label: 'Separado(a)' },
  { value: 'stable_union', label: 'União estável' },
];

export function maritalStatusLabel(value) {
  return maritalStatusOptions.find((o) => o.value === value)?.label ?? value ?? '—';
}
