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

## Why FieldTypes instead of a container in Form?

An application should be able to decide late which concrete implementation represents a standard UI concept (a date is a native input here, a calendar picker there) without the form knowing that decision. A container inside `Form` would make the form a service locator and tie the library to a container API.

Instead, field creation is an explicit seam with independent axes:

```text
FieldTypes
    concept requested → concrete Field implementation/factory

NodeRendererRegistry
    node → HTML structure

FormTheme
    rendering role → classes/attributes
```

The rule:

> Substitute the renderer when only the markup changes; substitute the field through `FieldTypes` when the model itself changes; change the theme when only decoration changes.

Visibility is presentation state: it may neutralize structural constraints of inactive fields, but submitted values are never filtered by it. Not rendered, not validated and not accepted stay three separate decisions; only the application defines what it accepts.

```php
// Same model, different markup.
$renderers->register(DateField::class, new CalendarDateFieldRenderer());

// Different model: extra interaction semantics.
$types->register(DateField::class, fn(...): Field => new CalendarDateField(...));
```

`Fields` is the typed authoring API (`$fields->date(...)`); `FieldTypes` is the composition/extensibility API configured once at the composition root. Factories are plain closures, so a factory needing a service captures it from the composition scope instead of the field resolving it:

```php
$types->register(
    DirectoryField::class,
    static fn(string $name, ?string $label = null): Field => new DirectoryField($name, $label, $countries),
);
```

`FieldTypes` is therefore deliberately mutable during composition, with `FieldTypes::defaults()` giving tests a fresh registry instead of a shared singleton. No `freeze()`/`lock()` until a real use case needs it.

Factories stay closures for now; introduce a `FooFieldSpec` only when a signature becomes painful to replicate, needs real normalization, or several substantial implementations must share exactly the same contract.

## Why separate field types instead of option flags?

`ChoiceField`, `RadioGroupField`, `MultipleSelectField` and `CheckboxGroupField` stay distinct because their submitted value (`string|null` vs `list<string>`), their HTML structure and their interaction differ. A `multiple: true, expanded: true` matrix on one type drifts toward a behavior matrix where one class means many things.

`ConfirmedPasswordField` is deliberately not a core field either: two `PasswordField` controls in a `Group` plus cross-validation compose the same UI. If that ever feels painful, the composition seam needs work, not another class. Same reasoning keeps tabs and toggles as nodes and interactions rather than fields.
