/** Atributos que reduzem autofill do Chrome / gerenciadores de senha */
export const noAutofillInputAttrs = {
  autocomplete: 'new-password',
  autocorrect: 'off',
  autocapitalize: 'off',
  spellcheck: 'false',
  'aria-autocomplete': 'none',
  'data-lpignore': 'true',
  'data-1p-ignore': 'true',
  'data-bwignore': 'true',
  'data-form-type': 'other',
};

/**
 * Mouse: remove readonly antes do focus (evita dropdown do Chrome).
 * Teclado (Tab): remove readonly no focus.
 */
export function enableInputOnMousedown(e) {
  const el = e.target;
  if (!el.readOnly) {
    return;
  }

  if (e.type === 'mousedown') {
    e.preventDefault();
    el.readOnly = false;
    el.focus();
    return;
  }

  el.readOnly = false;
}
