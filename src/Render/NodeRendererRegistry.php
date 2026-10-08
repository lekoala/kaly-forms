<?php

declare(strict_types=1);

namespace Kaly\Forms\Render;

use Kaly\Forms\Field\CheckboxField;
use Kaly\Forms\Field\CheckboxGroupField;
use Kaly\Forms\Field\ChoiceField;
use Kaly\Forms\Field\CustomElementField;
use Kaly\Forms\Field\DateField;
use Kaly\Forms\Field\DateTimeField;
use Kaly\Forms\Field\EmailField;
use Kaly\Forms\Field\FileField;
use Kaly\Forms\Field\HiddenField;
use Kaly\Forms\Field\MultipleSelectField;
use Kaly\Forms\Field\NumericField;
use Kaly\Forms\Field\OptionGroup;
use Kaly\Forms\Field\PasswordField;
use Kaly\Forms\Field\RadioGroupField;
use Kaly\Forms\Field\ReadonlyField;
use Kaly\Forms\Field\TextareaField;
use Kaly\Forms\Field\TextField;
use Kaly\Forms\Field\TimeField;
use Kaly\Forms\Html;
use Kaly\Forms\Node\Fieldset;
use Kaly\Forms\Node\FormNode;
use Kaly\Forms\Node\Group;
use Kaly\Forms\Node\Heading;
use Kaly\Forms\Node\HtmlBlock;
use Kaly\Forms\Node\Text;

/**
 * Which markup a node produces. Accepts closures and NodeRenderer objects alike.
 * Register a node renderer to change structure; change the theme to only decorate.
 */
final class NodeRendererRegistry
{
    /** @var array<class-string<FormNode>, callable(FormNode,RenderContext):Html> */
    private array $renderers = [];

    public static function defaults(): self
    {
        $renderers = new self();
        self::registerFieldControls($renderers);
        self::registerExtendedControls($renderers);
        self::registerContainerControls($renderers);
        self::registerContentControls($renderers);
        return $renderers;
    }

    /**
     * @param class-string<FormNode> $nodeClass
     * @param NodeRenderer|callable(FormNode,RenderContext):Html $renderer
     */
    public function register(string $nodeClass, NodeRenderer|callable $renderer): self
    {
        $this->renderers[$nodeClass] = $renderer instanceof NodeRenderer ? $renderer->render(...) : $renderer;
        return $this;
    }

    public function render(FormNode $node, RenderContext $context): Html
    {
        $class = $node::class;
        if (isset($this->renderers[$class])) {
            return $this->renderers[$class]($node, $context);
        }

        foreach ($this->renderers as $registered => $renderer) {
            if ($node instanceof $registered) {
                return $renderer($node, $context);
            }
        }

        throw new \LogicException(sprintf('No renderer registered for node %s', $class));
    }

    private static function registerFieldControls(self $renderers): void
    {
        $renderers->register(TextField::class, static function (FormNode $node, RenderContext $context): Html {
            /** @var TextField $field */ $field = $node;
            return self::renderTextControl($field, $context);
        });
        $renderers->register(EmailField::class, static function (FormNode $node, RenderContext $context): Html {
            /** @var EmailField $field */ $field = $node;
            return self::renderEmailControl($field, $context);
        });
        $renderers->register(PasswordField::class, static function (FormNode $node, RenderContext $context): Html {
            /** @var PasswordField $field */ $field = $node;
            return self::renderPasswordControl($field, $context);
        });
        $renderers->register(TextareaField::class, static function (FormNode $node, RenderContext $context): Html {
            /** @var TextareaField $field */ $field = $node;
            return self::renderTextareaControl($field, $context);
        });
        $renderers->register(ChoiceField::class, static function (FormNode $node, RenderContext $context): Html {
            /** @var ChoiceField $field */ $field = $node;
            return self::renderChoiceControl($field, $context);
        });
        $renderers->register(CheckboxField::class, static function (FormNode $node, RenderContext $context): Html {
            /** @var CheckboxField $field */ $field = $node;
            return self::renderCheckboxControl($field, $context);
        });
        $renderers->register(HiddenField::class, static function (FormNode $node, RenderContext $context): Html {
            /** @var HiddenField $field */ $field = $node;
            return self::renderHiddenControl($field, $context);
        });
        $renderers->register(DateField::class, static function (FormNode $node, RenderContext $context): Html {
            /** @var DateField $field */ $field = $node;
            return self::renderDateControl($field, $context);
        });
        $renderers->register(CustomElementField::class, static function (FormNode $node, RenderContext $context): Html {
            /** @var CustomElementField $field */ $field = $node;
            return self::renderCustomElementControl($field, $context);
        });
    }

