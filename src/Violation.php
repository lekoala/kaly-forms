<?php

declare(strict_types=1);

namespace Kaly\Forms;

final readonly class Violation
{
    public function __construct(
        public string $message,
        public ?string $field = null,
        public ?string $code = null,
    ) {}
}
