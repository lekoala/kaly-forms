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
    errors: KalyFormErrors::fromValidation($result->validation()),
);

$form = $registrationForm->create(/* presentation definition */)
    ->withState($state);

return new View('registration/edit', ['form' => $form]);
```

Kaly integration may additionally provide:

- conversion from Kaly `ValidationResult`/`Violation` into `FormError` (already resolved text), owned by the application adapter, not by the core;
- renderer integration so `HtmlRenderable` is marked safe in Twig/Latte only after `toHtml()` has escaped its components;
- helpers to inject CSRF hidden fields or route-generated endpoint URLs at form construction time.

Substituting a field implementation stays at the composition root. Conceptually:

```php
$di->callback(
    FieldTypes::class,
    static function (FieldTypes $types): void {
        $types->register(DateField::class, fn(...): Field => new CalendarDateField(...));
    },
);
```

The core package should still know nothing about Kaly.

## Slim / PSR-7

A Slim application can provide values directly from parsed request data:

```php
$state = FormState::from(
    values: (array) ($request->getParsedBody() ?? []),
    errors: $errors,
);

$form = $definition->withState($state);
```

The same manual wiring applies without a container:

```php
$types = FieldTypes::defaults();
$types->register(DateField::class, fn(...): Field => new CalendarDateField(...));

$fields = new Fields($types);
$form = $forms->create(name: 'registration', action: '/registrations', children: [
    $fields->date(name: 'birthDate', label: 'Birth date'),
]);
```

The application's validator and CSRF middleware remain unchanged.

## Render on error, redirect on success

A failed submission does not need a session, flash storage or a redirect: answer the POST itself with the same form, re-rendered from `FormState`.

```text
GET  /profile/edit   -> render the form
POST /profile        -> invalid: 422 + the form with values and errors
                     -> valid:   run the use case, then 303 See Other to a GET page
```

Conceptually (`renderPage()`, `htmlResponse()` and `redirect()` stand for your framework):

```php
$values = (array) ($request->getParsedBody() ?? []);
$errors = $validation->errors(); // list<FormError>, translated by the application

if ($errors !== []) {
    $form = $makeForm()->withState(FormState::from(
        values: [...$values, '_csrf' => $csrf->currentToken()],
        errors: $errors,
    ));
    return htmlResponse(renderPage('profile/edit', ['form' => $form]), 422);
}

$useCase->execute($command);
return redirect('/profile', 303);
```

What the re-render guarantees:

- text values, selections (select, radio, checkbox, groups) and errors come back; field errors are tied to their control (`aria-invalid`, `aria-describedby`), global errors are announced (`role="alert"`);
- passwords and files are never re-injected: a file must be sent again;
- an error that no visible row can show (unknown field, `HiddenField`, field in a branch inactive for the submitted values) is shown with the global errors, prefixed by the field label when there is one (presentation only: the `FormError` message is unchanged). Without JS, an error in an inactive branch therefore appears twice, globally and under its still-visible field; this is accepted.

Showing such an error never activates the field: revealing the branch would bypass its condition. An error on a field whose condition is false usually points to submitted data out of sync, a business error that deserves a global message, or a condition that does not match the validation; fix it in the application.

What stays with the application:

- pass the *current* CSRF token in the values, not the submitted one;
- reloading the 422 page re-posts the form; the browser asks first, and an invalid submission has no side effect;
- multi-step flows keep their state in the application (a persisted draft), not in the form.

### With sco-pe or another HTML-over-fetch enhancer

Keep HTML as the response format, including for AJAX: the enhancer posts the native form and swaps the returned HTML, so errors never need a JSON encoding nor a client-side renderer.

```php
$scope = $request->getHeaderLine('Scope-Request') === 'true';

if ($errors !== []) {
    $form = $makeForm()->withState(FormState::from($values, $errors));
    $html = $scope
        ? renderFragment('profile/form', ['form' => $form])
        : renderPage('profile/edit', ['form' => $form]);
    return htmlResponse($html, 422)->withHeader('Vary', 'Scope-Request');
}

$useCase->execute($command);
return $scope
    ? response(204)->withHeader('Scope-Location', '/profile')
    : redirect('/profile', 303);
```

If you use `assets/enhance.js` (or your own copy), re-apply it to swapped content; it is idempotent:

```js
document.addEventListener('scope:after-swap', (e) => enhanceForms(e.target));
```

A JSON API client is a different contract: serialize the same `FormError` list (`field`, `code`, `message`) as `application/problem+json` in the application. kaly-forms does not ship a JSON error format.

## Twig and Latte

A self-rendering object needs an adapter because auto-escaping engines will otherwise escape the generated HTML.

`kaly-forms` ships optional bridges that convert `HtmlRenderable::toHtml()` to the engine's native safe-markup representation, so the template API stays intentionally small:

```twig
{{ form }}
```

Do not require the template to understand a separate `form_start()` / `form_row()` DSL unless an application explicitly wants low-level layout control.

- Twig: `Kaly\Forms\Bridge\Twig\FormExtension` (`twig/twig`, safe `{{ form }}`);
- Latte: `Kaly\Forms\Bridge\Latte\FormExtension` (`latte/latte`).

kaly-tpl and plain PHP do not auto-escape, so `<?= $form ?>` already works and no bridge is needed. See [`integrations/templates.md`](integrations/templates.md).

## Design systems

The preferred integration points for a design system are a `FormTheme` first and node renderers second, never new field classes for every visual variation. This holds for any CSS framework or company design system: Actual CSS is mentioned below only as an illustrative example, not as a dependency or a recommendation.

A typical profile is mostly shared defaults:

```text
90% default node renderers
+ one FormTheme (classes/attributes per RenderPart)
+ a few structural renderer overrides (checkbox, group, ...)
```

For example, an Actual CSS theme can decide label/help/error/control classes and invalid-state attributes, while a Bootstrap integration additionally overrides the checkbox and group renderers for their specific structures. Both ship as `RenderProfile` values; the core never knows these frameworks.

Semantic application widgets can still use custom `Field` types where the control itself is meaningfully different.

## Example integrations

- [`integrations/templates.md`](integrations/templates.md) — Twig and Latte bridges for `HtmlRenderable`, plus the kaly-tpl/plain PHP usage.
- [`integrations/filepond.md`](integrations/filepond.md) — FilePond v5 upload enhancement (sync + async) over the same `FileField` definition.
