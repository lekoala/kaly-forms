<?php

declare(strict_types=1);

namespace Kaly\Forms\Field;

use Kaly\Forms\Interaction\Condition;

final class PasswordField extends TextField
{
    /** @param array<string,string|int|float|bool|null> $attributes */
    public function __construct(
        string $name,
        ?string $label = null,
        ?string $help = null,
        bool $required = false,
        ?int $minLength = null,
        ?int $maxLength = null,
        string $autocomplete = 'current-password',
        array $attributes = [],
        ?Condition $visibleWhen = null,
    ) {
        parent::__construct(
            name: $name,
            label: $label,
            help: $help,
            required: $required,
            minLength: $minLength,
            maxLength: $maxLength,
            autocomplete: $autocomplete,
            inputType: 'password',
            attributes: $attributes,
            visibleWhen: $visibleWhen,
        );
    }
}
