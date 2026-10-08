# AGENTS.md

## Purpose

`kaly-forms` is a small, framework-independent library for **server-rendered HTML forms with progressive enhancement**.

It should integrate especially well with Kaly, but the core must remain usable from Slim, Mezzio, plain PSR-7 applications, or no framework at all.

The library owns **form presentation and interaction metadata**. It does not own HTTP request mapping, application/business validation, persistence, workflow, or a JavaScript framework.

## Architectural invariants

1. **Form definition, state, validation and rendering stay distinct.**
   - `Field` / `Form` describe presentation.
   - `FormState` contains values and violations for one render.
   - structural `Rule` objects describe simple constraints that may project to HTML.
   - application/business validation remains authoritative outside the library.
   - `RendererInterface` owns HTML policy.

2. **The form may render itself ergonomically, but does not own markup policy.**
   - `echo $form` is supported.
   - `Form::toHtml()` delegates to its injected `RendererInterface`.
   - field classes must not concatenate their own final HTML.

3. **No framework service locator.**
   - no container in `Form`, `Field`, `FormState`, rules or renderers.
   - no ambient request, current user, current locale, router or CSRF service.
   - applications pass values or framework adapters explicitly.

4. **No automatic model/entity binding.**
   - forms do not mutate Doctrine/Cycle entities.
   - applications map accepted input to commands/use cases themselves.

5. **Server validation is authoritative.**
   - only simple structural rules may project to HTML attributes or optional JS hints.
   - never attempt to compile arbitrary PHP/business validation to JavaScript.

6. **Interaction metadata, not a frontend framework.**
   - conditions, remote option sources and custom-element metadata are data contracts.
   - optional JS may enhance them, but normal form submission remains canonical where possible.

7. **Wizard/workflow is outside the core.**
   - one wizard step may expose one `Form`.
   - transitions, persistence, back navigation, holds and expiration belong to the application.

8. **Custom fields are first-class.**
   - a custom field is a semantic `Field` subtype plus a registered renderer.
   - no reflection/discovery/tags are required.

9. **No hidden lifecycle state.**
   - definitions should be immutable or immutable-ish.
   - submitted values/errors belong in `FormState`, not on field definitions.
   - renderer instances must not retain per-render values.

10. **Escaping has one owner.**
    - renderers escape untrusted text and attributes.
    - `Html` represents explicitly trusted/generated HTML.
    - do not mark arbitrary translated/user values as safe HTML.

## API direction

Prefer small semantic types and named arguments:

```php
new EmailField(
    name: 'email',
    label: 'Email',
    required: true,
    autocomplete: 'email',
)
```

Prefer explicit composition over metadata discovery:

```php
$renderer->fieldRenderers()->register(MoneyField::class, $renderMoney(...));
```

Avoid a Symfony-Forms-style graph of `FormType` / resolved types / transformers / event listeners unless a concrete repeated use case proves that complexity is needed.

## Built-in field scope

The core should ship the common HTML controls and extension seams:

- text / email / password / textarea;
- select/choice / checkbox / hidden;
- file when implemented;
- trusted HTML escape hatch;
- custom element bridge;
- custom field renderer registry.

Specialized application widgets should normally be custom fields outside the package.

## Review questions

Before adding a feature, ask:

- Is this presentation, or is it HTTP/application/workflow logic?
- Can it be expressed as a custom field/renderer outside the core?
- Does it preserve normal HTML submission semantics?
- Does it introduce hidden mutable state?
- Does it require a container, discovery, reflection or global request state?
- Does it duplicate a responsibility already owned by the consuming framework?
- Is the feature demonstrated by at least one realistic rich-form use case?

## Tests

Changes should cover the contract, not only generated strings.

At minimum test:

- escaping of labels/help/values/errors/attributes;
- field renderer override/custom type;
- state isolation between two forms/renders;
- hidden/custom-element submission value semantics;
- condition evaluation with and without JS;
- structural rule -> HTML attribute projection;
- renderer replacement;
- no per-request state retained by shared renderer instances.

Run syntax checks and the smoke suite before considering a change complete.
