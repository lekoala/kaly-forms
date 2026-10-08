# Framework integration

The package is intended to have a framework-independent core and thin adapters.

It is designed to work well with Kaly, which is its primary target, but nothing in the core depends on Kaly: the same `Form` / `FormState` / renderer trio integrates with Slim, Mezzio, plain PSR-7 applications, or no framework at all. Each section below is one optional adapter, never a requirement.

## Kaly

Kaly already owns request mapping, validation, CSRF, routing and view adapters. `kaly-forms` should not duplicate those concerns.

Typical flow:

```php
$result = $inputs->mapResult($request, RegistrationInput::class);

$state = FormState::from(
    values: $result->values(),
    violations: KalyViolationAdapter::from($result->validation()),
);

$form = $registrationForm->create(/* presentation definition */)
    ->withState($state);

return new View('registration/edit', ['form' => $form]);
```

Kaly integration may additionally provide:

- conversion from Kaly `ValidationResult`/`Violation`;
- renderer integration so `HtmlRenderable` is marked safe in Twig/Latte only after `toHtml()` has escaped its components;
- helpers to inject CSRF hidden fields or route-generated endpoint URLs at form construction time.

The core package should still know nothing about Kaly.

## Slim / PSR-7

A Slim application can provide values directly from parsed request data:

```php
$state = FormState::from(
    values: (array) ($request->getParsedBody() ?? []),
    violations: $violations,
);

$form = $definition->withState($state);
```

The application's validator and CSRF middleware remain unchanged.

## Twig and Latte

A self-rendering object needs an adapter because auto-escaping engines will otherwise escape the generated HTML.

The integration should convert `HtmlRenderable::toHtml()` to the engine's native safe-markup representation.

The template API can remain intentionally small:

```twig
{{ form }}
```

Do not require the template to understand a separate `form_start()` / `form_row()` DSL unless an application explicitly wants low-level layout control.

## Design systems

The preferred integration point for a design system is a renderer, not new field classes for every visual variation. This holds for any CSS framework or company design system: Actual CSS is mentioned below only as an illustrative example, not as a dependency or a recommendation.

For example, an Actual CSS renderer can decide:

- row/container markup;
- label/help/error classes;
- invalid state attributes;
- action layout;
- control classes.

Semantic application widgets can still use custom `Field` types where the control itself is meaningfully different.
