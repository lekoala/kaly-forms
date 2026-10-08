<?php

declare(strict_types=1);

namespace Kaly\Forms\Node;

use Kaly\Forms\Interaction\Condition;

/**
 * Purely visual grouping: carries a layout intention, no data and no HTML semantics.
 * The renderer chooses the markup; a minimal renderer may output a plain div.
 */
final class Group implements ContainerNode
{
    /**
     * @param list<FormNode> $children
     * @param array<string,string|int|float|bool|null> $attributes
     */
    public function __construct(
        public readonly array $children,
        public readonly Layout $layout = new StackLayout(),
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
