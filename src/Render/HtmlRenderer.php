<?php

declare(strict_types=1);

namespace Kaly\Forms\Render;

use Kaly\Forms\Action\SubmitAction;
use Kaly\Forms\Form;
use Kaly\Forms\Html;

/**
 * Default form shell: renders the form element, global errors, node tree and actions zone.
 * Node markup comes from the profile renderers, decoration from the profile theme.
 */
final class HtmlRenderer implements RendererInterface
{
    public function render(Form $form): Html
    {
        $profile = $form->profile();
        $context = new RenderContext($form->state(), $profile->theme, $profile->renderers);
        $out = '<form' . $context->attrs($context->mergeAttributes($context->attributes(RenderPart::Form, null), [
            'name' => $form->name,
            'method' => strtolower($form->method),
            'action' => $form->action,
            ...$form->attributes,
        ])) . '>';

        foreach ($form->state()->globalErrors() as $error) {
            $out .=
                '<div'
                . $context->attrs($context->attributes(RenderPart::Errors, null))
                . ' role="alert">'
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

    private function renderAction(SubmitAction $action, RenderContext $context): Html
    {
        return new Html(
            '<button'
            . $context->attrs([
                'type' => 'submit',
                'name' => $action->name,
                'value' => '1',
                ...$action->attributes,
            ])
            . '>'
            . $context->e($action->label)
            . '</button>',
        );
    }
}
