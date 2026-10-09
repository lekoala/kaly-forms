<?php

declare(strict_types=1);

namespace Kaly\Forms\Render;

use Kaly\Forms\Field\CheckboxGroupField;
use Kaly\Forms\Field\Field;
use Kaly\Forms\Field\FileField;
use Kaly\Forms\Field\MultipleSelectField;
use Kaly\Forms\Field\RadioGroupField;
use Kaly\Forms\FormError;
use Kaly\Forms\FormState;
use Kaly\Forms\Html;
use Kaly\Forms\Node\ColumnsLayout;
use Kaly\Forms\Node\FormNode;
use Kaly\Forms\Node\Group;
use Kaly\Forms\Node\InlineLayout;
use Kaly\Forms\Node\Layout;
use Kaly\Forms\Node\StackLayout;
use Kaly\Forms\Validation\Checked;
use Kaly\Forms\Validation\Required;

/**
 * Stable extension toolkit for node renderers: state, recursion, escaping and theme access.
 * Renderers decide structure; decoration comes from the theme.
 *
 * idFor() provides a stable unique control ID. A renderer may associate a
 * label explicitly (for) or implicitly (wrapping the control).
 */
final class RenderContext
{
    public function __construct(
        private readonly FormState $state,
        private readonly FormTheme $theme,
        private readonly NodeRendererRegistry $renderers,
        private readonly string $formName = '',
        private readonly bool $inConditionalBranch = false,
    ) {}

    /**
     * Context for rendering inside a conditionally visible container.
     * Monotonic: once inside a conditional branch, descendants stay in one.
     * Only presentation (native constraint projection) is affected;
     * submitted values are never filtered.
     */
    public function withConditionalBranch(): self
    {
        if ($this->inConditionalBranch) {
            return $this;
        }
        return new self($this->state, $this->theme, $this->renderers, $this->formName, true);
    }

    public function state(): FormState
    {
        return $this->state;
    }

    public function theme(): FormTheme
    {
        return $this->theme;
    }

    public function render(FormNode $node): Html
    {
        return $this->renderers->render($node, $this);
    }

    public function value(Field $field): mixed
    {
        return $this->state->value($field->name);
    }

    /** @return list<FormError> */
    public function errors(Field $field): array
    {
        return $this->state->errorsFor($field->name);
    }

    /** @return array<string,string|int|float|bool|null> */
    public function attributes(RenderPart $part, ?FormNode $node): array
    {
        $invalid = $node instanceof Field && $this->errors($node) !== [];
        $required = false;
        if ($node instanceof Field) {
            foreach ($node->rules as $rule) {
                if ($rule instanceof Required || $rule instanceof Checked) {
                    $required = true;
                    break;
                }
            }
        }
        $disabled = $node instanceof Field && (bool) ($node->attributes['disabled'] ?? false);
        $layout = $node instanceof Group ? $node->layout : null;
        return $this->theme->attributes($part, $node, new ThemeContext($invalid, $disabled, $required, $layout));
    }

    /**
     * @param array<string,string|int|float|bool|null> $theme
     * @param array<string,string|int|float|bool|null> $node
     * @return array<string,string|int|float|bool|null>
     */
    public function mergeAttributes(array $theme, array $node): array
    {
        $merged = [...$theme, ...$node];
        $class = trim((string) ($theme['class'] ?? '') . ' ' . (string) ($node['class'] ?? ''));
        if ($class !== '') {
            $merged['class'] = $class;
        } else {
            unset($merged['class']);
        }
        return $merged;
    }

    /** @return array<string,string|int|float|bool|null> */
    public function controlAttributes(Field $field): array
    {
        return $this->mergeAttributes($this->attributes(RenderPart::Control, $field), $field->attributes);
    }

    /** @return array<string,string|int|float|bool|null> */
    public function layoutAttributes(Layout $layout): array
    {
        if ($layout instanceof ColumnsLayout) {
            return ['data-kf-layout' => 'columns', 'data-kf-columns' => $layout->count];
        }
        if ($layout instanceof InlineLayout) {
            return ['data-kf-layout' => 'inline'];
        }
        if ($layout instanceof StackLayout) {
            return ['data-kf-layout' => 'stack'];
        }
        return ['data-kf-layout' => 'custom'];
    }

    /** @return array<string,string|int|float|bool|null> */
    public function conditionAttributes(?\Kaly\Forms\Interaction\Condition $condition): array
    {
        if ($condition === null) {
            return [];
        }

        $attrs = [
            'data-kf-visible-field' => $condition->field,
            'data-kf-visible-op' => $condition->operator,
        ];
        $expected = \Kaly\Forms\Interaction\Condition::normalize($condition->expected);
        if ($expected === null) {
            return $attrs;
        }
        if (is_array($expected)) {
            $attrs['data-kf-visible-value'] = json_encode($expected);
            $attrs['data-kf-visible-type'] = 'list';
            return $attrs;
        }
        $attrs['data-kf-visible-value'] = $expected;
        $attrs['data-kf-visible-type'] = 'string';
        return $attrs;
    }