    private static function registerExtendedControls(self $renderers): void
    {
        $renderers->register(RadioGroupField::class, static function (FormNode $node, RenderContext $context): Html {
            /** @var RadioGroupField $field */ $field = $node;
            return self::renderRadioGroupControl($field, $context);
        });
        $renderers->register(MultipleSelectField::class, static function (FormNode $node, RenderContext $context): Html {
            /** @var MultipleSelectField $field */ $field = $node;
            return self::renderMultipleSelectControl($field, $context);
        });
        $renderers->register(CheckboxGroupField::class, static function (FormNode $node, RenderContext $context): Html {
            /** @var CheckboxGroupField $field */ $field = $node;
            return self::renderCheckboxGroupControl($field, $context);
        });
        $renderers->register(TimeField::class, static function (FormNode $node, RenderContext $context): Html {
            /** @var TimeField $field */ $field = $node;
            return self::renderTimeControl($field, $context);
        });
        $renderers->register(DateTimeField::class, static function (FormNode $node, RenderContext $context): Html {
            /** @var DateTimeField $field */ $field = $node;
            return self::renderDateTimeControl($field, $context);
        });
        $renderers->register(NumericField::class, static function (FormNode $node, RenderContext $context): Html {
            /** @var NumericField $field */ $field = $node;
            return self::renderNumericControl($field, $context);
        });
        $renderers->register(ReadonlyField::class, static function (FormNode $node, RenderContext $context): Html {
            /** @var ReadonlyField $field */ $field = $node;
            return self::renderReadonlyControl($field, $context);
        });
        $renderers->register(FileField::class, static function (FormNode $node, RenderContext $context): Html {
            /** @var FileField $field */ $field = $node;
            return self::renderFileControl($field, $context);
        });
    }

    private static function renderRadioGroupControl(RadioGroupField $field, RenderContext $context): Html
    {
        $value = $context->textValue($context->value($field));
        $out = '';
        foreach (array_values($field->choices) as $index => $label) {
            $optionValue = array_keys($field->choices)[$index];
            $out .=
                '<label><input'
                . $context->attrs([
                    'type' => 'radio',
                    'name' => $field->name,
                    'value' => $optionValue,
                    'checked' => (string) $optionValue === $value,
                    'required' => $context->radioRequired($field, $index),
                    'data-kf-field' => $field->name,
                    ...$context->controlAttributes($field),
                ])
                . '> '
                . $context->e($label)
                . '</label>';
        }
        return $context->fieldGroup($field, $out);
    }

    private static function renderMultipleSelectControl(MultipleSelectField $field, RenderContext $context): Html
    {
        $selected = $context->listValues($field);
        $out =
            '<select'
            . $context->attrs([
                'id' => $context->controlId($field),
                'name' => $context->htmlName($field),
                'data-kf-field' => $field->name,
                'multiple' => true,
                'size' => $field->size,
                ...$context->ruleAttributes($field),
                ...$context->controlAttributes($field),
            ])
            . '>';
        foreach ($field->choices as $optionValue => $choice) {
            if ($choice instanceof OptionGroup) {
                $out .= '<optgroup label="' . $context->e($choice->label) . '">';
                foreach ($choice->choices as $subValue => $subLabel) {
                    $out .= self::renderSelectOption($subValue, $subLabel, in_array((string) $subValue, $selected, true), $context);
                }
                $out .= '</optgroup>';
                continue;
            }
            $out .= self::renderSelectOption($optionValue, $choice, in_array((string) $optionValue, $selected, true), $context);
        }
        return $context->fieldRow($field, $out . '</select>');
    }

