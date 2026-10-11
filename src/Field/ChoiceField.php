<?php

declare(strict_types=1);

namespace Kaly\Forms\Field;

use Kaly\Forms\Interaction\Condition;
use Kaly\Forms\Interaction\RemoteOptions;
use Kaly\Forms\Validation\InChoices;
use Kaly\Forms\Validation\Required;

final class ChoiceField extends Field
{
    /**
     * Nested arrays are a shorthand for OptionGroup; only one level is allowed.
     *
     * $emptyLabel adds a leading `<option value="">` (default: present, blank
     * label) so nothing is submitted until the user picks a value; with
     * `required`, the browser then refuses the empty choice. Pass null to
     * omit it. It is skipped when `choices` already has an '' key.
     *
     * @param array<string|int,string|OptionGroup|array<string|int,mixed>> $choices
     * @param array<string,string|int|float|bool|null> $attributes
     */
    public function __construct(
        string $name,
        ?string $label = null,
        array $choices = [],
        ?string $help = null,
        bool $required = false,
        public readonly ?RemoteOptions $remote = null,
        array $attributes = [],
        ?Condition $visibleWhen = null,
        public readonly ?string $emptyLabel = '',
        bool $strictChoices = false,
    ) {
        $rules = $required ? [new Required()] : [];
        if ($strictChoices && $remote !== null) {
            throw new \InvalidArgumentException(
                'strictChoices cannot apply to remote options: validate the canonical value in the application',
            );
        }
        if ($strictChoices) {
            $rules[] = InChoices::fromChoices(OptionGroup::normalize($choices));
        }
        parent::__construct($name, $label, $help, $attributes, $rules, $visibleWhen);
        $this->choices = OptionGroup::normalize($choices);
    }

    /** @var array<string|int,string|OptionGroup> */
    public readonly array $choices;
}
