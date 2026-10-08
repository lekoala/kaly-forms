<?php

declare(strict_types=1);

namespace Kaly\Forms\Interaction;

final readonly class Condition
{
    private function __construct(
        public string $field,
        public string $operator,
        public mixed $expected = null,
    ) {}

    public static function equals(string $field, mixed $value): self
    {
        return new self($field, 'eq', $value);
    }

    public static function notEquals(string $field, mixed $value): self
    {
        return new self($field, 'neq', $value);
    }

    public static function filled(string $field): self
    {
        return new self($field, 'filled');
    }

    /** @param array<string,mixed> $values */
    public function matches(array $values): bool
    {
        $actual = $values[$this->field] ?? null;

        return match ($this->operator) {
            'eq' => $actual == $this->expected,
            'neq' => $actual != $this->expected,
            'filled' => $actual !== null && $actual !== '' && $actual !== [],
            default => false,
        };
    }
}
