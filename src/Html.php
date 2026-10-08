<?php

declare(strict_types=1);

namespace Kaly\Forms;

/** A rendered HTML fragment. Rendering adapters may map this to their native safe HTML type. */
final readonly class Html implements \Stringable
{
    public function __construct(
        private string $value,
    ) {}

    public function __toString(): string
    {
        return $this->value;
    }

    public function value(): string
    {
        return $this->value;
    }
}
