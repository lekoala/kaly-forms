# kaly-forms

A deliberately small library for **framework-independent, server-first form presentation + interaction**.

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
  -> mapper / input DTO                (Kaly RequestInput, Slim code, ...)
  -> structural validation             (optional kaly-forms rules)
  -> application/business validation   (authoritative)
  -> FormState(values, errors)
  -> Form
  -> HTML renderer
```

The core idea is that the **form can render itself** while rendering remains replaceable:

```php
$forms = new FormFactory($renderer);
$form = $forms->create(...)->withState($state);

// In a plain PHP template:
<?= $form ?>
```

`Form` delegates to `RendererInterface`; HTML is not hard-coded into the form definition.

## Intended use cases

### 1. Simple login form

Named arguments, built-in fields, structural browser/server rules, server errors:

```php
$form = $forms->create(
    name: 'login',
    action: '/login',
    children: [
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

$renderers->register(
    MoneyField::class,
    fn(FormNode $node, RenderContext $context): Html => ...,
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
FormState                submitted values + errors
Renderer                 HTML policy/theme
Form                     composes the three and is HtmlRenderable
```

### The form may render itself, without owning HTML policy

`Form::__toString()` delegates to its `RendererInterface`. This gives the ergonomic template API:

```php
<?= $form ?>
```

while allowing a Kaly application, Slim application or a package to inject a different renderer/theme.

For auto-escaping engines (Twig/Latte), optional bridges convert `HtmlRenderable::toHtml()` to the engine's native safe-markup type, so the template only receives `form`. kaly-tpl and plain PHP do not auto-escape, so `<?= $form ?>` already works. See [`docs/integrations/templates.md`](docs/integrations/templates.md).

### Server validation remains authoritative

The library ships only **structural** rules that can also project to native HTML attributes (`required`, `minlength`, ...). Business rules stay in the application and are merged into `FormState` as `FormError` values.

`StructuralValidator` is an **optional standalone** validator: convenient for a light application, but when the application already owns validation (Kaly, Symfony Validator, a domain service, ...) that layer stays the server authority. Do not run both for the same submission unless intentional. See [`docs/architecture.md`](docs/architecture.md).

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

## Included

- text / email / password / textarea / hidden
- choice / radio group / multiple select / checkbox group (+ optgroups)
- checkbox / date / time / datetime / numeric
- readonly (presentation only) / file (presentation only)
- trusted HTML block / custom element field
- semantic nodes: heading / text / fieldset / group + layout intentions
- custom node renderer registry + theme + render profiles
- FormState + errors
- structural rules: required, length, email, numeric
- optional `StructuralValidator` (standalone)
- interaction metadata: condition + remote options
- optional template bridges: Twig / Latte (`Kaly\Forms\Bridge\*`)

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

## Kaly integration

Kaly already owns request input mapping, validation, CSRF and rendering adapters. The integration can stay thin:

```php
$result = $inputs->mapResult($request, RegistrationInput::class);

$state = FormState::from(
    values: $result->values(),
    errors: KalyFormErrors::fromValidation($result->validation()),
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

## Slim integration

```php
$renderer = new HtmlRenderer();
$forms = new FormFactory($renderer);

$form = $forms->create(...);
$state = FormState::from($request->getParsedBody() ?? [], $errors);

return $response->withBody(stream((string) $form->withState($state)));
```

No Kaly dependency is required.
