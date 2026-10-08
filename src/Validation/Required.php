<?php

declare(strict_types=1);

namespace Kaly\Forms\Validation;

use Kaly\Forms\FormError;

final readonly class Required implements Rule
{
    public function __construct(
        private string $message = 'This field is required',
    ) {}

    public function validate(string $field, mixed $value, array $allValues): ?FormError
    {
        $empty = $value === null || $value === '' || $value === [];
        return $empty ? new FormError($this->message, $field, 'required') : null;
    }

    /** @return array<string,string|int|float|bool|null> */
    public function htmlAttributes(): array
    {
        return ['required' => true];
    }
}
