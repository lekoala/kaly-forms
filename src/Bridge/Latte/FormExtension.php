<?php

declare(strict_types=1);

namespace Kaly\Forms\Bridge\Latte;

use Kaly\Forms\HtmlRenderable;
use Latte\Extension;
use Latte\Runtime\Html;

/**
 * Idiomatic Latte exposure for HtmlRenderable form objects.
 *
 *     {$form|form}
 *     {=form_html($form)}
 *
 * Requires latte/latte. Register with $latte->addExtension(new FormExtension()).
 * The filter/function return a Latte\Runtime\Html, so the generated form is not
 * escaped a second time.
 */
final class FormExtension extends Extension
{
    public static function render(HtmlRenderable $form): Html
    {
        return new Html($form->toHtml()->value());
    }

    /** @return array<string, callable> */
    public function getFilters(): array
    {
        return ['form' => self::render(...)];
    }

    /** @return array<string, callable> */
    public function getFunctions(): array
    {
        return ['form_html' => self::render(...)];
    }
}
