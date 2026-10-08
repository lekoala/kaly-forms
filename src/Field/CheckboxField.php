<?php

declare(strict_types=1);

namespace Kaly\Forms\Field;

use Kaly\Forms\Interaction\Condition;
use Kaly\Forms\Validation\Required;

final class CheckboxField extends Field
{
    public function __construct(
        string $name,
        ?string $label = null,
        ?string $help = null,
        bool $required = false,
        array $attributes = [],
        ?Condition $visibleWhen = null,
    ) {
        parent::__construct(
            $name,
            $label,
            $help,
            $attributes,
            $required ? [new Required('This checkbox must be checked')] : [],
            $visibleWhen,
        );
    }
}
