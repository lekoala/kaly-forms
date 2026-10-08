<?php

declare(strict_types=1);

namespace Kaly\Forms\Validation;

use Kaly\Forms\Violation;

final readonly class Numeric implements Rule
{
    public function __construct(
        private string $message = 'Enter a number',
    ) {}

    public function validate(string $field, mixed $value, array $allValues): ?Violation
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_scalar($value) && !$value instanceof \Stringable) {
            return null;
        }

        return is_numeric((string) $value) ? null : new Violation($this->message, $field, 'numeric');
    }

    /** @return array<string,string|int|float|bool|null> */
    public function htmlAttributes(): array
    {
        return [];
    }
}
