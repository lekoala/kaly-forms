<?php

declare(strict_types=1);

namespace Kaly\Forms\Render;

use Kaly\Forms\Field\Field;
use Kaly\Forms\Node\FormNode;

/** Framework-neutral reference theme: preserves the historical semantic classes. */
class DefaultTheme implements FormTheme
{
    /** @return array<string,string|int|float|bool|null> */
    public function attributes(RenderPart $part, ?FormNode $node, ThemeContext $context): array
    {
        return match ($part) {
            RenderPart::Field => ['class' => $context->invalid() ? 'form-field is-invalid' : 'form-field'],
            RenderPart::Fieldset => $node instanceof Field ? ['class' => $context->invalid() ? 'form-field is-invalid' : 'form-field'] : [],
            RenderPart::Help => ['class' => 'form-help'],
            RenderPart::Errors => ['class' => 'form-error'],
            RenderPart::Actions => ['class' => 'form-actions'],
            default => [],
        };
    }
}
