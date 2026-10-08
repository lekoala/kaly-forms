<?php

declare(strict_types=1);

namespace Kaly\Forms\Validation;

use Kaly\Forms\FormError;

interface Rule
{
    /** @param array<string,mixed> $allValues */
    public function validate(string $field, mixed $value, array $allValues): ?FormError;

    /**
     * Attributes that can be projected to native browser validation.
     *
     * @return array<string,string|int|float|bool|null>
     */
    public function htmlAttributes(): array;
}
