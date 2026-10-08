<?php

declare(strict_types=1);

namespace Kaly\Forms\Render;

use Kaly\Forms\Node\Layout;

/**
 * Read-only decoration state for themes.
 * Never the whole renderer: a theme decorates, it does not render HTML.
 */
final class ThemeContext
{
    public function __construct(
        private readonly bool $invalid = false,
        private readonly bool $disabled = false,
        private readonly bool $required = false,
        private readonly ?Layout $layout = null,
    ) {}

    public function invalid(): bool
    {
        return $this->invalid;
    }

    public function disabled(): bool
    {
        return $this->disabled;
    }

    public function required(): bool
    {
        return $this->required;
    }

    public function layout(): ?Layout
    {
        return $this->layout;
    }
}
