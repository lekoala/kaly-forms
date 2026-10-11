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
        $required = $node instanceof Field && $this->hasRequiredRule($node);
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

    /**
     * Theme + field attributes, plus the aria error/help association. Radio
     * and checkbox groups only get aria-invalid per box: their description
     * belongs to the wrapping fieldset (see fieldGroup()).
     *
     * @return array<string,string|int|float|bool|null>
     */
    public function controlAttributes(Field $field): array
    {
        $aria = $this->ariaAttributes($field);
        if ($field instanceof RadioGroupField || $field instanceof CheckboxGroupField) {
            unset($aria['aria-describedby']);
        }
        return [...$aria, ...$this->mergeAttributes($this->attributes(RenderPart::Control, $field), $field->attributes)];
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
            $attrs['data-kf-visible-value'] = json_encode($expected, JSON_THROW_ON_ERROR);
            $attrs['data-kf-visible-type'] = 'list';
            return $attrs;
        }
        $attrs['data-kf-visible-value'] = $expected;
        $attrs['data-kf-visible-type'] = 'string';
        return $attrs;
    }

    /**
     * Constraints a browser checks even on a value the user never edited
     * (pre-filled or re-displayed). minlength/maxlength only apply to edited
     * values and step has a native default, so they are not in this list.
     */
    public const array DEFERRED_CONSTRAINTS = ['required', 'min', 'max', 'pattern'];

    /**
     * Native constraint projection.
     *
     * Without JS, an inactive conditional branch stays enabled: any native
     * constraint that can fail on a pre-filled value would block submission
     * while the server skips the branch. Conditionally visible fields (own
     * condition or ancestor Group/Fieldset) therefore render the constraints
     * of DEFERRED_CONSTRAINTS as `data-kf-<name>` (e.g. data-kf-required,
     * data-kf-min="1") so an enhancement can restore them while the branch is
     * visible. maxlength/minlength/step are kept. Checkbox groups never
     * project `required` per box (that would mean "all boxes required", not
     * "at least one").
     *
     * @return array<string,string|int|float|bool|null>
     */
    public function ruleAttributes(Field $field): array
    {
        if ($field instanceof CheckboxGroupField) {
            return [];
        }
        $attrs = [];
        foreach ($field->rules as $rule) {
            $attrs = [...$attrs, ...$rule->htmlAttributes()];
        }
        if (!$this->isConditional($field)) {
            return $attrs;
        }
        foreach (self::DEFERRED_CONSTRAINTS as $name) {
            if (!array_key_exists($name, $attrs)) {
                continue;
            }
            $value = $attrs[$name];
            unset($attrs[$name]);
            if ($value !== false && $value !== null) {
                $attrs['data-kf-' . $name] = $value;
            }
        }
        return $attrs;
    }

    private function isConditional(Field $field): bool
    {
        return $field->visibleWhen !== null || $this->inConditionalBranch;
    }

    /**
     * Error/help association for the control: aria-invalid and
     * aria-describedby pointing at the ids fieldRow()/fieldGroup() emit.
     *
     * @return array<string,string|int|float|bool|null>
     */
    public function ariaAttributes(Field $field): array
    {
        $describedBy = [];
        if ($field->help !== null) {
            $describedBy[] = $this->helpId($field);
        }
        $errors = $this->errors($field);
        foreach (array_keys($errors) as $index) {
            $describedBy[] = $this->errorId($field, $index);
        }
        return [
            'aria-invalid' => $errors !== [] ? 'true' : null,
            'aria-describedby' => $describedBy !== [] ? implode(' ', $describedBy) : null,
        ];
    }

    public function helpId(Field $field): string
    {
        return $this->controlId($field) . '-help';
    }

    public function errorId(Field $field, int $index): string
    {
        return $this->controlId($field) . '-error-' . $index;
    }

    /**
     * Whether this radio in a required group carries native `required`.
     * HTML treats one required radio per name as "one of the group".
     */
    public function radioRequired(RadioGroupField $field, int $index): bool
    {
        return !$this->isConditional($field) && $index === 0 && $this->hasRequiredRule($field);
    }

    /**
     * Required projection for one radio: native `required` on the first radio,
     * or `data-kf-required` (restored by the optional JS) inside a conditional branch.
     *
     * @return array<string,string|int|float|bool|null>
     */
    public function radioRequiredAttributes(RadioGroupField $field, int $index): array
    {
        if ($index !== 0 || !$this->hasRequiredRule($field)) {
            return [];
        }
        return $this->isConditional($field) ? ['data-kf-required' => true] : ['required' => true];
    }

    private function hasRequiredRule(Field $field): bool
    {
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
        return ControlIds::controlId($this->formName, $field);
    }

    /**
     * Labeled row for one control. With $labelFor = false (non-labelable
     * control such as a read-only display), the label gets labelId() instead
     * of a `for`, and the control should reference it with aria-labelledby.
     */
    public function fieldRow(Field $field, string $control, bool $labelFor = true): Html
    {
        $out =
            '<div'
            . $this->attrs(array_merge($this->attributes(RenderPart::Field, $field), $this->conditionAttributes($field->visibleWhen)))
            . '>';
        if ($field->label !== null) {
            $association = $labelFor ? ['for' => $this->controlId($field)] : ['id' => $this->labelId($field)];
            $out .=
                '<label'
                . $this->attrs([...$this->attributes(RenderPart::Label, $field), ...$association])
                . '>'
                . $this->e($field->label)
                . '</label>';
        }
        $out .= $control . $this->fieldMessages($field);

        return new Html($out . '</div>');
    }

    public function labelId(Field $field): string
    {
        return $this->controlId($field) . '-label';
    }

    /**
     * Semantic group wrapper: fieldset + legend, no for/id association.
     * Used for radio/checkbox groups which are multiple controls; help and
     * errors are referenced from the fieldset.
     */
    public function fieldGroup(Field $field, string $control): Html
    {
        $aria = $this->ariaAttributes($field);
        $out =
            '<fieldset'
            . $this->attrs([
                ...$this->attributes(RenderPart::Fieldset, $field),
                ...$this->conditionAttributes($field->visibleWhen),
                'aria-describedby' => $aria['aria-describedby'],
            ])
            . '>';
        if ($field->label !== null) {
            $out .= '<legend' . $this->attrs($this->attributes(RenderPart::Legend, $field)) . '>' . $this->e($field->label) . '</legend>';
        }
        $out .= $control . $this->fieldMessages($field);

        return new Html($out . '</fieldset>');
    }

    /**
     * Help and error messages with the ids ariaAttributes() references.
     * No role="alert" here: every error would be announced at once on load;
     * the global error summary is the live announcement.
     */
    private function fieldMessages(Field $field): string
    {
        $out = '';
        if ($field->help !== null) {
            $out .=
                '<div'
                . $this->attrs([...$this->attributes(RenderPart::Help, $field), 'id' => $this->helpId($field)])
                . '>'
                . $this->e($field->help)
                . '</div>';
        }
        foreach ($this->errors($field) as $index => $error) {
            $out .=
                '<div'
                . $this->attrs([...$this->attributes(RenderPart::Errors, $field), 'id' => $this->errorId($field, $index)])
                . '>'
                . $this->e($error->message)
                . '</div>';
        }
        return $out;
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

    /**
     * Attribute values are escaped; attribute names cannot be (escaping does
     * not stop a space or `=` from starting a new attribute), so invalid names
     * are rejected instead.
     *
     * @param array<string,string|int|float|bool|null> $attributes
     */
    public function attrs(array $attributes): string
    {
        $out = '';
        foreach ($attributes as $name => $value) {
            $name = (string) $name;
            if ($name === '' || preg_match('/[\s"\'>\/=\x00-\x1F\x7F]/', $name) === 1) {
                throw new \InvalidArgumentException(sprintf('Invalid HTML attribute name "%s"', $name));
            }
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
        return ControlIds::defaultId($this->formName, $name);
    }
}
