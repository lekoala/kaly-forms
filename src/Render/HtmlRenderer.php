<?php

declare(strict_types=1);

namespace Kaly\Forms\Render;

use Kaly\Forms\Action\SubmitAction;
use Kaly\Forms\Field\Field;
use Kaly\Forms\Form;
use Kaly\Forms\Html;
use Kaly\Forms\Node\ContainerNode;
use Kaly\Forms\Node\FormNode;

/**
 * Default form shell: renders the form element, global errors, node tree and actions zone.
 * Node markup comes from the profile renderers, decoration from the profile theme.
 */
final class HtmlRenderer implements RendererInterface
{
    public function render(Form $form): Html
    {
        $profile = $form->profile();
        $context = new RenderContext($form->state(), $profile->theme, $profile->renderers, $form->name);
        $formAttrs = [
            'name' => $form->name,
            'method' => strtolower($form->method),
            'action' => $form->action,
        ];
        if ($form->enctype() !== 'application/x-www-form-urlencoded') {
            $formAttrs['enctype'] = $form->enctype();
        }
        $out = '<form' . $context->attrs($context->mergeAttributes($context->attributes(RenderPart::Form, null), [
            ...$formAttrs,
            ...$form->attributes,
        ])) . '>';

        // Field errors shown here lost their row (inactive branch, hidden or
        // unknown field): name the field when it has a label. Presentation
        // only, the FormError message itself is never changed.
        $labels = self::labels($form->children());
        foreach ([...$form->state()->globalErrors(), ...$form->unmatchedErrors()] as $error) {
            $label = $error->field !== null ? $labels[$error->field] ?? null : null;
            $out .=
                '<div'
                . $context->attrs($context->attributes(RenderPart::Errors, null))
                . ' role="alert">'
                . ($label !== null ? '<strong>' . $context->e($label) . '</strong>: ' : '')
                . $context->e($error->message)
                . '</div>';
        }

        foreach ($form->children() as $child) {
            $out .= $context->render($child)->value();
        }

        if ($form->actions() !== []) {
            $out .=
                '<div'
                . $context->attrs($context->mergeAttributes(
                    $context->attributes(RenderPart::Actions, null),
                    $context->layoutAttributes($form->actionsLayout()),
                ))
                . '>';
            foreach ($form->actions() as $action) {
                $out .= $this->renderAction($action, $context)->value();
            }
            $out .= '</div>';
        }

        return new Html($out . '</form>');
    }

    /**
     * @param list<FormNode> $nodes
     * @return array<string,string> field name => label (first labeled field wins)
     */
    private static function labels(array $nodes): array
    {
        $labels = [];
        foreach ($nodes as $node) {
            if ($node instanceof Field && $node->label !== null) {
                $labels[$node->name] ??= $node->label;
            } elseif ($node instanceof ContainerNode) {
                $labels += self::labels($node->children());
            }
        }
        return $labels;
    }

    private function renderAction(SubmitAction $action, RenderContext $context): Html
    {
        return new Html('<button' . $context->attrs($context->mergeAttributes($context->attributes(RenderPart::Action, null), [
            'type' => 'submit',
            'name' => $action->name,
            'value' => '1',
            ...$action->attributes,
        ])) . '>' . $context->e($action->label) . '</button>');
    }
}
