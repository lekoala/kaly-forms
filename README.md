# kaly-forms prototype

A deliberately small prototype for a **framework-independent, server-first form presentation + interaction library**.

## Documentation

- [`docs/architecture.md`](docs/architecture.md) — responsibilities and boundaries
- [`docs/forms.md`](docs/forms.md) — defining, rendering and extending forms
- [`docs/interactions.md`](docs/interactions.md) — autocomplete, conditions, custom elements and multi-step boundaries
- [`docs/integration.md`](docs/integration.md) — Kaly, Slim, Twig/Latte and design-system integration
- [`docs/decisions.md`](docs/decisions.md) — design rationale and explicit non-goals
- [`AGENTS.md`](AGENTS.md) — invariants and contribution guidance

It is not a replacement for request mapping or application/business validation. The intended split is:

```text
HTTP request
  -> mapper / input DTO                 (Kaly RequestInput, Slim code, ...)
  -> structural validation             (optional kaly-forms rules)
  -> application/business validation   (authoritative)
  -> FormState(values, violations)
  -> Form
  -> HTML renderer
```

The main experiment is that the **form can render itself** while rendering remains replaceable:

```php
$forms = new FormFactory($renderer);
$form = $forms->create(...)->withState($state);

// In a plain PHP template:
<?= $form ?>
```

`Form` delegates to `RendererInterface`; HTML is not hard-coded into the form definition.

## Intended use cases

### 1. Simple login form

Named arguments, built-in fields, structural browser/server rules, server violations:

```php
$form = $forms->create(
    name: 'login',
    action: '/login',
    fields: [
        new EmailField('email', label: 'Email', required: true),
        new PasswordField('password', label: 'Password', required: true),
    ],
    actions: [new SubmitAction('login', 'Sign in')],
);
```

### 2. Rich server-first form

`examples/rich-registration.php` demonstrates:

- labels/help/errors;
- initial/submitted values;
- server + browser structural validation from the same field metadata;
- conditional visibility metadata;
- remote options endpoint metadata + CSRF metadata;
- custom element rendering with a hidden native value mirror;
- optional progressive enhancement (`assets/enhance.js`);
- whole-form rendering with `echo $form`.

### 3. Custom field type

A custom field is just a semantic PHP type plus a registered renderer:

```php
final class MoneyField extends Field
{
    public function __construct(
        string $name,
        ?string $label = null,
        public readonly string $currency = 'EUR',
    ) {
        parent::__construct($name, $label);
    }
}

$renderer->fieldRenderers()->register(
    MoneyField::class,
    fn(Field $field, mixed $value, FormState $state, HtmlRenderer $html): Html => ...,
);
```

No reflection or service discovery is needed.

### 4. Custom elements

`CustomElementField` renders a custom element and can keep a hidden native input as the canonical submitted value:

```php
new CustomElementField(
    name: 'address',
    tag: 'address-picker',
    label: 'Address',
    attributes: ['data-country-field' => 'country'],
)
```

This lets JavaScript own the rich UI while the normal HTML form still owns submission semantics.

## Core design choices

### Form definition, state and rendering are separate

```text
Field/Form definitions   immutable-ish semantic schema
FormState                submitted values + violations
Renderer                 HTML policy/theme
Form                      composes the three and is HtmlRenderable
```

### The form may render itself, without owning HTML policy

`Form::__toString()` delegates to its `RendererInterface`. This gives the ergonomic template API:

```php
<?= $form ?>
```

while allowing a Kaly application, Slim application or a package to inject a different renderer/theme.

For auto-escaping engines (Twig/Latte), an integration adapter should convert `HtmlRenderable::toHtml()` to the engine's native safe-markup type. The template should still only receive `form`.

### Server validation remains authoritative

The prototype ships only **structural** rules that can also project to native HTML attributes (`required`, `minlength`, ...). Business rules stay in the application and are merged into `FormState` as violations.

### Interaction metadata, not a JS framework

Conditions and remote option sources are represented as metadata. The included JS is intentionally tiny and optional. An application may use sco-pe, custom elements, Alpine, Stimulus, vanilla JS, etc.

### Wizard/multi-step is outside the form core

A wizard should own workflow/state and expose one Form per step:

```text
BookingWizard
  ReasonStep   -> Form
  SlotStep     -> Form
  DetailsStep  -> Form
  ReviewStep
```

The form library should not become a workflow engine.

## What should probably ship in a v0.1

- Text / email / password / textarea
- choice / checkbox / hidden
- file (not prototyped yet)
- explicit trusted HTML field
- custom element field
- custom field renderer registry
- FormState + violations
- structural rules: required, length, pattern/range later
- semantic HTML renderer
- interaction metadata: condition + remote options

## Explicit non-goals

- automatic ORM/entity binding;
- arbitrary object graph mapping;
- service discovery / attributes / tags;
- FormType inheritance graph;
- event dispatcher;
- workflow/wizard engine;
- JavaScript framework;
- compiling arbitrary PHP validation to JS;
- owning HTTP request mapping or CSRF.

## Kaly integration sketch

Kaly already owns request input mapping, violations, CSRF and rendering adapters. The integration can stay thin:

```php
$result = $inputs->mapResult($request, RegistrationInput::class);

$state = FormState::from(
    values: $result->values(),
    violations: KalyViolationAdapter::from($result->validation()),
);

$form = $registrationForm->create(
    csrf: $csrf->token('registration'),
)->withState($state);

return new View('registration/edit', ['form' => $form]);
```

A Kaly renderer adapter can recognize `HtmlRenderable` and turn it into native safe markup, so the template remains:

```php
<?= $form ?>
```

or the Twig equivalent without a `form_*` DSL in the template.

## Slim integration sketch

```php
$renderer = new HtmlRenderer();
$forms = new FormFactory($renderer);

$form = $forms->create(...);
$state = FormState::from($request->getParsedBody() ?? [], $violations);

return $response->withBody(stream((string) $form->withState($state)));
```

No Kaly dependency is required.
