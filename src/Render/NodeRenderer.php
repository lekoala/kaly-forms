<?php

declare(strict_types=1);

namespace Kaly\Forms\Render;

use Kaly\Forms\Html;
use Kaly\Forms\Node\FormNode;

/** Renders one node; structural overrides live here, decoration lives in the theme. */
interface NodeRenderer
{
    public function render(FormNode $node, RenderContext $context): Html;
}
