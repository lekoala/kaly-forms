<?php

declare(strict_types=1);

namespace Kaly\Forms\Validation;

use Kaly\Forms\Violation;

/**
 * A single checkbox must be checked.
 *
 * Canonical accepted values mirror native HTML submission for
 * `<input type="checkbox" value="1">`: true, 1, "1" and "on"
 * ("on" covers renderers omitting value). Everything else —
 * including "true", "false", "0" and false — is rejected.
 */
final readonly class Checked implements Rule
{
    public function __construct(
        private string $message = 'This checkbox must be checked',
    ) {}

    public function validate(string $field, mixed $value, array $allValues): ?Violation
    {
        $checked = $value === true || $value === 1 || $value === '1' || $value === 'on';
        return $checked ? null : new Violation($this->message, $field, 'checked');
    }

    /** @return array<string,string|int|float|bool|null> */
    public function htmlAttributes(): array
    {
        return ['required' => true];
    }
}
