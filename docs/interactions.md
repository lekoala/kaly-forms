# Rich interactions and progressive enhancement

`kaly-forms` is server-first. JavaScript may improve the interaction, but the browser is not trusted as the source of validation or submitted state.

## Conditional fields

A field can carry simple condition metadata:

```php
new TextField(
    name: 'vatNumber',
    label: 'VAT number',
    visibleWhen: Condition::equals('country', 'BE'),
)
```

The condition has two uses:

1. the server can evaluate it against `FormState`;
2. the renderer can expose enough metadata for optional client-side enhancement.

Do not turn conditions into a general expression language. Complex business rules belong in application validation/workflow.

### Without JavaScript

Conditional branches are always rendered visible and enabled: the server never adds `hidden`, because a branch hidden without JS could never be shown again. Without enhancement, the user simply sees more fields; with enhancement, the client hides and disables inactive branches.

Either way the server decides: `StructuralValidator` skips the rules of a branch whose condition does not match the submitted values. Submitted values of inactive fields are kept in `FormState` as-is; ignoring them is the application's mapping decision.

A conditional field never renders native `required` (an inactive branch must not block submission). It renders `data-kf-required` instead, so an enhancement can restore `required` while the branch is visible. Its other constraints (`maxlength`, `min`, ...) only apply to non-empty values and are rendered normally.

An enhancement should treat a control inside a hidden (disabled) branch as empty when evaluating other conditions: the browser does not submit it, so the server sees no value either. `assets/enhance.js` is a reference example of this contract, not a supported API.

## Remote options / autocomplete

A field may describe a remote source:

```php
new ChoiceField(
    name: 'city',
    label: 'City',
    remote: new RemoteOptions(
        endpoint: '/cities/suggest',
        minChars: 2,
        csrfToken: $csrfToken,
    ),
)
```

`RemoteOptions` is metadata only. The consuming application owns:

- the endpoint;
- authorization;
- CSRF policy;
- response contract;
- throttling;
- caching;
- actual browser enhancement.

The server must validate the submitted canonical value again. A value having appeared in an autocomplete response never makes it trusted input.

On re-render (for example after an error on another field), a submitted value missing from `choices` is kept as a selected option labeled with the value itself. To show a human label, look it up and pass it in `choices` (`choices: [$cityId => $cityName]`) when building the form for that render.

## Custom elements

`CustomElementField` lets a richer browser widget coexist with native form submission.

The reference renderer can output a hidden native input as the canonical submitted value plus the custom element UI:

```text
hidden input        canonical form value
custom element      enhanced interaction
```

This is useful for date pickers, address widgets, slot pickers and other components where native controls are not enough.

## CSRF

The core library does not own CSRF.

A framework/application may:

- add a normal hidden token field to the form;
- attach a token/header to remote interaction metadata;
- protect the final POST with its own middleware.

This keeps the forms package usable outside Kaly.

## Front and back validation

The backend is authoritative.

Simple structural rules may project to browser semantics:

```text
Required       -> required
Length         -> minlength / maxlength
Range          -> min / max / step        (NumericField)
TemporalRange  -> min / max + format      (DateField, TimeField, DateTimeField)
Email          -> type=email (through field semantics)
InChoices      -> nothing (server only, opt-in via strictChoices)
```

A constraint has one source: an attribute that a field rule already projects (`min`, `maxlength`, `required`, ...) is refused in `attributes`. Constraint attributes set through `attributes` without a rule (`pattern`, for example) are browser-only: the server does not validate them.

### Known differences

The projection aims at parity, but a few differences are intentional or inherent:

- **`Required` does not trim.** `'   '` and `['']` count as filled, like a value the browser would submit. Normalizing input (trimming, dropping empty list items) belongs to the application before validation.
- **Length unit.** `Length` counts Unicode characters; the browser counts UTF-16 code units for `minlength`/`maxlength`, so an emoji counts as 2 in the browser and 1 on the server. Line breaks match: CRLF counts as one character on both sides.
- **Numeric step.** `NumericField` defaults to `step="1"` like the native control, and the server applies the same step. Pass `step: 'any'` or `step: '0.01'` to accept decimals.
- **`strictChoices` is static.** `InChoices` only proves the value was among the rendered options. Remote or permission-dependent options must be validated by the application against its canonical source; `strictChoices` is refused on remote options.

Business rules should remain server-only:

```text
"this discount code applies to this order"
"this slot is still available"
"this customer may edit this resource"
```

Do not maintain two independently authored copies of business validation in PHP and JavaScript.

## Multi-step forms

A wizard is an application workflow, not a form feature.

Recommended model:

```text
BookingWizard
  ReasonStep    -> Form
  SlotStep      -> Form
  DetailsStep   -> Form
  ReviewStep
```

The wizard owns transitions and persisted state. `kaly-forms` only renders and re-renders the form for the current step.
