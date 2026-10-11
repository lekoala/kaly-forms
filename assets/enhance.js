// Reference example of progressive enhancement (dependency-free, untested).
// Not a supported API: the contract is the data-kf-* metadata and PHP
// Condition (tested server side). Copy and adapt it, or use any client
// library that reads the same attributes. Forms work without it.
//
// Conditional fields render data-kf-required instead of required; an
// enhancement should restore required only while the branch is visible.
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

// isActive(el) tells whether a control sits in an effectively visible branch.
// A control in a hidden branch is disabled, hence not submitted: it reads as
// null, exactly like the server sees it (chained conditions stay in sync).
function currentValue(form, name, isActive) {
  const nodes = readNodes(form, name).filter((el) => !el.hasAttribute('data-kf-mirror-for'));
  const els = (nodes.length > 0 ? nodes : readNodes(form, name)).filter(isActive);
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
    // Conditional fields render data-kf-required instead of required, so
    // native validation never blocks a branch that starts hidden.
    el.dataset.kfOrigRequired = el.required || 'kfRequired' in el.dataset ? '1' : '';
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

function ownVisibility(form, row, isActive) {
  const actual = currentValue(form, row.dataset.kfVisibleField, isActive);
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
  // A branch is effectively visible only when its own condition holds AND
  // its conditional ancestor is effectively visible. Without this, a nested
  // branch whose condition is true would re-enable controls inside a hidden
  // parent (leaving them enabled, required and submitted). Conditions read
  // values through isActive, so a field in a hidden branch counts as empty.
  const effective = new Map();
  const pending = new Set();
  const closestRow = (el) => {
    const row = el.closest('[data-kf-visible-field]');
    return row && form.contains(row) ? row : null;
  };
  const isEffective = (row) => {
    if (effective.has(row)) return effective.get(row);
    // Circular conditions: fall back to the current state instead of looping.
    if (pending.has(row)) return !row.hidden;
    pending.add(row);
    const parent = row.parentElement ? closestRow(row.parentElement) : null;
    const visible = (parent === null || isEffective(parent)) && ownVisibility(form, row, isActive);
    pending.delete(row);
    effective.set(row, visible);
    return visible;
  };
  const isActive = (el) => {
    const row = closestRow(el);
    return row === null || isEffective(row);
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
  // reset fires before the controls are restored.
  form.addEventListener('reset', () => setTimeout(() => applyConditions(form)));
}

// Remote option metadata is deliberately transport-only here. An application can
// attach its own autocomplete/select component to [data-kf-options-url] without
// changing the PHP form definition.
