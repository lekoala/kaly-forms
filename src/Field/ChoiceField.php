<?php

declare(strict_types=1);

namespace Kaly\Forms\Field;

use Kaly\Forms\Interaction\Condition;
use Kaly\Forms\Interaction\RemoteOptions;
use Kaly\Forms\Validation\Required;

final class ChoiceField extends Field
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
        public readonly ?RemoteOptions $remote = null,
        array $attributes = [],
        ?Condition $visibleWhen = null,
    ) {
        parent::__construct($name, $label, $help, $attributes, $required ? [new Required()] : [], $visibleWhen);
        $this->choices = $choices;
    }

    /** @var array<string|int,string> */
    public readonly array $choices;
}
