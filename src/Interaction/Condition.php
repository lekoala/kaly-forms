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

    /**
     * Shared normalization contract (mirrored in assets/enhance.js):
     * absent key => null, "" stays "" (so null !== ""), scalars stringify,
     * lists stringify + dedupe + sort (byte order, SORT_STRING), compared strictly.
     *
     * @param array<string,mixed> $values
     */
    public function matches(array $values): bool
    {
        $actual = array_key_exists($this->field, $values) ? self::normalize($values[$this->field]) : null;
        $expected = self::normalize($this->expected);

        return match ($this->operator) {
            'eq' => self::equalsNormalized($actual, $expected),
            'neq' => !self::equalsNormalized($actual, $expected),
            'filled' => $actual !== null && $actual !== '' && $actual !== [],
            default => false,
        };
    }

    /**
     * @return null|string|list<string>
     */
    public static function normalize(mixed $value): string|array|null
    {
        if ($value === null) {
            return null;
        }
        if (is_array($value)) {
            $out = [];
            foreach ($value as $item) {
                if ($item === null) {
                    continue;
                }
                if (is_scalar($item) || $item instanceof \Stringable) {
                    $out[] = (string) $item;
                }
            }
            $out = array_values(array_unique($out));
            sort($out, SORT_STRING);
            return $out;
        }
        if (is_scalar($value) || $value instanceof \Stringable) {
            return (string) $value;
        }
        return null;
    }

    /** @param null|string|list<string> $a @param null|string|list<string> $b */
    private static function equalsNormalized(mixed $a, mixed $b): bool
    {
        return $a === $b;
    }
}
