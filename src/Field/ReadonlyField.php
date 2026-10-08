<?php

declare(strict_types=1);

namespace Kaly\Forms\Field;

use Kaly\Forms\Interaction\Condition;

/**
 * Displays a submitted value without editing it, preserving it through a hidden input.
 * Presentation only: the server must still derive or validate protected values.
 */
final class ReadonlyField extends Field
{
    /** @param array<string,string|int|float|bool|null> $attributes */
    public function __construct(
        string $name,
        ?string $label = null,
        ?string $help = null,
        array $attributes = [],
        ?Condition $visibleWhen = null,
    ) {
        parent::__construct($name, $label, $help, $attributes, [], $visibleWhen);
    }
}
