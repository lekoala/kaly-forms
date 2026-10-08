# Design decisions and non-goals

## Why not Symfony Form as the core model?

Symfony Form solves a broader problem: bidirectional data mapping, normalized/model/view data, transformers, nested object graphs, form types, extensions, events and theming.

Those features are useful when an application needs them. `kaly-forms` deliberately starts with a narrower goal: server-rendered form presentation and interaction metadata over values + violations supplied by the application.

Using Symfony Form as a third-party library or migration bridge remains valid.

## Why not infer the UI from an input DTO?

A PHP type can describe accepted data but cannot fully describe presentation.

For example, `string $country` does not answer whether the UI should be:

- text input;
- select;
- searchable autocomplete;
- custom element;
- conditionally visible field.

Presentation therefore remains an explicit form definition rather than annotations/reflection on request DTOs.

## Why self-rendering?

The common case should be ergonomic:

```php
<?= $form ?>
```

But `Form` does not construct HTML itself; it delegates to the injected renderer. This keeps definitions reusable across themes/frameworks.

## Why no wizard abstraction?

Multi-step flows are workflows with domain/application state. Treating them as a generic form feature would pull persistence, transitions, guards and resumability into the forms package.

## Why no JavaScript framework?

The library exposes interaction semantics. Consumers choose vanilla JS, sco-pe, custom elements, Alpine, Stimulus or another enhancer.

## Why no automatic ORM binding?

A form is not an entity editor. Accepted input should be transformed into an application command/use case explicitly. This avoids coupling presentation to persistence and prevents accidental partial entity mutation.

## What would justify expanding the core?

A feature should normally satisfy all of these:

1. it appears in several unrelated real forms;
2. it cannot be expressed cleanly as a custom field/renderer/application helper;
3. it belongs to presentation/interaction rather than HTTP/domain/workflow;
4. its lifecycle can remain explicit and framework-independent.
