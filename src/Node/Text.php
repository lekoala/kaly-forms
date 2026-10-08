<?php

declare(strict_types=1);

namespace Kaly\Forms\Node;

/** Plain escaped text content: no data, no validation, no submission. */
final readonly class Text implements FormNode
{
    public function __construct(
        public string $text,
    ) {}
}
