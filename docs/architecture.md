# Architecture

## What kaly-forms is

`kaly-forms` models the **presentation and interaction contract of an HTML form**.

It intentionally does not try to become the application's request mapper, domain validator, ORM mapper or workflow engine.

The core flow is:

```text
request / existing model
        ↓
application/framework mapping
        ↓
values + violations
        ↓
FormState
        ↓
Form definition + Renderer
        ↓
HTML
```

A consuming application may use Kaly `RequestInput`, a PSR-7 body array, Symfony Validator, its own validator, or something else entirely.

## The four concepts

### Form definition

`Form` + `Field` + `SubmitAction` describe what should be presented.

Definitions should be safe to reuse. Submitted values and errors do not mutate them.

### Form state

`FormState` contains values and violations for one render:

```php
$state = FormState::from(
    values: $submitted,
    violations: $violations,
);

$form = $form->withState($state);
```

This makes an important distinction explicit:

```text
what the form is        !=        what the user submitted
```

### Structural rules

Rules such as required/length/email are intentionally small. They may serve two roles:

1. optional server-side structural validation;
2. projection to native HTML attributes such as `required` or `minlength`.

They are not a replacement for application/business validation.

### Rendering

`Form` implements `HtmlRenderable`, so a plain PHP template can use:

```php
<?= $form ?>
```

This is convenience, not coupling: `Form::toHtml()` delegates to `RendererInterface`.

The renderer owns:

- form/row/control markup;
- escaping;
- CSS classes;
- labels/help/errors;
- action markup;
- mapping semantic field types to controls.

This permits an application to replace the default renderer without changing form definitions.

## Why the form renders itself

The template should not have to know the form schema.

Instead of:

```php
<?= $forms->row($form->field('email')) ?>
<?= $forms->row($form->field('password')) ?>
```

simple pages can use:

```php
<?= $form ?>
```

A richer renderer can still expose lower-level rendering APIs later if applications need selective layout control.

The important rule is:

> self-rendering is an ergonomic façade over a replaceable renderer, not HTML embedded inside the form model.

## Framework boundary

The core must not depend on:

- Kaly `HttpContext` or `RequestInput`;
- PSR-7 request objects;
- Twig/Latte;
- Doctrine/Cycle;
- a DI container;
- a router;
- a CSRF implementation.

Framework adapters may bridge these concerns explicitly.

## Rich forms

Rich behavior is split into two categories.

### Interaction metadata owned by the form

Examples:

- conditional visibility;
- remote option source;
- custom element identity;
- structural client hints.

The form can expose these as semantic metadata and the renderer can project them to HTML/data attributes.

### Workflow owned by the application

Examples:

- multi-step booking tunnel;
- persisted drafts;
- slot holds;
- step authorization;
- expiration;
- resumability.

Those belong outside `kaly-forms`. Each step may simply expose a form.
