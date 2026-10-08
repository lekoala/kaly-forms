<?php

declare(strict_types=1);

namespace Kaly\Forms\Field;

use Kaly\Forms\Interaction\Condition;
use Kaly\Forms\Node\FormNode;
use Kaly\Forms\Validation\Rule;

abstract class Field implements FormNode
{
    /**
     * @param array<string,string|int|float|bool|null> $attributes
     * @param list<Rule> $rules
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $label = null,
        public readonly ?string $help = null,
        public readonly array $attributes = [],
        public readonly array $rules = [],
        public readonly ?Condition $visibleWhen = null,
    ) {
        if ($name === '') {
            throw new \InvalidArgumentException('Field name cannot be empty');
        }
    }

    /** @param array<string,mixed> $values */
    public function isActiveFor(array $values): bool
    {
        return $this->visibleWhen?->matches($values) ?? true;
    }
}
