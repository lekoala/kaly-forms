<?php

declare(strict_types=1);

namespace Kaly\Forms\Node;

use Kaly\Forms\Interaction\Condition;

/**
 * Semantic grouping: renders a fieldset with a legend (meaning + accessibility).
 * Unlike Group, the HTML contract is fixed; only decoration varies by theme.
 */
final class Fieldset implements ContainerNode
{
    /**
     * @param list<FormNode> $children
     * @param array<string,string|int|float|bool|null> $attributes
     */
    public function __construct(
        public readonly string $legend,
        public readonly array $children,
        public readonly ?Condition $visibleWhen = null,
        public readonly array $attributes = [],
    ) {}

    /** @return list<FormNode> */
    public function children(): array
    {
        return $this->children;
    }

    /** @param array<string,mixed> $values */
    public function isActiveFor(array $values): bool
    {
        return $this->visibleWhen?->matches($values) ?? true;
    }
}
