<?php

declare(strict_types=1);

namespace Kaly\Forms\Field;

use Kaly\Forms\Interaction\Condition;
use Kaly\Forms\Validation\Required;

/**
 * Several values from a fixed set, rendered as checkboxes sharing one name.
 * Submitted value is a list of strings; a missing submission means an empty list.
 */
final class CheckboxGroupField extends Field
{
    /**
     * @param array<string|int,string> $choices
     * @param array<string,string|int|float|bool|null> $attributes
     */
    public function __construct(
        string $name,
        ?string $label = null,
        array $choices = [],
        ?string $help = null,
        bool $required = false,
        array $attributes = [],
        ?Condition $visibleWhen = null,
    ) {
        parent::__construct($name, $label, $help, $attributes, $required ? [new Required()] : [], $visibleWhen);
        $this->choices = $choices;
    }

    /** @var array<string|int,string> */
    public readonly array $choices;
}
