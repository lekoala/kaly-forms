<?php

declare(strict_types=1);

namespace Kaly\Forms\Node;

/** Children are preferably composed over the given column count. */
final readonly class ColumnsLayout implements Layout
{
    public function __construct(
        public int $count,
    ) {
        if ($count < 1) {
            throw new \InvalidArgumentException('Column count must be at least 1');
        }
    }
}
