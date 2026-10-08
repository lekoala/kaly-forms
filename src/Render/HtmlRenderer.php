<?php

declare(strict_types=1);

namespace Kaly\Forms\Render;

use Kaly\Forms\Action\SubmitAction;
use Kaly\Forms\Field\CheckboxField;
use Kaly\Forms\Field\ChoiceField;
use Kaly\Forms\Field\CustomElementField;
use Kaly\Forms\Field\EmailField;
use Kaly\Forms\Field\Field;
use Kaly\Forms\Field\HiddenField;
use Kaly\Forms\Field\HtmlField;
use Kaly\Forms\Field\PasswordField;
use Kaly\Forms\Field\TextareaField;
use Kaly\Forms\Field\TextField;
use Kaly\Forms\Form;
use Kaly\Forms\FormState;
use Kaly\Forms\Html;
use Kaly\Forms\Interaction\Condition;

final class HtmlRenderer implements RendererInterface
{
    public function __construct(
        private readonly FieldRendererRegistry $fields = new FieldRendererRegistry(),
    ) {
        $this->registerDefaults();
    }

    public function fieldRenderers(): FieldRendererRegistry
    {
        return $this->fields;
    }

    public function render(Form $form): Html
    {
        $state = $form->state();
        $out =
            '<form'
            . $this->attrs([
                'name' => $form->name,
                'method' => strtolower($form->method),
                'action' => $form->action,
                ...$form->attributes,
            ])
            . '>';

        foreach ($state->globalErrors() as $error) {
            $out .= '<div class="form-error" role="alert">' . $this->e($error->message) . '</div>';
        }

        foreach ($form->fields() as $field) {
            $out .= $this->renderField($field, $state)->value();
        }

        if ($form->actions() !== []) {
            $out .= '<div class="form-actions">';
            foreach ($form->actions() as $action) {
                $out .= $this->renderAction($action)->value();
            }
            $out .= '</div>';
        }

        return new Html($out . '</form>');
    }

    public function renderField(Field $field, FormState $state): Html
    {
        $value = $state->value($field->name);
        $control = $this->fields->render($field, $value, $state, $this)->value();

        if ($field instanceof HiddenField || $field instanceof HtmlField) {
            return new Html($control);
        }

        $errors = $state->errorsFor($field->name);
        $rowAttrs = [
            'class' => trim('form-field' . ($errors !== [] ? ' is-invalid' : '')),
            ...$this->conditionAttributes($field->visibleWhen),
        ];

        $out = '<div' . $this->attrs($rowAttrs) . '>';
        if ($field->label !== null) {
            $out .= '<label for="' . $this->e($this->id($field->name)) . '">' . $this->e($field->label) . '</label>';
        }
        $out .= $control;

        if ($field->help !== null) {
            $out .= '<div class="form-help">' . $this->e($field->help) . '</div>';
        }
        foreach ($errors as $error) {
            $out .= '<div class="form-error" role="alert">' . $this->e($error->message) . '</div>';
        }

        return new Html($out . '</div>');
    }

