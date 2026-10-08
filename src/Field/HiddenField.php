<?php

declare(strict_types=1);

namespace Kaly\Forms\Field;

final class HiddenField extends Field
{
    public function __construct(string $name, array $attributes = [])
    {
        parent::__construct($name, attributes: $attributes);
    }
}
