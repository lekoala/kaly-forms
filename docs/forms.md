# Defining and rendering forms

## Create a form

The prototype favors normal PHP objects and named arguments over a metadata language.

```php
use Kaly\Forms\Action\SubmitAction;
use Kaly\Forms\Field\EmailField;
use Kaly\Forms\Field\PasswordField;
use Kaly\Forms\FormFactory;

$forms = new FormFactory();

$form = $forms->create(
    name: 'login',
    action: '/login',
    children: [
        new EmailField(
            name: 'email',
            label: 'Email',
            required: true,
        ),
        new PasswordField(
            name: 'password',
            label: 'Password',
            required: true,
        ),
    ],
    actions: [
        new SubmitAction('login', 'Sign in'),
    ],
);
```

In a PHP template:

```php
<?= $form ?>
```

The template does not need to enumerate or understand the fields.

## Re-render submitted values and errors

```php
use Kaly\Forms\FormState;
use Kaly\Forms\Violation;

$form = $form->withState(FormState::from(
    values: [
        'email' => 'wrong@example.test',
    ],
    violations: [
        new Violation('email', 'This account cannot sign in'),
    ],
));
```

`FormState` is separate from the definition so the same form definition can be reused safely.

## Author fields through Fields

`Fields` is the public authoring API: one typed method per field with named arguments and IDE support.

```php
use Kaly\Forms\Fields;

$fields = new Fields();

$form = $forms->create(
    name: 'registration',
    action: '/registrations',
    children: [
        $fields->text(name: 'firstName', label: 'First name', required: true),
        $fields->email(name: 'email', label: 'Email', required: true),
        $fields->date(name: 'birthDate', label: 'Birth date', max: '2026-12-31'),
    ],
    actions: [new SubmitAction('save', 'Register')],
);
```

Direct construction (`new EmailField(...)`) keeps working. Prefer `Fields` in application code: it delegates to the injected `FieldTypes` registry, so the application can substitute implementations without touching form definitions.

Two independent axes, one rule: substitute the renderer when only the markup changes; substitute the field through `FieldTypes` when the model itself changes. See `decisions.md`.

## Built-in field direction

The initial core is deliberately small:

- `TextField`;
- `EmailField`;
- `PasswordField`;
- `TextareaField`;
- `ChoiceField`;
- `CheckboxField`;
- `HiddenField`;
- `DateField`;
- `CustomElementField` for custom-element based widgets.

Content nodes (not fields, constructed directly, never submitted):

- `Heading`, `Text`, `HtmlBlock` for trusted content;
- `Fieldset` for semantic grouping;
- `Group` for visual grouping with a `Layout` intention (`Layouts::stack()`, `inline()`, `columns(n)`).

A file field and additional native HTML controls can be added without changing the architecture.

## Custom fields

Applications should be able to define semantic fields without modifying the package.

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
```

Register the rendering policy separately on the profile renderers.
The closure receives the node plus a `RenderContext` toolkit (state, recursion, escaping, theme access):

```php
$renderers->register(
    MoneyField::class,
    static function (FormNode $node, RenderContext $context): Html {
        /** @var MoneyField $field */
        $field = $node;

        $control = new Html(/* application markup */);
        return $context->fieldRow($field, $control->value());
    },
);
```

This is the primary extension mechanism. Prefer it over core feature growth for application-specific widgets.

Application-defined semantic types can also be registered in `FieldTypes` so form definitions keep requesting the concept while the application decides the implementation:

```php
$types->register(DateField::class, fn(...): Field => new CalendarDateField(...));

$fields = new Fields($types);
$fields->date(name: 'birthDate', label: 'Birth date'); // CalendarDateField
```

`FieldTypes` is mutable during application composition; treat it as read-only once forms are being created.

## Renderer replacement

`RenderProfile` (theme + node renderers) travels with the form definition, so the same form renders through any profile:

```php
$plain = (new FormFactory())->create(name: 'registration', action: '/registrations', children: [...]);

$profile = new RenderProfile($actualTheme, $actualRenderers);
$styled = (new FormFactory(profile: $profile))->create(name: 'registration', action: '/registrations', children: [...]);
```

The form definitions and state remain unchanged.

This is the intended seam for any design system or CSS framework. Actual CSS is only one illustrative example: the same profile mechanism can carry Bootstrap, Tailwind, Pico, a company design system, or plain semantic HTML. The library has no dependency on any of them.

Rule of thumb: if only styling changes, change the theme; if markup structure changes, replace the node renderer. A profile is typically 90% default renderers plus a theme plus a few structural overrides.
