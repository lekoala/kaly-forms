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
    fields: [
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

## Built-in field direction

The initial core is deliberately small:

- `TextField`;
- `EmailField`;
- `PasswordField`;
- `TextareaField`;
- `ChoiceField`;
- `CheckboxField`;
- `HiddenField`;
- `HtmlField` for explicitly trusted content;
- `CustomElementField` for custom-element based widgets.

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

Register the rendering policy separately:

```php
$renderer->fieldRenderers()->register(
    MoneyField::class,
    static function (
        Field $raw,
        mixed $value,
        FormState $state,
        HtmlRenderer $html,
    ): Html {
        /** @var MoneyField $field */
        $field = $raw;

        return new Html(/* application markup */);
    },
);
```

This is the primary extension mechanism. Prefer it over core feature growth for application-specific widgets.

## Renderer replacement

The default `HtmlRenderer` is a reference semantic renderer, not the only supported theme.

An application may provide another `RendererInterface` through `FormFactory`:

```php
$forms = new FormFactory($actualCssRenderer);
```

The form definitions and state remain unchanged.

This is the intended seam for any design system or CSS framework. Actual CSS is only one illustrative example: the same `RendererInterface` can carry Bootstrap, Tailwind, Pico, a company design system, or plain semantic HTML. The library has no dependency on any of them.
