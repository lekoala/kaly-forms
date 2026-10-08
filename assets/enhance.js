// Optional progressive enhancement proof-of-concept.
// Core rendering/validation works without this file.
//
// Shared contract with PHP Condition::normalize/matches:
// absent/unchecked => null, "" stays "" (null !== ""), scalars stringify,
// lists stringify + dedupe + sort, comparisons are strict.

function normalize(value) {
  if (value === null || value === undefined) return null;
  if (Array.isArray(value)) {
    const out = [];
    for (const item of value) {
      if (item === null || item === undefined) continue;
      const s = String(item);
      if (!out.includes(s)) out.push(s);
    }
    out.sort();
    return out;
  }
  if (typeof value === 'object') return null;
  return String(value);
}

function isEqual(a, b) {
  const na = normalize(a);
  const nb = normalize(b);
  if (Array.isArray(na) || Array.isArray(nb)) {
    if (!Array.isArray(na) || !Array.isArray(nb)) return false;
    if (na.length !== nb.length) return false;
    return na.every((v, i) => v === nb[i]);
  }
  return na === nb;
}

function parseExpected(row) {
  if (!('kfVisibleValue' in row.dataset)) return null;
  const raw = row.dataset.kfVisibleValue;
  if (raw.startsWith('[')) {
    try {
      const parsed = JSON.parse(raw);
      if (Array.isArray(parsed)) return parsed;
    } catch {
      // fall through to raw string
    }
  }
  return raw;
}

function readNodes(form, name) {
  const cssName = typeof CSS !== 'undefined' && CSS.escape ? CSS.escape(name) : name.replace(/["\\]/g, '\\$&');
  const scoped = form.querySelectorAll('[data-kf-field="' + cssName + '"]');
  if (scoped.length > 0) return Array.from(scoped);
  const legacy = form.elements.namedItem(name) ?? form.elements.namedItem(name + '[]');
  if (!legacy) return [];
  if (typeof RadioNodeList !== 'undefined' && legacy instanceof RadioNodeList) return Array.from(legacy);
  return [legacy];
}

function currentValue(form, name) {
  const nodes = readNodes(form, name).filter((el) => !el.hasAttribute('data-kf-mirror-for'));
  const els = nodes.length > 0 ? nodes : readNodes(form, name);
  if (els.length === 0) return null;
  const first = els[0];
  if (first instanceof HTMLSelectElement && first.multiple) {
    const selected = Array.from(first.selectedOptions).map((o) => o.value);
    return selected.length > 0 ? selected : null;
  }
  if (els.length > 1 || (els[0] instanceof HTMLInputElement && els[0].type === 'checkbox' && els.length > 1)) {
    const checked = els
      .filter((el) => el instanceof HTMLInputElement && el.type === 'checkbox' && el.checked)
      .map((el) => el.value);
    if (els[0] instanceof HTMLInputElement && (els[0].type === 'radio' || els[0].type === 'checkbox')) {
      if (els[0].type === 'radio') {
        const picked = els.find((el) => el.checked);
        return picked ? picked.value : null;
      }
      return checked.length > 0 ? checked : null;
    }
  }
  if (first instanceof HTMLInputElement && first.type === 'checkbox') {
    return first.checked ? first.value : null;
  }
  if (first instanceof HTMLInputElement && first.type === 'radio') {
    return first.checked ? first.value : null;
  }
  if ('value' in first) return first.value;
  return null;
}

function snapshotControl(el) {
  if (!('kfOrigDisabled' in el.dataset)) {
    el.dataset.kfOrigDisabled = el.disabled ? '1' : '';
    el.dataset.kfOrigRequired = el.required ? '1' : '';
  }
}

function setBranchState(row, visible) {
  const controls = row.querySelectorAll('input, select, textarea, [data-kf-field]');
  for (const el of controls) {
    if (!('disabled' in el) && !('required' in el)) continue;
    snapshotControl(el);
    if (!visible) {
      el.disabled = true;
      if ('required' in el) el.required = false;
    } else {
      el.disabled = el.dataset.kfOrigDisabled === '1';
      if ('required' in el) el.required = el.dataset.kfOrigRequired === '1';
    }
  }
}

function applyConditions(form) {
  for (const row of form.querySelectorAll('[data-kf-visible-field]')) {
    const actual = currentValue(form, row.dataset.kfVisibleField);
    const expected = parseExpected(row);
    const op = row.dataset.kfVisibleOp;
    const visible = op === 'filled' ? actual !== null && actual !== '' && !(Array.isArray(actual) && actual.length === 0) : op === 'neq' ? !isEqual(actual, expected) : isEqual(actual, expected);
    row.hidden = !visible;
    row.toggleAttribute('inert', !visible);
    setBranchState(row, visible);
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
