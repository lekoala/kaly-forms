<?php

declare(strict_types=1);

namespace Kaly\Forms\Field;

use Kaly\Forms\Interaction\Condition;
use Kaly\Forms\Validation\Required;

/**
 * A calendar date: semantic model only, no date handling.
 *
 * Values stay strings (YYYY-MM-DD) as submitted; mapping to date objects
 * belongs to the application. Subclasses may add richer interaction
 * semantics and be substituted through FieldTypes.
 */
class DateField extends Field
{
    /** @param array<string,string|int|float|bool|null> $attributes */
    public function __construct(
        string $name,
        ?string $label = null,
        ?string $help = null,
        bool $required = false,
        public readonly ?string $min = null,
        public readonly ?string $max = null,
        array $attributes = [],
        ?Condition $visibleWhen = null,
    ) {
        parent::__construct($name, $label, $help, $attributes, $required ? [new Required()] : [], $visibleWhen);
    }
}
