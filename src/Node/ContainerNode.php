<?php

declare(strict_types=1);

namespace Kaly\Forms\Node;

interface ContainerNode extends FormNode
{
    /** @return list<FormNode> */
    public function children(): array;
}
