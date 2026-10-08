// Optional progressive enhancement (demo-level, dependency-free).
// Core rendering/validation works without this file.
//
// Shared contract with PHP Condition::normalize/matches:
// absent/unchecked => null, "" stays "" (null !== ""), scalars stringify,
// lists stringify + dedupe + sort (code-unit order, mirrors SORT_STRING),
// comparisons are strict. Expected values carry an explicit
// data-kf-visible-type ("string"/"list") so a string like '["a"]' is never
// confused with a list.

function compareStrings(a, b) {
  return a < b ? -1 : a > b ? 1 : 0;
}

function normalize(value) {
  if (value === null || value === undefined) return null;
  if (Array.isArray(value)) {
    const out = [];
    for (const item of value) {
      if (item === null || item === undefined) continue;
      const s = String(item);
      if (!out.includes(s)) out.push(s);
    }
    out.sort(compareStrings);
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
  if (!('kfVisibleType' in row.dataset) || !('kfVisibleValue' in row.dataset)) return null;
  const raw = row.dataset.kfVisibleValue;
  if (row.dataset.kfVisibleType === 'list') {
    try {
      const parsed = JSON.parse(raw);
      if (Array.isArray(parsed)) return parsed;
    } catch {
      // Fall through: malformed metadata never matches a list silently.
    }
    return raw;
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
  if (
    first instanceof HTMLInputElement
    && (first.type === 'radio' || first.type === 'checkbox')
    && (els.length > 1 || first.dataset.kfValueKind === 'list')
  ) {
    if (first.type === 'radio') {
      const picked = els.find((el) => el.checked);
      return picked ? picked.value : null;
    }
    const checked = els
      .filter((el) => el instanceof HTMLInputElement && el.type === 'checkbox' && el.checked)
      .map((el) => el.value);
    return checked.length > 0 ? checked : null;
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

function ownVisibility(form, row) {
  const actual = currentValue(form, row.dataset.kfVisibleField);
  const expected = parseExpected(row);
  const op = row.dataset.kfVisibleOp;
  if (op === 'filled') {
    return actual !== null && actual !== '' && !(Array.isArray(actual) && actual.length === 0);
  }
  if (op === 'neq') return !isEqual(actual, expected);
  return isEqual(actual, expected);
}

function applyConditions(form) {
  const rows = Array.from(form.querySelectorAll('[data-kf-visible-field]'));
  const own = new Map();
  for (const row of rows) own.set(row, ownVisibility(form, row));
  // A branch is effectively visible only when its own condition holds AND
  // every conditional ancestor is effectively visible. Without this, a
  // nested branch whose condition is true would re-enable controls inside
  // a hidden parent (leaving them enabled, required and submitted).
  const effective = new Map();
  const isEffective = (row) => {
    if (effective.has(row)) return effective.get(row);
    let visible = own.get(row);
    let parent = row.parentElement;
    while (visible && parent && parent !== form) {
      if (parent.matches && parent.matches('[data-kf-visible-field]')) {
        if (!own.has(parent)) own.set(parent, ownVisibility(form, parent));
        visible = visible && isEffective(parent);
      }
      parent = parent.parentElement;
    }
    effective.set(row, visible);
    return visible;
  };
  // Document order is outer-before-inner, so restoring a parent before
  // disabling a hidden child (and vice versa) always ends correctly.
  for (const row of rows) {
    const visible = isEffective(row);
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
