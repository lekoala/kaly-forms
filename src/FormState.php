<?php

declare(strict_types=1);

namespace Kaly\Forms;

final readonly class FormState
{
    /**
     * @param array<string,mixed> $values
     * @param list<FormError> $errors
     */
    public function __construct(
        private array $values = [],
        private array $errors = [],
    ) {}

    /**
     * @param array<string,mixed> $values
     * @param list<FormError> $errors
     */
    public static function from(array $values = [], array $errors = []): self
    {
        return new self($values, $errors);
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

    /** @return list<FormError> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return list<FormError> */
    public function errorsFor(string $field): array
    {
        return array_values(array_filter($this->errors, static fn(FormError $e): bool => $e->field === $field));
    }

    /** @return list<FormError> */
    public function globalErrors(): array
    {
        return array_values(array_filter($this->errors, static fn(FormError $e): bool => $e->field === null));
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }
}
