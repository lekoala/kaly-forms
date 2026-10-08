// Optional progressive enhancement proof-of-concept.
// Core rendering/validation works without this file.

function currentValue(form, name) {
  const el = form.elements.namedItem(name);
  if (!el) return null;
  if (el instanceof RadioNodeList) return el.value;
  if (el.type === 'checkbox') return el.checked ? el.value : '';
  return el.value;
}

function applyConditions(form) {
  for (const row of form.querySelectorAll('[data-kf-visible-field]')) {
    const actual = currentValue(form, row.dataset.kfVisibleField);
    const expected = row.dataset.kfVisibleValue ?? '';
    const op = row.dataset.kfVisibleOp;
    const visible = op === 'filled' ? !!actual : op === 'neq' ? actual !== expected : actual === expected;
    row.hidden = !visible;
    row.toggleAttribute('inert', !visible);
  }
}

for (const form of document.querySelectorAll('form')) {
  applyConditions(form);
  form.addEventListener('input', () => applyConditions(form));
  form.addEventListener('change', () => applyConditions(form));
}

// Remote option metadata is deliberately transport-only here. An application can
// attach its own autocomplete/select component to [data-kf-options-url] without
// changing the PHP form definition.
