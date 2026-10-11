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
        // FormState is flat: a bracketed name would be submitted as a nested
        // array by PHP and never matched back (no refill, no validation).
        // PHP also rewrites dots and spaces to underscores in parsed bodies.
        if (preg_match('/[\[\].\s]/', $name) === 1) {
            throw new \InvalidArgumentException(sprintf(
                'Field name "%s" cannot contain brackets, dots or whitespace: PHP would not submit it under this name',
                $name,
            ));
        }
        // One source of truth per constraint: the server validates rules, so
        // an attribute contradicting (or duplicating) a projected rule is refused.
        foreach ($rules as $rule) {
            foreach (array_keys($rule->htmlAttributes()) as $projected) {
                if (array_key_exists($projected, $attributes)) {
                    throw new \InvalidArgumentException(sprintf(
                        'Field "%s": attribute "%s" is projected by a validation rule, set it through the field parameters',
                        $name,
                        $projected,
                    ));
                }
            }
        }
    }

    /** @param array<string,mixed> $values */
    public function isActiveFor(array $values): bool
    {
        return $this->visibleWhen?->matches($values) ?? true;
    }
}
