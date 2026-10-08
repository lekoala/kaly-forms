<?php

declare(strict_types=1);

namespace Kaly\Forms;

final readonly class FormState
{
    /**
     * @param array<string,mixed> $values
     * @param list<Violation> $violations
     */
    public function __construct(
        private array $values = [],
        private array $violations = [],
    ) {}

    /**
     * @param array<string,mixed> $values
     * @param list<Violation> $violations
     */
    public static function from(array $values = [], array $violations = []): self
    {
        return new self($values, $violations);
    }

    public function value(string $name, mixed $default = null): mixed
    {
        return array_key_exists($name, $this->values) ? $this->values[$name] : $default;
    }

    /** @return array<string,mixed> */
    public function values(): array
    {
        return $this->values;
    }

    /** @return list<Violation> */
    public function violations(): array
    {
        return $this->violations;
    }

    /** @return list<Violation> */
    public function errorsFor(string $field): array
    {
        return array_values(array_filter($this->violations, static fn(Violation $v): bool => $v->field === $field));
    }

    /** @return list<Violation> */
    public function globalErrors(): array
    {
        return array_values(array_filter($this->violations, static fn(Violation $v): bool => $v->field === null));
    }

    public function isValid(): bool
    {
        return $this->violations === [];
    }
}