    private static function renderCheckboxGroupControl(CheckboxGroupField $field, RenderContext $context): Html
    {
        $selected = $context->listValues($field);
        $out = '';
        foreach ($field->choices as $optionValue => $label) {
            $out .=
                '<label><input'
                . $context->attrs([
                    'type' => 'checkbox',
                    'name' => $context->htmlName($field),
                    'value' => $optionValue,
                    'checked' => in_array((string) $optionValue, $selected, true),
                    'data-kf-field' => $field->name,
                    'data-kf-value-kind' => 'list',
                    ...$context->controlAttributes($field),
                ])
                . '> '
                . $context->e($label)
                . '</label>';
        }
        return $context->fieldGroup($field, $out);
    }

    private static function renderTimeControl(TimeField $field, RenderContext $context): Html
    {
        $control =
            '<input'
            . $context->attrs([
                'id' => $context->controlId($field),
                'name' => $field->name,
                'type' => 'time',
                'data-kf-field' => $field->name,
                'value' => $context->textValue($context->value($field)),
                'min' => $field->min,
                'max' => $field->max,
                ...$context->ruleAttributes($field),
                ...$context->controlAttributes($field),
            ])
            . '>';
        return $context->fieldRow($field, $control);
    }

    private static function renderDateTimeControl(DateTimeField $field, RenderContext $context): Html
    {
        $control =
            '<input'
            . $context->attrs([
                'id' => $context->controlId($field),
                'name' => $field->name,
                'type' => 'datetime-local',
                'data-kf-field' => $field->name,
                'value' => $context->textValue($context->value($field)),
                'min' => $field->min,
                'max' => $field->max,
                ...$context->ruleAttributes($field),
                ...$context->controlAttributes($field),
            ])
            . '>';
        return $context->fieldRow($field, $control);
    }

    private static function renderNumericControl(NumericField $field, RenderContext $context): Html
    {
        $control =
            '<input'
            . $context->attrs([
                'id' => $context->controlId($field),
                'name' => $field->name,
                'type' => 'number',
                'data-kf-field' => $field->name,
                'value' => $context->textValue($context->value($field)),
                'min' => $field->min,
                'max' => $field->max,
                'step' => $field->step,
                ...$context->ruleAttributes($field),
                ...$context->controlAttributes($field),
            ])
            . '>';
        return $context->fieldRow($field, $control);
    }

    private static function renderReadonlyControl(ReadonlyField $field, RenderContext $context): Html
    {
        $value = $context->textValue($context->value($field));
        $out = '<span' . $context->attrs($context->controlAttributes($field)) . '>' . $context->e($value) . '</span>';
        $out .=
            '<input'
            . $context->attrs([
                'type' => 'hidden',
                'name' => $field->name,
                'value' => $value,
            ])
            . '>';
        return $context->fieldRow($field, $out);
    }

    private static function renderFileControl(FileField $field, RenderContext $context): Html
    {
        $control =
            '<input'
            . $context->attrs([
                'id' => $context->controlId($field),
                'name' => $context->htmlName($field),
                'type' => 'file',
                'accept' => $field->accept,
                'multiple' => $field->multiple,
                'data-kf-field' => $field->name,
                ...$context->ruleAttributes($field),
                ...$context->controlAttributes($field),
            ])
            . '>';
        return $context->fieldRow($field, $control);
    }

