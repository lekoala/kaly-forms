# Changelog

All notable changes to this project are documented here. This project follows
[Semantic Versioning](https://semver.org/) and is currently pre-1.0: the public
API may change between minor releases until 1.0.

## Unreleased

### Added

- `Range` (numeric min/max/step) and `TemporalRange` (date/time/datetime-local
  format + min/max) structural rules. `NumericField`, `DateField`, `TimeField`
  and `DateTimeField` now express their bounds as rules, so `StructuralValidator`
  enforces what the HTML projects.
- `Form::unmatchedErrors()`: field errors with no displayed field (absent from
  the tree or on a `HiddenField`). `HtmlRenderer` shows them with global errors.
- Accessibility: help and errors get ids, controls get `aria-describedby` and
  `aria-invalid`; radio/checkbox groups reference them from the fieldset.
- `ChoiceField::$emptyLabel`: a leading `<option value="">`, present by default
  (blank label); pass `null` to omit it.
- Opt-in `strictChoices` on choice/radio/checkbox-group/multi-select fields,
  backed by the new `InChoices` rule. Refused on remote options: the
  application validates those against its canonical source.
- `RenderPart::Action` (submit buttons) and `RenderPart::Legend` (fieldset and
  group legends) so themes can decorate them.
- Id collisions (generated, explicit, and derived `-help`/`-label`/`-error-n`/
  group item ids) throw a `LogicException` when the `Form` is built.

### Changed

- Per-field errors no longer carry `role="alert"`; only global errors do.
- Conditional fields keep their non-required constraints (`maxlength`, `min`...)
  and render `data-kf-required` instead of `required`; `enhance.js` restores
  `required` when the branch is visible.
- `enhance.js` reads controls in a hidden branch as empty (chained conditions
  match the server) and re-applies conditions on form reset.
- Explicit `attributes` win over convenience parameters (`rows`, `autocomplete`).
- `ReadonlyField` label references the value with `aria-labelledby` instead of
  an invalid `for`.
- `ChoiceField` renders an empty first option by default: a create form no
  longer submits the first choice implicitly.
- An attribute also projected by a field rule (`min`, `maxlength`, `required`...)
  is refused: constraints have a single, server-validated source.
- `NumericField` always projects and validates its step, 1 by default like the
  native control: pass `step: 'any'` or `'0.01'` for decimals.
- Field names cannot contain dots or whitespace (PHP rewrites them to `_`).
- `assets/enhance.js` is now a reference example, not a supported API.
- `mago guard` removed from `composer test` and CI (no perimeter rules).

### Fixed

- Radio and checkbox group wrappers now use `RenderPart::Fieldset`, matching
  explicit fieldsets. `DefaultTheme` preserves their `form-field` and
  `is-invalid` classes.
- Invalid HTML attribute names and custom element tags are rejected instead of
  being written into markup (attribute injection).
- A remote `ChoiceField` keeps its submitted value as a selected option on re-render.
- `Length` counts CRLF as one character, like the browser `maxlength`.
- `TemporalRange` compares fractional seconds by value (`12:00:00.1` equals
  `12:00:00.100`).
- `Form` rejects methods other than get/post/dialog (browsers send them as GET).
- Field names containing brackets are rejected (`FormState` is flat).
- Closest registered ancestor renderer wins, regardless of registration order.
- A custom `id` on a radio/checkbox group is suffixed per box, not duplicated.
- List conditions with invalid UTF-8 throw instead of rendering broken metadata.

## 0.1.0

First experimental release. API is deliberately not frozen yet.

### Added

- Form definition, state and rendering model: `Form`, `FormState`, `RendererInterface`.
- Built-in fields: text/email/password/textarea/hidden, choice/radio/multi-select,
  checkbox, date/time/datetime, numeric, readonly, file, trusted HTML, custom element.
- Semantic nodes: heading, text, fieldset, group + layout intentions.
- Custom node renderer registry, themes and render profiles.
- `FormState` values + `FormError` errors, with per-field and global errors.
- Optional structural rules: required, length, email, numeric, checked.
- Optional standalone `StructuralValidator`.
- Interaction metadata: conditional visibility and remote option sources.
- Optional template-engine bridges for auto-escaping engines:
  `Kaly\Forms\Bridge\Twig\FormExtension` and
  `Kaly\Forms\Bridge\Latte\FormExtension`.

### Notes

- `StructuralValidator` is optional: applications that own server validation
  should pass resolved errors into `FormState` instead.
- Accuracy of generated browser constraints does not replace application/business
  validation; only structural rules project to HTML attributes.
- kaly-tpl and plain PHP do not auto-escape, so `<?= $form ?>` works without an
  adapter.
