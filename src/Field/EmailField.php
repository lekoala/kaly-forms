<?php

declare(strict_types=1);

namespace Kaly\Forms\Field;

use Kaly\Forms\Interaction\Condition;
use Kaly\Forms\Validation\Email;
use Kaly\Forms\Validation\Required;

final class EmailField extends Field
{
    /** @param array<string,string|int|float|bool|null> $attributes */
    public function __construct(
        string $name,
        ?string $label = null,
        ?string $help = null,
        bool $required = false,
        string $autocomplete = 'email',
        array $attributes = [],
        ?Condition $visibleWhen = null,
    ) {
        $rules = [new Email()];
        if ($required) {
            array_unshift($rules, new Required());
        }
        $attributes['autocomplete'] = $autocomplete;

        parent::__construct($name, $label, $help, $attributes, $rules, $visibleWhen);
    }
}
