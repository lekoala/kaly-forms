<?php

declare(strict_types=1);

namespace Kaly\Forms\Validation;

use Kaly\Forms\FormError;

/**
 * Numeric bounds and step, projected to min/max/step.
 *
 * Non-numeric input is left to the Numeric rule. Step follows the HTML
 * definition: valid values are min + n * step (or n * step without min);
 * "any" disables the step check.
 */
final readonly class Range implements Rule
{
    public function __construct(
        private int|float|string|null $min = null,
        private int|float|string|null $max = null,
        private int|float|string|null $step = null,
    ) {
        foreach (['min' => $min, 'max' => $max] as $name => $bound) {
            if ($bound !== null && !is_numeric($bound)) {
                throw new \InvalidArgumentException(sprintf('Range %s must be numeric', $name));
            }
        }
        if ($step !== null && $step !== 'any' && (!is_numeric($step) || (float) $step <= 0)) {
            throw new \InvalidArgumentException('Range step must be a positive number or "any"');
        }
    }

    public function validate(string $field, mixed $value, array $allValues): ?FormError
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_scalar($value) && !$value instanceof \Stringable) {
            return null;
        }
        $text = (string) $value;
        if (!is_numeric($text)) {
            return null;
        }
        $number = (float) $text;
        if ($this->min !== null && $number < (float) $this->min) {
            return new FormError("Must be at least {$this->min}", $field, 'min');
        }
        if ($this->max !== null && $number > (float) $this->max) {
            return new FormError("Must be at most {$this->max}", $field, 'max');
        }
        if ($this->step !== null && $this->step !== 'any' && !self::onStep($text, (string) ($this->min ?? 0), (string) $this->step)) {
            return new FormError("Must be a multiple of {$this->step}", $field, 'step');
        }
        return null;
    }

    /**
     * Whether value = base + n * step. Plain decimals are compared exactly as
     * scaled integers (no float tolerance that grows with the value); exponent
     * notation or out-of-range digits fall back to floats with a fixed tolerance.
     */
    private static function onStep(string $value, string $base, string $step): bool
    {
        $scale = max(self::decimals($value), self::decimals($base), self::decimals($step));
        $v = self::scaled($value, $scale);
        $b = self::scaled($base, $scale);
        $s = self::scaled($step, $scale);
        // Operands have at most 18 digits, so the difference cannot overflow.
        if ($v !== null && $b !== null && $s !== null && $s > 0) {
            return (($v - $b) % $s) === 0;
        }
        $steps = ((float) $value - (float) $base) / (float) $step;
        return abs($steps - round($steps)) < 1e-9;
    }

    private static function decimals(string $number): int
    {
        $dot = strpos($number, '.');
        return $dot === false ? 0 : strlen(rtrim(substr($number, $dot + 1), '0'));
    }

    /** "12.5" at scale 2 => 1250; null when not a plain decimal or too large for an int. */
    private static function scaled(string $number, int $scale): ?int
    {
        $number = trim($number);
        if ($scale > 15 || preg_match('/^([+-]?)(\d*)(?:\.(\d*))?$/', $number, $m) !== 1 || $m[2] === '' && ($m[3] ?? '') === '') {
            return null;
        }
        $fraction = substr(str_pad($m[3] ?? '', $scale, '0'), 0, $scale);
        $digits = ltrim($m[2] . $fraction, '0');
        if (strlen($digits) > 18) {
            return null;
        }
        $int = (int) ($digits === '' ? '0' : $digits);
        return $m[1] === '-' ? -$int : $int;
    }

    /** @return array<string,string|int|float|bool|null> */
    public function htmlAttributes(): array
    {
        return array_filter(
            [
                'min' => $this->min,
                'max' => $this->max,
                'step' => $this->step,
            ],
            static fn(mixed $v): bool => $v !== null,
        );
    }
}
