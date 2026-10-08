<?php

declare(strict_types=1);

namespace Kaly\Forms\Validation;

use Kaly\Forms\FormError;

final readonly class Email implements Rule
{
    public function validate(string $field, mixed $value, array $allValues): ?FormError
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_scalar($value) && !$value instanceof \Stringable) {
            return new FormError('Enter a valid email address', $field, 'email');
        }

        return filter_var((string) $value, FILTER_VALIDATE_EMAIL) === false
            ? new FormError('Enter a valid email address', $field, 'email')
            : null;
    }

    /** @return array<string,string|int|float|bool|null> */
    public function htmlAttributes(): array
    {
        return [];
    }
}
