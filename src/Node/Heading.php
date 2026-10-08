<?php

declare(strict_types=1);

namespace Kaly\Forms\Node;

/** Semantic heading: renders a real h1-h6 element with escaped text. */
final class Heading implements FormNode
{
    public function __construct(
        public readonly int $level,
        public readonly string $text,
    ) {
        if ($level < 1 || $level > 6) {
            throw new \InvalidArgumentException('Heading level must be between 1 and 6');
        }
    }
}
