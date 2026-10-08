<?php

declare(strict_types=1);

namespace Kaly\Forms;

/**
 * A presentation-ready error attached to one render.
 *
 * The message is already resolved (translated, formatted): kaly-forms never
 * owns i18n or business validation. This is deliberately distinct from a
 * validation layer's own violation type (e.g. Kaly\Validation\Violation,
 * which carries messageId/domain/fallback/code): translating that object
 * into a FormError is the application adapter's job.
 */
final readonly class FormError
{
    public function __construct(
        public string $message,
        public ?string $field = null,
        public ?string $code = null,
    ) {}
}