    private static function renderTextControl(TextField $field, RenderContext $context): Html
    {
        $control =
            '<input'
            . $context->attrs([
                'id' => $context->controlId($field),
                'name' => $field->name,
                'type' => $field->inputType,
                'data-kf-field' => $field->name,
                'value' => $field->inputType === 'password' ? null : $context->textValue($context->value($field)),
                ...$context->ruleAttributes($field),
                ...$context->controlAttributes($field),
            ])
            . '>';
        return $context->fieldRow($field, $control);
    }

    private static function renderEmailControl(EmailField $field, RenderContext $context): Html
    {
        $control =
            '<input'
            . $context->attrs([
                'id' => $context->controlId($field),
                'name' => $field->name,
                'type' => 'email',
                'data-kf-field' => $field->name,
                'value' => $context->textValue($context->value($field)),
                ...$context->ruleAttributes($field),
                ...$context->controlAttributes($field),
            ])
            . '>';
        return $context->fieldRow($field, $control);
    }

    private static function renderPasswordControl(PasswordField $field, RenderContext $context): Html
    {
        $control =
            '<input'
            . $context->attrs([
                'id' => $context->controlId($field),
                'name' => $field->name,
                'type' => 'password',
                'data-kf-field' => $field->name,
                ...$context->ruleAttributes($field),
                ...$context->controlAttributes($field),
            ])
            . '>';
        return $context->fieldRow($field, $control);
    }

    private static function renderTextareaControl(TextareaField $field, RenderContext $context): Html
    {
        $control =
            '<textarea'
            . $context->attrs([
                'id' => $context->controlId($field),
                'name' => $field->name,
                'data-kf-field' => $field->name,
                ...$context->ruleAttributes($field),
                ...$context->controlAttributes($field),
            ])
            . '>'
            . $context->e($context->value($field))
            . '</textarea>';
        return $context->fieldRow($field, $control);
    }

    private static function renderChoiceControl(ChoiceField $field, RenderContext $context): Html
    {
        $value = $context->textValue($context->value($field));
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
            . $context->attrs([
                'id' => $context->controlId($field),
                'name' => $field->name,
                'data-kf-field' => $field->name,
                ...$context->ruleAttributes($field),
                ...$remoteAttrs,
                ...$context->controlAttributes($field),
            ])
            . '>';
        foreach ($field->choices as $optionValue => $choice) {
            if ($choice instanceof OptionGroup) {
                $out .= '<optgroup label="' . $context->e($choice->label) . '">';
                foreach ($choice->choices as $subValue => $subLabel) {
                    $out .= self::renderSelectOption($subValue, $subLabel, (string) $subValue === $value, $context);
                }
                $out .= '</optgroup>';
                continue;
            }
            $out .= self::renderSelectOption($optionValue, $choice, (string) $optionValue === $value, $context);
        }
        return $context->fieldRow($field, $out . '</select>');
    }

    private static function renderSelectOption(string|int $optionValue, string $label, bool $selected, RenderContext $context): string
    {
        return (
            '<option'
            . $context->attrs([
                'value' => $optionValue,
                'selected' => $selected,
            ])
            . '>'
            . $context->e($label)
            . '</option>'
        );
    }

    private static function renderCheckboxControl(CheckboxField $field, RenderContext $context): Html
    {
        $control =
            '<input'
            . $context->attrs([
                'id' => $context->controlId($field),
                'name' => $field->name,
                'type' => 'checkbox',
                'value' => '1',
                'data-kf-field' => $field->name,
                'checked' => in_array($context->value($field), [true, 1, '1', 'on'], true),
                ...$context->ruleAttributes($field),
                ...$context->controlAttributes($field),
            ])
            . '>';
        return $context->fieldRow($field, $control);
    }

    private static function renderHiddenControl(HiddenField $field, RenderContext $context): Html
    {
        return new Html(
            '<input'
            . $context->attrs([
                'name' => $field->name,
                'type' => 'hidden',
                'value' => $context->textValue($context->value($field)),
                ...$context->controlAttributes($field),
            ])
            . '>',
        );
    }

