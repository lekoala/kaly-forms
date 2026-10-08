<?php

declare(strict_types=1);

namespace Kaly\Forms;

/**
 * Framework-neutral contract for objects that can render trusted HTML.
 *
 * Twig/Latte/Kaly adapters should convert Html to their native safe-markup type,
 * so templates can print the form variable without knowing form internals.
 */
interface HtmlRenderable extends \Stringable
{
    public function toHtml(): Html;
}
