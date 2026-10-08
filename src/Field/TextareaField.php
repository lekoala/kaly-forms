<?php

declare(strict_types=1);

namespace Kaly\Forms\Field;

use Kaly\Forms\Interaction\Condition;
use Kaly\Forms\Validation\Length;
use Kaly\Forms\Validation\Required;

final class TextareaField extends Field
{
    public function __construct(
        string $name,
        ?string $label = null,
        ?string $help = null,
        bool $required = false,
        ?int $minLength = null,
        ?int $maxLength = null,
        int $rows = 4,
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
        $attributes['rows'] = $rows;

        parent::__construct($name, $label, $help, $attributes, $rules, $visibleWhen);
    }
}
