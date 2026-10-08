<?php

declare(strict_types=1);

namespace Kaly\Forms\Render;

use Kaly\Forms\Node\FormNode;

/**
 * Decorates existing markup with classes and attributes.
 * If the markup structure itself must change, replace the node renderer instead.
 */
interface FormTheme
{
    /** @return array<string,string|int|float|bool|null> */
    public function attributes(RenderPart $part, ?FormNode $node, ThemeContext $context): array;
}
