<?php

declare(strict_types=1);

namespace Kaly\Forms\Field;

use Kaly\Forms\Interaction\Condition;
use Kaly\Forms\Validation\Numeric;
use Kaly\Forms\Validation\Required;

/**
 * A numeric value: submitted values stay strings, mapping to int/float/decimal belongs to the application.
 */
class NumericField extends Field
{
    /** @param array<string,string|int|float|bool|null> $attributes */
    public function __construct(
        string $name,
        ?string $label = null,
        ?string $help = null,
        bool $required = false,
        public readonly int|float|string|null $min = null,
        public readonly int|float|string|null $max = null,
        public readonly int|float|string|null $step = null,
        array $attributes = [],
        ?Condition $visibleWhen = null,
    ) {
        $rules = [new Numeric()];
        if ($required) {
            array_unshift($rules, new Required());
        }
        parent::__construct($name, $label, $help, $attributes, $rules, $visibleWhen);
    }
}
