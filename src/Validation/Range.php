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
        if ($this->step !== null && $this->step !== 'any') {
            $steps = ($number - (float) ($this->min ?? 0)) / (float) $this->step;
            if (abs($steps - round($steps)) > (1e-9 * max(1.0, abs($steps)))) {
                return new FormError("Must be a multiple of {$this->step}", $field, 'step');
            }
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
                'step' => $this->step,
            ],
            static fn(mixed $v): bool => $v !== null,
        );
    }
}