    /**
     * Native constraint projection.
     *
     * Conditionally visible fields never project `required`: an inactive
     * branch must not block native validation (server skips it too).
     * This covers both a condition on the field itself and a condition on
     * an ancestor Group/Fieldset. Checkbox groups never project `required`
     * per box (that would mean "all boxes required", not "at least one").
     *
     * @return array<string,string|int|float|bool|null>
     */
    public function ruleAttributes(Field $field): array
    {
        if ($field->visibleWhen !== null || $this->inConditionalBranch) {
            return [];
        }
        if ($field instanceof CheckboxGroupField) {
            return [];
        }
        $attrs = [];
        foreach ($field->rules as $rule) {
            $attrs = [...$attrs, ...$rule->htmlAttributes()];
        }
        return $attrs;
    }

    /**
     * Whether this radio in a required group carries native `required`.
     * HTML treats one required radio per name as "one of the group".
     */
    public function radioRequired(RadioGroupField $field, int $index): bool
    {
        if ($field->visibleWhen !== null || $this->inConditionalBranch || $index !== 0) {
            return false;
        }
        foreach ($field->rules as $rule) {
            if ($rule instanceof Required || $rule instanceof Checked) {
                return true;
            }
        }
        return false;
    }

    /**
     * Submission name (PHP protocol) — with [] for multi-value controls —
     * while FormState keeps the logical name.
     */
    public function htmlName(Field $field): string
    {
        if ($field instanceof MultipleSelectField) {
            return $field->name . '[]';
        }
        if ($field instanceof CheckboxGroupField) {
            return $field->name . '[]';
        }
        if ($field instanceof FileField && $field->multiple) {
            return $field->name . '[]';
        }
        return $field->name;
    }

    /**
     * Effective control ID: custom attributes['id'] wins and labels follow it;
     * otherwise a form-scoped stable ID so two forms can share field names.
     */
    public function controlId(Field $field): string
    {
        $custom = $field->attributes['id'] ?? null;
        if (is_string($custom) && $custom !== '') {
            return $custom;
        }
        return $this->idFor($field->name);
    }

    public function fieldRow(Field $field, string $control): Html
    {
        $out =
            '<div'
            . $this->attrs(array_merge($this->attributes(RenderPart::Field, $field), $this->conditionAttributes($field->visibleWhen)))
            . '>';
        if ($field->label !== null) {
            $out .=
                '<label'
                . $this->attrs($this->attributes(RenderPart::Label, $field))
                . ' for="'
                . $this->e($this->controlId($field))
                . '">'
                . $this->e($field->label)
                . '</label>';
        }
        $out .= $control;

        if ($field->help !== null) {
            $out .= '<div' . $this->attrs($this->attributes(RenderPart::Help, $field)) . '>' . $this->e($field->help) . '</div>';
        }
        foreach ($this->errors($field) as $error) {
            $out .=
                '<div'
                . $this->attrs($this->attributes(RenderPart::Errors, $field))
                . ' role="alert">'
                . $this->e($error->message)
                . '</div>';
        }

        return new Html($out . '</div>');
    }

    /**
     * Semantic group wrapper: fieldset + legend, no for/id association.
     * Used for radio/checkbox groups which are multiple controls.
     */
    public function fieldGroup(Field $field, string $control): Html
    {
        $out =
            '<fieldset'
            . $this->attrs(array_merge($this->attributes(RenderPart::Fieldset, $field), $this->conditionAttributes($field->visibleWhen)))
            . '>';
        if ($field->label !== null) {
            $out .= '<legend>' . $this->e($field->label) . '</legend>';
        }
        $out .= $control;

        if ($field->help !== null) {
            $out .= '<div' . $this->attrs($this->attributes(RenderPart::Help, $field)) . '>' . $this->e($field->help) . '</div>';
        }
        foreach ($this->errors($field) as $error) {
            $out .=
                '<div'
                . $this->attrs($this->attributes(RenderPart::Errors, $field))
                . ' role="alert">'
                . $this->e($error->message)
                . '</div>';
        }

        return new Html($out . '</fieldset>');
    }

    /**
     * Normalizes a submitted value for multiple-value fields.
     * A missing submission means an empty list, never null.
     *
     * @return list<string>
     */
    public function listValues(Field $field): array
    {
        $value = $this->state->value($field->name);
        if ($value === null) {
            return [];
        }
        if (is_array($value)) {
            $out = [];
            foreach ($value as $item) {
                $out[] = $this->textValue($item);
            }
            return $out;
        }
        return [$this->textValue($value)];
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

    public function idFor(string $name): string
    {
        $slug = (string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $name);
        if ($this->formName !== '') {
            $form = (string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $this->formName);
            return 'kf-' . $form . '-' . $slug;
        }
        return 'field-' . $slug;
    }
}
