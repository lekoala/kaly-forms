<?php

declare(strict_types=1);

namespace Kaly\Forms\Action;

final readonly class SubmitAction
{
    /** @param array<string,string|int|float|bool|null> $attributes */
    public function __construct(
        public string $name,
        public string $label,
        public array $attributes = [],
    ) {}
}
