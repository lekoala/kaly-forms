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
Required   -> required
Length     -> minlength / maxlength
Email      -> type=email (through field semantics)
```

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
