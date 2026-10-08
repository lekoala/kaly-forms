<?php

declare(strict_types=1);

namespace Kaly\Forms\Node;

use Kaly\Forms\Html;

/** Explicit opt-in for already-trusted application HTML. Rendered verbatim. */
final readonly class HtmlBlock implements FormNode
{
    public function __construct(
        public Html $html,
    ) {}
}
