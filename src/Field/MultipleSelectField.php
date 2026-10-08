<?php

declare(strict_types=1);

namespace Kaly\Forms\Field;

use Kaly\Forms\Interaction\Condition;
use Kaly\Forms\Validation\Required;

/**
 * Several values from a fixed set, rendered as one multiple select.
 * Submitted value is a list of strings; a missing submission means an empty list.
 */
final class MultipleSelectField extends Field
{
    /**
     * Nested arrays are a shorthand for OptionGroup; only one level is allowed.
     *
     * @param array<string|int,string|OptionGroup|array<string|int,mixed>> $choices
     * @param array<string,string|int|float|bool|null> $attributes
     */
    public function __construct(
        string $name,
        ?string $label = null,
        array $choices = [],
        ?string $help = null,
        bool $required = false,
        ?int $size = null,
        array $attributes = [],
        ?Condition $visibleWhen = null,
    ) {
        parent::__construct($name, $label, $help, $attributes, $required ? [new Required()] : [], $visibleWhen);
        $this->choices = OptionGroup::normalize($choices);
        $this->size = $size;
    }

    /** @var array<string|int,string|OptionGroup> */
    public readonly array $choices;
    public readonly ?int $size;
}
