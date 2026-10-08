<?php

declare(strict_types=1);

namespace Kaly\Forms\Render;

use Kaly\Forms\Field\Field;
use Kaly\Forms\FormState;
use Kaly\Forms\Html;
use Kaly\Forms\Node\ColumnsLayout;
use Kaly\Forms\Node\FormNode;
use Kaly\Forms\Node\Group;
use Kaly\Forms\Node\InlineLayout;
use Kaly\Forms\Node\Layout;
use Kaly\Forms\Node\StackLayout;
use Kaly\Forms\Validation\Required;
use Kaly\Forms\Violation;

/**
 * Stable extension toolkit for node renderers: state, recursion, escaping and theme access.
 * Renderers decide structure; decoration comes from the theme.
 */
final class RenderContext
{
    public function __construct(
        private readonly FormState $state,
        private readonly FormTheme $theme,
        private readonly NodeRendererRegistry $renderers,
    ) {}

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

    /** @return list<Violation> */
    public function violations(Field $field): array
    {
        return $this->state->errorsFor($field->name);
    }

    /** @return array<string,string|int|float|bool|null> */
    public function attributes(RenderPart $part, ?FormNode $node): array
    {
        $invalid = $node instanceof Field && $this->violations($node) !== [];
        $required = false;
        if ($node instanceof Field) {
            foreach ($node->rules as $rule) {
                if ($rule instanceof Required) {
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

        return [
            'data-kf-visible-field' => $condition->field,
            'data-kf-visible-op' => $condition->operator,
            'data-kf-visible-value' => is_scalar($condition->expected) ? (string) $condition->expected : json_encode($condition->expected),
        ];
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
                . $this->e($this->idFor($field->name))
                . '">'
                . $this->e($field->label)
                . '</label>';
        }
        $out .= $control;

        if ($field->help !== null) {
            $out .= '<div' . $this->attrs($this->attributes(RenderPart::Help, $field)) . '>' . $this->e($field->help) . '</div>';
        }
        foreach ($this->violations($field) as $error) {
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
        return 'field-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', $name);
    }
}
