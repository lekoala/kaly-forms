<?php

declare(strict_types=1);

namespace Kaly\Forms\Field;

use Kaly\Forms\Interaction\Condition;
use Kaly\Forms\Validation\Required;

/**
 * Presentation of a file upload control only: never refilled, never read back.
 * Uploaded files live outside scalar form values (PSR-7 uploaded files); the
 * application owns the upload lifecycle.
 */
final class FileField extends Field
{
    /**
     * @param string|list<string>|null $accept
     * @param array<string,string|int|float|bool|null> $attributes
     */
    public function __construct(
        string $name,
        ?string $label = null,
        ?string $help = null,
        bool $required = false,
        string|array|null $accept = null,
        public readonly bool $multiple = false,
        array $attributes = [],
        ?Condition $visibleWhen = null,
    ) {
        parent::__construct($name, $label, $help, $attributes, $required ? [new Required()] : [], $visibleWhen);
        $this->accept = is_array($accept) ? implode(',', $accept) : $accept;
    }

    public readonly ?string $accept;
}
