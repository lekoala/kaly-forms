# Template engines

`Form` implements `HtmlRenderable`, so a template can print it directly:

```php
<?= $form ?>
```

Twig and Latte auto-escape variable output, so they would escape the generated
HTML. `kaly-forms` ships one optional bridge per auto-escaping engine so a form
stays a trusted value in templates. Each bridge is dependency-free at the core
level: it is only loaded when you use it, and it requires that engine to be
installed.

kaly-tpl (and plain PHP) do not auto-escape: output is escaped explicitly through
the `$v` runtime, so `<?= $form ?>` already works and no bridge is needed.

Rule of thumb: keep `{{ form }}` (or the closest equivalent) in the template. Do
not require templates to understand a `form_start()` / `form_row()` DSL.

## Twig — `Kaly\Forms\Bridge\Twig\FormExtension`

Requires `twig/twig` (safe-class support needs Twig ≥ 3.10).

```php
use Kaly\Forms\Bridge\Twig\FormExtension;
use Twig\Environment;

/** @var Environment $twig */
FormExtension::register($twig);
```

Register it last, once the Twig environment is otherwise configured. This installs
the helpers and marks `HtmlRenderable` as safe for the HTML strategy:

```twig
{{ form }}          {# preferred, no |raw needed #}
{{ form_html(form) }}
{{ form|form }}
```

## Latte — `Kaly\Forms\Bridge\Latte\FormExtension`

Requires `latte/latte`.

```php
use Kaly\Forms\Bridge\Latte\FormExtension;

$latte->addExtension(new FormExtension());
```

The filter and function return a `Latte\Runtime\Html`, so output is not escaped
again:

```latte
{$form|form}
{=form_html($form)}
```

## KalyTpl / plain PHP

No adapter. kaly-tpl renders native `.phtml` templates with explicit escaping, so
the standard echo is already correct:

```php
<?= $form ?>
```

Use `$v->e($form)` only if you deliberately want the markup escaped as text.

## Escaping contract

The renderer already escapes untrusted label/help/value/error text while building
the fragments, so the resulting `Html` is trusted as a whole (see `AGENTS.md`,
"Escaping has one owner"). The Twig/Latte bridges only convert
`HtmlRenderable::toHtml()` to the engine's native safe type; they never bypass
escaping of the form's content.
