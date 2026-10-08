<?php

declare(strict_types=1);

namespace Kaly\Forms\Field;

use Kaly\Forms\Html;

/** Explicit escape hatch for already-trusted application HTML. */
final class HtmlField extends Field
{
    public function __construct(
        string $name,
        public readonly Html $html,
    ) {
        parent::__construct($name);
    }
}
