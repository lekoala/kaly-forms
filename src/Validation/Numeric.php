<?php

declare(strict_types=1);

namespace Kaly\Forms\Validation;

use Kaly\Forms\FormError;

final readonly class Numeric implements Rule
{
    public function __construct(
        private string $message = 'Enter a number',
    ) {}

    public function validate(string $field, mixed $value, array $allValues): ?FormError
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_scalar($value) && !$value instanceof \Stringable) {
            return new FormError($this->message, $field, 'numeric');
        }

        return is_numeric((string) $value) ? null : new FormError($this->message, $field, 'numeric');
    }

    /** @return array<string,string|int|float|bool|null> */
    public function htmlAttributes(): array
    {
        return [];
    }
}
