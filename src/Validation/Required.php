<?php

declare(strict_types=1);

namespace Kaly\Forms\Validation;

use Kaly\Forms\Violation;

final readonly class Required implements Rule
{
    public function __construct(
        private string $message = 'This field is required',
    ) {}

    public function validate(string $field, mixed $value, array $allValues): ?Violation
    {
        $empty = $value === null || $value === '' || $value === [];
        return $empty ? new Violation($this->message, $field, 'required') : null;
    }

    /** @return array<string,string|int|float|bool|null> */
    public function htmlAttributes(): array
    {
        return ['required' => true];
    }
}