    private static function renderDateControl(DateField $field, RenderContext $context): Html
    {
        $control =
            '<input'
            . $context->attrs([
                'id' => $context->controlId($field),
                'name' => $field->name,
                'type' => 'date',
                'data-kf-field' => $field->name,
                'value' => $context->textValue($context->value($field)),
                'min' => $field->min,
                'max' => $field->max,
                ...$context->ruleAttributes($field),
                ...$context->controlAttributes($field),
            ])
            . '>';
        return $context->fieldRow($field, $control);
    }

    private static function renderCustomElementControl(CustomElementField $field, RenderContext $context): Html
    {
        $value = $context->textValue($context->value($field));
        $disabled = (bool) ($field->attributes['disabled'] ?? false);
        $out = '';
        if ($field->mirrorHiddenInput) {
            $out .=
                '<input'
                . $context->attrs([
                    'type' => 'hidden',
                    'name' => $field->name,
                    'value' => $value,
                    'disabled' => $disabled,
                    'data-kf-mirror-for' => $context->controlId($field),
                    'data-kf-field' => $field->name,
                ])
                . '>';
        }
        $out .=
            '<'
            . $context->e($field->tag)
            . $context->attrs([
                'id' => $context->controlId($field),
                'data-kf-field' => $field->name,
                'value' => $value,
                ...$context->controlAttributes($field),
            ])
            . '></'
            . $context->e($field->tag)
            . '>';
        return $context->fieldRow($field, $out);
    }

    private static function registerContainerControls(self $renderers): void
    {
        $renderers->register(Group::class, static function (FormNode $node, RenderContext $context): Html {
            /** @var Group $group */ $group = $node;
            $childContext = $group->visibleWhen !== null ? $context->withConditionalBranch() : $context;
            $out = '';
            foreach ($group->children() as $child) {
                $out .= $childContext->render($child)->value();
            }
            return new Html('<div' . $context->attrs($context->mergeAttributes($context->attributes(RenderPart::Group, $group), [
                ...$group->attributes,
                ...$context->layoutAttributes($group->layout),
                ...$context->conditionAttributes($group->visibleWhen),
            ])) . '>' . $out . '</div>');
        });
        $renderers->register(Fieldset::class, static function (FormNode $node, RenderContext $context): Html {
            /** @var Fieldset $fieldset */ $fieldset = $node;
            $childContext = $fieldset->visibleWhen !== null ? $context->withConditionalBranch() : $context;
            $out = '<legend>' . $context->e($fieldset->legend) . '</legend>';
            foreach ($fieldset->children() as $child) {
                $out .= $childContext->render($child)->value();
            }
            return new Html('<fieldset' . $context->attrs($context->mergeAttributes($context->attributes(RenderPart::Fieldset, $fieldset), [
                ...$fieldset->attributes,
                ...$context->conditionAttributes($fieldset->visibleWhen),
            ])) . '>' . $out . '</fieldset>');
        });
    }

    private static function registerContentControls(self $renderers): void
    {
        $renderers->register(Heading::class, static function (FormNode $node, RenderContext $context): Html {
            /** @var Heading $heading */ $heading = $node;
            return new Html(
                '<h'
                . $heading->level
                . $context->attrs($context->attributes(RenderPart::Heading, $heading))
                . '>'
                . $context->e($heading->text)
                . '</h'
                . $heading->level
                . '>',
            );
        });
        $renderers->register(Text::class, static function (FormNode $node, RenderContext $context): Html {
            /** @var Text $text */ $text = $node;
            return new Html(
                '<p' . $context->attrs($context->attributes(RenderPart::Text, $text)) . '>' . $context->e($text->text) . '</p>',
            );
        });
        $renderers->register(HtmlBlock::class, static function (FormNode $node): Html {
            /** @var HtmlBlock $block */ $block = $node;
            return $block->html;
        });
    }
}
