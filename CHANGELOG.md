# Changelog

All notable changes to this project are documented here. This project follows
[Semantic Versioning](https://semver.org/) and is currently pre-1.0: the public
API may change between minor releases until 1.0.

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
