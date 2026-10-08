<?php

declare(strict_types=1);

namespace Kaly\Forms\Field;

use Kaly\Forms\Interaction\Condition;
use Kaly\Forms\Validation\Length;
use Kaly\Forms\Validation\Required;

class TextField extends Field
{
    public readonly string $inputType;

    /** @param array<string,string|int|float|bool|null> $attributes */
    public function __construct(
        string $name,
        ?string $label = null,
        ?string $help = null,
        bool $required = false,
        ?int $minLength = null,
        ?int $maxLength = null,
        ?string $autocomplete = null,
        string $inputType = 'text',
        array $attributes = [],
        ?Condition $visibleWhen = null,
    ) {
        $rules = [];
        if ($required) {
            $rules[] = new Required();
        }
        if ($minLength !== null || $maxLength !== null) {
            $rules[] = new Length($minLength, $maxLength);
        }
        if ($autocomplete !== null) {
            $attributes['autocomplete'] = $autocomplete;
        }

        parent::__construct($name, $label, $help, $attributes, $rules, $visibleWhen);
        $this->inputType = $inputType;
    }
}
