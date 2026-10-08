<?php

declare(strict_types=1);

namespace Kaly\Forms\Validation;

use Kaly\Forms\Violation;

final readonly class Length implements Rule
{
    public function __construct(
        private ?int $min = null,
        private ?int $max = null,
    ) {}

    public function validate(string $field, mixed $value, array $allValues): ?Violation
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_scalar($value) && !$value instanceof \Stringable) {
            return new Violation('Invalid value', $field, 'type');
        }

        $length = mb_strlen((string) $value, 'UTF-8');
        if ($this->min !== null && $length < $this->min) {
            return new Violation("Must contain at least {$this->min} characters", $field, 'min_length');
        }
        if ($this->max !== null && $length > $this->max) {
            return new Violation("Must contain at most {$this->max} characters", $field, 'max_length');
        }

        return null;
    }

    /** @return array<string,string|int|float|bool|null> */
    public function htmlAttributes(): array
    {
        return array_filter(
            [
                'minlength' => $this->min,
                'maxlength' => $this->max,
            ],
            static fn(mixed $v): bool => $v !== null,
        );
    }
}
