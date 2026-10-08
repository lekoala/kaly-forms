<?php

declare(strict_types=1);

namespace Kaly\Forms\Render;

/**
 * A complete rendering environment: theme plus node renderers.
 * Framework integrations ship their own profiles; the core stays unaware of them.
 */
final readonly class RenderProfile
{
    public function __construct(
        public FormTheme $theme,
        public NodeRendererRegistry $renderers,
    ) {}

    public static function plain(): self
    {
        return new self(new DefaultTheme(), NodeRendererRegistry::defaults());
    }
}