    public function e(mixed $value): string
    {
        return htmlspecialchars($this->textValue($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Normalizes a submitted value for text output.
     *
     * Submitted values are strings in practice, but FormState carries mixed;
     * non-textual values render as empty instead of "Array" or throwing.
     */
    public function textValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        if ($value instanceof \Stringable) {
            return (string) $value;
        }
        return '';
    }

    /** @param array<string,string|int|float|bool|null> $attributes */
    public function attrs(array $attributes): string
    {
        $out = '';
        foreach ($attributes as $name => $value) {
            if ($value === null || $value === false) {
                continue;
            }
            if ($value === true) {
                $out .= ' ' . $this->e($name);
                continue;
            }
            $out .= ' ' . $this->e($name) . '="' . $this->e($value) . '"';
        }
        return $out;
    }

    /** @return array<string,string|int|float|bool|null> */
    public function ruleAttributes(Field $field): array
    {
        $attrs = [];
        foreach ($field->rules as $rule) {
            $attrs = [...$attrs, ...$rule->htmlAttributes()];
        }
        return $attrs;
    }

    private function registerDefaults(): void
    {
        $this->registerTextControls();
        $this->registerChoiceControls();
        $this->registerEmbeddedControls();
    }

    private function registerTextControls(): void
    {
        $this->fields
            ->register(TextField::class, function (Field $raw, mixed $value): Html {
                /** @var TextField $field */ $field = $raw;
                return new Html(
                    '<input'
                    . $this->attrs([
                        'id' => $this->id($field->name),
                        'name' => $field->name,
                        'type' => $field->inputType,
                        'value' => $field->inputType === 'password' ? null : $this->textValue($value),
                        ...$this->ruleAttributes($field),
                        ...$field->attributes,
                    ])
                    . '>',
                );
            })
            ->register(EmailField::class, function (Field $raw, mixed $value): Html {
                /** @var EmailField $field */ $field = $raw;
                return new Html(
                    '<input'
                    . $this->attrs([
                        'id' => $this->id($field->name),
                        'name' => $field->name,
                        'type' => 'email',
                        'value' => $this->textValue($value),
                        ...$this->ruleAttributes($field),
                        ...$field->attributes,
                    ])
                    . '>',
                );
            })
            ->register(PasswordField::class, function (Field $raw): Html {
                /** @var PasswordField $field */ $field = $raw;
                return new Html(
                    '<input'
                    . $this->attrs([
                        'id' => $this->id($field->name),
                        'name' => $field->name,
                        'type' => 'password',
                        ...$this->ruleAttributes($field),
                        ...$field->attributes,
                    ])
                    . '>',
                );
            })
            ->register(TextareaField::class, function (Field $raw, mixed $value): Html {
                /** @var TextareaField $field */ $field = $raw;
                return new Html(
                    '<textarea'
                    . $this->attrs([
                        'id' => $this->id($field->name),
                        'name' => $field->name,
                        ...$this->ruleAttributes($field),
                        ...$field->attributes,
                    ])
                    . '>'
                    . $this->e($value)
                    . '</textarea>',
                );
            });
    }

    private function registerChoiceControls(): void
    {
        $this->fields->register(ChoiceField::class, function (Field $raw, mixed $value): Html {
            /** @var ChoiceField $field */ $field = $raw;
            $remoteAttrs = [];
            if ($field->remote !== null) {
                $remoteAttrs = [
                    'data-kf-options-url' => $field->remote->endpoint,
                    'data-kf-options-min-chars' => $field->remote->minChars,
                    'data-kf-options-query-param' => $field->remote->queryParam,
                    'data-kf-options-csrf' => $field->remote->csrfToken,
                    'data-kf-options-csrf-header' => $field->remote->csrfHeader,
                ];
            }
            $out =
                '<select'
                . $this->attrs([
                    'id' => $this->id($field->name),
                    'name' => $field->name,
                    ...$this->ruleAttributes($field),
                    ...$remoteAttrs,
                    ...$field->attributes,
                ])
                . '>';
            foreach ($field->choices as $optionValue => $label) {
                $out .=
                    '<option'
                    . $this->attrs([
                        'value' => $optionValue,
                        'selected' => (string) $optionValue === $this->textValue($value),
                    ])
                    . '>'
                    . $this->e($label)
                    . '</option>';
            }
            return new Html($out . '</select>');
        })->register(CheckboxField::class, function (Field $raw, mixed $value): Html {
            /** @var CheckboxField $field */ $field = $raw;
            return new Html(
                '<input'
                . $this->attrs([
                    'id' => $this->id($field->name),
                    'name' => $field->name,
                    'type' => 'checkbox',
                    'value' => '1',
                    'checked' => filter_var($value, FILTER_VALIDATE_BOOL),
                    ...$this->ruleAttributes($field),
                    ...$field->attributes,
                ])
                . '>',
            );
        });
    }

    private function registerEmbeddedControls(): void
    {
        $this->fields
            ->register(HiddenField::class, function (Field $raw, mixed $value): Html {
                /** @var HiddenField $field */ $field = $raw;
                return new Html(
                    '<input'
                    . $this->attrs([
                        'name' => $field->name,
                        'type' => 'hidden',
                        'value' => $this->textValue($value),
                        ...$field->attributes,
                    ])
                    . '>',
                );
            })
            ->register(HtmlField::class, static function (Field $raw): Html {
                /** @var HtmlField $field */ $field = $raw;
                return $field->html;
            })
            ->register(CustomElementField::class, function (Field $raw, mixed $value): Html {
                /** @var CustomElementField $field */ $field = $raw;
                $out = '';
                if ($field->mirrorHiddenInput) {
                    $out .=
                        '<input'
                        . $this->attrs([
                            'type' => 'hidden',
                            'name' => $field->name,
                            'value' => $this->textValue($value),
                            'data-kf-mirror-for' => $this->id($field->name),
                        ])
                        . '>';
                }
                $out .=
                    '<'
                    . $this->e($field->tag)
                    . $this->attrs([
                        'id' => $this->id($field->name),
                        'data-kf-field' => $field->name,
                        'value' => $this->textValue($value),
                        ...$field->attributes,
                    ])
                    . '></'
                    . $this->e($field->tag)
                    . '>';
                return new Html($out);
            });
    }

    private function renderAction(SubmitAction $action): Html
    {
        return new Html(
            '<button'
            . $this->attrs([
                'type' => 'submit',
                'name' => $action->name,
                'value' => '1',
                ...$action->attributes,
            ])
            . '>'
            . $this->e($action->label)
            . '</button>',
        );
    }

    /** @return array<string,string|int|float|bool|null> */
    private function conditionAttributes(?Condition $condition): array
    {
        if ($condition === null) {
            return [];
        }

        return [
            'data-kf-visible-field' => $condition->field,
            'data-kf-visible-op' => $condition->operator,
            'data-kf-visible-value' => is_scalar($condition->expected) ? (string) $condition->expected : json_encode($condition->expected),
        ];
    }

    private function id(string $name): string
    {
        return 'field-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', $name);
    }
}
