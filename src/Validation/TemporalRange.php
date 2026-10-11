<?php

declare(strict_types=1);

namespace Kaly\Forms\Validation;

use Kaly\Forms\FormError;

/**
 * Format and bounds of a native date/time control value, projected to min/max.
 *
 * Values stay submission strings (YYYY-MM-DD, HH:MM[:SS], YYYY-MM-DDTHH:MM[:SS]);
 * once the format is checked and seconds are made explicit, bounds compare as
 * strings, which is exact for these fixed-width ISO forms.
 */
final readonly class TemporalRange implements Rule
{
    public const string DATE = 'date';
    public const string TIME = 'time';
    public const string DATETIME = 'datetime-local';

    private const array PATTERNS = [
        self::DATE => '/^\d{4}-\d{2}-\d{2}$/',
        self::TIME => '/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d(\.\d{1,3})?)?$/',
        self::DATETIME => '/^\d{4}-\d{2}-\d{2}T([01]\d|2[0-3]):[0-5]\d(:[0-5]\d(\.\d{1,3})?)?$/',
    ];

    public function __construct(
        private string $type,
        private ?string $min = null,
        private ?string $max = null,
        private string $message = 'Enter a valid value',
    ) {
        if (!isset(self::PATTERNS[$type])) {
            throw new \InvalidArgumentException(sprintf('Unsupported temporal type "%s"', $type));
        }
        foreach (['min' => $min, 'max' => $max] as $name => $bound) {
            if ($bound !== null && !self::isValid($type, $bound)) {
                throw new \InvalidArgumentException(sprintf('Invalid %s bound "%s" for %s', $name, $bound, $type));
            }
        }
    }

    public function validate(string $field, mixed $value, array $allValues): ?FormError
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) || !self::isValid($this->type, $value)) {
            return new FormError($this->message, $field, 'format');
        }
        $key = $this->sortKey($value);
        if ($this->min !== null && $key < $this->sortKey($this->min)) {
            return new FormError("Must be {$this->min} or later", $field, 'min');
        }
        if ($this->max !== null && $key > $this->sortKey($this->max)) {
            return new FormError("Must be {$this->max} or earlier", $field, 'max');
        }
        return null;
    }

    /** @return array<string,string|int|float|bool|null> */
    public function htmlAttributes(): array
    {
        return array_filter(
            [
                'min' => $this->min,
                'max' => $this->max,
            ],
            static fn(mixed $v): bool => $v !== null,
        );
    }

    /**
     * HH:MM, HH:MM:00 and HH:MM:00.000 are the same instant: compare with
     * explicit seconds and milliseconds (12:00:00.1 === 12:00:00.100).
     */
    private function sortKey(string $value): string
    {
        if ($this->type === self::DATE) {
            return $value;
        }
        [$main, $fraction] = explode('.', $value, 2) + [1 => ''];
        if (preg_match('/(^|T)\d{2}:\d{2}$/', $main) === 1) {
            $main .= ':00';
        }
        return $main . '.' . str_pad($fraction, 3, '0');
    }

    private static function isValid(string $type, string $value): bool
    {
        if (preg_match(self::PATTERNS[$type], $value) !== 1) {
            return false;
        }
        // Date part (when present) is YYYY-MM-DD at the start of the value.
        return $type === self::TIME || checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4));
    }
}
