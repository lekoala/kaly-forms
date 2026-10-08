<?php

declare(strict_types=1);

namespace Kaly\Forms\Field;

use Kaly\Forms\Interaction\Condition;

/**
 * Renders a custom element and, by default, a hidden native input carrying the submitted value.
 * This keeps custom JS UI separate from the server-side submission contract.
 */
final class CustomElementField extends Field
{
    public function __construct(
        string $name,
        public readonly string $tag,
        ?string $label = null,
        ?string $help = null,
        public readonly bool $mirrorHiddenInput = true,
        array $attributes = [],
        ?Condition $visibleWhen = null,
    ) {
        if (!str_contains($tag, '-')) {
            throw new \InvalidArgumentException('Custom element tag must contain a hyphen');
        }

        parent::__construct($name, $label, $help, $attributes, [], $visibleWhen);
    }
}
