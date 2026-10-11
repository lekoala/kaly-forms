<?php

declare(strict_types=1);

namespace Kaly\Forms\Validation;

use Kaly\Forms\Field\OptionGroup;
use Kaly\Forms\FormError;

/**
 * Opt-in: the submitted value (or every item of a list) is one of a static set.
 *
 * Only meaningful when the rendered choices ARE the allowed values. Remote or
 * permission-dependent options must be checked by the application against
 * its canonical source: an option having been rendered proves nothing.
 */
final readonly class InChoices implements Rule
{
    /** @var array<string,true> */
    private array $allowed;

    /** @param list<string|int> $values */
    public function __construct(
        array $values,
        private string $message = 'Select a valid choice',
    ) {
        $allowed = [];
        foreach ($values as $value) {
            $allowed[(string) $value] = true;
        }
        $this->allowed = $allowed;
    }

    /** @param array<string|int,string|OptionGroup> $choices value => label, OptionGroup flattened */
    public static function fromChoices(array $choices, string $message = 'Select a valid choice'): self
    {
        $values = [];
        foreach ($choices as $value => $choice) {
            if ($choice instanceof OptionGroup) {
                array_push($values, ...array_keys($choice->choices));
            } else {
                $values[] = $value;
            }
        }
        return new self($values, $message);
    }

    public function validate(string $field, mixed $value, array $allValues): ?FormError
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }
        foreach (is_array($value) ? $value : [$value] as $item) {
            if (!is_scalar($item) || !isset($this->allowed[(string) $item])) {
                return new FormError($this->message, $field, 'choice');
            }
        }
        return null;
    }

    /** @return array<string,string|int|float|bool|null> */
    public function htmlAttributes(): array
    {
        return [];
    }
}
